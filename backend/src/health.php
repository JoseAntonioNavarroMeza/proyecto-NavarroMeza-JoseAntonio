<?php
header('Content-Type: application/json');

$dbHost = getenv('DB_HOST') ?: 'db';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'inventario_db';
$dbUser = getenv('DB_USER') ?: 'lab_user';
$dbPass = getenv('DB_PASSWORD') ?: 'lab_secure_pass_2026';

$redisHost = getenv('REDIS_HOST') ?: 'redis';
$redisPort = (int)(getenv('REDIS_PORT') ?: 6379);

$status = [
    'status' => 'ok',
    'database' => 'disconnected',
    'redis' => 'disconnected'
];

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_TIMEOUT => 3
    ]);
    $status['database'] = 'connected';
} catch (Exception $e) {
    $status['database'] = 'error: ' . $e->getMessage();
    $status['status'] = 'degraded';
}

try {
    $redis = new Redis();
    if ($redis->connect($redisHost, $redisPort, 2.5)) {
        $status['redis'] = 'connected';
        $redis->close();
    }
} catch (Exception $e) {
    $status['redis'] = 'error: ' . $e->getMessage();
    $status['status'] = 'degraded';
}

$httpCode = ($status['status'] === 'ok') ? 200 : 503;
http_response_code($httpCode);
echo json_encode($status);