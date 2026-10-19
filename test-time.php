<?php
echo "PHP Timezone: " . date_default_timezone_get() . "<br>";
echo "PHP Time Now: " . date('Y-m-d H:i:s') . "<br>";
echo "PHP Timestamp: " . time() . "<br><br>";

require_once __DIR__ . '/includes/init.php';

$row = $pdo->query("SELECT NOW() AS db_now, @@session.time_zone AS tz, @@global.time_zone AS gtz")->fetch();
echo "MySQL NOW(): " . $row['db_now'] . "<br>";
echo "MySQL Session TZ: " . $row['tz'] . "<br>";
echo "MySQL Global TZ: " . $row['gtz'] . "<br>";

$row2 = $pdo->query("SELECT created_at FROM orders ORDER BY id DESC LIMIT 1")->fetch();
echo "Last order created_at (raw): " . $row2['created_at'] . "<br>";