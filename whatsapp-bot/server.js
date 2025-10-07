/**
 * server.js
 * Baileys 多账户 WhatsApp API 服务（重构版）
 */

import express from 'express';
import cors from 'cors';
import pino from 'pino';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import makeWASocket, {
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
  jidNormalizedUser
} from '@whiskeysockets/baileys';
import * as qrcode from 'qrcode';

// ------------------------ 基础与全局 ------------------------
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
app.use(cors());
app.use(express.json());

const logger = pino({ level: process.env.LOG_LEVEL || 'info' });

const PORT = process.env.PORT || 3000;
const SESSIONS_DIR = path.join(__dirname, 'sessions');
if (!fs.existsSync(SESSIONS_DIR)) fs.mkdirSync(SESSIONS_DIR, { recursive: true });

// 会话内存表与“单飞锁”
/** Map<sessionId, Ctx> */
const sessions = new Map();
/** Map<sessionId, Promise<Ctx>> —— 避免并发初始化/重连 */
const sessionPromises = new Map();

const MAX_RETRY = Number(process.env.MAX_RETRY || 8);

// ------------------------ 工具函数 ------------------------
const ensureGroupId = (gid) => (gid?.endsWith?.('@g.us') ? gid : `${gid}@g.us`);
const phoneToUserJid = (phone) => {
  const digits = String(phone || '').replace(/\D/g, '');
  return `${digits}@s.whatsapp.net`;
};
const jidToPhone = (jid) => String(jid || '').split('@')[0];

// 你若不需要 Laravel 回调，可将两个函数置空实现
const LARAVEL_STATUS_WEBHOOK = process.env.LARAVEL_STATUS_WEBHOOK || ''; // e.g., https://your-app/api/wa/status
const LARAVEL_QR_WEBHOOK = process.env.LARAVEL_QR_WEBHOOK || '';        // e.g., https://your-app/api/wa/qr

async function sendStatusToLaravel(sessionId, status, phone = null, note = '') {
  if (!LARAVEL_STATUS_WEBHOOK) return;
  try {
    await fetch(LARAVEL_STATUS_WEBHOOK, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        sessionId,
        status,      // connecting | online | offline | close
        phone,
        note,
        ts: Date.now()
      })
    });
  } catch (e) {
    logger.warn({ sessionId, err: e?.message }, 'sendStatusToLaravel failed');
  }
}

async function sendQrCodeToLaravel(sessionId, qrDataURL) {
  if (!LARAVEL_QR_WEBHOOK) return;
  try {
    await fetch(LARAVEL_QR_WEBHOOK, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        sessionId,
        qr: qrDataURL,
        ts: Date.now()
      })
    });
  } catch (e) {
    logger.warn({ sessionId, err: e?.message }, 'sendQrCodeToLaravel failed');
  }
}

// ------------------------ 会话 & 连接核心 ------------------------
/**
 * Ctx:
 * {
 *   sock, state, saveCreds,
 *   status: 'connecting' | 'open' | 'close',
 *   lastQR: string | null,
 *   retry: number
 * }
 */

/**
 * 确保拿到一个有效的会话：
 * - 首次：创建 authState & socket
 * - 已存在：若 status !== 'close' 直接复用；若 'close' 则重建
 * - 单飞锁：同一 sessionId 并发调用只会有一个真正执行
 */
async function ensureSession(sessionId) {
  if (sessionPromises.has(sessionId)) return sessionPromises.get(sessionId);

  const p = (async () => {
    const existing = sessions.get(sessionId);
    if (existing && existing.status !== 'close') {
      return existing;
    }

    const sessionPath = path.join(SESSIONS_DIR, sessionId);
    const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
    const ctx = await createSocket(sessionId, state, saveCreds);
    sessions.set(sessionId, ctx);
    return ctx;
  })();

  sessionPromises.set(sessionId, p);
  try {
    return await p;
  } finally {
    sessionPromises.delete(sessionId);
  }
}

