const express = require('express');
const router = express.Router();
const bcrypt = require('bcrypt');
const db = require('../db');
const {
  generateJwt,
  generateToken,
  getAuthUser,
  requireAuth,
  sanitize,
  validEmail,
  checkRateLimit,
  createNotification,
  updatePresence,
  getUserPresence,
  areContacts,
  isBlocked,
  getClientIp,
} = require('../utils/helpers');

router.post('/auth', async (req, res) => {
  const data = req.body || {};
  const username = sanitize(data.username || '');
  const password = data.password || '';

  if (!username || !password) {
    return res.status(400).json({ error: 'missing_fields', message: 'Username and password required' });
  }

  try {
    const userRes = await db.query(
      'SELECT id, username, name, email, password, avatar, bio, is_admin, is_verified FROM users WHERE username = $1 OR email = $1',
      [username]
    );
    const user = userRes.rows[0];
    if (!user) {
      return res.status(401).json({ error: 'invalid_credentials', message: 'Invalid username or password' });
    }
    const passwordMatch = await bcrypt.compare(password, user.password);
    if (!passwordMatch) {
      return res.status(401).json({ error: 'invalid_credentials', message: 'Invalid username or password' });
    }

    const ip = getClientIp(req);
    const allowed = await checkRateLimit(`login:${ip}`, 'login', 5, 300);
    if (!allowed) {
      return res.status(429).json({ error: 'rate_limited', message: 'Too many login attempts. Try again in 5 minutes.' });
    }

    const accessToken = generateJwt({ user_id: user.id, username: user.username, is_admin: !!user.is_admin });
    const refreshToken = generateToken();

    const deviceInfo = req.headers['user-agent'] || 'unknown';
    await db.query(
      `INSERT INTO user_sessions (user_id, access_token, refresh_token, device_info, ip_address, last_activity, expires_at)
       VALUES ($1, $2, $3, $4, $5, NOW(), NOW() + INTERVAL '7 days')`,
      [user.id, accessToken, refreshToken, deviceInfo, ip]
    );

    await updatePresence(user.id, 'online');

    return res.json({
      access_token: accessToken,
      refresh_token: refreshToken,
      user: {
        id: user.id,
        username: user.username,
        name: user.name,
        email: user.email,
        avatar: user.avatar,
        bio: user.bio,
        is_admin: !!user.is_admin,
        is_verified: !!user.is_verified,
      },
    });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error', message: 'An unexpected error occurred' });
  }
});

router.post('/register', async (req, res) => {
  const data = req.body || {};
  const name = sanitize(data.name || '');
  const email = sanitize(data.email || '');
  const username = sanitize(data.username || '');
  const password = data.password || '';
  const confirmPassword = data.confirm_password || '';

  if (!name || !email || !username || !password) {
    return res.status(400).json({ error: 'missing_fields', message: 'All fields are required' });
  }
  if (!validEmail(email)) {
    return res.status(400).json({ error: 'invalid_email', message: 'Please provide a valid email address' });
  }
  if (username.length < 3 || username.length > 30) {
    return res.status(400).json({ error: 'invalid_username', message: 'Username must be between 3 and 30 characters' });
  }
  if (!/^[a-zA-Z0-9_]+$/.test(username)) {
    return res.status(400).json({ error: 'invalid_username', message: 'Username can only contain letters, numbers, and underscores' });
  }
  if (password.length < 6) {
    return res.status(400).json({ error: 'weak_password', message: 'Password must be at least 6 characters' });
  }
  if (password !== confirmPassword) {
    return res.status(400).json({ error: 'password_mismatch', message: 'Passwords do not match' });
  }

  const ip = getClientIp(req);
  const allowed = await checkRateLimit(`register:${ip}`, 'register', 3, 3600);
  if (!allowed) {
    return res.status(429).json({ error: 'rate_limited', message: 'Too many registration attempts' });
  }

  try {
    const existing = await db.query('SELECT id FROM users WHERE email = $1 OR username = $2', [email, username]);
    if (existing.rowCount > 0) {
      return res.status(409).json({ error: 'user_exists', message: 'Email or username already exists' });
    }

    const hashedPassword = await bcrypt.hash(password, 12);
    const insertRes = await db.query(
      'INSERT INTO users (name, email, username, password) VALUES ($1, $2, $3, $4) RETURNING id',
      [name, email, username, hashedPassword]
    );
    const userId = insertRes.rows[0].id;

    const accessToken = generateJwt({ user_id: userId, username, is_admin: false });
    const refreshToken = generateToken();

    const deviceInfo = req.headers['user-agent'] || 'unknown';
    await db.query(
      `INSERT INTO user_sessions (user_id, access_token, refresh_token, device_info, ip_address, last_activity, expires_at)
       VALUES ($1, $2, $3, $4, $5, NOW(), NOW() + INTERVAL '7 days')`,
      [userId, accessToken, refreshToken, deviceInfo, ip]
    );

    await updatePresence(userId, 'online');

    return res.status(201).json({
      access_token: accessToken,
      refresh_token: refreshToken,
      user: {
        id: userId,
        username,
        name,
        email,
        avatar: null,
        bio: null,
      },
    });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error', message: 'An unexpected error occurred' });
  }
});

