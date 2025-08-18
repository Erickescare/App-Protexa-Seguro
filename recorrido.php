<?php
// app-protexa-seguro/recorrido.php
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
$total_steps = count($categorias);

// Configurar variables para templates
$page_title = APP_NAME . ' - Recorrido ' . ucfirst($recorrido['tour_type']);
$page_description = 'Recorrido de Seguridad - ' . $recorrido['location'];
$theme_color = $is_emergency ? '#dc2626' : '#2563eb';
$show_back_button = true;
$back_url = 'dashboard.php';
$header_title = 'Recorrido ' . ($is_emergency ? 'de Emergencia' : 'Programado');
$show_footer_stats = false;
$no_cache = true; // Importante para evitar cache

// CSS y JS adicionales con cache busting mejorado
$additional_css = ['assets/css/app.css?t=' . time()];
$additional_scripts = ['assets/js/app.js?t=' . time()];

// Variables especiales para emergency mode
$body_class = $is_emergency ? 'emergency-mode' : '';

include 'includes/head.php';
include 'includes/header.php';
?>

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
    <div id="tour-form-container" class="tour-form-container">
        <input type="hidden" id="tour_id" value="<?php echo $tour_id; ?>">
        
        <?php foreach ($categorias as $cat_index => $categoria): ?>
        <div class="wizard-step" data-step="<?php echo $cat_index; ?>" style="<?php echo $cat_index === 1 ? 'display: block;' : 'display: none;'; ?>">
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
                    
                    <!-- Photo Upload -->
                    <div class="photo-upload" data-question-id="<?php echo $pregunta_key; ?>">
                        <input type="file" 
                               id="photo_<?php echo $pregunta_key; ?>" 
                               name="photos[<?php echo $pregunta_key; ?>][]" 
                               accept="image/*" 
                               multiple 
                               capture="environment"
                               data-question-id="<?php echo $pregunta_key; ?>">
                        <div class="photo-upload-icon">📷</div>
                        <p>Toca para agregar fotos</p>
                        <small>Máximo 5 fotos por pregunta</small>
                        
                        <div class="photo-preview" data-question-id="<?php echo $pregunta_key; ?>">
                            <!-- Photos will be added here by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Comments -->
                    <div class="comments-section">
                        <label for="comment_<?php echo $pregunta_key; ?>">Comentarios (opcional)</label>
                        <textarea id="comment_<?php echo $pregunta_key; ?>" 
                                  name="comments[<?php echo $pregunta_key; ?>]" 
                                  placeholder="Agrega observaciones o detalles adicionales..."
                                  rows="3"><?php echo $respuesta_existente ? htmlspecialchars($respuesta_existente['comentarios']) : ''; ?></textarea>
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
        <?php endforeach; ?>
        
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
    </div>
    
    <!-- Navigation -->
    <div class="wizard-navigation">
        <button type="button" class="btn btn-secondary nav-prev" style="display: none;">
            <span class="btn-text">← Anterior</span>
        </button>
        
        <button type="button" class="btn btn-outline nav-save-draft">
            <span class="btn-text">💾 Guardar Borrador</span>
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

<!-- Camera Modal -->
<div id="camera-modal" class="modal" style="display: none;">
    <div class="modal-content camera-modal-content">
        <div class="modal-header">
            <h2>Tomar Foto</h2>
            <button type="button" class="modal-close" onclick="closeCamera()">&times;</button>
        </div>
        
        <div class="camera-container">
            <video id="camera-video" autoplay playsinline></video>
            <div class="camera-controls">
                <button type="button" class="btn btn-primary" onclick="capturePhoto()">
                    📷 Capturar
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeCamera()">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Script específico para el formulario del recorrido
$inline_scripts = "
    // Cache busting y debugging
    console.log('🔧 Recorrido.php cargado - Timestamp: ' + Date.now());
    console.log('📋 Datos del tour:', {
        id: " . $tour_id . ",
        type: '" . $recorrido['tour_type'] . "',
        totalSteps: " . $total_steps . ",
        isEmergency: " . ($is_emergency ? 'true' : 'false') . ",
        categories: " . json_encode(array_values($categorias)) . "
    });

    // Forzar recarga de scripts si hay cache
    if (window.protexaApp) {
        console.log('♻️ Reinicializando app para evitar cache...');
        delete window.protexaApp;
    }

    // Configuración global del tour
    window.tourData = {
        id: " . $tour_id . ",
        type: '" . $recorrido['tour_type'] . "',
        totalSteps: " . $total_steps . ",
        isEmergency: " . ($is_emergency ? 'true' : 'false') . ",
        categories: " . json_encode(array_values($categorias)) . "
    };

    document.addEventListener('DOMContentLoaded', function() {
        console.log('📱 DOM cargado, inicializando wizard...');
        
        // Fix para el FormData error - Crear un form virtual si es necesario
        if (!document.querySelector('form')) {
            console.log('⚠️ No se encontró form, creando contenedor virtual...');
            const formContainer = document.getElementById('tour-form-container');
            if (formContainer) {
                formContainer.setAttribute('data-is-form', 'true');
            }
        }
        
        // Inicializar wizard manualmente si la clase no se carga
        if (typeof ProtexaApp !== 'undefined') {
            console.log('✅ ProtexaApp encontrada, inicializando...');
            window.protexaApp = new ProtexaApp();
        } else {
            console.log('⚠️ ProtexaApp no cargada, esperando...');
            // Retry en caso de cache
            setTimeout(() => {
                if (typeof ProtexaApp !== 'undefined') {
                    console.log('✅ ProtexaApp cargada en retry, inicializando...');
                    window.protexaApp = new ProtexaApp();
                } else {
                    console.error('❌ Error: ProtexaApp no se pudo cargar');
                    showToast('Error cargando la aplicación. Por favor recarga la página.', 'error');
                }
            }, 1000);
        }
        
        // Handle priority level changes
        document.querySelectorAll('input[name=\"priority_level\"]').forEach(radio => {
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
        
        // Debug: Verificar elementos del DOM
        console.log('🔍 Elementos encontrados:', {
            steps: document.querySelectorAll('.wizard-step').length,
            questions: document.querySelectorAll('.question-card').length,
            navigation: document.querySelectorAll('.wizard-navigation button').length,
            tourContainer: !!document.getElementById('tour-form-container')
        });
    });
    
    // Función de emergencia para limpiar cache
    function clearAppCache() {
        if ('caches' in window) {
            caches.keys().then(names => {
                names.forEach(name => {
                    caches.delete(name);
                });
            });
        }
        localStorage.clear();
        sessionStorage.clear();
        location.reload(true);
    }
    
    // Shortcut para debug: Ctrl+Alt+R
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.altKey && e.key === 'r') {
            console.log('🧹 Limpiando cache...');
            clearAppCache();
        }
    });
";

include 'includes/footer.php';
?>