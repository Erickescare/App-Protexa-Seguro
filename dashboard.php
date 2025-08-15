<?php
// app-protexa-seguro/dashboard.php
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

// Manejar acciones del dashboard
$action = $_GET['action'] ?? '';
$notification = '';
$notification_type = '';

// Si se selecciona un tipo de recorrido
if ($_POST && isset($_POST['start_tour'])) {
    $tour_type = $_POST['tour_type'];
    $location = $_POST['location'] ?? '';
    $division = $_POST['division'] ?? '';
    $business = $_POST['business'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    // Crear nuevo recorrido en la base de datos
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO " . getTableName('recorridos') . " (
                user_id, user_name, tour_type, location, division, 
                business, reason, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'in_progress', NOW())
        ");
        
        $stmt->execute([
            $user['id'], 
            $user['display_name'], 
            $tour_type, 
            $location, 
            $division, 
            $business, 
            $reason
        ]);
        
        $tour_id = $pdo->lastInsertId();
        
        // Redirigir al formulario de recorrido
        header("Location: recorrido.php?id={$tour_id}");
        exit;
        
    } catch (Exception $e) {
        $notification = 'Error al crear el recorrido: ' . $e->getMessage();
        $notification_type = 'error';
    }
}

// Obtener estadísticas del usuario
try {
    $pdo = getDBConnection();
    
    // Recorridos del mes actual
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_mes 
        FROM " . getTableName('recorridos') . " 
        WHERE user_id = ? 
        AND MONTH(created_at) = MONTH(NOW()) 
        AND YEAR(created_at) = YEAR(NOW())
    ");
    $stmt->execute([$user['id']]);
    $stats_mes = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Último recorrido
    $stmt = $pdo->prepare("
        SELECT tour_type, location, created_at, status 
        FROM " . getTableName('recorridos') . " 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $ultimo_recorrido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Borradores pendientes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as borradores_pendientes
        FROM " . getTableName('recorridos') . " 
        WHERE user_id = ? AND status = 'draft'
    ");
    $stmt->execute([$user['id']]);
    $borradores_pendientes = $stmt->fetchColumn();
    
    // Recorridos completados
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as completados_total
        FROM " . getTableName('recorridos') . " 
        WHERE user_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user['id']]);
    $completados_total = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $stats_mes = ['total_mes' => 0];
    $ultimo_recorrido = null;
    $borradores_pendientes = 0;
    $completados_total = 0;
}

// Configurar variables para templates
$page_title = APP_NAME . ' - Dashboard';
$page_description = 'Panel principal de recorridos de seguridad';
$header_title = PWA_SHORT_NAME;
$show_footer_stats = true;

