require('dotenv').config();
const express = require('express');
const cors = require('cors');
const bodyParser = require('express').json;
const authMiddleware = require('./middleware/auth');
const apiRouter = require('./routes/api');

const app = express();
const PORT = process.env.PORT || 3000;

app.use(cors());
app.use(express.json({ limit: '50mb' })); // Parse JSON bodies

// API routes
app.use('/api', apiRouter);

// Fallback for unknown routes
app.use((req, res) => {
  res.status(404).json({ error: 'not_found', message: 'Route not found' });
});

app.listen(PORT, () => {
  console.log(`Server listening on port ${PORT}`);
});
