'use strict';

const http = require('http');
const mysql = require('mysql2/promise');
const { URL } = require('url');
const { Whatsapp } = require('./dist');
const { downloadMediaMessage } = require('baileys');

const PORT = Number(process.env.WA_MS_PORT || 3100);
const HOST = process.env.WA_MS_HOST || '127.0.0.1';
const AUTH_TOKEN = String(process.env.WA_MS_AUTH_TOKEN || '').trim();
const MASTER_KEY = String(process.env.WA_MS_MASTER_KEY || '').trim();
const WEBHOOK_URL = String(process.env.WA_MS_WEBHOOK_URL || '').trim();
const DB_HOST = process.env.WA_MS_DB_HOST || '127.0.0.1';
const DB_PORT = Number(process.env.WA_MS_DB_PORT || 3306);
const DB_NAME = process.env.WA_MS_DB_NAME || '';
const DB_USER = process.env.WA_MS_DB_USER || '';
const DB_PASSWORD = process.env.WA_MS_DB_PASSWORD || '';
const DB_TABLE = String(process.env.WA_MS_DB_TABLE || 'wa_multi_session_auth_store').trim();

const sessionMeta = new Map();

function ensureSessionMeta(sessionId) {
  if (!sessionMeta.has(sessionId)) {
    sessionMeta.set(sessionId, {
      status: 'idle',
      qr: null,
      lastError: null,
      updatedAt: new Date().toISOString(),
    });
  }

  return sessionMeta.get(sessionId);
}

function updateSessionMeta(sessionId, changes) {
  const prev = ensureSessionMeta(sessionId);
  const next = {
    ...prev,
    ...changes,
    updatedAt: new Date().toISOString(),
  };
  sessionMeta.set(sessionId, next);

  return next;
}

function sendJson(res, statusCode, payload) {
  const body = JSON.stringify(payload);
  res.writeHead(statusCode, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(body),
  });
  res.end(body);
}

function parseBody(req) {
  return new Promise((resolve, reject) => {
    let raw = '';

    req.on('data', (chunk) => {
      raw += chunk;
      if (raw.length > 2_000_000) {
        reject(new Error('Payload too large'));
      }
    });

    req.on('end', () => {
      if (raw.trim() === '') {
        resolve({});
        return;
      }

      try {
        resolve(JSON.parse(raw));
      } catch (_error) {
        reject(new Error('Invalid JSON payload'));
      }
    });

    req.on('error', (error) => reject(error));
  });
}

function extractAuthToken(req) {
  const authorization = String(req.headers.authorization || '').trim();
  if (authorization === '') {
    return '';
  }

  if (authorization.toLowerCase().startsWith('bearer ')) {
    return authorization.slice(7).trim();
  }

  return authorization;
}

function isAuthorized(req) {
  const incomingToken = extractAuthToken(req);
  const incomingKey = String(req.headers.key || '').trim();

  if (AUTH_TOKEN !== '' && incomingToken !== AUTH_TOKEN) {
    return false;
  }

  if (MASTER_KEY !== '' && incomingKey !== MASTER_KEY) {
    return false;
  }

  return true;
}

function resolveSessionId(req, payloadItem = {}) {
  const payloadSession = String(payloadItem.session || payloadItem.sessionId || '').trim();
  const headerSession = String(req.headers['x-session-id'] || '').trim();

  if (payloadSession !== '') {
    return payloadSession;
  }

  if (headerSession !== '') {
    return headerSession;
  }

  return 'default';
}

class MysqlAdapter {
  constructor({ pool, table }) {
    this.pool = pool;
    this.table = table;
  }

  async init() {
    await this.pool.query(`
      CREATE TABLE IF NOT EXISTS \`${this.table}\` (
        \`id\` varchar(191) NOT NULL,
        \`session_id\` varchar(191) NOT NULL,
        \`category\` varchar(120) DEFAULT NULL,
        \`value\` longtext,
        \`created_at\` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        \`updated_at\` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (\`id\`, \`session_id\`),
        KEY \`wa_multi_session_auth_store_session_id_index\` (\`session_id\`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
  }

  async readData(sessionId, key) {
    const [rows] = await this.pool.query(
      `SELECT \`value\` FROM \`${this.table}\` WHERE \`id\` = ? AND \`session_id\` = ? LIMIT 1`,
      [key, sessionId],
    );

    return rows?.[0]?.value ?? null;
  }

