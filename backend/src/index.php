<?php
header('Content-Type: application/json; charset=utf-8');

// Configuración de conexiones
$dbHost = getenv('DB_HOST') ?: 'db';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'inventario_db';
$dbUser = getenv('DB_USER') ?: 'lab_user';
$dbPass = getenv('DB_PASSWORD') ?: 'lab_secure_pass_2026';

$redisHost = getenv('REDIS_HOST') ?: 'redis';
$redisPort = (int)(getenv('REDIS_PORT') ?: 6379);

$smtpHost = getenv('SMTP_HOST') ?: 'mailpit';
$smtpPort = (int)(getenv('SMTP_PORT') ?: 1025);

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a BD: ' . $e->getMessage()]);
    exit;
}

$redis = new Redis();
$redisConnected = false;
try {
    if ($redis->connect($redisHost, $redisPort, 2.0)) {
        $redisConnected = true;
    }
} catch (Exception $e) {
    $redisConnected = false;
}

function sendSmtpMail($host, $port, $to, $subject, $message) {
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    if (!$socket) return false;

    fgets($socket, 512);
    fputs($socket, "HELO localhost\r\n"); fgets($socket, 512);
    fputs($socket, "MAIL FROM: <laboratorio@escuela.local>\r\n"); fgets($socket, 512);
    fputs($socket, "RCPT TO: <{$to}>\r\n"); fgets($socket, 512);
    fputs($socket, "DATA\r\n"); fgets($socket, 512);
    
    $headers  = "From: Laboratorio Inventario <laboratorio@escuela.local>\r\n";
    $headers .= "To: {$to}\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    fputs($socket, "{$headers}\r\n{$message}\r\n.\r\n"); fgets($socket, 512);
    fputs($socket, "QUIT\r\n"); fgets($socket, 512);
    fclose($socket);
    return true;
}

function invalidarCache($redis, $redisConnected) {
    if ($redisConnected) {
        $keys = $redis->keys('equipos:*');
        if (!empty($keys)) {
            $redis->del($keys);
        }
        $redis->del('equipos_all');
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : null;

// ROUTING
switch ($method) {
    case 'GET':
        // Contador de visitas/consultas en Redis
        if ($redisConnected) {
            $redis->incr('contador_consultas');
        }

        $cacheKey = $buscar ? 'equipos:buscar:' . md5($buscar) : 'equipos_all';
        $fromCache = false;

        if ($redisConnected && $redis->exists($cacheKey)) {
            $data = json_decode($redis->get($cacheKey), true);
            $fromCache = true;
        } else {
            if ($buscar) {
                $stmt = $pdo->prepare("SELECT * FROM equipos WHERE nombre LIKE :b OR numero_serie LIKE :b ORDER BY id DESC");
                $stmt->execute([':b' => "%{$buscar}%"]);
            } else {
                $stmt = $pdo->query("SELECT * FROM equipos ORDER BY id DESC");
            }
            $data = $stmt->fetchAll();

            // Guardar en Redis por 30 segundos
            if ($redisConnected) {
                $redis->setex($cacheKey, 30, json_encode($data));
            }
        }

        $contador = $redisConnected ? (int)$redis->get('contador_consultas') : 0;
        echo json_encode([
            'source' => $fromCache ? 'redis_cache' : 'mariadb',
            'consultas_totales' => $contador,
            'equipos' => $data
        ]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['nombre']) || empty($input['numero_serie']) || empty($input['laboratorio'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Campos obligatorios: nombre, numero_serie, laboratorio']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO equipos (nombre, numero_serie, laboratorio, estado) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $input['nombre'],
            $input['numero_serie'],
            $input['laboratorio'],
            $input['estado'] ?? 'Operativo'
        ]);
        $newId = $pdo->lastInsertId();

        // Invalidar caché
        invalidarCache($redis, $redisConnected);

        // Notificación Mailpit
        $correoMsg = "Se ha dado de alta un nuevo equipo de laboratorio:\n\n" .
                     "ID: {$newId}\n" .
                     "Nombre: {$input['nombre']}\n" .
                     "Nº Serie: {$input['numero_serie']}\n" .
                     "Laboratorio: {$input['laboratorio']}\n" .
                     "Estado: " . ($input['estado'] ?? 'Operativo') . "\n";
        sendSmtpMail($smtpHost, $smtpPort, 'admin@laboratorio.local', "Nuevo Equipo Registrado: {$input['nombre']}", $correoMsg);

        http_response_code(201);
        echo json_encode(['message' => 'Equipo registrado exitosamente', 'id' => $newId]);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$id || empty($input['estado'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Se requiere ID en URL y campo estado']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE equipos SET estado = ? WHERE id = ?");
        $stmt->execute([$input['estado'], $id]);

        invalidarCache($redis, $redisConnected);
        echo json_encode(['message' => 'Equipo actualizado exitosamente']);
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Se requiere ID del equipo a eliminar']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM equipos WHERE id = ?");
        $stmt->execute([$id]);

        invalidarCache($redis, $redisConnected);
        echo json_encode(['message' => 'Equipo eliminado correctamente']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no soportado']);
        break;
}