/**
 * 每次“新建”一个 socket（初始化或重连都走这里），并把连接事件逻辑绑定在这里
 */
async function createSocket(sessionId, state, saveCreds) {
  const { version } = await fetchLatestBaileysVersion();
  logger.info({ sessionId, version }, 'Creating new socket');

  const sock = makeWASocket({
    version,
    auth: state,
    printQRInTerminal: false,
    browser: ['Chrome', 'Windows', '10.0.0'],
    logger,
    connectTimeoutMs: 60000,
    defaultQueryTimeoutMs: 0,
    keepAliveIntervalMs: 30000,
    retryRequestDelayMs: 250,
    maxMsgRetryCount: 5,
    markOnlineOnConnect: true,
    syncFullHistory: false,
    fireInitQueries: true,
    generateHighQualityLinkPreview: false,
    getMessage: async () => ({ conversation: 'hello' })
  });

  const ctx = {
    sock,
    state,
    saveCreds,
    status: 'connecting',
    lastQR: null,
    retry: 0
  };

  // 凭据持久化
  sock.ev.on('creds.update', saveCreds);

  // 连接更新事件（含 QR / open / close）
  sock.ev.on('connection.update', async (update) => {
    const { connection, qr, lastDisconnect } = update;

    logger.info({ sessionId, connection: connection || 'unknown' }, 'connection.update');

    if (qr) {
      try {
        ctx.lastQR = await qrcode.toDataURL(qr);
        await sendQrCodeToLaravel(sessionId, ctx.lastQR);
        await sendStatusToLaravel(sessionId, 'connecting', null, '等待扫码/配对');
      } catch (e) {
        logger.error({ sessionId, err: e?.message }, 'QR generate failed');
      }
    }

    if (connection === 'open') {
      ctx.status = 'open';
      ctx.lastQR = null;
      ctx.retry = 0;
      const meRaw = sock?.user?.id || '';
      const phone = meRaw ? meRaw.split(':')[0] : null;
      const pushname = sock?.user?.name || null;
      logger.info({ sessionId, phone, pushname }, 'connected');
      await sendStatusToLaravel(sessionId, 'online', phone, '连接成功');
    }

    if (connection === 'close') {
      // 断开原因解析
      const code =
        lastDisconnect?.error?.output?.statusCode ||
        lastDisconnect?.error?.reason ||
        lastDisconnect?.error?.message ||
        0;

      const isLoggedOut =
        code === DisconnectReason.loggedOut || String(code).toLowerCase().includes('logged');

      const needRestart =
        code === DisconnectReason.restartRequired ||
        code === 515 || String(code).toLowerCase().includes('restart');

      const transient =
        needRestart ||
        code === DisconnectReason.timedOut ||
        code === DisconnectReason.connectionLost ||
        code === DisconnectReason.connectionClosed ||
        code === DisconnectReason.connectionReplaced ||
        code === 408 || code === 503;

      logger.warn({ sessionId, code, isLoggedOut, transient }, 'connection closed');
      ctx.status = 'close';

      if (isLoggedOut) {
        // 彻底登出：清理会话目录，等待重新扫码
        try {
          await sendStatusToLaravel(sessionId, 'offline', null, '已登出，需要重新扫码');
        } finally {
          sessions.delete(sessionId);
        }
        return;
      }

      if (transient) {
        // 指数退避重连（关键：要“重建 socket 实例”，不能复用旧 sock）
        const delay = Math.min(30000, 1000 * 2 ** Math.min(ctx.retry, MAX_RETRY));
        ctx.retry++;
        await sendStatusToLaravel(
          sessionId,
          'offline',
          null,
          `连接中断（code=${code}），${Math.round(delay / 1000)}s 后重连…`
        );

        setTimeout(async () => {
          try {
            const newCtx = await createSocket(sessionId, state, saveCreds);
            sessions.set(sessionId, newCtx);
            logger.info({ sessionId }, 'reconnected (new socket)');
          } catch (e) {
            logger.error({ sessionId, err: e?.message }, 'reconnect failed');
          }
        }, delay);
      } else {
        await sendStatusToLaravel(sessionId, 'offline', null, `连接关闭（code=${code}）`);
        sessions.delete(sessionId);
      }
    }
  });

  return ctx;
}

