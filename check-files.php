<?php
// app-protexa-seguro/check-files.php
// Script para verificar que todos los archivos necesarios existen

$required_files = [
    'index.php',
    'dashboard.php',
    'config.php',
    'manifest.json',
    'sw.js',
    'offline.html',
    'assets/css/app.css',
    'assets/js/app.js',
    'includes/head.php',
    'includes/header.php',
    'includes/footer.php'
];

$optional_files = [
    'assets/images/logo.png',
    'assets/images/icon-152x152.png',
    'assets/images/icon-32x32.png',
    'assets/images/icon-16x16.png'
];

echo "<h1>🔍 Verificación de Archivos - Protexa Seguro</h1>";

echo "<h2>✅ Archivos Requeridos</h2>";
$missing_required = [];
foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $file</p>";
    } else {
        echo "<p style='color: red;'>❌ $file - <strong>FALTANTE</strong></p>";
        $missing_required[] = $file;
    }
}

echo "<h2>📂 Archivos Opcionales</h2>";
$missing_optional = [];
foreach ($optional_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $file</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ $file - Opcional, usando fallback</p>";
        $missing_optional[] = $file;
    }
}

// Verificar permisos de directorios
echo "<h2>🔐 Permisos de Directorios</h2>";
$directories = ['uploads/', 'assets/', 'includes/', 'api/'];

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "<p style='color: green;'>✅ $dir - Escribible</p>";
        } else {
            echo "<p style='color: red;'>❌ $dir - Sin permisos de escritura</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ $dir - Directorio no existe</p>";
    }
}

// Verificar configuración
echo "<h2>⚙️ Configuración</h2>";
if (file_exists('config.php')) {
    include 'config.php';
    
    if (defined('BASE_URL')) {
        echo "<p style='color: green;'>✅ BASE_URL configurada: " . BASE_URL . "</p>";
    } else {
        echo "<p style='color: red;'>❌ BASE_URL no configurada</p>";
    }
    
    if (defined('TABLE_PREFIX')) {
        echo "<p style='color: green;'>✅ TABLE_PREFIX configurada: " . TABLE_PREFIX . "</p>";
    } else {
        echo "<p style='color: red;'>❌ TABLE_PREFIX no configurada</p>";
    }
    
    // Probar conexión a BD
    try {
        $pdo = getDBConnection();
        echo "<p style='color: green;'>✅ Conexión a base de datos exitosa</p>";
        
        // Verificar tablas
        $tables = [
            getTableName('recorridos'),
            getTableName('respuestas_categorias'),
            getTableName('fotos_recorrido'),
            getTableName('configuracion_preguntas')
        ];
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->fetch()) {
                echo "<p style='color: green;'>✅ Tabla $table existe</p>";
            } else {
                echo "<p style='color: red;'>❌ Tabla $table no existe</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error de conexión a BD: " . $e->getMessage() . "</p>";
    }
}

// Resumen
echo "<h2>📊 Resumen</h2>";
if (empty($missing_required)) {
    echo "<p style='color: green; font-weight: bold;'>🎉 Todos los archivos requeridos están presentes</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>⚠️ Faltan " . count($missing_required) . " archivos requeridos:</p>";
    foreach ($missing_required as $file) {
        echo "<li>$file</li>";
    }
}

if (!empty($missing_optional)) {
    echo "<p style='color: orange;'>ℹ️ Archivos opcionales faltantes (se usan fallbacks): " . count($missing_optional) . "</p>";
}

echo "<hr>";
echo "<p><a href='install.php'>🔧 Ir al Instalador</a> | <a href='dashboard.php'>🏠 Ir al Dashboard</a></p>";
?>