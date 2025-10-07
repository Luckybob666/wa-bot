# WhatsApp 群用户统计机器人开发文档

## 1. 项目背景

本项目旨在开发一个基于 **WhatsApp** 的机器人管理系统，用于 **监控多个 WhatsApp 账号下的群成员加入情况，收集群用户号码，并在后台进行可视化统计**。

### 核心功能
- **多机器人账号管理**：支持管理多个 WhatsApp 号码
- **后台扫码登录**：通过 Web 后台扫码登录 WhatsApp 账号
- **群成员监控**：实时监控群成员加入/退出情况
- **数据可视化**：提供群运营数据分析和统计图表

### 应用场景
- 营销推广：多账号群发管理
- 用户增长追踪：跨账号用户增长分析
- 群运营数据分析：多维度数据统计
- 客服管理：多账号客服群管理

---

## 2. 技术栈选型

### 2.1 机器人层

- **语言**：Node.js
- **框架/库**：
    - [Baileys](https://github.com/adiwajshing/Baileys) —— 非官方 WhatsApp Web API SDK，支持群事件监听
    - [Socket.io](https://socket.io/) —— WebSocket 通信，用于实时 QR 码传输
- **功能**：
    - **多账号管理**：支持同时管理多个 WhatsApp 账号
    - **后台扫码登录**：通过 WebSocket 实时传输 QR 码到后台
    - **会话管理**：自动保存和恢复 WhatsApp 会话状态
    - **群事件监听**：监听群成员加入/退出事件
    - **数据采集**：收集用户手机号、昵称、加入时间
    - **API 调用**：将数据实时推送到 Laravel 后台

---

### 2.2 后端层

- **语言**：PHP
- **框架**：Laravel
- **后台管理面板**：Filament Admin
- **数据库**：MySQL
- **实时通信**：Laravel WebSockets / Pusher
- **功能**：
    - **机器人账号管理**：管理多个 WhatsApp 机器人账号
    - **QR 码登录界面**：提供扫码登录界面
    - **API 接口**：接收机器人传来的群成员数据
    - **会话状态管理**：管理 WhatsApp 登录状态
    - **数据存储**：存储群与成员关系
    - **后台管理**：管理机器人账号、群/用户信息
    - **实时通知**：WebSocket 推送登录状态和群事件
    - **图表统计**：Filament Chart Widgets 数据可视化

---

### 2.3 前端层（可视化）

- **框架**：Filament 自带管理后台
- **图表库**：Chart.js（集成在 Filament）
- **实时通信**：Alpine.js + WebSocket
- **功能**：
    - **机器人管理界面**：添加/删除/管理 WhatsApp 机器人账号
    - **扫码登录界面**：实时显示 QR 码，支持扫码登录
    - **登录状态监控**：实时显示各机器人登录状态
    - **群成员增长曲线**：按机器人账号分组的增长趋势
    - **多群用户数对比**：跨机器人账号的群对比
    - **用户活跃度统计**：多维度活跃度分析
    - **实时通知**：群事件实时推送显示

---

## 3. 系统架构

```
[多个 WhatsApp 账号]
      │
      ▼
[多机器人管理器 - Node.js]
      │  (WebSocket + API)
      ▼
[Laravel 后台系统]
      │
      ├── [Filament 管理面板]
      │   ├── 机器人账号管理
      │   ├── QR 码登录界面
      │   ├── 登录状态监控
      │   └── 数据统计图表
      │
      ├── [WebSocket 服务]
      │   ├── QR 码实时传输
      │   ├── 登录状态推送
      │   └── 群事件通知
      │
      └── [API 接口层]
          ├── 机器人数据接收
          ├── 会话状态管理
          └── 数据持久化
              │
              ▼
          [MySQL 数据库]
```

### 架构特点

- **多机器人支持**：支持同时管理多个 WhatsApp 机器人账号
- **实时通信**：WebSocket 实现 QR 码实时传输和状态推送
- **统一管理**：通过 Filament 后台统一管理所有机器人账号
- **数据隔离**：通过 `bot_id` 区分不同机器人账号的数据
- **解耦设计**：机器人与后台通过 API + WebSocket 对接，支持独立扩展

---

## 4. 业务功能设计

### 4.1 机器人功能

#### 4.1.1 多账号管理
- **账号注册**：在后台添加新的 WhatsApp 机器人账号
- **会话管理**：自动保存和恢复 WhatsApp 会话状态
- **状态监控**：实时监控各机器人账号的登录状态
- **自动重连**：网络断开时自动重连

#### 4.1.2 扫码登录流程
1. 用户在后台点击"添加机器人"
2. 系统生成唯一的机器人 ID
3. 机器人通过 WebSocket 发送 QR 码到后台
4. 用户在后台界面扫码登录
5. 登录成功后保存会话状态

#### 4.1.3 群事件监听
- **群事件监听**：
    - 新成员加入
    - 成员退出
    - 群信息变更
- **数据采集**：
    - 机器人 ID（区分不同账号）
    - 群 ID、群名称
    - 用户 ID（手机号）、昵称
    - 加入/退出时间
- **实时推送**：通过 WebSocket 实时推送事件到后台
- **API 调用**：调用 Laravel API，将事件数据写入数据库

---

### 4.2 后端功能

#### 4.2.1 API 接口
- **机器人管理接口**
    - `POST /api/bots` → 创建新机器人账号
    - `GET /api/bots` → 获取机器人列表
    - `PUT /api/bots/{id}` → 更新机器人信息
    - `DELETE /api/bots/{id}` → 删除机器人账号
- **群事件接口**
    - `POST /api/member-joined` → 记录用户进群
    - `POST /api/member-left` → 记录用户退群
    - `POST /api/group-updated` → 记录群信息变更
- **会话管理接口**
    - `POST /api/bot/session/save` → 保存会话状态
    - `GET /api/bot/session/{bot_id}` → 获取会话状态
    - `POST /api/bot/qr-code` → 接收 QR 码数据

#### 4.2.2 WebSocket 服务
- **QR 码传输**：实时传输 WhatsApp 登录 QR 码
- **状态推送**：推送机器人登录状态变化
- **事件通知**：实时推送群事件到后台界面

#### 4.2.3 后台管理（Filament）
- **机器人管理**
    - 添加/编辑/删除机器人账号
    - 查看机器人登录状态
    - 扫码登录界面
- **群管理**
    - 按机器人分组的群列表
    - 群信息编辑
    - 群成员查看
- **用户管理**
    - 跨机器人账号的用户管理
    - 用户活跃度分析
- **数据统计**
    - 按机器人分组的群人数趋势
    - 跨机器人账号对比分析
    - 实时数据监控面板

---

### 4.3 可视化统计功能

#### 4.3.1 机器人账号统计
- **机器人状态监控**：实时显示各机器人登录状态
- **机器人活跃度**：统计各机器人的群事件数量
- **账号对比分析**：对比不同机器人账号的群数量

#### 4.3.2 群数据统计
- **群人数趋势**（折线图）：按机器人分组的每日新增用户数、累计人数
- **跨机器人群对比**（柱状图）：展示不同机器人账号下的群人数对比
- **群活跃度分析**：统计各群在一定周期内的新增人数

#### 4.3.3 用户数据分析
- **用户分布**：按机器人账号统计用户分布
- **用户活跃度**：跨机器人账号的用户活跃度分析
- **用户增长趋势**：多维度用户增长趋势分析

#### 4.3.4 实时监控面板
- **实时事件流**：显示最新的群事件
- **机器人状态**：实时显示各机器人连接状态
- **数据更新通知**：实时推送数据更新通知

---

## 5. 数据库设计（MySQL）

### 5.1 表：`bots`（机器人账号表）

| 字段 | 类型 | 描述 |
| --- | --- | --- |
| id (PK) | BIGINT | 主键 |
| name | VARCHAR(255) | 机器人名称 |
| phone_number | VARCHAR(50) | WhatsApp 手机号 |
| status | ENUM('offline', 'connecting', 'online', 'error') | 登录状态 |
| session_data | LONGTEXT | WhatsApp 会话数据（JSON） |
| last_seen | TIMESTAMP | 最后活跃时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

---

### 5.2 表：`groups`（群表）

| 字段 | 类型 | 描述 |
| --- | --- | --- |
| id (PK) | BIGINT | 主键 |
| bot_id (FK) | BIGINT | 关联 `bots.id` |
| group_id | VARCHAR(50) | WhatsApp 群 ID |
| name | VARCHAR(255) | 群名称 |
| description | TEXT | 群描述 |
| member_count | INT | 当前成员数量 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

---

### 5.3 表：`whatsapp_users`（WhatsApp用户表）

| 字段 | 类型 | 描述 |
| --- | --- | --- |
| id (PK) | BIGINT | 主键 |
| phone_number | VARCHAR(50) | 用户手机号 |
| nickname | VARCHAR(255) | 用户昵称 |
| profile_picture | VARCHAR(500) | 头像URL |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

---

### 5.4 表：`group_whatsapp_user`（群-WhatsApp用户关系表）

| 字段 | 类型 | 描述 |
| --- | --- | --- |
| id (PK) | BIGINT | 主键 |
| group_id (FK) | BIGINT | 关联 `groups.id` |
| whatsapp_user_id (FK) | BIGINT | 关联 `whatsapp_users.id` |
| joined_at | TIMESTAMP | 加入时间 |
| left_at | TIMESTAMP | 退出时间（未退出为 NULL） |
| is_admin | BOOLEAN | 是否为群管理员 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

---

### 5.5 表：`bot_sessions`（机器人会话表）

| 字段 | 类型 | 描述 |
| --- | --- | --- |
| id (PK) | BIGINT | 主键 |
| bot_id (FK) | BIGINT | 关联 `bots.id` |
| session_data | LONGTEXT | 会话数据（JSON） |
| qr_code | TEXT | QR 码数据 |
| expires_at | TIMESTAMP | 会话过期时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

---

### 5.6 表：`group_events`（群事件日志表）

| 字段 | 类型 | 描述 |
| --- | --- | --- |
| id (PK) | BIGINT | 主键 |
| bot_id (FK) | BIGINT | 关联 `bots.id` |
| group_id (FK) | BIGINT | 关联 `groups.id` |
| whatsapp_user_id (FK) | BIGINT | 关联 `whatsapp_users.id`（可为空） |
| event_type | ENUM('member_joined', 'member_left', 'group_updated') | 事件类型 |
| event_data | JSON | 事件详细数据 |
| created_at | TIMESTAMP | 事件发生时间 |

---

## 6. 部署方案

### 6.1 服务器架构

```
[负载均衡器] (可选)
      │
      ▼
[Web 服务器] (Nginx + PHP-FPM)
      │
      ├── [Laravel 应用]
      │   ├── Filament 后台管理
      │   ├── API 接口
      │   └── WebSocket 服务
      │
      └── [Node.js 机器人服务]
          ├── 多机器人管理器
          ├── Baileys WhatsApp 客户端
          └── WebSocket 客户端
      │
      ▼
[MySQL 数据库]
```

### 6.2 部署组件

#### 6.2.1 机器人服务（Node.js）
- **部署方式**：Docker 容器或直接部署
- **进程管理**：PM2 管理多个机器人进程
- **配置管理**：环境变量配置机器人参数
- **日志管理**：Winston 日志库，按机器人分文件记录
- **监控**：PM2 监控 + 自定义健康检查

#### 6.2.2 后台服务（Laravel）
- **Web 服务器**：Nginx + PHP-FPM
- **队列服务**：Redis + Laravel Queue
- **WebSocket 服务**：Laravel WebSockets 或 Pusher
- **缓存服务**：Redis
- **文件存储**：本地存储或云存储（会话文件）

#### 6.2.3 数据库服务
- **主数据库**：MySQL 8.0+
- **备份策略**：每日自动备份
- **性能优化**：索引优化，读写分离（可选）

### 6.3 环境配置

#### 6.3.1 开发环境
```bash
# Laravel 开发环境
php artisan serve
php artisan websockets:serve

# Node.js 机器人开发
npm run dev
```

#### 6.3.2 生产环境
```bash
# Laravel 生产部署
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Node.js 生产部署
npm install --production
pm2 start ecosystem.config.js
```

### 6.4 监控和维护

#### 6.4.1 系统监控
- **服务器监控**：CPU、内存、磁盘使用率
- **应用监控**：Laravel 日志、Node.js 日志
- **数据库监控**：MySQL 性能监控
- **机器人状态**：实时监控各机器人连接状态

#### 6.4.2 日志管理
- **Laravel 日志**：按日期分文件，保留 30 天
- **机器人日志**：按机器人 ID 分文件
- **WebSocket 日志**：连接状态和事件日志
- **错误告警**：关键错误自动邮件通知

#### 6.4.3 备份策略
- **数据库备份**：每日自动备份，保留 7 天
- **会话文件备份**：每日备份 WhatsApp 会话数据
- **配置文件备份**：版本控制管理

---

## 7. 主要改进总结

### 7.1 新增功能

#### 7.1.1 多机器人账号支持
- **统一管理**：通过 Filament 后台统一管理多个 WhatsApp 机器人账号
- **数据隔离**：每个机器人账号的数据独立存储和统计
- **状态监控**：实时监控各机器人账号的登录状态和活跃度
- **灵活扩展**：支持动态添加/删除机器人账号

#### 7.1.2 后台扫码登录
- **Web 界面登录**：通过后台 Web 界面扫码登录 WhatsApp
- **实时 QR 码**：WebSocket 实时传输 QR 码到后台
- **会话管理**：自动保存和恢复 WhatsApp 会话状态
- **状态同步**：登录状态实时同步到后台界面

#### 7.1.3 增强的数据统计
- **多维度分析**：按机器人账号、群、用户等多维度统计
- **实时监控**：实时显示群事件和机器人状态
- **对比分析**：跨机器人账号的数据对比分析
- **可视化图表**：丰富的图表展示数据趋势

### 7.2 数据库结构优化

#### 7.2.1 新增表结构
- **`bots` 表**：管理机器人账号信息
- **`bot_sessions` 表**：管理 WhatsApp 会话数据
- **`group_events` 表**：记录详细的群事件日志

#### 7.2.2 现有表结构增强
- **`groups` 表**：增加 `bot_id` 外键关联
- **`whatsapp_users` 表**：增加头像字段
- **`group_whatsapp_user` 表**：增加管理员标识

### 7.3 技术架构升级

#### 7.3.1 实时通信
- **WebSocket 服务**：实现实时 QR 码传输和状态推送
- **事件驱动**：基于事件的实时数据更新
- **状态同步**：多端状态实时同步

#### 7.3.2 系统解耦
- **API 接口**：标准化的 RESTful API
- **微服务架构**：机器人和后台服务独立部署
- **数据隔离**：多租户数据隔离设计

### 7.4 开发建议

#### 7.4.1 开发顺序
1. **数据库迁移**：创建新的数据库表结构
2. **Filament 管理面板**：实现机器人账号管理界面
3. **WebSocket 服务**：实现实时通信功能
4. **Node.js 机器人**：实现多机器人管理功能
5. **API 接口**：实现数据交互接口
6. **统计图表**：实现数据可视化功能

#### 7.4.2 关键技术点
- **会话管理**：WhatsApp 会话的保存和恢复机制
- **QR 码处理**：实时 QR 码生成和传输
- **状态同步**：多端状态一致性保证
- **错误处理**：网络异常和登录失败的处理机制
- **性能优化**：大量群事件的处理和存储优化

---
