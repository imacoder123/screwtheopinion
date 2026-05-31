# ScrewTheOpinion – Node.js Backend

## Overview
This project is a complete migration of the original PHP backend for **ScrewTheOpinion** to a production‑ready **Node.js** API built with **Express**, **PostgreSQL** (via the `pg` package), **JWT** authentication, and **bcrypt** password hashing. All original business logic, validation rules, API contracts, and response structures have been preserved so the existing frontend requires no changes.

## Features
- **RESTful API** under `/api/*` (e.g., `/api/auth`, `/api/register`, `/api/conversations`).
- **JWT‑based authentication** with refresh tokens.
- Secure password storage using **bcrypt**.
- Rate limiting for critical actions.
- Presence handling, contacts, blocks, notifications, and message threading.
- Database layer uses **parameterised queries** with PostgreSQL (compatible with Supabase).
- Environment configuration via `.env` (example provided).
- Ready for **Vercel** deployment – includes `vercel.json` and a simple `npm start` script.

## Project Structure
```
src/
├─ db/                # PostgreSQL connection pool
├─ middleware/        # Authentication middleware
├─ routes/            # API routes (express router)
├─ utils/             # Helper functions (JWT, bcrypt, rate‑limit, etc.)
├─ server.js          # Express entry point

env.example          # Template for required environment variables
package.json          # Node dependencies and start script
vercel.json           # Vercel deployment configuration
README.md             # This file
```

## Getting Started
1. **Clone the repository**
   ```bash
   git clone <repo-url>
   cd ScrewTheOpinion
   ```
2. **Install dependencies**
   ```bash
   npm install
   ```
3. **Configure environment variables**
   - Copy the example file:
     ```bash
     cp .env.example .env
     ```
   - Edit `.env` and set:
     - `DATABASE_URL` – PostgreSQL connection string (e.g., Supabase URL).
     - `JWT_SECRET` – secret for signing JWTs.
     - `JWT_EXPIRY`, `JWT_REFRESH_EXPIRY`, `PORT` as needed.
4. **Run database migrations** (the original `init_db.php` script is replaced by `schema.sql`).
   ```bash
   psql $DATABASE_URL -f api/schema.sql
   ```
5. **Start the server locally**
   ```bash
   npm run start
   ```
   The API will be available at `http://localhost:{PORT||3000}/api/...`.

## Deploying to Vercel
1. Push the repo to GitHub/GitLab/Bitbucket.
2. In the Vercel dashboard click **New Project** and import the repository.
3. Vercel detects `vercel.json` and installs the Node.js builder.
4. Add the environment variables (same as in `.env`) under **Settings → Environment Variables**.
5. Click **Deploy** – Vercel will run `npm install` and start the server.
6. Your API will be reachable at `https://<your‑project>.vercel.app/api/...`.

## API Documentation
All endpoints keep the same URLs and JSON response shapes as the original PHP version. A brief summary:
| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/auth` | Log in – returns `access_token`, `refresh_token`, and user data |
| `POST` | `/api/register` | Create a new user account |
| `POST` | `/api/logout` | Revoke the current (or all) sessions |
| `POST` | `/api/refresh` | Exchange a refresh token for a new access token |
| `GET`  | `/api/conversations` | List user’s conversations |
| `GET`  | `/api/contacts` | List accepted contacts |
| `GET/POST/DELETE` | `/api/block` | Block, unblock, or query block status |
| `GET/PUT` | `/api/profile` | Get or update own profile |
| `GET` | `/api/messages` | Paginated fetch of messages for a conversation |
| `POST` | `/api/send_message` | Send a new message |
| `POST` | `/api/upload` | **Not implemented** – placeholder for file uploads (add Multer if needed) |

For full request/response schemas refer to the original PHP files in the `api/` folder or inspect the route handlers in `src/routes/api.js`.

## Development Notes
- **Database**: The schema is defined in `api/schema.sql`. Modify and re‑run the migration as needed.
- **File uploads**: The migration includes a placeholder route. To enable uploads, add a Multer middleware and adjust `src/routes/api.js` accordingly.
- **Testing**: Add Jest or another test framework to `tests/` and run `npm test` (not included by default).

## License
This project is derived from the original ScrewTheOpinion codebase. See the original repository for licensing information.