// ------------------------ API 路由 ------------------------

/** 列出当前内存中的会话（调试用） */
app.get('/sessions', (req, res) => {
  const list = [];
  for (const [sessionId, ctx] of sessions.entries()) {
    list.push({
      sessionId,
      status: ctx.status,
      hasQR: !!ctx.lastQR,
      retry: ctx.retry
    });
  }
  res.json(list);
});

/** 获取/拉起某会话的二维码（dataURL）。前端轮询该端点直到 {status:'open', qr:null} 即登录完成 */
app.get('/sessions/:sessionId/qr', async (req, res) => {
  const { sessionId } = req.params;
  try {
    const ctx = await ensureSession(sessionId);
    res.json({
      sessionId,
      status: ctx.status,
      qr: ctx.lastQR
    });
  } catch (e) {
    logger.error({ sessionId, err: e?.message }, 'get qr failed');
    res.status(500).json({ error: 'failed_to_get_qr', detail: String(e?.message || e) });
  }
});

/** 查看会话状态 */
app.get('/sessions/:sessionId/status', async (req, res) => {
  const { sessionId } = req.params;
  try {
    const ctx = await ensureSession(sessionId);
    const meRaw = ctx?.sock?.user?.id || '';
    const phone = meRaw ? meRaw.split(':')[0] : null;
    res.json({
      sessionId,
      status: ctx.status,
      hasQR: !!ctx.lastQR,
      retry: ctx.retry,
      phone
    });
  } catch (e) {
    logger.error({ sessionId, err: e?.message }, 'get status failed');
    res.status(500).json({ error: 'failed_to_get_status', detail: String(e?.message || e) });
  }
});

/** 手动触发重连（会立即重建 socket 实例） */
app.post('/sessions/:sessionId/reconnect', async (req, res) => {
  const { sessionId } = req.params;
  try {
    const sessionPath = path.join(SESSIONS_DIR, sessionId);
    const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
    const newCtx = await createSocket(sessionId, state, saveCreds);
    sessions.set(sessionId, newCtx);
    res.json({ ok: true, sessionId, status: newCtx.status });
  } catch (e) {
    logger.error({ sessionId, err: e?.message }, 'manual reconnect failed');
    res.status(500).json({ error: 'failed_to_reconnect', detail: String(e?.message || e) });
  }
});

/** 获取该账号加入的群组信息（id/subject/size） */
app.get('/sessions/:sessionId/groups', async (req, res) => {
  const { sessionId } = req.params;
  try {
    const ctx = await ensureSession(sessionId);
    if (ctx.status !== 'open') {
      return res.status(409).json({ error: 'not_connected', message: 'session not connected yet' });
    }
    const groupsDict = await ctx.sock.groupFetchAllParticipating();
    const groups = Object.values(groupsDict).map((g) => ({
      id: g.id,
      subject: g.subject,
      size: g.participants?.length || 0
    }));
    res.json({ sessionId, groups });
  } catch (e) {
    logger.error({ sessionId, err: e?.message }, 'fetch groups failed');
    res.status(500).json({ error: 'failed_to_fetch_groups', detail: String(e?.message || e) });
  }
});

