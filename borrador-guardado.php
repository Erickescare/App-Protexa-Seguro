<?php
// app-protexa-seguro/borrador-guardado.php
require_once 'config.php';
setSecurityHeaders();

// Verificar autenticación
if (!checkWPAuth()) {
    header('Location: index.php');
    exit;
}

$user = getWPUser();
if (!$user) {
    header('Location: index.php');
    exit;
}

// Obtener ID del recorrido guardado
$tour_id = $_GET['id'] ?? null;
$from_auto_save = isset($_GET['auto']) && $_GET['auto'] === '1';

if (!$tour_id) {
    header('Location: dashboard.php');
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Verificar que el borrador existe y pertenece al usuario
    $stmt = $pdo->prepare("
        SELECT r.*,
               CASE 
                   WHEN r.total_questions > 0 THEN ROUND((r.answered_questions / r.total_questions) * 100)
                   ELSE 0 
               END as porcentaje_completado
        FROM " . getTableName('recorridos') . " r
        WHERE r.id = ? AND r.user_id = ? AND r.status = 'draft'
    ");
    $stmt->execute([$tour_id, $user['id']]);
    $recorrido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recorrido) {
        header('Location: dashboard.php?error=not_found');
        exit;
    }
    
    // Obtener tiempo transcurrido desde la última actualización
    $tiempo_transcurrido = strtotime('now') - strtotime($recorrido['updated_at']);
    
} catch (Exception $e) {
    header('Location: dashboard.php?error=database');
    exit;
}

// Configurar variables para templates
$page_title = APP_NAME . ' - Borrador Guardado';
$page_description = 'Tu recorrido ha sido guardado como borrador';
$show_back_button = false;
$header_title = 'Borrador Guardado';
$show_footer_stats = false;

