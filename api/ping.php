<?php
// app-protexa-seguro/api/ping.php
header('Content-Type: application/json');
require_once '../config.php';
setSecurityHeaders();

// Simple ping endpoint para verificar conectividad
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse([
        'status' => 'ok',
        'timestamp' => time(),
        'version' => APP_VERSION,
        'server_time' => date('Y-m-d H:i:s')
    ]);
}

http_response_code(405);
exit;
?>