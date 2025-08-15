<?php
// app-protexa-seguro/install.php
// Script de verificación e instalación automática

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar si ya está instalado
if (file_exists('config.php')) {
    require_once 'config.php';
    
    // Verificar si las tablas ya existen
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([TABLE_PREFIX . 'recorridos']);
        
        if ($stmt->fetch()) {
            die('
            <h1>✅ Protexa Seguro ya está instalado</h1>
            <p>Las tablas ya existen en la base de datos.</p>
            <p><a href="index.php">Ir a la aplicación</a></p>
            <hr>
            <p><small>Si necesitas reinstalar, elimina las tablas con prefijo "' . TABLE_PREFIX . '" primero.</small></p>
            ');
        }
    } catch (Exception $e) {
        // Continuar con la instalación si hay error
    }
}

$errors = [];
$warnings = [];
$success = [];

// Verificaciones del sistema
echo '<h1>🔧 Instalador de Protexa Seguro</h1>';

// 1. Verificar versión de PHP
echo '<h2>1. Verificando PHP...</h2>';
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    echo '<p>✅ PHP ' . PHP_VERSION . ' (Requerido: 8.0+)</p>';
    $success[] = 'PHP version OK';
} else {
    echo '<p>❌ PHP ' . PHP_VERSION . ' (Necesitas PHP 8.0 o superior)</p>';
    $errors[] = 'PHP version insuficiente';
}

// 2. Verificar extensiones requeridas
echo '<h2>2. Verificando extensiones PHP...</h2>';
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'fileinfo'];
$optional_extensions = ['gd', 'curl'];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo '<p>✅ ' . $ext . '</p>';
        $success[] = "Extensión $ext OK";
    } else {
        echo '<p>❌ ' . $ext . ' (REQUERIDA)</p>';
        $errors[] = "Extensión $ext faltante";
    }
}

foreach ($optional_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo '<p>✅ ' . $ext . ' (opcional)</p>';
        $success[] = "Extensión $ext OK";
    } else {
        echo '<p>⚠️ ' . $ext . ' (recomendada para thumbnails/uploads)</p>';
        $warnings[] = "Extensión $ext no disponible";
    }
}

// 3. Verificar permisos de archivos
echo '<h2>3. Verificando permisos...</h2>';
$upload_dir = __DIR__ . '/uploads/';

if (!is_dir($upload_dir)) {
    if (mkdir($upload_dir, 0755, true)) {
        echo '<p>✅ Carpeta uploads/ creada</p>';
        $success[] = 'Carpeta uploads creada';
    } else {
        echo '<p>❌ No se pudo crear la carpeta uploads/</p>';
        $errors[] = 'Error creando carpeta uploads';
    }
} else {
    echo '<p>✅ Carpeta uploads/ existe</p>';
}

if (is_writable($upload_dir)) {
    echo '<p>✅ Carpeta uploads/ escribible</p>';
    $success[] = 'Permisos uploads OK';
} else {
    echo '<p>❌ Carpeta uploads/ no escribible</p>';
    $errors[] = 'Permisos uploads incorrectos';
    echo '<p><small>Ejecuta: <code>chmod 755 ' . $upload_dir . '</code></small></p>';
}

// 4. Verificar config.php
echo '<h2>4. Verificando configuración...</h2>';
if (file_exists('config.php')) {
    echo '<p>✅ config.php existe</p>';
    
    try {
        require_once 'config.php';
        
        // Verificar constantes requeridas
        $required_constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'BASE_URL', 'TABLE_PREFIX'];
        foreach ($required_constants as $const) {
            if (defined($const)) {
                echo '<p>✅ ' . $const . ' configurado</p>';
            } else {
                echo '<p>❌ ' . $const . ' no configurado</p>';
                $errors[] = "Constante $const faltante";
            }
        }
        
        // Probar conexión a base de datos
        try {
            $pdo = getDBConnection();
            echo '<p>✅ Conexión a base de datos exitosa</p>';
            $success[] = 'Conexión BD OK';
            
            // Verificar si WordPress está disponible
            if (defined('WP_PATH') && file_exists(WP_PATH)) {
                echo '<p>✅ WordPress encontrado en: ' . WP_PATH . '</p>';
                $success[] = 'WordPress encontrado';
            } else {
                echo '<p>⚠️ WordPress no encontrado en: ' . (defined('WP_PATH') ? WP_PATH : 'WP_PATH no definido') . '</p>';
                $warnings[] = 'WordPress no encontrado';
            }
            
        } catch (Exception $e) {
            echo '<p>❌ Error conectando a BD: ' . htmlspecialchars($e->getMessage()) . '</p>';
            $errors[] = 'Error conexión BD';
        }
        
    } catch (Exception $e) {
        echo '<p>❌ Error en config.php: ' . htmlspecialchars($e->getMessage()) . '</p>';
        $errors[] = 'Error en config.php';
    }
    
} else {
    echo '<p>❌ config.php no existe</p>';
    $errors[] = 'config.php faltante';
    echo '<p><small>Copia config.php.example y configúralo con tus datos</small></p>';
}

// 5. Verificar archivos requeridos
echo '<h2>5. Verificando archivos...</h2>';
$required_files = [
    'index.php',
    'dashboard.php', 
    'recorrido.php',
    'success.php',
    'manifest.json',
    'sw.js',
    'database.sql',
    'assets/css/app.css',
    'assets/js/app.js',
    'api/save-progress.php',
    'api/upload-photo.php',
    'api/ping.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo '<p>✅ ' . $file . '</p>';
    } else {
        echo '<p>❌ ' . $file . ' faltante</p>';
        $errors[] = "Archivo $file faltante";
    }
}