include 'includes/head.php';
include 'includes/header.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="container">
        <!-- Success Message -->
        <div class="draft-saved-card">
            <div class="draft-icon">
                <?php if ($from_auto_save): ?>
                    <div class="auto-save-icon">💾</div>
                <?php else: ?>
                    <div class="manual-save-icon">✅</div>
                <?php endif; ?>
            </div>
            
            <div class="draft-content">
                <h1 class="draft-title">
                    <?php if ($from_auto_save): ?>
                        Progreso Guardado Automáticamente
                    <?php else: ?>
                        ¡Borrador Guardado Correctamente!
                    <?php endif; ?>
                </h1>
                
                <p class="draft-subtitle">
                    <?php if ($from_auto_save): ?>
                        Tu progreso se ha guardado automáticamente. Puedes continuar cuando gustes.
                    <?php else: ?>
                        Tu recorrido ha sido guardado como borrador. Puedes continuar completándolo más tarde.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Recorrido Info -->
        <div class="draft-info-card">
            <div class="draft-info-header">
                <h2>Información del Borrador</h2>
                <div class="draft-type">
                    <?php if ($recorrido['tour_type'] === 'emergency'): ?>
                        <span class="type-badge emergency">🚨 Emergencia</span>
                    <?php else: ?>
                        <span class="type-badge scheduled">📋 Programado</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="draft-details">
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Ubicación:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($recorrido['location']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">División:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($recorrido['division']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Negocio:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($recorrido['business']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Motivo:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($recorrido['reason']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Progress Info -->
            <div class="draft-progress">
                <div class="progress-header">
                    <h3>Progreso del Recorrido</h3>
                    <span class="progress-percentage"><?php echo $recorrido['porcentaje_completado']; ?>%</span>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $recorrido['porcentaje_completado']; ?>%"></div>
                </div>
                
                <div class="progress-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $recorrido['answered_questions']; ?></span>
                        <span class="stat-label">Respondidas</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $recorrido['total_questions'] - $recorrido['answered_questions']; ?></span>
                        <span class="stat-label">Pendientes</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $recorrido['total_questions']; ?></span>
                        <span class="stat-label">Total</span>
                    </div>
                </div>
            </div>
            
            <!-- Time Info -->
            <div class="draft-meta">
                <div class="meta-row">
                    <span class="meta-label">Iniciado:</span>
                    <span class="meta-value"><?php echo date('d/m/Y H:i', strtotime($recorrido['created_at'])); ?></span>
                </div>
                
                <div class="meta-row">
                    <span class="meta-label">Última actualización:</span>
                    <span class="meta-value">
                        <?php echo date('d/m/Y H:i', strtotime($recorrido['updated_at'])); ?>
                        <small class="time-ago">
                            (hace 
                            <?php 
                            $minutos = floor($tiempo_transcurrido / 60);
                            if ($minutos < 1) {
                                echo 'menos de 1 minuto';
                            } elseif ($minutos < 60) {
                                echo $minutos . ' minuto' . ($minutos > 1 ? 's' : '');
                            } elseif ($minutos < 1440) {
                                $horas = floor($minutos / 60);
                                echo $horas . ' hora' . ($horas > 1 ? 's' : '');
                            } else {
                                $dias = floor($minutos / 1440);
                                echo $dias . ' día' . ($dias > 1 ? 's' : '');
                            }
                            ?>)
                        </small>
                    </span>
                </div>
                
                <div class="meta-row">
                    <span class="meta-label">ID del recorrido:</span>
                    <span class="meta-value">#<?php echo $recorrido['id']; ?></span>
                </div>
            </div>
        </div>

        <!-- Action Cards -->
        <div class="draft-actions">
            <div class="action-card primary-action">
                <div class="action-icon">▶️</div>
                <div class="action-content">
                    <h3>Continuar Recorrido</h3>
                    <p>Reanuda el recorrido desde donde lo dejaste</p>
                </div>
                <a href="recorrido.php?id=<?php echo $tour_id; ?>" class="btn btn-primary btn-lg">
                    Continuar
                </a>
            </div>
            
            <div class="action-card secondary-action">
                <div class="action-icon">📋</div>
                <div class="action-content">
                    <h3>Ver Mis Borradores</h3>
                    <p>Gestiona todos tus recorridos guardados</p>
                </div>
                <a href="borradores.php" class="btn btn-outline btn-lg">
                    Ver Borradores
                </a>
            </div>
            
            <div class="action-card secondary-action">
                <div class="action-icon">🏠</div>
                <div class="action-content">
                    <h3>Ir al Dashboard</h3>
                    <p>Vuelve al panel principal</p>
                </div>
                <a href="dashboard.php" class="btn btn-secondary btn-lg">
                    Dashboard
                </a>
            </div>
        </div>

        <!-- Tips Section -->
        <div class="draft-tips">
            <h3>💡 Consejos sobre Borradores</h3>
            <div class="tips-grid">
                <div class="tip-item">
                    <div class="tip-icon">💾</div>
                    <div class="tip-content">
                        <h4>Guardado Automático</h4>
                        <p>Tu progreso se guarda automáticamente cada vez que respondes una pregunta.</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <div class="tip-icon">📱</div>
                    <div class="tip-content">
                        <h4>Funciona Offline</h4>
                        <p>Puedes continuar el recorrido sin conexión. Se sincronizará cuando vuelvas a estar online.</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <div class="tip-icon">⏰</div>
                    <div class="tip-content">
                        <h4>Sin Límite de Tiempo</h4>
                        <p>Los borradores se conservan indefinidamente. Puedes completarlos cuando tengas tiempo.</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <div class="tip-icon">🔄</div>
                    <div class="tip-content">
                        <h4>Sincronización</h4>
                        <p>Tus borradores se sincronizan entre dispositivos si usas la misma cuenta.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <?php
        try {
            // Obtener estadísticas rápidas del usuario
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_borradores,
                    COUNT(CASE WHEN tour_type = 'emergency' THEN 1 END) as borradores_emergencia,
                    AVG(CASE WHEN total_questions > 0 THEN (answered_questions / total_questions) * 100 ELSE 0 END) as promedio_progreso
                FROM " . getTableName('recorridos') . " 
                WHERE user_id = ? AND status = 'draft'
            ");
            $stmt->execute([$user['id']]);
            $stats_borradores = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($stats_borradores['total_borradores'] > 1):
        ?>
        <div class="draft-stats">
            <h3>📊 Tus Borradores</h3>
            <div class="stats-summary">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $stats_borradores['total_borradores']; ?></div>
                    <div class="stat-label">Borradores Totales</div>
                </div>
                
                <?php if ($stats_borradores['borradores_emergencia'] > 0): ?>
                <div class="stat-box emergency">
                    <div class="stat-number"><?php echo $stats_borradores['borradores_emergencia']; ?></div>
                    <div class="stat-label">De Emergencia</div>
                </div>
                <?php endif; ?>
                
                <div class="stat-box">
                    <div class="stat-number"><?php echo round($stats_borradores['promedio_progreso']); ?>%</div>
                    <div class="stat-label">Progreso Promedio</div>
                </div>
            </div>
            
            <div class="stats-action">
                <p>Tienes <?php echo $stats_borradores['total_borradores'] - 1; ?> borrador(es) más esperando.</p>
                <a href="borradores.php" class="btn btn-outline">Ver Todos los Borradores</a>
            </div>
        </div>
        <?php 
            endif;
        } catch (Exception $e) {
            // Silenciar errores de estadísticas
        }
        ?>
    </div>
</main>

<?php
// Script para auto-redirect opcional
$auto_redirect_time = $from_auto_save ? 10 : 0; // 10 segundos para auto-save, 0 para manual

$inline_scripts = "
    // Contador de tiempo para auto-redirect (solo en auto-save)
    let redirectTime = $auto_redirect_time;
    let redirectInterval;
    
    if (redirectTime > 0) {
        const redirectButton = document.querySelector('.btn-primary');
        const originalText = redirectButton.textContent;
        
        redirectInterval = setInterval(() => {
            if (redirectTime > 0) {
                redirectButton.textContent = originalText + ' (' + redirectTime + 's)';
                redirectTime--;
            } else {
                clearInterval(redirectInterval);
                window.location.href = 'recorrido.php?id=$tour_id';
            }
        }, 1000);
        
        // Cancelar auto-redirect si el usuario interactúa
        document.addEventListener('click', () => {
            if (redirectInterval) {
                clearInterval(redirectInterval);
                redirectButton.textContent = originalText;
                redirectTime = 0;
            }
        });
        
        document.addEventListener('scroll', () => {
            if (redirectInterval) {
                clearInterval(redirectInterval);
                redirectButton.textContent = originalText;
                redirectTime = 0;
            }
        });
    }
    
    // Mostrar notificación de éxito
    document.addEventListener('DOMContentLoaded', function() {
        " . ($from_auto_save ? 
            "showToast('Progreso guardado automáticamente', 'success', 3000);" : 
            "showToast('Borrador guardado correctamente', 'success', 4000);"
        ) . "
        
        // Animación de entrada
        document.querySelector('.draft-saved-card').classList.add('fade-in');
        
        setTimeout(() => {
            document.querySelector('.draft-info-card').classList.add('fade-in');
        }, 200);
        
        setTimeout(() => {
            document.querySelector('.draft-actions').classList.add('fade-in');
        }, 400);
    });
    
    // Funcionalidad de compartir progreso
    function shareProgress() {
        const progress = {$recorrido['porcentaje_completado']};
        const location = '" . addslashes($recorrido['location']) . "';
        const text = `Progreso del recorrido en \${location}: \${progress}% completado`;
        
        if (navigator.share) {
            navigator.share({
                title: 'Progreso de Recorrido de Seguridad',
                text: text,
                url: window.location.href
            });
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('Progreso copiado al portapapeles', 'success');
            });
        }
    }
";

include 'includes/footer.php';
?>