router.post('/logout', async (req, res) => {
  const auth = getAuthUser(req);
  if (!auth) {
    return res.status(401).json({ error: 'unauthorized', message: 'Authentication required' });
  }
  const userId = auth.user_id;
  const data = req.body || {};
  const allDevices = data.all_devices === true;

  try {
    if (allDevices) {
      await db.query('UPDATE user_sessions SET is_revoked = 1 WHERE user_id = $1', [userId]);
    } else {
      const authHeader = req.headers['authorization'] || '';
      const token = authHeader.replace(/^Bearer\s+/i, '');
      await db.query('UPDATE user_sessions SET is_revoked = 1 WHERE access_token = $1 AND user_id = $2', [token, userId]);
    }
    await updatePresence(userId, 'offline');
    return res.json({ status: 'logged_out' });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error' });
  }
});

router.post('/refresh', async (req, res) => {
  const data = req.body || {};
  const refreshToken = data.refresh_token;
  if (!refreshToken) {
    return res.status(400).json({ error: 'missing_token', message: 'Refresh token required' });
  }
  try {
    const sessionRes = await db.query(
      `SELECT s.*, u.username, u.is_admin
       FROM user_sessions s
       JOIN users u ON u.id = s.user_id
       WHERE s.refresh_token = $1 AND s.is_revoked = 0 AND s.expires_at > NOW()`,
      [refreshToken]
    );
    const session = sessionRes.rows[0];
    if (!session) {
      return res.status(401).json({ error: 'invalid_token', message: 'Invalid or expired refresh token' });
    }

    await db.query('UPDATE user_sessions SET is_revoked = 1 WHERE id = $1', [session.id]);

    const newAccessToken = generateJwt({ user_id: session.user_id, username: session.username, is_admin: !!session.is_admin });
    const newRefreshToken = generateToken();

    const deviceInfo = req.headers['user-agent'] || 'unknown';
    const ip = getClientIp(req);
    await db.query(
      `INSERT INTO user_sessions (user_id, access_token, refresh_token, device_info, ip_address, last_activity, expires_at)
       VALUES ($1, $2, $3, $4, $5, NOW(), NOW() + INTERVAL '7 days')`,
      [session.user_id, newAccessToken, newRefreshToken, deviceInfo, ip]
    );

    return res.json({ access_token: newAccessToken, refresh_token: newRefreshToken });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error' });
  }
});

router.use(requireAuth);

