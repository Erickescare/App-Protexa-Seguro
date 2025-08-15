<?php
// app-protexa-seguro/includes/header.php
// Template reutilizable para el header

$header_class = $header_class ?? '';
$show_menu = $show_menu ?? true;
$show_back_button = $show_back_button ?? false;
$back_url = $back_url ?? 'dashboard.php';
$header_title = $header_title ?? PWA_SHORT_NAME;

// Determinar clase CSS del header
if ($is_emergency ?? false) {
    $header_class .= ' emergency-header';
} elseif (isset($success_mode) && $success_mode) {
    $header_class .= ' success-header';
}
?>
<body class="<?php echo $body_class ?? ''; ?>">
    <div class="app-container">
        <!-- Header -->
        <header class="app-header <?php echo $header_class; ?>">
            <div class="header-content">
                <?php if ($show_back_button): ?>
                <div class="header-back">
                    <a href="<?php echo $back_url; ?>" class="back-button">
                        ← Volver
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="logo-container">
                    <img src="<?php echo BASE_URL; ?>assets/images/logo.png" 
                         alt="<?php echo APP_NAME; ?>" 
                         class="app-logo" 
                         onerror="this.style.display='none'">
                    <h1 class="app-title"><?php echo htmlspecialchars($header_title); ?></h1>
                </div>
                
                <?php if ($show_menu && checkWPAuth()): ?>
                <div class="header-menu">
                    <button type="button" class="menu-toggle" onclick="toggleMenu()">
                        <span class="menu-icon">☰</span>
                    </button>
                    
                    <div class="dropdown-menu" id="headerMenu">
                        <div class="menu-header">
                            <?php $user = getWPUser(); ?>
                            <div class="user-info">
                                <strong><?php echo htmlspecialchars($user['display_name']); ?></strong>
                                <small><?php echo htmlspecialchars($user['username']); ?></small>
                            </div>
                        </div>
                        
                        <div class="menu-items">
                            <a href="<?php echo BASE_URL; ?>dashboard.php" class="menu-item">
                                🏠 Dashboard
                            </a>
                            
                            <a href="<?php echo BASE_URL; ?>mis-recorridos.php" class="menu-item">
                                📋 Mis Recorridos
                            </a>
                            
                            <?php 
                            // Verificar si hay borradores
                            try {
                                $pdo = getDBConnection();
                                $stmt = $pdo->prepare("
                                    SELECT COUNT(*) as total 
                                    FROM " . getTableName('recorridos') . " 
                                    WHERE user_id = ? AND status = 'draft'
                                ");
                                $stmt->execute([$user['id']]);
                                $draft_count = $stmt->fetchColumn();
                                
                                if ($draft_count > 0): ?>
                                <a href="<?php echo BASE_URL; ?>borradores.php" class="menu-item">
                                    📝 Borradores <span class="badge"><?php echo $draft_count; ?></span>
                                </a>
                                <?php endif;
                            } catch (Exception $e) {
                                // Silenciar error si no se puede conectar
                            }
                            ?>
                            
                            <a href="<?php echo BASE_URL; ?>estadisticas.php" class="menu-item">
                                📊 Estadísticas
                            </a>
                            
                            <div class="menu-divider"></div>
                            
                            <a href="<?php echo BASE_URL; ?>configuracion.php" class="menu-item">
                                ⚙️ Configuración
                            </a>
                            
                            <a href="<?php echo BASE_URL; ?>ayuda.php" class="menu-item">
                                ❓ Ayuda
                            </a>
                            
                            <div class="menu-divider"></div>
                            
                            <a href="<?php echo str_replace('/app-protexa-seguro/', '/', BASE_URL) . 'wp-login.php?action=logout'; ?>" 
                               class="menu-item logout">
                                🚪 Cerrar Sesión
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </header>

        <!-- Notification Area -->
        <div id="notification-area" class="notification-area"></div>