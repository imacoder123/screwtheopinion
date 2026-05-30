require('dotenv').config();
const jwt = require('jsonwebtoken');
const bcrypt = require('bcrypt');
const db = require('../db');

// Configuration
const JWT_SECRET = process.env.JWT_SECRET || 'default_secret';
const JWT_EXPIRY = process.env.JWT_EXPIRY ? parseInt(process.env.JWT_EXPIRY) : 3600; // seconds
const JWT_REFRESH_EXPIRY = process.env.JWT_REFRESH_EXPIRY ? parseInt(process.env.JWT_REFRESH_EXPIRY) : 604800; // seconds

/** Generate a JWT access token */
function generateJwt(payload) {
  const options = { expiresIn: JWT_EXPIRY };
  return jwt.sign(payload, JWT_SECRET, options);
}

/** Verify and decode a JWT token */
function verifyJwt(token) {
  try {
    const decoded = jwt.verify(token, JWT_SECRET);
    return decoded;
  } catch (err) {
    return null;
  }
}

/** Generate a secure random token (e.g., refresh token) */
function generateToken(length = 64) {
  // length is number of hex characters, so bytes = length/2
  return require('crypto').randomBytes(length / 2).toString('hex');
}

/** Get JWT-authenticated user from Authorization header */
function getAuthUser(req) {
  const authHeader = req.headers['authorization'] || req.headers['Authorization'];
  if (!authHeader) return null;
  const match = authHeader.match(/^Bearer\s+(.+)$/i);
  if (!match) return null;
  return verifyJwt(match[1]);
}

/** Middleware to require authentication */
function requireAuth(req, res, next) {
  const user = getAuthUser(req);
  if (!user) {
    return res.status(401).json({ error: 'unauthorized', message: 'Authentication required' });
  }
  req.user = user;
  next();
}

/** Sanitize input string */
function sanitize(input) {
  if (typeof input !== 'string') return '';
  return input.replace(/<[^>]*>/g, '').trim();
}

/** Validate email format */
function validEmail(email) {
  return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email);
}

/** Get JSON request body (already parsed by Express) */
function getJsonBody(req) {
  return req.body || {};
}

/** Send JSON response with status */
function jsonResponse(res, data, status = 200) {
  return res.status(status).json(data);
}

/** Get client IP address */
function getClientIp(req) {
  const headers = req.headers;
  const ip = headers['x-forwarded-for'] || req.socket?.remoteAddress || '';
  // In case of list of IPs, take first
  if (ip && typeof ip === 'string' && ip.includes(',')) {
    return ip.split(',')[0].trim();
  }
  return ip;
}

/** Rate limiting - check and increment */
async function checkRateLimit(identifier, action, maxHits = 30, windowSecs = 60) {
  const client = await db.getClient();
  try {
    const windowStart = new Date(Date.now() - windowSecs * 1000);
    // Count hits in the window
    const countRes = await client.query(
      'SELECT COUNT(*) FROM rate_limits WHERE identifier = $1 AND action = $2 AND window_start >= $3',
      [identifier, action, windowStart]
    );
    const total = parseInt(countRes.rows[0].count, 10);
    if (total >= maxHits) {
      return false;
    }
    // Insert a new hit
    await client.query(
      'INSERT INTO rate_limits (identifier, action, hits, window_start) VALUES ($1, $2, 1, NOW())',
      [identifier, action]
    );
    return true;
  } finally {
    client.release();
  }
}

/** Create a notification */
async function createNotification({ userId, type, referenceId = null, conversationId = null, messageId = null, senderId = null, title = null, body = null }) {
  const client = await db.getClient();
  try {
    const res = await client.query(
      `INSERT INTO notifications (user_id, type, reference_id, conversation_id, message_id, sender_id, title, body)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING id`,
      [userId, type, referenceId, conversationId, messageId, senderId, title, body]
    );
    return parseInt(res.rows[0].id, 10);
  } finally {
    client.release();
  }
}

/** Get user presence info */
async function getUserPresence(userId) {
  const client = await db.getClient();
  try {
    const res = await client.query('SELECT status, last_seen FROM user_presence WHERE user_id = $1', [userId]);
    if (res.rowCount === 0) {
      // Insert default offline record
      await client.query('INSERT INTO user_presence (user_id, status) VALUES ($1, $2)', [userId, 'offline']);
      return { status: 'offline', last_seen: null };
    }
    return res.rows[0];
  } finally {
    client.release();
  }
}

/** Update user presence */
async function updatePresence(userId, status = 'online') {
  const client = await db.getClient();
  try {
    await client.query(
      `INSERT INTO user_presence (user_id, status, last_seen)
       VALUES ($1, $2, NOW())
       ON CONFLICT (user_id) DO UPDATE SET status = $2, last_seen = NOW()`,
      [userId, status]
    );
  } finally {
    client.release();
  }
}

/** Check if two users are contacts */
async function areContacts(userId1, userId2) {
  const client = await db.getClient();
  try {
    const res = await client.query(
      `SELECT id FROM contacts WHERE (user1_id = $1 AND user2_id = $2) OR (user1_id = $2 AND user2_id = $1) LIMIT 1`,
      [userId1, userId2]
    );
    return res.rowCount > 0;
  } finally {
    client.release();
  }
}

/** Check if a user is blocked by another */
async function isBlocked(userId, blockedByUserId) {
  const client = await db.getClient();
  try {
    const res = await client.query(
      'SELECT id FROM blocks WHERE blocker_id = $1 AND blocked_id = $2 LIMIT 1',
      [blockedByUserId, userId]
    );
    return res.rowCount > 0;
  } finally {
    client.release();
  }
}

module.exports = {
  generateJwt,
  verifyJwt,
  generateToken,
  getAuthUser,
  requireAuth,
  sanitize,
  validEmail,
  getJsonBody,
  jsonResponse,
  checkRateLimit,
  createNotification,
  getUserPresence,
  updatePresence,
  getClientIp,
  areContacts,
  isBlocked,
};