include 'includes/head.php';
include 'includes/header.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="container">
        <!-- User Info -->
        <div class="user-info">
            <h2>¡Bienvenido, <?php echo htmlspecialchars($user['display_name']); ?>!</h2>
            <p>Usuario: <?php echo htmlspecialchars($user['username']); ?></p>
            <p>Recorridos este mes: <strong><?php echo $stats_mes['total_mes']; ?></strong></p>
            
            <?php if ($ultimo_recorrido): ?>
            <p class="last-tour">
                Último recorrido: <?php echo ucfirst($ultimo_recorrido['tour_type']); ?> 
                en <?php echo htmlspecialchars($ultimo_recorrido['location']); ?>
                (<?php echo date('d/m/Y H:i', strtotime($ultimo_recorrido['created_at'])); ?>)
            </p>
            <?php endif; ?>
        </div>

        <!-- Notification -->
        <?php if ($notification): ?>
        <div class="notification <?php echo $notification_type; ?>">
            <?php echo htmlspecialchars($notification); ?>
        </div>
        <?php endif; ?>
        
        <!-- Borradores Alert -->
        <?php if ($borradores_pendientes > 0): ?>
        <div class="notification info">
            <h3>📝 Tienes <?php echo $borradores_pendientes; ?> borrador(es) pendiente(s)</h3>
            <p>Continúa con los recorridos que dejaste a medias.</p>
            <a href="borradores.php" class="btn btn-sm btn-primary">Ver Borradores</a>
        </div>
        <?php endif; ?>

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1>¿Qué tipo de recorrido realizarás?</h1>
            <p>Selecciona el tipo de inspección de seguridad</p>
        </div>

        <!-- Action Cards -->
        <div class="action-cards">
            <!-- Recorrido de Emergencia -->
            <div class="action-card action-card-emergency" onclick="showTourForm('emergency')">
                <div class="action-card-icon">🚨</div>
                <h3>Recorrido de Emergencia</h3>
                <p>Para situaciones que requieren atención inmediata. Registro rápido de hallazgos críticos de seguridad.</p>
            </div>

            <!-- Recorrido Programado -->
            <div class="action-card action-card-scheduled" onclick="showTourForm('scheduled')">
                <div class="action-card-icon">📋</div>
                <h3>Recorrido Programado</h3>
                <p>Inspección completa de rutina. Evaluación detallada de todas las categorías de seguridad.</p>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <h3><?php echo $stats_mes['total_mes']; ?></h3>
                    <p>Recorridos este mes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3><?php echo $completados_total; ?></h3>
                    <p>Completados</p>
                </div>
            </div>
            
            <?php if ($borradores_pendientes > 0): ?>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-content">
                    <h3><?php echo $borradores_pendientes; ?></h3>
                    <p>Borradores</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="stat-card">
                <div class="stat-icon">⏱️</div>
                <div class="stat-content">
                    <h3><?php echo $ultimo_recorrido ? date('d/m', strtotime($ultimo_recorrido['created_at'])) : '--'; ?></h3>
                    <p>Último recorrido</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3>⚡ Acciones Rápidas</h3>
            <div class="quick-actions-grid">
                <a href="mis-recorridos.php" class="quick-action-item">
                    <div class="quick-action-icon">📋</div>
                    <div class="quick-action-text">Ver Historial</div>
                </a>
                
                <?php if ($borradores_pendientes > 0): ?>
                <a href="borradores.php" class="quick-action-item">
                    <div class="quick-action-icon">📝</div>
                    <div class="quick-action-text">Continuar Borrador</div>
                </a>
                <?php endif; ?>
                
                <a href="estadisticas.php" class="quick-action-item">
                    <div class="quick-action-icon">📊</div>
                    <div class="quick-action-text">Estadísticas</div>
                </a>
                
                <a href="ayuda.php" class="quick-action-item">
                    <div class="quick-action-icon">❓</div>
                    <div class="quick-action-text">Ayuda</div>
                </a>
            </div>
        </div>
    </div>
</main>

<!-- Modal para configurar recorrido -->
<div id="tour-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Configurar Recorrido</h2>
            <button type="button" class="modal-close" onclick="closeTourForm()">&times;</button>
        </div>
        
        <form method="post" class="tour-form">
            <input type="hidden" name="tour_type" id="tour_type" value="">
            
            <div class="form-group">
                <label for="location">Ubicación/Sitio *</label>
                <input type="text" id="location" name="location" required 
                       placeholder="Ej: Planta Norte, Oficinas Centrales, Almacén 3">
            </div>
            
            <div class="form-group">
                <label for="division">División a auditar *</label>
                <select id="division" name="division" required>
                    <option value="">Seleccionar división...</option>
                    <option value="produccion">Producción</option>
                    <option value="almacenes">Almacenes</option>
                    <option value="mantenimiento">Mantenimiento</option>
                    <option value="oficinas">Oficinas Administrativas</option>
                    <option value="seguridad">Seguridad</option>
                    <option value="calidad">Control de Calidad</option>
                    <option value="logistica">Logística</option>
                    <option value="todas">Todas las divisiones</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="business">Negocio a auditar *</label>
                <select id="business" name="business" required>
                    <option value="">Seleccionar negocio...</option>
                    <option value="manufactura">Manufactura</option>
                    <option value="distribucion">Distribución</option>
                    <option value="servicios">Servicios</option>
                    <option value="corporativo">Corporativo</option>
                    <option value="operaciones">Operaciones</option>
                </select>
            </div>
            
            <div class="form-group" id="reason-group">
                <label for="reason">Motivo del recorrido *</label>
                <select id="reason" name="reason" required>
                    <option value="">Seleccionar motivo...</option>
                    <!-- Opciones se llenarán con JavaScript según el tipo -->
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeTourForm()">
                    Cancelar
                </button>
                <button type="submit" name="start_tour" class="btn btn-primary">
                    <span class="btn-text">Iniciar Recorrido</span>
                    <span class="btn-loading" style="display: none;">
                        <span class="spinner"></span>
                        Iniciando...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Scripts específicos de esta página