  async writeData(sessionId, key, category = null, value = null) {
    try {
      await this.pool.query(
        `INSERT INTO \`${this.table}\` (\`id\`, \`session_id\`, \`category\`, \`value\`, \`created_at\`, \`updated_at\`)
         VALUES (?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE \`category\` = VALUES(\`category\`), \`value\` = VALUES(\`value\`), \`updated_at\` = NOW()`,
        [key, sessionId, category, value],
      );
    } catch (err) {
      console.error(`[MysqlAdapter] writeData FAILED session=${sessionId} key=${key} category=${category} error=${err.code || ''} ${err.message}`);
      throw err;
    }
  }

  async deleteData(sessionId, key) {
    await this.pool.query(
      `DELETE FROM \`${this.table}\` WHERE \`id\` = ? AND \`session_id\` = ?`,
      [key, sessionId],
    );
  }

  async clearData(sessionId) {
    // Jika session sedang dalam mode reconnect (protected), skip clearData
    // agar credentials tidak dihapus saat Baileys retry/close connection error
    if (this._reconnectProtected && this._reconnectProtected.has(sessionId)) {
      console.log(`[MysqlAdapter] clearData SKIPPED (reconnect-protected) session=${sessionId}`);
      return;
    }
    console.log(`[MysqlAdapter] clearData session=${sessionId}`);
    await this.pool.query(`DELETE FROM \`${this.table}\` WHERE \`session_id\` = ?`, [sessionId]);
  }

  async listSessions() {
    const [rows] = await this.pool.query(`SELECT DISTINCT \`session_id\` FROM \`${this.table}\``);
    return rows.map((row) => row.session_id).filter(Boolean);
  }
}

if (!/^[A-Za-z0-9_]+$/.test(DB_TABLE)) {
  throw new Error('Invalid WA_MS_DB_TABLE value');
}

if (DB_NAME === '' || DB_USER === '') {
  throw new Error('WA_MS_DB_NAME and WA_MS_DB_USER are required');
}

const pool = mysql.createPool({
  host: DB_HOST,
  port: DB_PORT,
  database: DB_NAME,
  user: DB_USER,
  password: DB_PASSWORD,
  waitForConnections: true,
  connectionLimit: 10,
});

const adapter = new MysqlAdapter({ pool, table: DB_TABLE });

function forwardToWebhook(body) {
  if (!WEBHOOK_URL) return;
  const bodyStr = JSON.stringify(body);
  const parsedUrl = new URL(WEBHOOK_URL);
  const isHttps = parsedUrl.protocol === 'https:';
  const httpModule = isHttps ? require('https') : require('http');
  const options = {
    hostname: parsedUrl.hostname,
    port: parsedUrl.port || (isHttps ? 443 : 80),
    path: parsedUrl.pathname + parsedUrl.search,
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Content-Length': Buffer.byteLength(bodyStr),
    },
  };
  const req = httpModule.request(options, (res) => {
    res.resume();
  });
  req.on('error', (err) => {
    console.error('[webhook] forward error:', err.message);
  });
  req.write(bodyStr);
  req.end();
}

