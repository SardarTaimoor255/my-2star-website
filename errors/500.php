<?php
http_response_code(500);

if (file_exists(dirname(__DIR__) . '/includes/init.php')) {
    require_once dirname(__DIR__) . '/includes/init.php';
    $user = current_user($pdo);
} else {
    $user = null;
    if (!defined('BASE_URL'))    define('BASE_URL', 'http://localhost/5star/');
    if (!defined('ASSETS_URL'))  define('ASSETS_URL', BASE_URL . 'assets/');
    if (!defined('SITE_SHORT'))  define('SITE_SHORT', '2 Star');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>500 — Server Error | <?= htmlspecialchars(SITE_SHORT) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="<?= ASSETS_URL ?>images/favicon.svg">
<style>
body {
    background: linear-gradient(135deg, #78350f 0%, #451a03 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: system-ui, -apple-system, sans-serif;
    color: #fff;
    padding: 20px;
}
.error-wrap { max-width: 560px; text-align: center; }
.error-code {
    font-size: 8rem;
    font-weight: 800;
    line-height: 1;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -3px;
}
.error-icon { font-size: 5rem; color: #fbbf24; margin-bottom: 1rem; }
h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 12px; }
p { color: #fde68a; margin-bottom: 24px; }
.btn-home {
    background: #f59e0b;
    color: #1b1b1b;
    font-weight: 600;
    padding: 12px 28px;
    border-radius: 8px;
    border: none;
    text-decoration: none;
    transition: all .2s;
}
.btn-home:hover { background: #d97706; color: #1b1b1b; transform: translateY(-2px); }
</style>
</head>
<body>
<div class="error-wrap">
    <div class="error-code">500</div>
    <div class="error-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
    <h1>Something Went Wrong</h1>
    <p>
        Server par kuch masla ho gaya hai.<br>
        Hum is par kaam kar rahe hain. Thori dair baad dobara try karein.
    </p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="<?= BASE_URL ?>" class="btn-home">
            <i class="bi bi-house-door"></i> Go to Homepage
        </a>
        <a href="javascript:location.reload()" class="btn btn-outline-light">
            <i class="bi bi-arrow-clockwise"></i> Try Again
        </a>
    </div>
</div>
</body>
</html>