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

The monitor now checks all configured tents and can route each tent to its own Telegram topic.

### 1. Configure bot credentials

```bash
cp .env.telegram.example .env.telegram
```

Edit `.env.telegram`:

- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_TARGET` (recommended): `@channel_username` or `-100...` channel/group id
- Optional `TELEGRAM_TARGETS` for multiple channels: comma separated
- Optional `TELEGRAM_CHAT_ID` (legacy fallback)
- Optional `TELEGRAM_MESSAGE_THREAD_ID` (for forum topics)
- Optional `TELEGRAM_TARGET_TOPIC_MAP` for per-target topic routing
- Optional `TELEGRAM_TENT_TOPIC_MAP` for per-tent topic routing
- Optional `CHECK_URL`
- Optional `FISCHER_VRONI_OFFICIAL_URL`
- Optional `FISCHER_VRONI_FORMAT_ALERT` (`true`/`false`)
- Optional `FISCHER_VRONI_FORMAT_TOPIC_ID`
- Optional `FISCHER_VRONI_ACTIVATION_ALERT` (`true`/`false`)
- Optional `FISCHER_VRONI_ACTIVATION_TOPIC_ID`

Channel requirements:

- Add your bot to the target channel/group
- Grant the bot permission to post messages
- For private channels, use numeric `-100...` id if `@username` does not deliver

Examples for sub-channel style routing:

```env
TELEGRAM_TARGETS=@oktoberfest_2026,@another_channel
TELEGRAM_TARGET_TOPIC_MAP=@oktoberfest_2026:12,@another_channel:3
TELEGRAM_TENT_TOPIC_MAP=fischer-vroni:11,hofbraeu-festzelt:12,hacker-festzelt:13
```

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

### 2c. Auto-create one topic per tent

If your target is a forum-enabled Telegram chat and the bot has admin rights,
you can auto-create one topic per tent and write the resulting mapping to `.env.telegram`:

```bash
php scripts/telegram_create_tent_topics.php
```

This updates `TELEGRAM_TENT_TOPIC_MAP` automatically.

### 2d. Bot command for live status

Users can message the bot:

- `/status` for last known status from storage
- `/status live` for a live fetch from public sources

Run listener periodically (e.g., every minute):

```bash
php scripts/telegram_status_command_listener.php
```

Example cron:

```bash
* * * * * /opt/plesk/php/8.2/bin/php /var/www/vhosts/<domain>/httpdocs/scripts/telegram_status_command_listener.php >> /var/www/vhosts/<domain>/httpdocs/storage/status-listener.log 2>&1
```

### 3. Run every 10 minutes via cron

```bash
*/10 * * * * /opt/plesk/php/8.2/bin/php /var/www/vhosts/<domain>/httpdocs/scripts/fischer_vroni_telegram_monitor.php >> /var/www/vhosts/<domain>/httpdocs/storage/monitor.log 2>&1
```

Plesk notes:

- If your subscription uses another PHP version, replace `8.2` with your installed version (for example `8.1` or `8.3`).
- Use absolute paths only in Plesk cron tasks.
- Make sure `/var/www/vhosts/<domain>/httpdocs/storage` exists and is writable.

Behavior:

- Sends Telegram message when status changes (per tent)
- Stores last known status in `storage/oktoberfest_tent_monitor_state.json`
- For `fischer-vroni`, the monitor uses the official page signal (`reservierung.fischer-vroni.de/reservation`) as primary source and falls back to marketplace parsing if needed.
- For `fischer-vroni`, the monitor also tracks a normalized page signal checksum and can alert if the official page format/signal changes.
- If `FISCHER_VRONI_FORMAT_TOPIC_ID` is set, those format-change alerts are posted to that separate topic.
- For `fischer-vroni`, the monitor detects public booking activation hints from the Livewire snapshot (non-null booking/date/seat fields) and can alert to a separate topic.
