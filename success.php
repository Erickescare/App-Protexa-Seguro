<?php
// app-protexa-seguro/success.php
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

// Obtener el último recorrido completado del usuario
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT r.*, 
               COUNT(f.id) as total_fotos,
               COUNT(hc.id) as hallazgos_criticos
        FROM " . getTableName('recorridos') . " r
        LEFT JOIN " . getTableName('fotos_recorrido') . " f ON r.id = f.recorrido_id
        LEFT JOIN " . getTableName('hallazgos_criticos') . " hc ON r.id = hc.recorrido_id
        WHERE r.user_id = ? AND r.status = 'completed'
        GROUP BY r.id
        ORDER BY r.completed_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $recorrido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recorrido) {
        header('Location: dashboard.php');
        exit;
    }
    
    // Obtener estadísticas del usuario
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_recorridos
        FROM " . getTableName('recorridos') . " 
        WHERE user_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    header('Location: dashboard.php?error=database');
    exit;
}

$porcentaje_completado = $recorrido['total_questions'] > 0 
    ? round(($recorrido['answered_questions'] / $recorrido['total_questions']) * 100) 
    : 0;
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="description" content="Recorrido Completado - <?php echo PWA_DESCRIPTION; ?>">
    <meta name="theme-color" content="<?php echo $recorrido['tour_type'] === 'emergency' ? '#dc2626' : '#059669'; ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo PWA_SHORT_NAME; ?>">
    
    <title><?php echo APP_NAME; ?> - Recorrido Completado</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Iconos -->
    <link rel="apple-touch-icon" href="assets/images/icon-152x152.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/icon-32x32.png">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="<?php echo APP_NAME; ?>" class="app-logo" onerror="this.style.display='none'">
                <h1 class="app-title"><?php echo PWA_SHORT_NAME; ?></h1>
            </div>
        </header>

        <!-- Main Content -->
        <main class="dashboard-main">
            <!-- Success Message -->
            <div class="completion-card">
                <div class="completion-icon">🎉</div>
                <h2>¡Recorrido Completado Exitosamente!</h2>
                <p>Tu inspección de seguridad ha sido registrada correctamente</p>
            </div>

            <!-- Recorrido Summary -->
            <div class="summary-card">
                <div class="summary-header">
                    <h2>Resumen del Recorrido</h2>
                    <div class="summary-badge <?php echo $recorrido['tour_type']; ?>">
                        <?php echo $recorrido['tour_type'] === 'emergency' ? 'Emergencia' : 'Programado'; ?>
                    </div>
                </div>
                
                <!-- Información básica -->
                <div class="tour-summary-info">
                    <div class="info-row">
                        <span class="info-label">Ubicación:</span>
                        <span class="info-value"><?php echo htmlspecialchars($recorrido['location']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">División:</span>
                        <span class="info-value"><?php echo htmlspecialchars($recorrido['division']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Fecha de inicio:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($recorrido['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Fecha de finalización:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($recorrido['completed_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Duración:</span>
                        <span class="info-value">
                            <?php 
                            $inicio = new DateTime($recorrido['created_at']);
                            $fin = new DateTime($recorrido['completed_at']);
                            $duracion = $inicio->diff($fin);
                            echo $duracion->format('%H:%I:%S');
                            ?>
                        </span>
                    </div>
                </div>
                
                <!-- Estadísticas -->
                <div class="summary-stats">
                    <div class="summary-stat">
                        <span class="summary-stat-value"><?php echo $porcentaje_completado; ?>%</span>
                        <span class="summary-stat-label">Completado</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-stat-value"><?php echo $recorrido['yes_count']; ?></span>
                        <span class="summary-stat-label">Sí</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-stat-value"><?php echo $recorrido['no_count']; ?></span>
                        <span class="summary-stat-label">No</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-stat-value"><?php echo $recorrido['na_count']; ?></span>
                        <span class="summary-stat-label">N/A</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-stat-value"><?php echo $recorrido['total_fotos']; ?></span>
                        <span class="summary-stat-label">Fotos</span>
                    </div>
                </div>
                
                <!-- Alertas si hay hallazgos -->
                <?php if ($recorrido['hallazgos_criticos'] > 0): ?>
                <div class="alert-section">
                    <div class="alert alert-warning">
                        <h3>⚠️ Hallazgos Críticos Detectados</h3>
                        <p>Se encontraron <strong><?php echo $recorrido['hallazgos_criticos']; ?></strong> hallazgos que requieren atención urgente.</p>
                        <?php if ($recorrido['tour_type'] === 'emergency'): ?>
                        <p><strong>Nota:</strong> Al ser un recorrido de emergencia, se han enviado notificaciones automáticas a los responsables.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Mensaje de éxito personalizado -->
                <div class="success-message">
                    <?php if ($recorrido['tour_type'] === 'emergency'): ?>
                        <h3>🚨 Recorrido de Emergencia Registrado</h3>
                        <p>Su reporte de emergencia ha sido procesado. Los responsables de seguridad han sido notificados automáticamente y revisarán la situación de inmediato.</p>
                    <?php else: ?>
                        <h3>✅ Inspección de Rutina Completada</h3>
                        <p>Su inspección programada ha sido registrada exitosamente. Los datos serán revisados como parte del programa regular de seguridad.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- User Stats -->
            <div class="user-stats-card">
                <h3>📊 Tus Estadísticas</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['total_recorridos']; ?></span>
                        <span class="stat-label">Recorridos Totales</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $recorrido['answered_questions']; ?></span>
                        <span class="stat-label">Preguntas Respondidas</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $recorrido['total_fotos']; ?></span>
                        <span class="stat-label">Fotos Capturadas</span>
                    </div>
                </div>
            </div>
            
            <!-- Next Steps -->
            <div class="next-steps-card">
                <h3>🎯 Próximos Pasos</h3>
                <div class="steps-list">
                    <div class="step-item">
                        <span class="step-icon">📋</span>
                        <div class="step-content">
                            <h4>Seguimiento</h4>
                            <p>El equipo de seguridad revisará tu reporte y tomará las acciones necesarias.</p>
                        </div>
                    </div>
                    
                    <?php if ($recorrido['no_count'] > 0): ?>
                    <div class="step-item">
                        <span class="step-icon">🔧</span>
                        <div class="step-content">
                            <h4>Acciones Correctivas</h4>
                            <p>Se programarán las correcciones necesarias para los <?php echo $recorrido['no_count']; ?> hallazgos identificados.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="step-item">
                        <span class="step-icon">📧</span>
                        <div class="step-content">
                            <h4>Notificación</h4>
                            <p>Recibirás confirmación por email una vez que se complete la revisión.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="success-actions">
                <a href="recorrido.php?id=<?php echo $recorrido['id']; ?>&view=1" class="btn btn-outline btn-block">
                    📄 Ver Reporte Completo
                </a>
                
                <a href="dashboard.php" class="btn btn-primary btn-block">
                    🏠 Volver al Dashboard
                </a>
                
                <button onclick="shareResults()" class="btn btn-secondary btn-block">
                    📤 Compartir Resultados
                </button>
            </div>
            
            <!-- Tips -->
            <div class="tips-card">
                <h3>💡 ¿Sabías que...?</h3>
                <div class="tip-content">
                    <?php if ($recorrido['tour_type'] === 'emergency'): ?>
                        <p>Los recorridos de emergencia son procesados con prioridad alta y generan alertas automáticas al equipo de seguridad.</p>
                    <?php else: ?>
                        <p>Los recorridos programados regulares ayudan a mantener un ambiente de trabajo seguro y prevenir accidentes.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="app-footer">
            <p>&copy; 2024 Protexa. Todos los derechos reservados.</p>
            <p class="version">Versión <?php echo APP_VERSION; ?></p>
        </footer>
    </div>

    <!-- Offline Indicator -->
    <div id="offline-indicator" class="offline-indicator" style="display: none;">
        <span class="offline-icon">📡</span>
        <span class="offline-text">Sin conexión - Trabajando offline</span>
    </div>

    <!-- Scripts -->
    <script src="assets/js/app.js"></script>
    <script>
        // Datos del recorrido completado
        const tourResults = {
            id: <?php echo $recorrido['id']; ?>,
            type: '<?php echo $recorrido['tour_type']; ?>',
            location: '<?php echo addslashes($recorrido['location']); ?>',
            completion: <?php echo $porcentaje_completado; ?>,
            questions: {
                total: <?php echo $recorrido['total_questions']; ?>,
                answered: <?php echo $recorrido['answered_questions']; ?>,
                yes: <?php echo $recorrido['yes_count']; ?>,
                no: <?php echo $recorrido['no_count']; ?>,
                na: <?php echo $recorrido['na_count']; ?>
            },
            photos: <?php echo $recorrido['total_fotos']; ?>,
            criticalFindings: <?php echo $recorrido['hallazgos_criticos']; ?>,
            duration: '<?php 
                $inicio = new DateTime($recorrido['created_at']);
                $fin = new DateTime($recorrido['completed_at']);
                echo $inicio->diff($fin)->format('%H:%I:%S');
            ?>'
        };

        // Función para compartir resultados
        function shareResults() {
            if (navigator.share) {
                navigator.share({
                    title: 'Recorrido de Seguridad Completado',
                    text: `Completé un recorrido de seguridad en ${tourResults.location} con ${tourResults.completion}% de finalización.`,
                    url: window.location.href
                }).catch(console.error);
            } else {
                // Fallback: copiar al portapapeles
                const text = `Recorrido de Seguridad Completado:\n- Ubicación: ${tourResults.location}\n- Completado: ${tourResults.completion}%\n- Preguntas: ${tourResults.questions.answered}/${tourResults.questions.total}\n- Fotos: ${tourResults.photos}`;
                
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(() => {
                        alert('Resultados copiados al portapapeles');
                    });
                } else {
                    alert('Función de compartir no disponible en este dispositivo');
                }
            }
        }

        // Celebración automática si es un recorrido perfecto
        document.addEventListener('DOMContentLoaded', function() {
            if (tourResults.completion === 100 && tourResults.criticalFindings === 0) {
                // Agregar confetti o animación de celebración
                createConfetti();
            }
        });

        function createConfetti() {
            // Simple confetti effect
            for (let i = 0; i < 50; i++) {
                createConfettiPiece();
            }
        }

        function createConfettiPiece() {
            const confetti = document.createElement('div');
            confetti.style.position = 'fixed';
            confetti.style.top = '-10px';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.width = '10px';
            confetti.style.height = '10px';
            confetti.style.backgroundColor = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ffc93c'][Math.floor(Math.random() * 5)];
            confetti.style.zIndex = '9999';
            confetti.style.pointerEvents = 'none';
            confetti.style.animation = 'confettiFall 3s linear forwards';
            
            document.body.appendChild(confetti);
            
            setTimeout(() => {
                confetti.remove();
            }, 3000);
        }

        // CSS Animation for confetti
        if (!document.querySelector('#confetti-style')) {
            const style = document.createElement('style');
            style.id = 'confetti-style';
            style.textContent = `
                @keyframes confettiFall {
                    to {
                        transform: translateY(100vh) rotate(360deg);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    </script>
</body>
</html>