const whatsapp = new Whatsapp({
  adapter,
  autoLoad: true,
  debugLevel: 'silent',
  onConnecting(sessionId) {
    updateSessionMeta(sessionId, { status: 'connecting', lastError: null });
  },
  onConnected(sessionId) {
    updateSessionMeta(sessionId, { status: 'connected', lastError: null, qr: null });
  },
  onDisconnected(sessionId, code) {
    const codeNames = { 401: 'loggedOut', 408: 'connectionLost', 411: 'multideviceMismatch', 428: 'connectionClosed', 440: 'connectionReplaced', 500: 'badSession', 503: 'unavailableService', 515: 'restartRequired' };
    const reason = codeNames[code] || (code ? `unknown(${code})` : 'unknown');
    console.log(`[gateway] session disconnected: ${sessionId} — reason: ${reason}`);
    updateSessionMeta(sessionId, { status: 'disconnected', lastError: reason });
  },
  onQRUpdated(qrPayload) {
    if (!qrPayload) {
      return;
    }

    const payload = typeof qrPayload === 'string' ? { sessionId: 'default', qr: qrPayload } : qrPayload;
    const sessionId = String(payload.sessionId || 'default').trim() || 'default';
    updateSessionMeta(sessionId, { status: 'awaiting_qr', qr: payload.qr || null });
  },
  async onMessageReceived(msg) {
    if (!WEBHOOK_URL) return;
    try {
      const sessionId = String(msg.sessionId || '');
      const fromMe = Boolean(msg.key?.fromMe);
      const rawJid = String(msg.key?.remoteJid || '');
      const isGroup = rawJid.includes('@g.us') || rawJid.includes('@newsletter');
      const isLid = rawJid.endsWith('@lid');
      const rawPhone = rawJid.replace(/@.*/, '');

      // Skip group, status, outbound
      if (isGroup || rawPhone === 'status' || fromMe) return;

      const messageContent = msg.message?.conversation
        || msg.message?.extendedTextMessage?.text
        || msg.message?.imageMessage?.caption
        || msg.message?.videoMessage?.caption
        || msg.message?.documentMessage?.caption
        || null;

      // Detect media type
      let mediaInfo = null;
      const imgMsg = msg.message?.imageMessage;
      const vidMsg = msg.message?.videoMessage;
      const docMsg = msg.message?.documentMessage;
      const audMsg = msg.message?.audioMessage;

      const mediaMsg = imgMsg || vidMsg || docMsg || audMsg;
      if (mediaMsg) {
        const mediaType = imgMsg ? 'image' : vidMsg ? 'video' : audMsg ? 'audio' : 'document';
        try {
          const buf = await downloadMediaMessage(msg, 'buffer', {});
          mediaInfo = {
            type: mediaType,
            data: buf.toString('base64'),
            mimetype: String(mediaMsg.mimetype || ''),
            filename: String(docMsg?.fileName || ''),
          };
        } catch (dlErr) {
          console.error('[webhook] media download error:', dlErr.message);
        }
      }

      // Skip if no text and no media
      if (!messageContent && !mediaInfo) return;

      const pushName = String(msg.pushName || '');
      const messageId = String(msg.key?.id || '');

      const payload = {
        event: 'message',
        session: sessionId,
        sender: rawPhone,
        from: rawPhone,
        receiver: '',
        message: messageContent,
        fromMe: false,
        isGroup: false,
        pushName: pushName,
        id: messageId,
        ...(mediaInfo ? { media: mediaInfo } : {}),
      };

      if (isLid) {
        pool.execute(
          'SELECT value FROM `' + DB_TABLE + '` WHERE session_id = ? AND id = ? AND category = ?',
          [sessionId, 'lid-mapping-' + rawPhone + '_reverse', 'lid-mapping']
        ).then(([rows]) => {
          if (!rows || !rows.length) {
            console.error('[webhook] LID resolve: not found for', rawPhone);
            return;
          }
          let phone = rows[0].value;
          try { phone = JSON.parse(phone); } catch (_) {}
          phone = String(phone || '').replace(/\D/g, '');
          if (!phone) return;
          forwardToWebhook({ ...payload, sender: phone, from: phone });
        }).catch((err) => {
          console.error('[webhook] LID resolve error:', err.message);
        });
      } else {
        forwardToWebhook(payload);
      }
    } catch (e) {
      console.error('[webhook] onMessageReceived error:', e.message);
    }
  },
});

async function ensureSessionStarted(sessionId) {
  const existing = await whatsapp.getSessionById(sessionId);
  if (existing) {
    return { started: false };
  }

  await whatsapp.startSession(sessionId, { printQR: false });
  return { started: true };
}

/**
 * Reconnect session tanpa menghapus credentials dari DB.
 * Set adapter._reconnectProtected agar clearData() di-skip selama proses reconnect,
 * sehingga Baileys tidak bisa menghapus credentials saat retry/close connection error.
 * Proteksi dilepas setelah session connected atau timeout 3 menit.
 */
async function reconnectSession(sessionId) {
  // Aktifkan proteksi clearData untuk session ini
  if (!adapter._reconnectProtected) adapter._reconnectProtected = new Set();
  adapter._reconnectProtected.add(sessionId);
  console.log(`[reconnectSession] protecting credentials for ${sessionId}`);

  // Jadwalkan release proteksi setelah 3 menit (safety fallback)
  const releaseProtection = () => {
    if (adapter._reconnectProtected) {
      adapter._reconnectProtected.delete(sessionId);
      console.log(`[reconnectSession] protection released for ${sessionId}`);
    }
  };
  const protectionTimer = setTimeout(releaseProtection, 3 * 60 * 1000);

  try {
    // Matikan socket tanpa logout agar tidak memutus pair perangkat WA.
    await whatsapp.closeSession(sessionId);
    updateSessionMeta(sessionId, { status: 'reconnecting', qr: null });

    // Start ulang dengan timeout 90 detik agar tidak hang selamanya
    await Promise.race([
      whatsapp.startSession(sessionId, { printQR: false }),
      new Promise((_, reject) =>
        setTimeout(() => reject(new Error('startSession timeout after 90s')), 90 * 1000)
      ),
    ]);
  } catch (err) {
    console.error(`[reconnectSession] error for ${sessionId}:`, err.message);
    updateSessionMeta(sessionId, { status: 'error', lastError: err.message });
    clearTimeout(protectionTimer);
    releaseProtection();
    throw err;
  }
}