router.get('/conversations', async (req, res) => {
  const userId = req.user.user_id;
  try {
    await updatePresence(userId, 'online');
    const convRes = await db.query(
      `SELECT c.id, c.type, c.name as group_name, c.avatar as group_avatar, c.created_at,
              cp.last_read_at, cp.is_muted, cp.role as my_role
       FROM conversations c
       JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = $1
       WHERE c.is_archived = 0
       ORDER BY (SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.id) DESC,
                c.created_at DESC`,
      [userId]
    );
    const conversations = convRes.rows;
    for (const conv of conversations) {
      if (conv.type === 'direct') {
        const otherRes = await db.query(
          `SELECT u.id, u.username, u.name, u.avatar, u.is_verified
           FROM conversation_participants cp
           JOIN users u ON u.id = cp.user_id
           WHERE cp.conversation_id = $1 AND cp.user_id != $2`,
          [conv.id, userId]
        );
        const otherUser = otherRes.rows[0];
        conv.other_user = otherUser || null;
        conv.name = otherUser ? otherUser.name : 'Unknown';
        conv.avatar = otherUser ? otherUser.avatar : null;
        if (otherUser) {
          const presence = await getUserPresence(otherUser.id);
          conv.presence = presence.status;
          conv.last_seen = presence.last_seen;
        }
      } else {
        const memberRes = await db.query(
          `SELECT COUNT(*) as cnt FROM conversation_participants WHERE conversation_id = $1`,
          [conv.id]
        );
        conv.member_count = parseInt(memberRes.rows[0].cnt, 10);
      }

      const lastMsgRes = await db.query(
        `SELECT m.id, m.content, m.type, m.created_at, m.sender_id,
                u.username as sender_username
         FROM messages m
         LEFT JOIN users u ON u.id = m.sender_id
         WHERE m.conversation_id = $1 AND m.is_deleted = 0
         ORDER BY m.created_at DESC
         LIMIT 1`,
        [conv.id]
      );
      const lastMsg = lastMsgRes.rows[0];
      if (lastMsg) {
        conv.last_message = lastMsg.type === 'text' ? lastMsg.content.substring(0, 120) : '[' + lastMsg.type + ']';
        conv.last_message_time = lastMsg.created_at;
        conv.last_sender_id = parseInt(lastMsg.sender_id, 10);
        conv.last_sender_username = lastMsg.sender_username;
      } else {
        conv.last_message = null;
        conv.last_message_time = null;
      }

      const unreadRes = await db.query(
        `SELECT COUNT(*) FROM messages m
         WHERE m.conversation_id = $1 AND m.sender_id != $2
           AND m.created_at > COALESCE($3, '2000-01-01')
           AND m.is_deleted = 0`,
        [conv.id, userId, conv.last_read_at]
      );
      conv.unread_count = parseInt(unreadRes.rows[0].count, 10);

      const pinnedRes = await db.query(
        `SELECT COUNT(*) FROM messages WHERE conversation_id = $1 AND is_pinned = 1 AND is_deleted = 0`,
        [conv.id]
      );
      conv.pinned_count = parseInt(pinnedRes.rows[0].count, 10);
    }
    return res.json(conversations);
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error' });
  }
});

router.get('/contacts', async (req, res) => {
  const userId = req.user.user_id;
  try {
    const contactsRes = await db.query(
      `SELECT u.id, u.username, u.name, u.avatar, u.is_verified
       FROM contacts c
       JOIN users u ON (
         (u.id = c.user1_id AND c.user2_id = $1) OR
         (u.id = c.user2_id AND c.user1_id = $1)
       )
       ORDER BY u.username ASC`,
      [userId]
    );
    const contacts = contactsRes.rows;
    for (const contact of contacts) {
      const presence = await getUserPresence(contact.id);
      contact.presence = presence.status;
      contact.last_seen = presence.last_seen;

      const lastMsgRes = await db.query(
        `SELECT m.id, m.content, m.type, m.created_at, m.sender_id
         FROM messages m
         JOIN conversation_participants cp1 ON cp1.user_id = $1
         JOIN conversation_participants cp2 ON cp2.user_id = $2 AND cp1.conversation_id = cp2.conversation_id
         JOIN conversations c ON c.id = cp1.conversation_id AND c.type = 'direct'
         WHERE m.conversation_id = c.id
         ORDER BY m.created_at DESC
         LIMIT 1`,
        [userId, contact.id]
      );
      const lastMsg = lastMsgRes.rows[0];
      if (lastMsg) {
        contact.last_message = lastMsg.type === 'text' ? lastMsg.content.substring(0, 100) : '[' + lastMsg.type + ']';
        contact.last_message_time = lastMsg.created_at;
        contact.last_message_sender = parseInt(lastMsg.sender_id, 10);
      } else {
        contact.last_message = null;
        contact.last_message_time = null;
      }

      const unreadRes = await db.query(
        `SELECT COUNT(*) as unread
         FROM messages m
         JOIN conversation_participants cp1 ON cp1.user_id = $1
         JOIN conversation_participants cp2 ON cp2.user_id = $2 AND cp1.conversation_id = cp2.conversation_id
         JOIN conversations c ON c.id = cp1.conversation_id AND c.type = 'direct'
         LEFT JOIN message_status ms ON ms.message_id = m.id AND ms.user_id = $1
         WHERE m.conversation_id = c.id AND m.sender_id != $1
           AND (ms.id IS NULL OR ms.status != 'read')
           AND (cp1.last_read_at IS NULL OR m.created_at > cp1.last_read_at)`,
        [userId, contact.id]
      );
      contact.unread_count = parseInt(unreadRes.rows[0].unread, 10);
    }
    return res.json(contacts);
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error' });
  }
});

