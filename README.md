# ANN Student Performance Predictor

A machine learning-powered web application that predicts student academic performance based on WAEC/NECO grades, current CGPA, and socio-economic factors. Built with PHP, Python (scikit-learn), and PostgreSQL (Supabase).

## Features

- **Student Dashboard** — View predictions, track performance trends over time
- **AI Prediction Engine** — Enter WAEC/NECO grades + CGPA, get a predicted performance score with confidence level
- **Personalized Recommendations** — AI-generated study plans, subject-specific tips, and improvement timelines
- **Lecturer Dashboard** — Manage student groups, view batch analytics, export data
- **Admin Panel** — User management, lecturer verification, system settings
- **Export** — CSV export of predictions and analytics

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | PHP, Bootstrap 5, Chart.js |
| Backend | PHP 8.x |
| Database | PostgreSQL (Supabase) |
| ML Model | Python, scikit-learn (RandomForest) |
| CI/CD | GitHub Actions |

## Project Structure

```
├── db/
│   ├── student_predictor.sql        # Legacy MySQL schema
│   └── supabase_schema.sql          # PostgreSQL schema for Supabase
├── php_app/
│   ├── assets/css/style.css         # Custom styles + mobile responsive
│   ├── assets/js/script.js          # Grade selection, form validation
│   ├── lib/
│   │   ├── config.php               # Env-based configuration
│   │   ├── database.php             # PDO PostgreSQL connection
│   │   ├── auth.php                 # Authentication & authorization
│   │   ├── utils.php                # Helper functions, Python model bridge
│   │   └── recommendations.php      # AI recommendation engine
│   └── public/                      # Web root (served by Apache/Nginx)
├── python_model/
│   ├── models/                      # Trained model files
│   ├── scripts/predict.py           # ML prediction script (called by PHP)
│   └── requirements.txt             # Python dependencies
└── .github/workflows/ci.yml        # CI/CD pipeline
```

## How It Works

1. Student enters WAEC/NECO subject grades (A1-F9), current CGPA, and socio-economic factors
2. PHP backend calls the Python ML model via `shell_exec()`
3. Python script uses a trained RandomForest model to predict performance (0-100%)
4. Results are saved to Supabase along with feature importance analysis
5. Recommendation engine generates personalized study plans based on the prediction

## Local Development

```bash
# Clone
git clone https://github.com/Akakaui/ann-for-student-performance-prediction.git
cd ann-for-student-performance-prediction

# Python model
cd python_model
pip install -r requirements.txt
cd ..

# Run (requires Supabase or local PostgreSQL)
cd php_app/public
php -S localhost:8000
```

Default admin login: `admin` / `admin123`

## License

MIT