/** 获取指定群组所有用户（含手机号/admin 标记） */
app.get('/sessions/:sessionId/groups/:groupId/members', async (req, res) => {
  const { sessionId, groupId } = req.params;
  const gid = ensureGroupId(groupId);
  try {
    const ctx = await ensureSession(sessionId);
    if (ctx.status !== 'open') {
      return res.status(409).json({ error: 'not_connected', message: 'session not connected yet' });
    }
    const meta = await ctx.sock.groupMetadata(gid);
    const members = (meta.participants || []).map((p) => {
      const jid = jidNormalizedUser(p.id);
      return {
        jid,
        phone: jidToPhone(jid),
        admin: p.admin || null, // 'admin' | 'superadmin' | null
        isAdmin: !!p.admin
      };
    });
    res.json({
      sessionId,
      groupId: meta.id,
      subject: meta.subject,
      count: members.length,
      members
    });
  } catch (e) {
    logger.error({ sessionId, groupId: gid, err: e?.message }, 'fetch members failed');
    res.status(500).json({ error: 'failed_to_fetch_members', detail: String(e?.message || e) });
  }
});

/** 按手机号查询群内某个用户 */
app.get('/sessions/:sessionId/groups/:groupId/members/phone/:phone', async (req, res) => {
  const { sessionId, groupId, phone } = req.params;
  const gid = ensureGroupId(groupId);
  try {
    const ctx = await ensureSession(sessionId);
    if (ctx.status !== 'open') {
      return res.status(409).json({ error: 'not_connected', message: 'session not connected yet' });
    }
    const meta = await ctx.sock.groupMetadata(gid);
    const targetJid = jidNormalizedUser(phoneToUserJid(phone));
    const found = (meta.participants || []).find((p) => jidNormalizedUser(p.id) === targetJid);
    if (!found) {
      return res.status(404).json({ error: 'not_found', message: 'user not in group' });
    }
    const info = {
      jid: jidNormalizedUser(found.id),
      phone: jidToPhone(found.id),
      admin: found.admin || null,
      isAdmin: !!found.admin
    };
    res.json({ sessionId, groupId: meta.id, subject: meta.subject, user: info });
  } catch (e) {
    logger.error({ sessionId, groupId: gid, err: e?.message }, 'query member by phone failed');
    res.status(500).json({ error: 'failed_to_query_member', detail: String(e?.message || e) });
  }
});

/** 按 JID 查询群内某个用户 */
app.get('/sessions/:sessionId/groups/:groupId/members/jid/:jid', async (req, res) => {
  const { sessionId, groupId, jid } = req.params;
  const gid = ensureGroupId(groupId);
  try {
    const ctx = await ensureSession(sessionId);
    if (ctx.status !== 'open') {
      return res.status(409).json({ error: 'not_connected', message: 'session not connected yet' });
    }
    const meta = await ctx.sock.groupMetadata(gid);
    const target = jidNormalizedUser(jid);
    const found = (meta.participants || []).find((p) => jidNormalizedUser(p.id) === target);
    if (!found) {
      return res.status(404).json({ error: 'not_found', message: 'user not in group' });
    }
    const info = {
      jid: jidNormalizedUser(found.id),
      phone: jidToPhone(found.id),
      admin: found.admin || null,
      isAdmin: !!found.admin
    };
    res.json({ sessionId, groupId: meta.id, subject: meta.subject, user: info });
  } catch (e) {
    logger.error({ sessionId, groupId: gid, err: e?.message }, 'query member by jid failed');
    res.status(500).json({ error: 'failed_to_query_member', detail: String(e?.message || e) });
  }
});

// ------------------------ 启动与优雅关闭 ------------------------
const server = app.listen(PORT, () => {
  logger.info(`Baileys API server listening on :${PORT}`);
});

// 优雅关闭：结束 HTTP；对已连接会话，仅结束 socket，不强制登出（减少风控）
async function shutdown() {
  logger.info('Shutting down...');
  try {
    server.close();
  } catch (_) {}
  for (const [sessionId, ctx] of sessions.entries()) {
    try {
      await ctx.sock?.end?.();
      logger.info({ sessionId }, 'socket ended');
    } catch (e) {
      logger.warn({ sessionId, err: e?.message }, 'end socket failed');
    }
  }
  process.exit(0);
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
