<?php
http_response_code(503);
header('Retry-After: 3600'); // 1 hour

if (!defined('BASE_URL'))    define('BASE_URL', 'http://localhost/5star/');
if (!defined('ASSETS_URL'))  define('ASSETS_URL', BASE_URL . 'assets/');
if (!defined('SITE_NAME'))   define('SITE_NAME', '2 Star Electric & Electronics');
if (!defined('SITE_SHORT'))  define('SITE_SHORT', '2 Star');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Under Maintenance | <?= htmlspecialchars(SITE_SHORT) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
    background: linear-gradient(135deg, #0b3d91 0%, #081f4d 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: system-ui, -apple-system, sans-serif;
    color: #fff;
    padding: 20px;
}
.maintenance-wrap { max-width: 620px; text-align: center; }
.maintenance-icon {
    font-size: 6rem;
    color: #f5a623;
    margin-bottom: 1.5rem;
    animation: spin 3s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
h1 { font-size: 2rem; font-weight: 800; margin-bottom: 16px; }
p { color: #c9d6f2; font-size: 1.05rem; margin-bottom: 24px; }
.info-box {
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
}
.info-box small { color: #cbd5e1; }
</style>
</head>
<body>
<div class="maintenance-wrap">
    <div class="maintenance-icon"><i class="bi bi-gear-wide-connected"></i></div>
    <h1>We'll Be Back Soon</h1>
    <p>
        Hum website ko update kar rahe hain taake aap ko behtar experience de sakein.<br>
        Thori dair mein wapas aa rahe hain.
    </p>
    <div class="info-box">
        <div class="fw-semibold mb-2">Expected Downtime</div>
        <small>
            <i class="bi bi-clock"></i> Approximately 1 hour<br>
            <i class="bi bi-exclamation-circle"></i> Koi urgent kaam ho toh direct call karein
        </small>
    </div>
    <a href="javascript:location.reload()" class="btn btn-warning px-4 py-2">
        <i class="bi bi-arrow-clockwise"></i> Retry
    </a>
</div>
</body>
</html>