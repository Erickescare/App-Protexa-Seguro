<?php
// app-protexa-seguro/index.php
require_once 'config.php';
setSecurityHeaders();

// Verificar si el usuario ya está logueado
if (checkWPAuth()) {
    header('Location: dashboard.php');
    exit;
}

// Si se envió el formulario de login
if ($_POST && isset($_POST['login'])) {
    // El login se maneja via WordPress, redirigir
    $redirect_url = urlencode(BASE_URL . 'dashboard.php');
    header('Location: ' . str_replace('/app-protexa-seguro/', '/', BASE_URL) . 'wp-login.php?redirect_to=' . $redirect_url);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="description" content="<?php echo PWA_DESCRIPTION; ?>">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo PWA_SHORT_NAME; ?>">
    
    <title><?php echo APP_NAME; ?> - Inicio de Sesión</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Iconos para diferentes dispositivos -->
    <link rel="apple-touch-icon" href="assets/images/icon-152x152.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/icon-16x16.png">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/app.css">
    
    <!-- Preload crítico -->
    <link rel="preload" href="assets/js/app.js" as="script">
</head>
<body class="login-page">
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="<?php echo APP_NAME; ?>" class="app-logo" onerror="this.style.display='none'">
                <h1 class="app-title"><?php echo APP_NAME; ?></h1>
            </div>
        </header>

        <!-- Login Form -->
        <main class="login-main">
            <div class="login-card">
                <div class="login-header">
                    <h2>Iniciar Sesión</h2>
                    <p>Accede con tu cuenta de WordPress</p>
                </div>
                
                <form class="login-form" method="post" action="">
                    <div class="form-group">
                        <label for="username">Usuario o Email</label>
                        <input type="text" id="username" name="username" required 
                               placeholder="Ingresa tu usuario" autocomplete="username">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Ingresa tu contraseña" autocomplete="current-password">
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" value="1">
                            <span class="checkmark"></span>
                            Mantener sesión iniciada
                        </label>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary btn-block">
                        <span class="btn-text">Iniciar Sesión</span>
                        <span class="btn-loading" style="display: none;">
                            <span class="spinner"></span>
                            Cargando...
                        </span>
                    </button>
                </form>
                
                <div class="login-links">
                    <a href="#" onclick="alert('Contacta al administrador del sistema')">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="app-footer">
            <p>&copy; 2024 Protexa. Todos los derechos reservados.</p>
            <p class="version">Versión <?php echo APP_VERSION; ?></p>
        </footer>
    </div>

    <!-- PWA Install Prompt -->
    <div id="pwa-install-prompt" class="pwa-prompt" style="display: none;">
        <div class="pwa-prompt-content">
            <h3>¡Instala la App!</h3>
            <p>Agrega <?php echo PWA_SHORT_NAME; ?> a tu pantalla de inicio para un acceso más rápido.</p>
            <div class="pwa-prompt-buttons">
                <button id="pwa-install-btn" class="btn btn-primary btn-sm">Instalar</button>
                <button id="pwa-dismiss-btn" class="btn btn-secondary btn-sm">Ahora no</button>
            </div>
        </div>
    </div>

    <!-- Offline Indicator -->
    <div id="offline-indicator" class="offline-indicator" style="display: none;">
        <span class="offline-icon">📡</span>
        <span class="offline-text">Sin conexión - Trabajando offline</span>
    </div>

    <!-- Scripts -->
    <script src="assets/js/app.js"></script>
    <script>
        // Registrar Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(registration => {
                        console.log('SW registrado:', registration);
                        
                        // Verificar actualizaciones cada 30 segundos
                        setInterval(() => {
                            registration.update();
                        }, 30000);
                    })
                    .catch(error => {
                        console.log('Error registrando SW:', error);
                    });
            });
        }

        // Manejar eventos de conexión
        window.addEventListener('online', () => {
            document.getElementById('offline-indicator').style.display = 'none';
        });

        window.addEventListener('offline', () => {
            document.getElementById('offline-indicator').style.display = 'flex';
        });

        // Prompt de instalación PWA
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Mostrar prompt personalizado después de 3 segundos
            setTimeout(() => {
                document.getElementById('pwa-install-prompt').style.display = 'block';
            }, 3000);
        });

        // Manejar instalación
        document.getElementById('pwa-install-btn')?.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                document.getElementById('pwa-install-prompt').style.display = 'none';
            }
        });

        // Descartar prompt
        document.getElementById('pwa-dismiss-btn')?.addEventListener('click', () => {
            document.getElementById('pwa-install-prompt').style.display = 'none';
        });
    </script>
</body>
</html>