# oktoberfest

JavaScript frontend + PHP backend starter project.

## Project Structure

- `index.html`, `styles.css`, `app.js` - Static frontend at web root
- `api/health/index.php` - Health endpoint
- `api/matrix/index.php` - Reservation matrix endpoint
- `backend/public/index.php` - Optional local API router

## Run Locally

From the repository root run one command:

```bash
php -S localhost:8000 -t .
```

Open:

- http://localhost:8000

API endpoints:

- http://localhost:8000/api/health/
- http://localhost:8000/api/matrix/?timeslot=all

Timeslot values:

- `all`
- `mittag`
- `abend`

## Telegram Alert Bot (Fischer-Vroni)

This project includes a monitor script that checks Fischer-Vroni availability and sends a Telegram message when status changes.

### 1. Configure bot credentials

```bash
cp .env.telegram.example .env.telegram
```

Edit `.env.telegram`:

- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_TARGET` (recommended): `@channel_username` or `-100...` channel/group id
- Optional `TELEGRAM_CHAT_ID` (legacy fallback)
- Optional `TELEGRAM_MESSAGE_THREAD_ID` (for forum topics)
- Optional `CHECK_URL`

Channel requirements:

- Add your bot to the target channel/group
- Grant the bot permission to post messages
- For private channels, use numeric `-100...` id if `@username` does not deliver

### 2. Manual test

```bash
php scripts/fischer_vroni_telegram_monitor.php --force
```

If this succeeds, the script prints the exact target it sent to.

### 2b. Telegram channel debug helper

If messages do not arrive, run:

```bash
php scripts/telegram_debug.php
```

This checks:

- `getMe` (token validity)
- `getChat` (bot access to target channel)
- `sendMessage` (actual posting permission)

### 3. Run every 10 minutes via cron

```bash
*/10 * * * * cd /Users/silvester/PythonDev/Git/oktoberfest && /usr/bin/php scripts/fischer_vroni_telegram_monitor.php >> storage/monitor.log 2>&1
```

Behavior:

- Sends Telegram message when status changes (not available -> available, or available -> not available)
- Stores last known status in `storage/fischer_vroni_monitor_state.json`