$inline_scripts = "
    // Motivos según tipo de recorrido
    const tourReasons = {
        'emergency': [
            'Accidente reportado',
            'Condición insegura identificada',
            'Derrame o fuga',
            'Equipo de seguridad dañado',
            'Emergencia médica',
            'Incidente de seguridad',
            'Otro motivo de emergencia'
        ],
        'scheduled': [
            'Inspección rutinaria mensual',
            'Auditoria programada',
            'Seguimiento a hallazgos previos',
            'Inspección post-mantenimiento',
            'Verificación de cumplimiento',
            'Evaluación de nuevas instalaciones',
            'Inspección pre-evento especial',
            'Revisión anual de seguridad'
        ]
    };

    function showTourForm(tourType) {
        const modal = document.getElementById('tour-modal');
        const modalTitle = document.getElementById('modal-title');
        const tourTypeInput = document.getElementById('tour_type');
        const reasonSelect = document.getElementById('reason');
        
        // Configurar tipo de recorrido
        tourTypeInput.value = tourType;
        
        // Actualizar título y color
        if (tourType === 'emergency') {
            modalTitle.textContent = '🚨 Recorrido de Emergencia';
            modalTitle.style.color = '#dc2626';
            modal.querySelector('.modal-content').style.borderTop = '4px solid #dc2626';
        } else {
            modalTitle.textContent = '📋 Recorrido Programado';
            modalTitle.style.color = '#2563eb';
            modal.querySelector('.modal-content').style.borderTop = '4px solid #2563eb';
        }
        
        // Llenar opciones de motivo
        reasonSelect.innerHTML = '<option value=\"\">Seleccionar motivo...</option>';
        tourReasons[tourType].forEach(reason => {
            const option = document.createElement('option');
            option.value = reason.toLowerCase().replace(/\s+/g, '_');
            option.textContent = reason;
            reasonSelect.appendChild(option);
        });
        
        // Mostrar modal con animación
        modal.style.display = 'flex';
        modal.style.opacity = '0';
        document.body.style.overflow = 'hidden';
        
        // Animar entrada
        setTimeout(() => {
            modal.style.opacity = '1';
            modal.querySelector('.modal-content').style.transform = 'scale(1)';
        }, 10);
        
        // Focus en primer campo
        setTimeout(() => {
            document.getElementById('location').focus();
        }, 300);
    }

    function closeTourForm() {
        const modal = document.getElementById('tour-modal');
        
        // Animar salida
        modal.style.opacity = '0';
        modal.querySelector('.modal-content').style.transform = 'scale(0.9)';
        
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Limpiar formulario
            modal.querySelector('form').reset();
            modal.querySelector('.modal-content').style.borderTop = 'none';
        }, 200);
    }

    // Cerrar modal al hacer clic fuera
    document.getElementById('tour-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeTourForm();
        }
    });
    
    // Cerrar modal con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('tour-modal').style.display === 'flex') {
            closeTourForm();
        }
    });

    // Validación en tiempo real
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('.tour-form');
        const submitBtn = form.querySelector('button[type=\"submit\"]');
        
        // Validar formulario
        function validateForm() {
            const requiredFields = form.querySelectorAll('input[required], select[required]');
            let allValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    allValid = false;
                }
            });
            
            submitBtn.disabled = !allValid;
            return allValid;
        }
        
        // Escuchar cambios en el formulario
        form.addEventListener('input', validateForm);
        form.addEventListener('change', validateForm);
        
        // Manejar envío del formulario
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                showToast('Por favor completa todos los campos requeridos', 'error');
                return;
            }
            
            // Mostrar loading
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            
            btnText.style.display = 'none';
            btnLoading.style.display = 'flex';
            submitBtn.disabled = true;
        });
        
        // Auto-focus y acciones rápidas en URL
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        
        if (action === 'new') {
            showTourForm('scheduled');
        } else if (action === 'emergency') {
            showTourForm('emergency');
        }
        
        // Mostrar notificación de bienvenida si es primera visita
        if (!localStorage.getItem('dashboard_visited')) {
            setTimeout(() => {
                showToast('¡Bienvenido a Protexa Seguro! Selecciona un tipo de recorrido para comenzar.', 'info', 6000);
                localStorage.setItem('dashboard_visited', 'true');
            }, 1000);
        }
    });
";

include 'includes/footer.php';
?>