async function sessionStatus(sessionId) {
  const current = await whatsapp.getSessionById(sessionId);
  const meta = ensureSessionMeta(sessionId);

  return {
    session: sessionId,
    status: current?.status || meta.status || 'idle',
    qr: meta.qr,
    last_error: meta.lastError,
    updated_at: meta.updatedAt,
  };
}

const server = http.createServer(async (req, res) => {
  if (!isAuthorized(req)) {
    sendJson(res, 401, {
      status: false,
      message: 'Unauthorized',
    });
    return;
  }

  const method = String(req.method || 'GET').toUpperCase();
  const parsedUrl = new URL(req.url || '/', `http://${HOST}:${PORT}`);
  const pathname = parsedUrl.pathname;

  try {
    if (method === 'GET' && pathname === '/status') {
      sendJson(res, 200, { status: true, service: 'wa-multi-session-bridge' });
      return;
    }

    if (method === 'GET' && pathname === '/api/v2/device/info') {
      const sessionId = resolveSessionId(req);
      const status = await sessionStatus(sessionId);
      sendJson(res, 200, {
        status: true,
        message: 'ok',
        data: {
          service: 'wa-multi-session-bridge',
          ...status,
        },
      });
      return;
    }

    if (method === 'GET' && pathname === '/api/v2/sessions/status') {
      const requestedSession = String(parsedUrl.searchParams.get('session') || '').trim();
      const sessionId = requestedSession !== '' ? requestedSession : resolveSessionId(req);
      const status = await sessionStatus(sessionId);
      sendJson(res, 200, {
        status: true,
        message: 'ok',
        data: status,
      });
      return;
    }

    if (method === 'POST' && pathname === '/api/v2/sessions/start') {
      const payload = await parseBody(req);
      const sessionId = resolveSessionId(req, payload || {});

      await ensureSessionStarted(sessionId);

      sendJson(res, 200, {
        status: true,
        message: 'Session start triggered',
        data: await sessionStatus(sessionId),
      });
      return;
    }

    if (method === 'POST' && pathname === '/api/v2/sessions/stop') {
      const payload = await parseBody(req);
      const sessionId = resolveSessionId(req, payload || {});
      // keep_credentials=true: matikan socket saja tanpa hapus credentials dari DB
      const keepCredentials = Boolean((payload || {}).keep_credentials);

      if (keepCredentials) {
        if (!adapter._reconnectProtected) adapter._reconnectProtected = new Set();
        adapter._reconnectProtected.add(sessionId);
        setTimeout(() => {
          if (adapter._reconnectProtected) adapter._reconnectProtected.delete(sessionId);
        }, 10 * 60 * 1000); // 10 menit
      }

      await whatsapp.deleteSession(sessionId);
      updateSessionMeta(sessionId, { status: 'stopped', qr: null });

      sendJson(res, 200, {
        status: true,
        message: 'Session stopped',
        data: await sessionStatus(sessionId),
      });
      return;
    }

    if (method === 'POST' && pathname === '/api/v2/sessions/restart') {
      const payload = await parseBody(req);
      const sessionId = resolveSessionId(req, payload || {});

      // Reconnect: matikan socket tanpa hapus credentials di DB
      try {
        await reconnectSession(sessionId);
        sendJson(res, 200, {
          status: true,
          message: 'Session reconnect triggered',
          data: await sessionStatus(sessionId),
        });
      } catch (reconnectErr) {
        sendJson(res, 500, {
          status: false,
          message: reconnectErr instanceof Error ? reconnectErr.message : String(reconnectErr),
          data: await sessionStatus(sessionId),
        });
      }
      return;
    }

    if (method === 'POST' && pathname === '/api/v2/send-message') {
      const payload = await parseBody(req);
      const data = Array.isArray(payload.data) ? payload.data : [];

      if (data.length === 0) {
        sendJson(res, 422, {
          status: false,
          message: 'Invalid payload: data[] is required',
        });
        return;
      }

      const messages = [];
      for (const item of data) {
        const sessionId = resolveSessionId(req, item || {});
        const phone = String(item.phone || '').trim();
        const message = String(item.message || '').trim();
        const isGroup = Boolean(item.isGroup);
        const refId = String(item.ref_id || '').trim() || null;

        if (phone === '' || message === '') {
          messages.push({
            status: 'failed',
            session: sessionId,
            ref_id: refId,
            error: 'phone/message is required',
          });
          continue;
        }

        try {
          const started = await ensureSessionStarted(sessionId);
          if (started.started) {
            updateSessionMeta(sessionId, { status: 'connecting' });
          }

          await whatsapp.sendText({
            sessionId,
            to: phone,
            text: message,
            isGroup,
          });

          messages.push({
            status: 'queued',
            session: sessionId,
            ref_id: refId,
          });
        } catch (error) {
          const errMessage = error instanceof Error ? error.message : String(error);
          updateSessionMeta(sessionId, { status: 'error', lastError: errMessage });

          messages.push({
            status: 'failed',
            session: sessionId,
            ref_id: refId,
            error: errMessage,
          });
        }
      }

      sendJson(res, 200, {
        status: true,
        message: 'Processed',
        data: { messages },
      });
      return;
    }

    if (method === 'POST' && pathname === '/api/v2/send-image') {
      const payload = await parseBody(req);
      const data = Array.isArray(payload.data) ? payload.data : [];

      if (data.length === 0) {
        sendJson(res, 422, { status: false, message: 'Invalid payload: data[] is required' });
        return;
      }

      const messages = [];
      for (const item of data) {
        const sessionId = resolveSessionId(req, item || {});
        const phone = String(item.phone || '').trim();
        const caption = String(item.caption || '').trim();
        const mediaUrl = String(item.media_url || '').trim();
        const refId = String(item.ref_id || '').trim() || null;

        if (phone === '' || mediaUrl === '') {
          messages.push({ status: 'failed', session: sessionId, ref_id: refId, error: 'phone/media_url is required' });
          continue;
        }

        try {
          const started = await ensureSessionStarted(sessionId);
          if (started.started) updateSessionMeta(sessionId, { status: 'connecting' });

          await whatsapp.sendImage({
            sessionId,
            to: phone,
            text: caption,
            media: mediaUrl,
            isGroup: false,
          });

          messages.push({ status: 'queued', session: sessionId, ref_id: refId });
        } catch (error) {
          const errMessage = error instanceof Error ? error.message : String(error);
          updateSessionMeta(sessionId, { status: 'error', lastError: errMessage });
          messages.push({ status: 'failed', session: sessionId, ref_id: refId, error: errMessage });
        }
      }

      sendJson(res, 200, { status: true, message: 'Processed', data: { messages } });
      return;
    }

    // List joined WhatsApp groups for a session
    if (method === 'GET' && pathname === '/api/v2/groups') {
      const requestedSession = String(parsedUrl.searchParams.get('session') || '').trim();
      const sessionId = requestedSession || 'default';

      try {
        const session = await whatsapp.getSessionById(sessionId);

        if (!session || !session.sock) {
          sendJson(res, 404, { status: false, message: 'Session not found or not ready' });
          return;
        }

        const groupsMap = await session.sock.groupFetchAllParticipating();
        const groups = Object.values(groupsMap).map((g) => ({
          id: g.id,
          subject: g.subject || '',
          size: g.size || 0,
        }));

        groups.sort((a, b) => a.subject.localeCompare(b.subject));

        sendJson(res, 200, { status: true, data: { groups } });
      } catch (error) {
        const errMessage = error instanceof Error ? error.message : String(error);
        sendJson(res, 500, { status: false, message: errMessage });
      }
      return;
    }

    // Keepalive presence — update status "available" untuk menjaga sesi tetap aktif
    if (method === 'POST' && pathname === '/api/v2/presence') {
      const payload = await parseBody(req);
      const sessionId = resolveSessionId(req, payload || {});

      try {
        const session = await whatsapp.getSessionById(sessionId);

        if (!session || !session.sock) {
          sendJson(res, 404, { status: false, message: 'Session not found or not ready' });
          return;
        }

        await session.sock.sendPresenceUpdate('available');

        sendJson(res, 200, { status: true, message: 'Presence updated', session: sessionId });
      } catch (error) {
        const errMessage = error instanceof Error ? error.message : String(error);
        sendJson(res, 500, { status: false, message: errMessage });
      }
      return;
    }

    sendJson(res, 404, {
      status: false,
      message: 'Not Found',
    });
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    sendJson(res, 500, {
      status: false,
      message,
    });
  }
});

(async () => {
  await adapter.init();

  server.listen(PORT, HOST, () => {
    console.log(`[wa-multi-session-bridge] listening on http://${HOST}:${PORT}`);
  });
})();