router.post('/block', async (req, res) => {
  const userId = req.user.user_id;
  const data = req.body || {};
  const blockedId = parseInt(data.blocked_id, 10);
  if (!blockedId || blockedId === userId) {
    return res.status(400).json({ error: 'invalid_user' });
  }
  try {
    await db.query('INSERT INTO blocks (blocker_id, blocked_id) VALUES ($1, $2) ON CONFLICT DO NOTHING', [userId, blockedId]);
    await db.query(
      `DELETE FROM contacts WHERE (user1_id = $1 AND user2_id = $2) OR (user1_id = $2 AND user2_id = $1)`,
      [userId, blockedId]
    );
    await db.query(
      `UPDATE friend_requests SET status = 'rejected'
       WHERE ((sender_id = $1 AND receiver_id = $2) OR (sender_id = $2 AND receiver_id = $1))
         AND status = 'pending'`,
      [userId, blockedId]
    );
    return res.json({ status: 'blocked' });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error' });
  }
});

router.delete('/block', async (req, res) => {
  const userId = req.user.user_id;
  const data = req.body || {};
  const blockedId = parseInt(data.blocked_id, 10);
  if (!blockedId) {
    return res.status(400).json({ error: 'invalid_user' });
  }
  try {
    await db.query('DELETE FROM blocks WHERE blocker_id = $1 AND blocked_id = $2', [userId, blockedId]);
    return res.json({ status: 'unblocked' });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error' });
  }
});

router.get('/block', async (req, res) => {
  const userId = req.user.user_id;
  const targetId = parseInt(req.query.user_id, 10) || 0;
  try {
    if (!targetId) {
      const allRes = await db.query(
        `SELECT b.*, u.username, u.name, u.avatar
         FROM blocks b
         JOIN users u ON u.id = b.blocked_id
         WHERE b.blocker_id = $1`,
        [userId]
      );
      return res.json(allRes.rows);
    }
    const status = await isBlocked(targetId, userId) || await isBlocked(userId, targetId);
    return res.json({ is_blocked: status });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error' });
  }
});

router.post('/upload', async (req, res) => {
  // Placeholder: file upload handling requires multipart middleware (e.g., multer)
  return res.status(501).json({ error: 'not_implemented', message: 'Upload endpoint not implemented in this migration' });
});

router.get('/profile', async (req, res) => {
  const userId = req.user.user_id;
  const targetId = parseInt(req.query.user_id, 10) || userId;
  try {
    const userRes = await db.query(
      `SELECT id, username, name, email, avatar, bio, is_verified, last_active, created_at
       FROM users WHERE id = $1`,
      [targetId]
    );
    const user = userRes.rows[0];
    if (!user) {
      return res.status(404).json({ error: 'user_not_found' });
    }
    const presence = await getUserPresence(targetId);
    user.presence = presence.status;
    user.last_seen = presence.last_seen;
    user.is_contact = await areContacts(userId, targetId);
    user.is_blocked = await isBlocked(targetId, userId);
    user.i_blocked = await isBlocked(userId, targetId);
    const reqRes = await db.query(
      `SELECT id, status, sender_id FROM friend_requests
       WHERE ((sender_id = $1 AND receiver_id = $2) OR (sender_id = $2 AND receiver_id = $1))
         AND status = 'pending'
       LIMIT 1`,
      [userId, targetId]
    );
    const friendReq = reqRes.rows[0];
    if (friendReq) {
      user.request_status = friendReq.sender_id === userId ? 'sent' : 'received';
      user.request_id = friendReq.id;
    } else {
      user.request_status = null;
      user.request_id = null;
    }
    delete user.email;
    return res.json(user);
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error' });
  }
});