// Mostrar resumen
echo '<h2>📊 Resumen de Instalación</h2>';

if (!empty($errors)) {
    echo '<div style="background: #fee; border: 1px solid #f00; padding: 1rem; margin: 1rem 0; border-radius: 5px;">';
    echo '<h3>❌ Errores críticos (' . count($errors) . '):</h3>';
    echo '<ul>';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul>';
    echo '<p><strong>Debes corregir estos errores antes de continuar.</strong></p>';
    echo '</div>';
}

if (!empty($warnings)) {
    echo '<div style="background: #ffc; border: 1px solid #f90; padding: 1rem; margin: 1rem 0; border-radius: 5px;">';
    echo '<h3>⚠️ Advertencias (' . count($warnings) . '):</h3>';
    echo '<ul>';
    foreach ($warnings as $warning) {
        echo '<li>' . htmlspecialchars($warning) . '</li>';
    }
    echo '</ul>';
    echo '<p>La aplicación funcionará, pero se recomienda corregir estas advertencias.</p>';
    echo '</div>';
}

if (!empty($success)) {
    echo '<div style="background: #efe; border: 1px solid #0a0; padding: 1rem; margin: 1rem 0; border-radius: 5px;">';
    echo '<h3>✅ Verificaciones exitosas (' . count($success) . '):</h3>';
    echo '<ul>';
    foreach ($success as $item) {
        echo '<li>' . htmlspecialchars($item) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}

// Formulario de instalación de BD
if (empty($errors) && isset($pdo)) {
    echo '<h2>🗄️ Instalación de Base de Datos</h2>';
    
    if ($_POST && isset($_POST['install_db'])) {
        try {
            $sql_content = file_get_contents('database.sql');
            
            if ($sql_content) {
                // Ejecutar cada statement por separado
                $statements = explode(';', $sql_content);
                $installed = 0;
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement) && !str_starts_with($statement, '--')) {
                        try {
                            $pdo->exec($statement);
                            $installed++;
                        } catch (Exception $e) {
                            if (!str_contains($e->getMessage(), 'already exists')) {
                                throw $e;
                            }
                        }
                    }
                }
                
                echo '<div style="background: #efe; border: 1px solid #0a0; padding: 1rem; margin: 1rem 0; border-radius: 5px;">';
                echo '<h3>✅ Base de datos instalada exitosamente</h3>';
                echo '<p>Se ejecutaron ' . $installed . ' statements SQL.</p>';
                echo '<p><strong>¡Protexa Seguro está listo para usar!</strong></p>';
                echo '<p><a href="index.php" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🚀 Ir a la aplicación</a></p>';
                echo '</div>';
                
                // Opcional: eliminar installer después de instalación exitosa
                echo '<p><small>Por seguridad, se recomienda eliminar install.php después de la instalación.</small></p>';
                
            } else {
                throw new Exception('No se pudo leer database.sql');
            }
            
        } catch (Exception $e) {
            echo '<div style="background: #fee; border: 1px solid #f00; padding: 1rem; margin: 1rem 0; border-radius: 5px;">';
            echo '<h3>❌ Error instalando base de datos</h3>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
    } else {
        echo '<form method="post">';
        echo '<p>La verificación del sistema fue exitosa. ¿Deseas instalar las tablas de la base de datos?</p>';
        echo '<p><strong>Tablas que se crearán:</strong></p>';
        echo '<ul>';
        echo '<li>ps_recorridos - Datos principales de recorridos</li>';
        echo '<li>ps_respuestas_categorias - Respuestas a preguntas</li>';
        echo '<li>ps_fotos_recorrido - Fotos adjuntas</li>';
        echo '<li>ps_hallazgos_criticos - Hallazgos importantes</li>';
        echo '<li>ps_configuracion_preguntas - Configuración del formulario</li>';
        echo '<li>ps_configuracion_app - Configuración general</li>';
        echo '<li>ps_borradores_recorrido - Borradores temporales</li>';
        echo '<li>ps_logs_sistema - Logs de auditoría</li>';
        echo '</ul>';
        echo '<button type="submit" name="install_db" style="background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">📦 Instalar Base de Datos</button>';
        echo '</form>';
    }
}

// Información adicional
echo '<h2>📚 Información Adicional</h2>';
echo '<ul>';
echo '<li><strong>Prefijo de tablas:</strong> ps_ (Protexa Seguro)</li>';
echo '<li><strong>Documentación:</strong> README.md</li>';
echo '<li><strong>Versión:</strong> 1.0.0</li>';
echo '<li><strong>Soporte:</strong> Revisar logs en ps_logs_sistema</li>';
echo '</ul>';

echo '<h3>🔧 Próximos pasos después de la instalación:</h3>';
echo '<ol>';
echo '<li>Configurar iconos PWA en assets/images/</li>';
echo '<li>Configurar emails de emergencia en ps_configuracion_app</li>';
echo '<li>Probar la aplicación con un usuario de WordPress</li>';
echo '<li>Configurar certificado SSL (requerido para PWA)</li>';
echo '<li>Eliminar install.php por seguridad</li>';
echo '</ol>';

?>
<style>
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    max-width: 800px;
    margin: 2rem auto;
    padding: 1rem;
    line-height: 1.6;
}
h1, h2, h3 { color: #333; }
code { background: #f5f5f5; padding: 2px 4px; border-radius: 3px; }
ul, ol { margin: 1rem 0; }
li { margin: 0.5rem 0; }
</style>