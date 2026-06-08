<?php
require_once '../lib/config.php';
session_start();

// If logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
// Otherwise, show the landing page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Performance Predictor — AI-Powered Academic Insights</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg-deep: #0a0a0f;
            --bg-card: rgba(255,255,255,0.03);
            --accent: #f97316;
            --accent-glow: rgba(249,115,22,0.3);
            --teal: #14b8a6;
            --teal-glow: rgba(20,184,166,0.3);
            --violet: #8b5cf6;
            --violet-glow: rgba(139,92,246,0.2);
            --rose: #f43f5e;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --border: rgba(255,255,255,0.06);
            --glass: rgba(255,255,255,0.04);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-deep);
            color: var(--text);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* ===== NAV ===== */
        .nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            backdrop-filter: blur(20px);
            background: rgba(10,10,15,0.8);
            border-bottom: 1px solid var(--border);
            transition: all 0.3s;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text);
        }

        .nav-logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), var(--rose));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 800;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .nav-logo-icon::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 40%, rgba(255,255,255,0.2));
        }

        .nav-logo span {
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: -0.02em;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link {
            padding: 0.5rem 1rem;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .nav-link:hover { color: var(--text); background: var(--glass); }

        .nav-cta {
            padding: 0.6rem 1.5rem;
            background: var(--accent);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .nav-cta:hover {
            background: #ea580c;
            box-shadow: 0 0 30px var(--accent-glow);
            transform: translateY(-1px);
        }

        .nav-admin {
            padding: 0.5rem 1rem;
            background: var(--glass);
            border: 1px solid var(--border);
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .nav-admin:hover { color: var(--text); border-color: var(--accent); }

        /* ===== HERO ===== */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            padding: 8rem 2rem 4rem;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            overflow: hidden;
        }

        .hero-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: float 20s ease-in-out infinite;
        }

        .hero-orb-1 {
            width: 600px;
            height: 600px;
            background: var(--accent);
            top: -200px;
            right: -100px;
            animation-delay: 0s;
        }

        .hero-orb-2 {
            width: 500px;
            height: 500px;
            background: var(--teal);
            bottom: -150px;
            left: -100px;
            animation-delay: -7s;
        }

        .hero-orb-3 {
            width: 300px;
            height: 300px;
            background: var(--violet);
            top: 50%;
            left: 50%;
            animation-delay: -14s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.05); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
        }

        .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse at center, black 30%, transparent 70%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--accent);
            margin-bottom: 1.5rem;
            animation: fadeUp 0.8s ease-out;
        }

        .hero-badge-dot {
            width: 6px;
            height: 6px;
            background: var(--accent);
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.5); }
        }

        .hero h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2.5rem, 5vw, 4.5rem);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.03em;
            margin-bottom: 1.5rem;
            animation: fadeUp 0.8s ease-out 0.1s both;
        }

        .hero h1 .gradient-text {
            background: linear-gradient(135deg, var(--accent), var(--rose), var(--violet));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-desc {
            font-size: 1.15rem;
            color: var(--text-muted);
            max-width: 500px;
            margin-bottom: 2.5rem;
            line-height: 1.7;
            animation: fadeUp 0.8s ease-out 0.2s both;
        }

        .hero-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            animation: fadeUp 0.8s ease-out 0.3s both;
        }

        .btn-primary {
            padding: 0.9rem 2rem;
            background: linear-gradient(135deg, var(--accent), #ea580c);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px var(--accent-glow);
        }

        .btn-secondary {
            padding: 0.9rem 2rem;
            background: var(--glass);
            border: 1px solid var(--border);
            color: var(--text);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-secondary:hover {
            border-color: var(--accent);
            background: rgba(249,115,22,0.05);
            transform: translateY(-2px);
        }

        /* Hero visual */
        .hero-visual {
            position: relative;
            animation: fadeUp 0.8s ease-out 0.4s both;
        }

        .hero-card {
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
        }

        .hero-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), var(--teal), var(--violet));
        }

        .hero-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .hero-card-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--teal), var(--accent));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .hero-card-name {
            font-weight: 600;
            font-size: 1rem;
        }

        .hero-card-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .hero-score {
            text-align: center;
            padding: 2rem 0;
        }

        .hero-score-circle {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: conic-gradient(var(--accent) 0deg, var(--teal) 120deg, var(--violet) 240deg, var(--accent) 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            position: relative;
            animation: spin 10s linear infinite;
        }

        .hero-score-circle::after {
            content: '';
            position: absolute;
            inset: 6px;
            background: var(--bg-deep);
            border-radius: 50%;
        }

        .hero-score-value {
            position: relative;
            z-index: 1;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent), var(--teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-score-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .hero-stat {
            text-align: center;
            padding: 1rem;
            background: rgba(255,255,255,0.02);
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .hero-stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--accent);
        }

        .hero-stat-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.25rem;
        }

        /* Floating elements */
        .float-tag {
            position: absolute;
            padding: 0.5rem 1rem;
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
            animation: floatTag 6s ease-in-out infinite;
        }

        .float-tag-1 { top: -20px; right: -30px; animation-delay: 0s; color: var(--teal); }
        .float-tag-2 { bottom: -15px; left: -25px; animation-delay: -2s; color: var(--violet); }
        .float-tag-3 { top: 40%; right: -40px; animation-delay: -4s; color: var(--accent); }

        @keyframes floatTag {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== FEATURES ===== */
        .features {
            padding: 6rem 2rem;
            position: relative;
        }

        .features-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 4rem;
        }

        .features-header h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(1.8rem, 3vw, 2.8rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 1rem;
        }

        .features-header p {
            color: var(--text-muted);
            font-size: 1.05rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            border-color: rgba(255,255,255,0.1);
        }

        .feature-card:hover::before { opacity: 1; }

        .feature-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .feature-icon-orange { background: rgba(249,115,22,0.1); color: var(--accent); }
        .feature-icon-teal { background: rgba(20,184,166,0.1); color: var(--teal); }
        .feature-icon-violet { background: rgba(139,92,246,0.1); color: var(--violet); }
        .feature-icon-rose { background: rgba(244,63,94,0.1); color: var(--rose); }

        .feature-card h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.7;
        }

        /* ===== HOW IT WORKS ===== */
        .how-it-works {
            padding: 6rem 2rem;
            background: linear-gradient(180deg, transparent, rgba(249,115,22,0.02), transparent);
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        .step {
            text-align: center;
            padding: 2rem;
            position: relative;
        }

        .step-number {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--rose));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0 auto 1.5rem;
            position: relative;
        }

        .step-number::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            border: 2px dashed rgba(249,115,22,0.3);
            animation: spin 20s linear infinite;
        }

        .step h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .step p {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.7;
        }

        /* ===== CTA ===== */
        .cta {
            padding: 6rem 2rem;
            text-align: center;
        }

        .cta-box {
            max-width: 700px;
            margin: 0 auto;
            padding: 4rem 3rem;
            background: linear-gradient(135deg, rgba(249,115,22,0.08), rgba(139,92,246,0.05));
            border: 1px solid rgba(249,115,22,0.15);
            border-radius: 24px;
            position: relative;
            overflow: hidden;
        }

        .cta-box::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 30%, rgba(249,115,22,0.05), transparent 50%);
            animation: float 15s ease-in-out infinite;
        }

        .cta-box h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            font-weight: 800;
            margin-bottom: 1rem;
            position: relative;
        }

        .cta-box p {
            color: var(--text-muted);
            font-size: 1.05rem;
            margin-bottom: 2rem;
            position: relative;
        }

        .cta-box .btn-primary { position: relative; }

        /* ===== FOOTER ===== */
        .footer {
            padding: 3rem 2rem;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .footer p {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .footer a {
            color: var(--accent);
            text-decoration: none;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 968px) {
            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .hero-desc { margin-left: auto; margin-right: auto; }
            .hero-actions { justify-content: center; }
            .hero-visual { max-width: 450px; margin: 0 auto; }
            .float-tag { display: none; }
        }

        @media (max-width: 768px) {
            .nav { padding: 0.75rem 1rem; }
            .nav-links { gap: 0.25rem; }
            .nav-link { display: none; }
            .hero { padding: 7rem 1rem 3rem; }
            .features { padding: 4rem 1rem; }
            .how-it-works { padding: 4rem 1rem; }
            .cta { padding: 4rem 1rem; }
            .cta-box { padding: 2.5rem 1.5rem; }
            .features-grid { grid-template-columns: 1fr; }
            .steps { grid-template-columns: 1fr; gap: 1rem; }
            .hero-stats { grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
            .hero-card { padding: 1.5rem; }
        }

        @media (max-width: 480px) {
            .hero h1 { font-size: 2rem; }
            .hero-actions { flex-direction: column; align-items: center; }
            .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
            .hero-score-circle { width: 110px; height: 110px; }
            .hero-score-value { font-size: 2rem; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
    <a href="index.php" class="nav-logo">
        <div class="nav-logo-icon"><i class="bi bi-graph-up-arrow"></i></div>
        <span>PredictEd</span>
    </a>
    <div class="nav-links">
        <a href="#features" class="nav-link">Features</a>
        <a href="#how" class="nav-link">How It Works</a>
        <a href="register.php" class="nav-cta">Get Started Free</a>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg">
        <div class="hero-orb hero-orb-1"></div>
        <div class="hero-orb hero-orb-2"></div>
        <div class="hero-orb hero-orb-3"></div>
        <div class="hero-grid"></div>
    </div>
    <div class="hero-content">
        <div class="hero-text">
            <div class="hero-badge">
                <span class="hero-badge-dot"></span>
                AI-Powered Prediction
            </div>
            <h1>
                Predict Your<br>
                <span class="gradient-text">Academic Future</span><br>
                Before It Happens
            </h1>
            <p class="hero-desc">
                Get instant, AI-powered predictions for your WAEC/NECO performance based on your grades, study habits, and academic profile. Know where you stand before exam day.
            </p>
            <div class="hero-actions">
                <a href="register.php" class="btn-primary">
                    Start Predicting <i class="bi bi-arrow-right"></i>
                </a>
                <a href="#how" class="btn-secondary">
                    <i class="bi bi-play-circle"></i> See How It Works
                </a>
            </div>
        </div>
        <div class="hero-visual">
            <div class="hero-card">
                <div class="float-tag float-tag-1"><i class="bi bi-check-circle-fill"></i> Verified Results</div>
                <div class="float-tag float-tag-2"><i class="bi bi-shield-check"></i> Secure Data</div>
                <div class="float-tag float-tag-3"><i class="bi bi-lightning-fill"></i> Instant</div>
                <div class="hero-card-header">
                    <div class="hero-card-avatar"><i class="bi bi-person-fill"></i></div>
                    <div>
                        <div class="hero-card-name">Sample Prediction</div>
                        <div class="hero-card-sub">WAEC 2026 Candidate</div>
                    </div>
                </div>
                <div class="hero-score">
                    <div class="hero-score-circle">
                        <span class="hero-score-value">87%</span>
                    </div>
                    <div class="hero-score-label">Predicted Performance</div>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <div class="hero-stat-value">A1</div>
                        <div class="hero-stat-label">Math</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-value">B2</div>
                        <div class="hero-stat-label">English</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-value">92%</div>
                        <div class="hero-stat-label">Confidence</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="features" id="features">
    <div class="features-header">
        <h2>Everything You Need to <span class="gradient-text">Succeed</span></h2>
        <p>Powerful tools designed to help Nigerian students predict, plan, and perform better in WAEC/NECO examinations.</p>
    </div>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon feature-icon-orange"><i class="bi bi-cpu"></i></div>
            <h3>AI-Powered Predictions</h3>
            <p>Our machine learning model analyzes your grades, study patterns, and academic background to deliver accurate performance forecasts.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon feature-icon-teal"><i class="bi bi-journal-check"></i></div>
            <h3>Personalized Study Plans</h3>
            <p>Get custom study schedules and subject-specific tips tailored to your weak areas and learning style.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon feature-icon-violet"><i class="bi bi-graph-up-arrow"></i></div>
            <h3>Performance Tracking</h3>
            <p>Monitor your improvement over time with detailed charts and trend analysis across multiple predictions.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon feature-icon-rose"><i class="bi bi-people-fill"></i></div>
            <h3>Lecturer Dashboard</h3>
            <p>Lecturers can manage student groups, view batch analytics, and track class performance trends.</p>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-it-works" id="how">
    <div class="features-header">
        <h2>How It <span class="gradient-text">Works</span></h2>
        <p>Three simple steps to know your academic potential.</p>
    </div>
    <div class="steps">
        <div class="step">
            <div class="step-number">1</div>
            <h3>Enter Your Grades</h3>
            <p>Input your WAEC/NECO subject grades, current CGPA, and a few details about your study environment.</p>
        </div>
        <div class="step">
            <div class="step-number">2</div>
            <h3>AI Analyzes</h3>
            <p>Our trained model processes your data against thousands of academic patterns to generate your prediction.</p>
        </div>
        <div class="step">
            <div class="step-number">3</div>
            <h3>Get Your Results</h3>
            <p>Receive your predicted score, confidence level, and personalized recommendations to improve.</p>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <div class="cta-box">
        <h2>Ready to Know Your <span class="gradient-text">Potential</span>?</h2>
        <p>Join thousands of students using AI to predict and improve their academic performance.</p>
        <a href="register.php" class="btn-primary">
            Create Free Account <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</section>

<!-- FOOTER -->
<footer class="footer">
    <p>&copy; 2026 PredictEd. Built with <i class="bi bi-heart-fill" style="color:var(--rose)"></i> for Nigerian students.</p>
</footer>

</body>
</html>
