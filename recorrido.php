<?php
// app-protexa-seguro/recorrido.php - VERSION MEJORADA
require_once 'config.php';
setSecurityHeaders();

// Verificar autenticación
if (!checkWPAuth()) {
    header('Location: index.php');
    exit;
}

// Obtener datos del usuario
$user = getWPUser();
if (!$user) {
    header('Location: index.php');
    exit;
}

// Obtener ID del recorrido
$tour_id = $_GET['id'] ?? null;
if (!$tour_id) {
    header('Location: dashboard.php');
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Verificar que el recorrido existe y pertenece al usuario
    $stmt = $pdo->prepare("
        SELECT * FROM " . getTableName('recorridos') . " 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$tour_id, $user['id']]);
    $recorrido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recorrido) {
        header('Location: dashboard.php?error=not_found');
        exit;
    }
    
    // Obtener preguntas configuradas
    $stmt = $pdo->prepare("
        SELECT * FROM " . getTableName('configuracion_preguntas') . " 
        WHERE activa = 1 
        ORDER BY categoria_id, orden_pregunta
    ");
    $stmt->execute();
    $preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar preguntas por categoría
    $categorias = [];
    foreach ($preguntas as $pregunta) {
        $cat_id = $pregunta['categoria_id'];
        if (!isset($categorias[$cat_id])) {
            $categorias[$cat_id] = [
                'id' => $cat_id,
                'nombre' => $pregunta['categoria_nombre'],
                'preguntas' => []
            ];
        }
        $categorias[$cat_id]['preguntas'][] = $pregunta;
    }
    
    // Obtener respuestas existentes
    $stmt = $pdo->prepare("
        SELECT * FROM " . getTableName('respuestas_categorias') . " 
        WHERE recorrido_id = ?
    ");
    $stmt->execute([$tour_id]);
    $respuestas_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Indexar respuestas por pregunta
    $respuestas_map = [];
    foreach ($respuestas_existentes as $respuesta) {
        $key = $respuesta['categoria_id'] . '_' . $respuesta['pregunta_numero'];
        $respuestas_map[$key] = $respuesta;
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Determinar si es un recorrido de emergencia
$is_emergency = $recorrido['tour_type'] === 'emergency';
$total_steps = count($categorias) + 1; // +1 para la sección de fotos/resumen
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="description" content="Recorrido de Seguridad - <?php echo PWA_DESCRIPTION; ?>">
    <meta name="theme-color" content="<?php echo $is_emergency ? '#dc2626' : '#2563eb'; ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo PWA_SHORT_NAME; ?>">
    
    <title><?php echo APP_NAME; ?> - Recorrido <?php echo ucfirst($recorrido['tour_type']); ?></title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Iconos -->
    <link rel="apple-touch-icon" href="assets/images/icon-152x152.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/icon-32x32.png">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/app.css">
    
    <style>
        <?php if ($is_emergency): ?>
        .app-header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }
        .wizard-title {
            color: #dc2626;
        }
        .emergency-mode .category-header {
            background: linear-gradient(135deg, #dc2626, #ef4444);
        }
        <?php endif; ?>
    </style>
</head>
<body <?php echo $is_emergency ? 'class="emergency-mode"' : ''; ?>>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="header-content">
                <div class="header-back">
                    <a href="dashboard.php" class="back-button">← Dashboard</a>
                </div>
                <div class="logo-container">
                    <img src="assets/images/logo.png" alt="<?php echo APP_NAME; ?>" class="app-logo" onerror="this.style.display='none'">
                    <h1 class="app-title"><?php echo PWA_SHORT_NAME; ?></h1>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="wizard-container">
            <!-- Wizard Header -->
            <div class="wizard-header">
                <!-- Progress Bar -->
                <div class="wizard-progress">
                    <?php for ($i = 1; $i <= $total_steps; $i++): ?>
                        <div class="progress-step" data-step="<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </div>
                        <?php if ($i < $total_steps): ?>
                            <div class="progress-connector"></div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                
                <h1 class="wizard-title">
                    Recorrido <?php echo $is_emergency ? 'de Emergencia' : 'Programado'; ?>
                </h1>
                <p class="wizard-subtitle">
                    <?php echo htmlspecialchars($recorrido['location']); ?> - 
                    <?php echo htmlspecialchars($recorrido['division']); ?>
                </p>
            </div>

            <!-- Tour Info -->
            <div class="tour-info">
                <div class="info-card">
                    <div class="info-row">
                        <span class="info-label">Ubicación:</span>
                        <span class="info-value"><?php echo htmlspecialchars($recorrido['location']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">División:</span>
                        <span class="info-value"><?php echo htmlspecialchars($recorrido['division']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Negocio:</span>
                        <span class="info-value"><?php echo htmlspecialchars($recorrido['business']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Motivo:</span>
                        <span class="info-value"><?php echo htmlspecialchars($recorrido['reason']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Inspector:</span>
                        <span class="info-value"><?php echo htmlspecialchars($recorrido['user_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Fecha:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($recorrido['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Wizard Steps -->
            <form id="tour-form" class="tour-form">
                <input type="hidden" name="tour_id" value="<?php echo $tour_id; ?>">
                
                <?php $step_index = 1; ?>
                <?php foreach ($categorias as $cat_index => $categoria): ?>
                <div class="wizard-step" data-step="<?php echo $step_index; ?>" style="<?php echo $step_index === 1 ? 'display: block;' : 'display: none;'; ?>">
                    <div class="category-header">
                        <h2><?php echo $categoria['id']; ?>. <?php echo htmlspecialchars($categoria['nombre']); ?></h2>
                        <p>Responde todas las preguntas de esta categoría</p>
                    </div>
                    
                    <?php foreach ($categoria['preguntas'] as $pregunta): ?>
                        <?php 
                        $pregunta_key = $categoria['id'] . '_' . $pregunta['pregunta_numero'];
                        $respuesta_existente = $respuestas_map[$pregunta_key] ?? null;
                        ?>
                        
                        <div class="question-card" data-question-id="<?php echo $pregunta_key; ?>">
                            <div class="question-header">
                                <div class="question-number"><?php echo $pregunta['pregunta_numero']; ?></div>
                                <div class="question-text"><?php echo htmlspecialchars($pregunta['pregunta_texto']); ?></div>
                            </div>
                            
                            <div class="answer-options">
                                <div class="answer-option answer-yes">
                                    <input type="radio" 
                                           id="<?php echo $pregunta_key; ?>_si" 
                                           name="<?php echo $pregunta_key; ?>" 
                                           value="si"
                                           <?php echo ($respuesta_existente && $respuesta_existente['respuesta'] === 'si') ? 'checked' : ''; ?>>
                                    <label for="<?php echo $pregunta_key; ?>_si">
                                        <div class="answer-option-indicator"></div>
                                        <span>Sí</span>
                                    </label>
                                </div>
                                
                                <div class="answer-option answer-no">
                                    <input type="radio" 
                                           id="<?php echo $pregunta_key; ?>_no" 
                                           name="<?php echo $pregunta_key; ?>" 
                                           value="no"
                                           <?php echo ($respuesta_existente && $respuesta_existente['respuesta'] === 'no') ? 'checked' : ''; ?>>
                                    <label for="<?php echo $pregunta_key; ?>_no">
                                        <div class="answer-option-indicator"></div>
                                        <span>No</span>
                                    </label>
                                </div>
                                
                                <div class="answer-option answer-na">
                                    <input type="radio" 
                                           id="<?php echo $pregunta_key; ?>_na" 
                                           name="<?php echo $pregunta_key; ?>" 
                                           value="na"
                                           <?php echo ($respuesta_existente && $respuesta_existente['respuesta'] === 'na') ? 'checked' : ''; ?>>
                                    <label for="<?php echo $pregunta_key; ?>_na">
                                        <div class="answer-option-indicator"></div>
                                        <span>N/A</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Category Comments -->
                    <div class="category-comments">
                        <h3>Comentarios generales - <?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                        <textarea name="category_comments[<?php echo $categoria['id']; ?>]" 
                                  placeholder="Comentarios generales sobre esta categoría..."
                                  rows="4"></textarea>
                    </div>
                </div>
                <?php $step_index++; ?>
                <?php endforeach; ?>
                
                <!-- Fotos y Adjuntos Step -->
                <div class="wizard-step" data-step="<?php echo $step_index; ?>" style="display: none;">
                    <div class="category-header">
                        <h2>📷 Fotos y Evidencias</h2>
                        <p>Agrega fotos que documenten los hallazgos encontrados</p>
                    </div>
                    
                    <!-- Photo Upload por cada categoría que tuvo respuestas "No" -->
                    <?php foreach ($categorias as $categoria): ?>
                    <div class="photo-section">
                        <h3><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                        <div class="photo-upload" data-category-id="<?php echo $categoria['id']; ?>">
                            <input type="file" 
                                   id="photo_cat_<?php echo $categoria['id']; ?>" 
                                   name="photos[categoria_<?php echo $categoria['id']; ?>][]" 
                                   accept="image/*" 
                                   multiple 
                                   capture="environment"
                                   data-category-id="<?php echo $categoria['id']; ?>">
                            <div class="photo-upload-icon">📷</div>
                            <p>Toca para agregar fotos de esta categoría</p>
                            <small>Máximo 5 fotos por categoría</small>
                            
                            <div class="photo-preview" data-category-id="<?php echo $categoria['id']; ?>">
                                <!-- Photos will be added here by JavaScript -->
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php $step_index++; ?>
                
                <!-- Summary Step -->
                <div class="wizard-step wizard-summary" style="display: none;">
                    <div class="completion-card">
                        <div class="completion-icon">✅</div>
                        <h2>¡Recorrido Completado!</h2>
                        <p>Revisa el resumen antes de enviar el recorrido</p>
                    </div>
                    
                    <div class="summary-card">
                        <h3>Resumen del Recorrido</h3>
                        <div id="summary-content">
                            <!-- Summary will be generated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Critical Findings -->
                    <div class="critical-findings">
                        <h3>¿Hallazgos críticos?</h3>
                        <p>Selecciona el nivel de prioridad si encontraste hallazgos que requieren atención urgente:</p>
                        
                        <div class="priority-options">
                            <div class="priority-option">
                                <input type="radio" id="priority_none" name="priority_level" value="none" checked>
                                <label for="priority_none">
                                    <span class="priority-indicator none"></span>
                                    Sin hallazgos críticos
                                </label>
                            </div>
                            
                            <div class="priority-option">
                                <input type="radio" id="priority_low" name="priority_level" value="baja">
                                <label for="priority_low">
                                    <span class="priority-indicator low"></span>
                                    Prioridad Baja
                                </label>
                            </div>
                            
                            <div class="priority-option">
                                <input type="radio" id="priority_medium" name="priority_level" value="media">
                                <label for="priority_medium">
                                    <span class="priority-indicator medium"></span>
                                    Prioridad Media
                                </label>
                            </div>
                            
                            <div class="priority-option">
                                <input type="radio" id="priority_high" name="priority_level" value="alta">
                                <label for="priority_high">
                                    <span class="priority-indicator high"></span>
                                    Prioridad Alta
                                </label>
                            </div>
                        </div>
                        
                        <div class="critical-description" style="display: none;">
                            <label for="critical_description">Descripción de hallazgos críticos</label>
                            <textarea id="critical_description" 
                                      name="critical_description" 
                                      placeholder="Describe los hallazgos críticos encontrados..."
                                      rows="4"></textarea>
                        </div>
                    </div>
                    
                    <!-- Final Comments -->
                    <div class="final-comments">
                        <h3>Comentarios finales</h3>
                        <textarea name="final_comments" 
                                  placeholder="Comentarios generales del recorrido completo..."
                                  rows="4"></textarea>
                    </div>
                </div>
            </form>
            
            <!-- Navigation -->
            <div class="wizard-navigation">
                <button type="button" class="btn btn-secondary nav-prev" style="display: none;">
                    <span class="btn-text">← Anterior</span>
                </button>
                
                <button type="button" class="btn btn-outline nav-save-draft">
                    <span class="btn-text">💾 Guardar Borrador</span>
                    <span class="btn-loading" style="display: none;">
                        <span class="spinner"></span>
                        Guardando...
                    </span>
                </button>
                
                <button type="button" class="btn btn-primary nav-next">
                    <span class="btn-text">Siguiente →</span>
                    <span class="btn-loading" style="display: none;">
                        <span class="spinner"></span>
                        Cargando...
                    </span>
                </button>
                
                <button type="button" class="btn btn-success nav-submit" style="display: none;">
                    <span class="btn-text">✓ Enviar Recorrido</span>
                    <span class="btn-loading" style="display: none;">
                        <span class="spinner"></span>
                        Enviando...
                    </span>
                </button>
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
    <script>
        // Pass PHP data to JavaScript
        window.tourData = {
            id: <?php echo $tour_id; ?>,
            type: '<?php echo $recorrido['tour_type']; ?>',
            totalSteps: <?php echo $total_steps; ?>,
            isEmergency: <?php echo $is_emergency ? 'true' : 'false'; ?>,
            categories: <?php echo json_encode(array_values($categorias)); ?>,
            userId: <?php echo $user['id']; ?>,
            userName: '<?php echo addslashes($user['display_name']); ?>'
        };
    </script>
    <script src="assets/js/app.js"></script>
    <script>
        // Custom initialization for tour form
        document.addEventListener('DOMContentLoaded', function() {
            // Handle priority level changes
            document.querySelectorAll('input[name="priority_level"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const description = document.querySelector('.critical-description');
                    if (this.value !== 'none') {
                        description.style.display = 'block';
                        description.querySelector('textarea').required = true;
                    } else {
                        description.style.display = 'none';
                        description.querySelector('textarea').required = false;
                    }
                });
            });
            
            // Auto-resize textareas
            document.querySelectorAll('textarea').forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });
        });
    </script>
</body>
</html>