# Student Performance Predictor

A machine learning-powered web application that predicts student performance based on WAEC/NECO grades, current CGPA, and socio-economic factors.

## Tech Stack

- **Frontend:** PHP, Bootstrap 5, Chart.js
- **Backend:** PHP 8.x
- **Database:** PostgreSQL (Supabase)
- **ML Model:** Python (scikit-learn RandomForest)
- **CI/CD:** GitHub Actions

## Quick Start

### Prerequisites

- PHP 8.1+ with PDO PostgreSQL extension
- Python 3.9+
- Supabase account (free tier works)
- Web server (Apache/Nginx) or PHP built-in server

### 1. Clone the Repository

```bash
git clone https://github.com/Akakaui/ann-for-student-performance-prediction.git
cd ann-for-student-performance-prediction
```

### 2. Set Up Supabase Database

1. Create a free account at [supabase.com](https://supabase.com)
2. Create a new project
3. Go to **SQL Editor** in the dashboard
4. Paste and run the contents of `db/supabase_schema.sql`
5. Copy your **Project URL** and **database password**

### 3. Configure Environment

Create a `.env` file in the project root:

```env
# Supabase Database
DB_HOST=db.your-project-id.supabase.co
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres
DB_PASS=your-supabase-password
DB_SSLMODE=require

# Application
APP_NAME=Student Performance Predictor
BASE_URL=http://localhost/php_app/public/
APP_DEBUG=false

# Python
PYTHON_PATH=python3
```

### 4. Install PHP Dependencies

Ensure you have the PostgreSQL PDO extension:

```bash
# Ubuntu/Debian
sudo apt install php8.2-pgsql

# macOS
brew install php
```

### 5. Install Python Dependencies

```bash
cd python_model
pip install -r requirements.txt
```

### 6. Run the Application

```bash
# Using PHP built-in server (development only)
cd php_app/public
php -S localhost:8000

# Or configure Apache/Nginx to serve php_app/public
```

Visit `http://localhost:8000` in your browser.

### 7. First Login

- **Admin:** username `admin`, password `admin123`
- Register new student/lecturer accounts at `/register.php`

## Deployment Options

### Option A: Railway (Recommended - Free Tier)

1. Push to GitHub
2. Go to [railway.app](https://railway.app)
3. Create new project from GitHub repo
4. Add environment variables (see `.env` section above)
5. Deploy

### Option B: Render

1. Push to GitHub
2. Go to [render.com](https://render.com)
3. Create a new **Web Service**
4. Set build command: `pip install -r python_model/requirements.txt`
5. Set start command: `cd php_app/public && php -S 0.0.0.0:$PORT`
6. Add environment variables

### Option C: VPS (DigitalOcean, Hetzner, etc.)

```bash
# SSH into your server
sudo apt update && sudo apt upgrade -y
sudo apt install php8.2-fpm php8.2-pgsql nginx python3 python3-pip -y

# Clone the repo
git clone https://github.com/Akakaui/ann-for-student-performance-prediction.git
cd ann-for-student-performance-prediction

# Install Python dependencies
pip3 install -r python_model/requirements.txt

# Configure Nginx (copy the config below)
sudo cp nginx.conf /etc/nginx/sites-available/student-predictor
sudo ln -s /etc/nginx/sites-available/student-predictor /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# Set permissions
sudo chown -R www-data:www-data /var/www/ann-for-student-performance-prediction
```

### Option D: Docker

```dockerfile
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY --from=python:3.11-slim /usr/local/lib/python3.11/site-packages /usr/local/lib/python3.11/site-packages
COPY --from=python:3.11-slim /usr/local/bin/python3 /usr/local/bin/python3

WORKDIR /app
COPY . .

CMD ["php", "-S", "0.0.0.0:8000", "-t", "php_app/public"]
```

## Project Structure

```
ann-for-student-performance-prediction/
├── .github/workflows/ci.yml    # CI/CD pipeline
├── db/
│   ├── student_predictor.sql    # MySQL schema (legacy)
│   └── supabase_schema.sql      # PostgreSQL schema (for Supabase)
├── php_app/
│   ├── assets/
│   │   ├── css/style.css        # Custom styles + mobile responsive
│   │   └── js/script.js         # Grade selection, form validation
│   ├── lib/
│   │   ├── config.php           # Configuration (reads from env)
│   │   ├── database.php         # PDO PostgreSQL connection
│   │   ├── auth.php             # Authentication & authorization
│   │   ├── utils.php            # Helper functions
│   │   └── recommendations.php  # AI recommendation engine
│   └── public/
│       ├── index.php            # Entry point (redirects)
│       ├── login.php            # Login page
│       ├── register.php         # Registration page
│       ├── dashboard.php        # Main dashboard
│       ├── prediction_form.php  # Prediction input form
│       ├── prediction_result.php # Prediction results
│       ├── my_predictions.php   # Prediction history
│       └── ...                  # Other pages
├── python_model/
│   ├── models/                  # Trained model files
│   ├── scripts/
│   │   └── predict.py           # ML prediction script
│   └── requirements.txt         # Python dependencies
├── .gitignore
└── README.md
```

## Features

- **Student Dashboard:** View predictions, track performance trends
- **Prediction Form:** Input WAEC/NECO grades, CGPA, socio-economic factors
- **AI Recommendations:** Personalized study plans and improvement tips
- **Lecturer Dashboard:** Manage student groups, view analytics
- **Admin Panel:** User management, lecturer verification
- **Export:** CSV export of predictions and analytics

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_HOST` | Supabase database host | `localhost` |
| `DB_PORT` | Database port | `5432` |
| `DB_NAME` | Database name | `postgres` |
| `DB_USER` | Database user | `postgres` |
| `DB_PASS` | Database password | - |
| `DB_SSLMODE` | SSL mode | `require` |
| `BASE_URL` | Application base URL | `http://localhost/php_app/public/` |
| `PYTHON_PATH` | Python executable path | `python3` |
| `APP_DEBUG` | Enable debug mode | `false` |
| `APP_TIMEZONE` | Application timezone | `Africa/Lagos` |

## License

MIT
