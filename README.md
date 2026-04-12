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
