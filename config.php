<?php
// app-protexa-seguro/config.php

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'gykqnyrzxj');
define('DB_USER', 'gykqnyrzxj');
define('DB_PASS', 'vwtPT4Syye');

// Prefijo para las tablas de Protexa Seguro
define('TABLE_PREFIX', 'ps_');

// Configuración de la aplicación
define('APP_NAME', 'Protexa Seguro');
define('APP_VERSION', '1.0.65');
define('BASE_URL', 'https://www.universidadprotexa.mx/app-protexa-seguro/');

// Rutas importantes
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');

// Integración WordPress (ajusta la ruta según tu instalación)
define('WP_PATH', '../wp-load.php');

// Configuración PWA
define('PWA_NAME', 'Protexa Seguro App');
define('PWA_SHORT_NAME', 'Protexa');
define('PWA_DESCRIPTION', 'App de Recorridos de Seguridad');

// Función helper para nombres de tablas
function getTableName($table) {
    return TABLE_PREFIX . $table;
}

// Conexión a base de datos
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
                       DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Función para verificar si el usuario está logueado via WordPress
function checkWPAuth() {
    if (file_exists(WP_PATH)) {
        require_once WP_PATH;
        return is_user_logged_in();
    }
    return false;
}

// Función para obtener datos del usuario de WordPress
function getWPUser() {
    if (checkWPAuth()) {
        $current_user = wp_get_current_user();
        return [
            'id' => $current_user->ID,
            'username' => $current_user->user_login,
            'email' => $current_user->user_email,
            'display_name' => $current_user->display_name
        ];
    }
    return null;
}

// Headers de seguridad
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
}

// Función para respuestas JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>