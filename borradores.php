<?php
// app-protexa-seguro/borradores.php
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

// Manejar acciones
$action = $_GET['action'] ?? '';
$message = '';
$message_type = '';

if ($action === 'delete' && isset($_GET['id'])) {
    $draft_id = intval($_GET['id']);
    
    try {
        $pdo = getDBConnection();
        
        // Verificar que el borrador pertenece al usuario
        $stmt = $pdo->prepare("
            SELECT id FROM " . getTableName('recorridos') . " 
            WHERE id = ? AND user_id = ? AND status = 'draft'
        ");
        $stmt->execute([$draft_id, $user['id']]);
        
        if ($stmt->fetch()) {
            // Eliminar borrador
            $stmt = $pdo->prepare("DELETE FROM " . getTableName('recorridos') . " WHERE id = ?");
            $stmt->execute([$draft_id]);
            
            $message = 'Borrador eliminado correctamente';
            $message_type = 'success';
        } else {
            $message = 'Borrador no encontrado';
            $message_type = 'error';
        }
    } catch (Exception $e) {
        $message = 'Error al eliminar borrador: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Obtener borradores del usuario
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT r.*, 
               TIMESTAMPDIFF(MINUTE, r.updated_at, NOW()) as minutos_transcurridos,
               CASE 
                   WHEN r.answered_questions > 0 THEN ROUND((r.answered_questions / r.total_questions) * 100)
                   ELSE 0 
               END as porcentaje_completado
        FROM " . getTableName('recorridos') . " r
        WHERE r.user_id = ? AND r.status = 'draft'
        ORDER BY r.updated_at DESC
    ");
    $stmt->execute([$user['id']]);
    $borradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $borradores = [];
    $message = 'Error al cargar borradores: ' . $e->getMessage();
    $message_type = 'error';
}

// Configurar variables para templates
$page_title = APP_NAME . ' - Mis Borradores';
$page_description = 'Gestiona tus recorridos guardados como borrador';
$show_back_button = true;
$back_url = 'dashboard.php';
$header_title = 'Mis Borradores';
$show_footer_stats = true;

include 'includes/head.php';
include 'includes/header.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">📝 Mis Borradores</h1>
            <p class="page-subtitle">Continúa con los recorridos que dejaste pendientes</p>
        </div>

        <!-- Notification -->
        <?php if ($message): ?>
        <div class="notification <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Borradores List -->
        <?php if (empty($borradores)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3>No tienes borradores guardados</h3>
                <p>Los recorridos que guardes como borrador aparecerán aquí para que puedas continuarlos después.</p>
                <a href="dashboard.php" class="btn btn-primary">
                    Iniciar Nuevo Recorrido
                </a>
            </div>
        <?php else: ?>
            <div class="borradores-grid">
                <?php foreach ($borradores as $borrador): ?>
                <div class="borrador-card">
                    <div class="borrador-header">
                        <div class="borrador-type">
                            <?php if ($borrador['tour_type'] === 'emergency'): ?>
                                <span class="type-badge emergency">🚨 Emergencia</span>
                            <?php else: ?>
                                <span class="type-badge scheduled">📋 Programado</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="borrador-actions">
                            <button class="action-btn delete" 
                                    onclick="confirmDelete(<?php echo $borrador['id']; ?>, '<?php echo addslashes($borrador['location']); ?>')">
                                🗑️
                            </button>
                        </div>
                    </div>
                    
                    <div class="borrador-content">
                        <h3 class="borrador-location"><?php echo htmlspecialchars($borrador['location']); ?></h3>
                        <p class="borrador-details">
                            <strong>División:</strong> <?php echo htmlspecialchars($borrador['division']); ?><br>
                            <strong>Motivo:</strong> <?php echo htmlspecialchars($borrador['reason']); ?>
                        </p>
                        
                        <div class="borrador-progress">
                            <div class="progress-info">
                                <span class="progress-text">
                                    Progreso: <?php echo $borrador['answered_questions']; ?>/<?php echo $borrador['total_questions']; ?> preguntas
                                </span>
                                <span class="progress-percentage"><?php echo $borrador['porcentaje_completado']; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $borrador['porcentaje_completado']; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="borrador-meta">
                            <div class="meta-item">
                                <span class="meta-label">Creado:</span>
                                <span class="meta-value"><?php echo date('d/m/Y H:i', strtotime($borrador['created_at'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Última edición:</span>
                                <span class="meta-value time-ago" data-time="<?php echo $borrador['minutos_transcurridos']; ?>">
                                    <?php 
                                    if ($borrador['minutos_transcurridos'] < 60) {
                                        echo 'Hace ' . $borrador['minutos_transcurridos'] . ' min';
                                    } elseif ($borrador['minutos_transcurridos'] < 1440) {
                                        echo 'Hace ' . floor($borrador['minutos_transcurridos'] / 60) . ' horas';
                                    } else {
                                        echo 'Hace ' . floor($borrador['minutos_transcurridos'] / 1440) . ' días';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="borrador-footer">
                        <a href="recorrido.php?id=<?php echo $borrador['id']; ?>" 
                           class="btn btn-primary btn-block">
                            ▶️ Continuar Recorrido
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Acciones masivas -->
            <div class="bulk-actions">
                <h3>Acciones</h3>
                <div class="bulk-buttons">
                    <button onclick="confirmDeleteAll()" class="btn btn-danger">
                        🗑️ Eliminar Todos los Borradores
                    </button>
                    <a href="dashboard.php" class="btn btn-primary">
                        ➕ Nuevo Recorrido
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirmar Eliminación</h2>
            <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <p>¿Estás seguro de que deseas eliminar el borrador de:</p>
            <p><strong id="delete-location"></strong></p>
            <p class="warning-text">⚠️ Esta acción no se puede deshacer.</p>
        </div>
        
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                Cancelar
            </button>
            <a id="delete-confirm-btn" href="#" class="btn btn-danger">
                Eliminar Borrador
            </a>
        </div>
    </div>
</div>

<?php
// Scripts específicos de esta página
$inline_scripts = "
    function confirmDelete(id, location) {
        document.getElementById('delete-location').textContent = location;
        document.getElementById('delete-confirm-btn').href = 'borradores.php?action=delete&id=' + id;
        document.getElementById('delete-modal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closeDeleteModal() {
        document.getElementById('delete-modal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    function confirmDeleteAll() {
        if (confirm('¿Estás seguro de que deseas eliminar TODOS los borradores? Esta acción no se puede deshacer.')) {
            window.location.href = 'borradores.php?action=delete_all';
        }
    }
    
    // Cerrar modal al hacer clic fuera
    document.getElementById('delete-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
    
    // Actualizar tiempo transcurrido cada minuto
    setInterval(function() {
        document.querySelectorAll('.time-ago').forEach(function(el) {
            const minutes = parseInt(el.dataset.time) + 1;
            el.dataset.time = minutes;
            
            if (minutes < 60) {
                el.textContent = 'Hace ' + minutes + ' min';
            } else if (minutes < 1440) {
                el.textContent = 'Hace ' + Math.floor(minutes / 60) + ' horas';
            } else {
                el.textContent = 'Hace ' + Math.floor(minutes / 1440) + ' días';
            }
        });
    }, 60000);
";

include 'includes/footer.php';
?>