router.put('/profile', async (req, res) => {
  const userId = req.user.user_id;
  const data = req.body || {};
  const fields = [];
  const params = [];
  if (data.name) {
    fields.push('name = $' + (fields.length + 1));
    params.push(sanitize(data.name));
  }
  if (data.bio) {
    fields.push('bio = $' + (fields.length + 1));
    params.push(sanitize(data.bio));
  }
  if (data.avatar) {
    fields.push('avatar = $' + (fields.length + 1));
    params.push(sanitize(data.avatar));
  }
  if (fields.length === 0) {
    return res.status(400).json({ error: 'nothing_to_update' });
  }
  params.push(userId);
  const sql = `UPDATE users SET ${fields.join(', ')} WHERE id = $${fields.length + 1}`;
  try {
    await db.query(sql, params);
    return res.json({ status: 'updated' });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error' });
  }
});

router.get('/messages', async (req, res) => {
  const userId = req.user.user_id;
  const conversationId = parseInt(req.query.conversation_id, 10) || 0;
  const beforeId = req.query.before ? parseInt(req.query.before, 10) : null;
  const limit = Math.min(parseInt(req.query.limit, 10) || 50, 100);

  if (!conversationId) {
    return res.status(400).json({ error: 'invalid_conversation' });
  }
  try {
    const participantRes = await db.query(
      'SELECT id FROM conversation_participants WHERE conversation_id = $1 AND user_id = $2',
      [conversationId, userId]
    );
    if (participantRes.rowCount === 0) {
      return res.status(403).json({ error: 'not_participant', message: 'You are not part of this conversation' });
    }

    const convRes = await db.query(
      `SELECT c.*, cp.last_read_at
       FROM conversations c
       JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = $1
       WHERE c.id = $2`,
      [userId, conversationId]
    );
    const conversation = convRes.rows[0];
    if (!conversation) {
      return res.status(404).json({ error: 'conversation_not_found' });
    }

    let sql = `SELECT m.*, u.username as sender_username, u.name as sender_name, u.avatar as sender_avatar
               FROM messages m
               JOIN users u ON u.id = m.sender_id
               WHERE m.conversation_id = $1`;
    const params = [conversationId];
    if (beforeId) {
      sql += ' AND m.id < $2';
      params.push(beforeId);
    }
    sql += ' ORDER BY m.created_at DESC LIMIT $' + (params.length + 1);
    params.push(limit);
    const msgsRes = await db.query(sql, params);
    const messages = msgsRes.rows.reverse();

    const messageIds = messages.map(m => m.id);
    let reactionsByMessage = {};
    let statusByMessage = {};
    if (messageIds.length > 0) {
      const placeholders = messageIds.map((_, i) => '$' + (i + 1)).join(',');
      const reactionRes = await db.query(
        `SELECT mr.*, u.username
         FROM message_reactions mr
         JOIN users u ON u.id = mr.user_id
         WHERE mr.message_id IN (${placeholders})
         ORDER BY mr.created_at ASC`,
        messageIds
      );
      for (const r of reactionRes.rows) {
        if (!reactionsByMessage[r.message_id]) reactionsByMessage[r.message_id] = [];
        reactionsByMessage[r.message_id].push(r);
      }
      const statusParams = [...messageIds, userId];
      const statusPlaceholders = messageIds.map((_, i) => '$' + (i + 1)).join(',');
      const statusRes = await db.query(
        `SELECT message_id, status FROM message_status
         WHERE message_id IN (${statusPlaceholders}) AND user_id = $${messageIds.length + 1}`,
        statusParams
      );
      for (const s of statusRes.rows) {
        statusByMessage[s.message_id] = s.status;
      }
    }
    for (const msg of messages) {
      msg.id = parseInt(msg.id, 10);
      msg.sender_id = parseInt(msg.sender_id, 10);
      msg.is_mine = msg.sender_id === userId;
      msg.reactions = reactionsByMessage[msg.id] || [];
      msg.my_status = statusByMessage[msg.id] || null;

      if (msg.reply_to_id) {
        const replyRes = await db.query(
          `SELECT m.id, m.content, m.type, u.username as sender_username
           FROM messages m
           JOIN users u ON u.id = m.sender_id
           WHERE m.id = $1`,
          [msg.reply_to_id]
        );
        const reply = replyRes.rows[0];
        if (reply) {
          msg.reply_to = {
            id: parseInt(reply.id, 10),
            content: (reply.content || '').substring(0, 100),
            type: reply.type,
            sender_username: reply.sender_username,
          };
        }
      }

      if (!msg.is_mine) {
        await db.query(
          `INSERT INTO message_status (message_id, user_id, status, timestamp)
           VALUES ($1, $2, 'read', NOW())
           ON CONFLICT (message_id, user_id) DO UPDATE SET status = 'read', timestamp = NOW()`,
          [msg.id, userId]
        );
      }
    }

    await db.query(
      `UPDATE conversation_participants SET last_read_at = NOW()
       WHERE conversation_id = $1 AND user_id = $2`,
      [conversationId, userId]
    );

    let otherUsers = [];
    if (conversation.type === 'direct') {
      const otherRes = await db.query(
        `SELECT u.id, u.username, u.name, u.avatar, u.is_verified
         FROM conversation_participants cp
         JOIN users u ON u.id = cp.user_id
         WHERE cp.conversation_id = $1 AND cp.user_id != $2`,
        [conversationId, userId]
      );
      otherUsers = otherRes.rows;
      for (const ou of otherUsers) {
        const presence = await getUserPresence(ou.id);
        ou.presence = presence.status;
        ou.last_seen = presence.last_seen;
      }
    }

    return res.json({
      conversation,
      messages,
      other_users: otherUsers,
      has_more: messages.length === limit,
    });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error' });
  }
});

