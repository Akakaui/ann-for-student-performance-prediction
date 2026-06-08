<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found — PredictEd</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg: #0a0a0f;
            --accent: #f97316;
            --accent-glow: rgba(249,115,22,0.3);
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --border: rgba(255,255,255,0.06);
            --glass: rgba(255,255,255,0.04);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            animation: float 15s ease-in-out infinite;
        }

        .orb-1 { width: 400px; height: 400px; background: var(--accent); top: -100px; right: -100px; }
        .orb-2 { width: 300px; height: 300px; background: #14b8a6; bottom: -80px; left: -80px; animation-delay: -5s; }
        .orb-3 { width: 200px; height: 200px; background: #8b5cf6; top: 50%; left: 50%; animation-delay: -10s; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(20px, -20px) scale(1.05); }
            66% { transform: translate(-15px, 15px) scale(0.95); }
        }

        .content {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 2rem;
        }

        .code {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(6rem, 15vw, 12rem);
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent), #f43f5e, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
            margin-bottom: 1rem;
        }

        h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        p {
            color: var(--text-muted);
            font-size: 1rem;
            max-width: 400px;
            margin: 0 auto 2rem;
            line-height: 1.7;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.8rem 1.8rem;
            border-radius: 10px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #ea580c);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px var(--accent-glow);
        }

        .btn-secondary {
            background: var(--glass);
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-secondary:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }

        @media (max-width: 480px) {
            .code { font-size: 5rem; }
            .actions { flex-direction: column; align-items: center; }
        }
    </style>
</head>
<body>

<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>

<div class="content">
    <div class="code">404</div>
    <h1>Page Not Found</h1>
    <p>The page you're looking for doesn't exist or has been moved. Let's get you back on track.</p>
    <div class="actions">
        <a href="dashboard.php" class="btn btn-primary"><i class="bi bi-house"></i> Dashboard</a>
        <a href="javascript:history.back()" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Go Back</a>
    </div>
</div>

</body>
</html>
