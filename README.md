# oktoberfest

JavaScript frontend + PHP backend starter project.

## Project Structure

- `frontend/` - Static frontend (HTML/CSS/JS)
- `backend/public/index.php` - PHP API entrypoint

## Run Locally

### 1. Start the PHP backend

From the repository root:

```bash
cd backend/public
php -S localhost:8000
```

Health endpoint:

- http://localhost:8000/api/health

### 2. Start the frontend

In a second terminal from the repository root:

```bash
cd frontend
python3 -m http.server 5173
```

Open:

- http://localhost:5173

Click **Check API Health** to verify frontend -> backend connection.
