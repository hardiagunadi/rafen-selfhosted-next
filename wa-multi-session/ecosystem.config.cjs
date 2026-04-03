'use strict';

const path = require('path');

const waPath = __dirname;
const appRoot = path.resolve(__dirname, '..');
const logFile = path.join(appRoot, 'storage', 'logs', 'wa-multi-session-pm2.log');

module.exports = {
  apps: [
    {
      name: 'wa-multi-session',
      script: 'gateway-server.cjs',
      cwd: waPath,
      output: logFile,
      error_file: logFile,
      time: true,
      max_memory_restart: '400M',
      kill_timeout: 5000,
      restart_delay: 3000,
      env: {
        WA_MS_PORT: '3100',
        WA_MS_HOST: '127.0.0.1',
        WA_MS_AUTH_TOKEN: '',
        WA_MS_MASTER_KEY: '',
        WA_MS_DB_HOST: '127.0.0.1',
        WA_MS_DB_PORT: '3306',
        WA_MS_DB_NAME: 'rafen_selfhosted',
        WA_MS_DB_USER: 'rafen',
        WA_MS_DB_PASSWORD: '',
        WA_MS_DB_TABLE: 'wa_multi_session_auth_store',
        WA_MS_WEBHOOK_URL: '',
      },
    },
  ],
};
