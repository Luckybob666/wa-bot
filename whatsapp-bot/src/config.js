import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

export const config = {
    port: process.env.PORT || 3000,
    laravelUrl: process.env.LARAVEL_URL || 'http://localhost:89',
    sessionsDir: path.join(rootDir, 'sessions')
};

