<?php
// app-protexa-seguro/includes/footer.php
// Template reutilizable para el footer

$show_footer = $show_footer ?? true;
$footer_class = $footer_class ?? '';
?>

        <?php if ($show_footer): ?>
        <!-- Footer -->
        <footer class="app-footer <?php echo $footer_class; ?>">
            <div class="footer-content">
                <div class="footer-info">
                    <p>&copy; <?php echo date('Y'); ?> Protexa. Todos los derechos reservados.</p>
                    <p class="version">Versión <?php echo APP_VERSION; ?></p>
                </div>
                
                <?php if (isset($show_footer_stats) && $show_footer_stats): ?>
                <div class="footer-stats">
                    <?php
                    try {
                        if (checkWPAuth()) {
                            $user = getWPUser();
                            $pdo = getDBConnection();
                            
                            // Estadísticas rápidas
                            $stmt = $pdo->prepare("
                                SELECT 
                                    COUNT(*) as total_recorridos,
                                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completados,
                                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as borradores
                                FROM " . getTableName('recorridos') . " 
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$user['id']]);
                            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            <small>
                                📊 Total: <?php echo $stats['total_recorridos']; ?> | 
                                ✅ Completados: <?php echo $stats['completados']; ?> | 
                                📝 Borradores: <?php echo $stats['borradores']; ?>
                            </small>
                            <?php
                        }
                    } catch (Exception $e) {
                        // Silenciar errores
                    }
                    ?>
                </div>
                <?php endif; ?>
            </div>
        </footer>
        <?php endif; ?>
    </div>

    <!-- Offline Indicator -->
    <div id="offline-indicator" class="offline-indicator" style="display: none;">
        <span class="offline-icon">📡</span>
        <span class="offline-text">Sin conexión - Trabajando offline</span>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <p>Cargando...</p>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Scripts base -->
    <script src="<?php echo BASE_URL; ?>assets/js/app.js?v=<?php echo APP_VERSION; ?>"></script>
    
    <!-- Scripts adicionales específicos de página -->
    <?php if (isset($additional_scripts)): ?>
        <?php foreach ($additional_scripts as $script): ?>
            <script src="<?php echo BASE_URL . $script; ?>?v=<?php echo APP_VERSION; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- JavaScript inline específico de página -->
    <?php if (isset($inline_scripts)): ?>
        <script><?php echo $inline_scripts; ?></script>
    <?php endif; ?>
    
    <script>
        // Inicialización global
        document.addEventListener('DOMContentLoaded', function() {
            // Registrar Service Worker solo si está disponible
            if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
                navigator.serviceWorker.register('<?php echo BASE_URL; ?>sw.js')
                    .then(registration => {
                        console.log('SW registrado correctamente:', registration.scope);
                        
                        // Verificar actualizaciones cada 5 minutos
                        setInterval(() => {
                            registration.update().catch(error => {
                                console.warn('Error verificando actualizaciones del SW:', error);
                            });
                        }, 300000);
                    })
                    .catch(error => {
                        console.warn('Error registrando SW:', error);
                    });
            } else {
                console.log('Service Worker no disponible o sitio no HTTPS');
            }

            // Manejar eventos de conexión
            window.addEventListener('online', () => {
                const indicator = document.getElementById('offline-indicator');
                if (indicator) indicator.style.display = 'none';
                showToast('Conexión restaurada', 'success');
            });

            window.addEventListener('offline', () => {
                const indicator = document.getElementById('offline-indicator');
                if (indicator) indicator.style.display = 'flex';
                showToast('Sin conexión - Trabajando offline', 'warning');
            });

            // Inicializar menú
            initializeMenu();
            
            // Verificar estado inicial de conexión
            if (!navigator.onLine) {
                const indicator = document.getElementById('offline-indicator');
                if (indicator) indicator.style.display = 'flex';
            }
        });

        // Funciones globales del menú
        function toggleMenu() {
            const menu = document.getElementById('headerMenu');
            const isOpen = menu.classList.contains('active');
            
            if (isOpen) {
                menu.classList.remove('active');
                document.removeEventListener('click', closeMenuOnOutsideClick);
            } else {
                menu.classList.add('active');
                setTimeout(() => {
                    document.addEventListener('click', closeMenuOnOutsideClick);
                }, 10);
            }
        }

        function closeMenuOnOutsideClick(event) {
            const menu = document.getElementById('headerMenu');
            const toggle = document.querySelector('.menu-toggle');
            
            if (!menu.contains(event.target) && !toggle.contains(event.target)) {
                menu.classList.remove('active');
                document.removeEventListener('click', closeMenuOnOutsideClick);
            }
        }

        function initializeMenu() {
            // Marcar item activo del menú
            const currentPage = window.location.pathname.split('/').pop();
            const menuItems = document.querySelectorAll('.menu-item');
            
            menuItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href && href.includes(currentPage)) {
                    item.classList.add('active');
                }
            });
        }

        // Sistema de notificaciones toast
        function showToast(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            const icon = {
                'success': '✅',
                'error': '❌', 
                'warning': '⚠️',
                'info': 'ℹ️'
            };
            
            toast.innerHTML = `
                <span class="toast-icon">${icon[type] || icon.info}</span>
                <span class="toast-message">${message}</span>
                <button class="toast-close" onclick="this.parentElement.remove()">×</button>
            `;
            
            container.appendChild(toast);
            
            // Auto remove
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, duration);
        }

        // Loading overlay
        function showLoading(message = 'Cargando...') {
            const overlay = document.getElementById('loading-overlay');
            const text = overlay.querySelector('p');
            text.textContent = message;
            overlay.style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loading-overlay').style.display = 'none';
        }

        // PWA Installation
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Solo mostrar prompt en páginas principales
            const currentPage = window.location.pathname.split('/').pop();
            const mainPages = ['index.php', 'dashboard.php', ''];
            
            if (mainPages.includes(currentPage)) {
                setTimeout(showInstallBanner, 10000); // Mostrar después de 10 segundos
            }
        });

        function showInstallBanner() {
            if (deferredPrompt && !localStorage.getItem('pwa-dismissed-' + new Date().toDateString())) {
                showToast(
                    '¡Instala Protexa Seguro para acceso rápido! <button onclick="installPWA()" style="margin-left: 10px; padding: 2px 8px; border: none; background: white; border-radius: 3px;">Instalar</button>',
                    'info',
                    8000
                );
            }
        }

        async function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                
                if (outcome === 'accepted') {
                    showToast('¡Aplicación instalada correctamente!', 'success');
                } else {
                    localStorage.setItem('pwa-dismissed-' + new Date().toDateString(), 'true');
                }
            }
        }
    </script>
</body>
</html>