router.post('/send_message', async (req, res) => {
  const userId = req.user.user_id;
  const data = req.body || {};
  const conversationId = parseInt(data.conversation_id, 10) || 0;
  const content = (data.content || '').trim();
  const type = data.type || 'text';
  const replyToId = data.reply_to_id ? parseInt(data.reply_to_id, 10) : null;

  if (!conversationId) {
    return res.status(400).json({ error: 'invalid_conversation' });
  }
  if (!content && type === 'text') {
    return res.status(400).json({ error: 'empty_message', message: 'Message cannot be empty' });
  }

  try {
    const participantRes = await db.query(
      'SELECT id FROM conversation_participants WHERE conversation_id = $1 AND user_id = $2',
      [conversationId, userId]
    );
    if (participantRes.rowCount === 0) {
      return res.status(403).json({ error: 'not_participant', message: 'You are not part of this conversation' });
    }

    const allowed = await checkRateLimit(`msg:${userId}`, 'send_message', 20, 10);
    if (!allowed) {
      return res.status(429).json({ error: 'rate_limited', message: 'Sending too fast. Please slow down.' });
    }

    const insertRes = await db.query(
      `INSERT INTO messages (conversation_id, sender_id, type, content, reply_to_id)
       VALUES ($1, $2, $3, $4, $5) RETURNING id`,
      [conversationId, userId, type, content, replyToId]
    );
    const messageId = insertRes.rows[0].id;

    const participantsRes = await db.query(
      `SELECT user_id FROM conversation_participants WHERE conversation_id = $1 AND user_id != $2`,
      [conversationId, userId]
    );
    const participants = participantsRes.rows;
    for (const p of participants) {
      await db.query(
        `INSERT INTO message_status (message_id, user_id, status) VALUES ($1, $2, 'sent')`,
        [messageId, p.user_id]
      );

      const convInfoRes = await db.query('SELECT type FROM conversations WHERE id = $1', [conversationId]);
      const convType = convInfoRes.rows[0].type;
      const preview = type === 'text' ? content.substring(0, 100) : '[' + type + ']';
      const senderName = req.user.username || 'Someone';
      await createNotification(
        p.user_id,
        'message',
        null,
        conversationId,
        messageId,
        userId,
        convType === 'direct' ? senderName : convType,
        preview
      );
    }

    const msgRes = await db.query(
      `SELECT m.*, u.username as sender_username, u.name as sender_name, u.avatar as sender_avatar
       FROM messages m
       JOIN users u ON u.id = m.sender_id
       WHERE m.id = $1`,
      [messageId]
    );
    const message = msgRes.rows[0];
    message.id = parseInt(message.id, 10);
    message.sender_id = parseInt(message.sender_id, 10);
    message.is_mine = true;
    message.reactions = [];

    return res.status(201).json({ message, status: 'sent' });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'internal_error' });
  }
});

module.exports = router;
