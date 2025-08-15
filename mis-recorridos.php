<?php
// app-protexa-seguro/mis-recorridos.php
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

// Filtros
$filter_status = $_GET['status'] ?? 'all';
$filter_type = $_GET['type'] ?? 'all';
$filter_location = $_GET['location'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Construir query con filtros
$where_conditions = ["r.user_id = ?"];
$params = [$user['id']];

if ($filter_status !== 'all') {
    $where_conditions[] = "r.status = ?";
    $params[] = $filter_status;
}

if ($filter_type !== 'all') {
    $where_conditions[] = "r.tour_type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_location)) {
    $where_conditions[] = "r.location LIKE ?";
    $params[] = '%' . $filter_location . '%';
}

$where_clause = implode(' AND ', $where_conditions);

try {
    $pdo = getDBConnection();
    
    // Obtener total de registros
    $count_query = "
        SELECT COUNT(*) as total 
        FROM " . getTableName('recorridos') . " r 
        WHERE $where_clause
    ";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
    // Obtener recorridos con paginación
    $query = "
        SELECT r.*, 
               COUNT(f.id) as total_fotos,
               COUNT(hc.id) as hallazgos_criticos,
               CASE 
                   WHEN r.total_questions > 0 THEN ROUND((r.answered_questions / r.total_questions) * 100)
                   ELSE 0 
               END as porcentaje_completado
        FROM " . getTableName('recorridos') . " r
        LEFT JOIN " . getTableName('fotos_recorrido') . " f ON r.id = f.recorrido_id
        LEFT JOIN " . getTableName('hallazgos_criticos') . " hc ON r.id = hc.recorrido_id
        WHERE $where_clause
        GROUP BY r.id
        ORDER BY r.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $recorridos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener ubicaciones para el filtro
    $stmt = $pdo->prepare("
        SELECT DISTINCT location 
        FROM " . getTableName('recorridos') . " 
        WHERE user_id = ? 
        ORDER BY location
    ");
    $stmt->execute([$user['id']]);
    $ubicaciones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Estadísticas generales
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completados,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as borradores,
            SUM(CASE WHEN tour_type = 'emergency' THEN 1 ELSE 0 END) as emergencias,
            AVG(CASE WHEN status = 'completed' AND total_questions > 0 
                THEN (answered_questions / total_questions) * 100 ELSE 0 END) as promedio_completado
        FROM " . getTableName('recorridos') . " 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $recorridos = [];
    $stats = [];
    $message = 'Error al cargar recorridos: ' . $e->getMessage();
    $message_type = 'error';
}

// Configurar variables para templates
$page_title = APP_NAME . ' - Mis Recorridos';
$page_description = 'Historial completo de tus recorridos de seguridad';
$show_back_button = true;
$back_url = 'dashboard.php';
$header_title = 'Mis Recorridos';
$show_footer_stats = true;

include 'includes/head.php';
include 'includes/header.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">📋 Mis Recorridos</h1>
            <p class="page-subtitle">Historial completo de tus inspecciones de seguridad</p>
        </div>

        <!-- Stats Overview -->
        <?php if (!empty($stats)): ?>
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['completados']; ?></div>
                <div class="stat-label">Completados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['borradores']; ?></div>
                <div class="stat-label">Borradores</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['emergencias']; ?></div>
                <div class="stat-label">Emergencias</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo round($stats['promedio_completado']); ?>%</div>
                <div class="stat-label">Promedio</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters-section">
            <form method="get" class="filters-form">
                <div class="filter-group">
                    <label for="status">Estado:</label>
                    <select name="status" id="status">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Todos</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completados</option>
                        <option value="draft" <?php echo $filter_status === 'draft' ? 'selected' : ''; ?>>Borradores</option>
                        <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>En Progreso</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="type">Tipo:</label>
                    <select name="type" id="type">
                        <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>Todos</option>
                        <option value="scheduled" <?php echo $filter_type === 'scheduled' ? 'selected' : ''; ?>>Programado</option>
                        <option value="emergency" <?php echo $filter_type === 'emergency' ? 'selected' : ''; ?>>Emergencia</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="location">Ubicación:</label>
                    <select name="location" id="location">
                        <option value="">Todas</option>
                        <?php foreach ($ubicaciones as $ubicacion): ?>
                        <option value="<?php echo htmlspecialchars($ubicacion); ?>" 
                                <?php echo $filter_location === $ubicacion ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ubicacion); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                    <a href="mis-recorridos.php" class="btn btn-secondary">🔄 Limpiar</a>
                </div>
            </form>
        </div>

        <!-- Results Info -->
        <div class="results-info">
            <p>Mostrando <?php echo count($recorridos); ?> de <?php echo $total_records; ?> recorridos</p>
            <?php if ($total_pages > 1): ?>
            <p>Página <?php echo $page; ?> de <?php echo $total_pages; ?></p>
            <?php endif; ?>
        </div>

        <!-- Recorridos List -->
        <?php if (empty($recorridos)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <h3>No se encontraron recorridos</h3>
                <p>No hay recorridos que coincidan con los filtros seleccionados.</p>
                <a href="dashboard.php" class="btn btn-primary">
                    Iniciar Nuevo Recorrido
                </a>
            </div>
        <?php else: ?>
            <div class="recorridos-list">
                <?php foreach ($recorridos as $recorrido): ?>
                <div class="recorrido-card">
                    <div class="recorrido-header">
                        <div class="recorrido-type">
                            <?php if ($recorrido['tour_type'] === 'emergency'): ?>
                                <span class="type-badge emergency">🚨 Emergencia</span>
                            <?php else: ?>
                                <span class="type-badge scheduled">📋 Programado</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="recorrido-status">
                            <?php
                            $status_config = [
                                'completed' => ['✅', 'Completado', 'success'],
                                'draft' => ['📝', 'Borrador', 'warning'],
                                'in_progress' => ['⏳', 'En Progreso', 'info']
                            ];
                            $config = $status_config[$recorrido['status']] ?? ['❓', 'Desconocido', 'secondary'];
                            ?>
                            <span class="status-badge <?php echo $config[2]; ?>">
                                <?php echo $config[0]; ?> <?php echo $config[1]; ?>
                            </span>
                        </div>
                        
                        <div class="recorrido-id">#<?php echo $recorrido['id']; ?></div>
                    </div>
                    
                    <div class="recorrido-content">
                        <h3 class="recorrido-location"><?php echo htmlspecialchars($recorrido['location']); ?></h3>
                        
                        <div class="recorrido-details">
                            <div class="detail-row">
                                <span class="detail-label">División:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($recorrido['division']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Negocio:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($recorrido['business']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Motivo:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($recorrido['reason']); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($recorrido['status'] === 'completed' || $recorrido['answered_questions'] > 0): ?>
                        <div class="recorrido-progress">
                            <div class="progress-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $recorrido['porcentaje_completado']; ?>%</span>
                                    <span class="stat-text">Completado</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $recorrido['yes_count']; ?></span>
                                    <span class="stat-text">Sí</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $recorrido['no_count']; ?></span>
                                    <span class="stat-text">No</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $recorrido['total_fotos']; ?></span>
                                    <span class="stat-text">Fotos</span>
                                </div>
                                <?php if ($recorrido['hallazgos_criticos'] > 0): ?>
                                <div class="stat-item critical">
                                    <span class="stat-number"><?php echo $recorrido['hallazgos_criticos']; ?></span>
                                    <span class="stat-text">Críticos</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($recorrido['total_questions'] > 0): ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $recorrido['porcentaje_completado']; ?>%"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="recorrido-dates">
                            <div class="date-item">
                                <span class="date-label">Creado:</span>
                                <span class="date-value"><?php echo date('d/m/Y H:i', strtotime($recorrido['created_at'])); ?></span>
                            </div>
                            <?php if ($recorrido['completed_at']): ?>
                            <div class="date-item">
                                <span class="date-label">Completado:</span>
                                <span class="date-value"><?php echo date('d/m/Y H:i', strtotime($recorrido['completed_at'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="recorrido-actions">
                        <?php if ($recorrido['status'] === 'draft' || $recorrido['status'] === 'in_progress'): ?>
                            <a href="recorrido.php?id=<?php echo $recorrido['id']; ?>" 
                               class="btn btn-primary">
                                ▶️ Continuar
                            </a>
                        <?php else: ?>
                            <a href="ver-recorrido.php?id=<?php echo $recorrido['id']; ?>" 
                               class="btn btn-outline">
                                👁️ Ver Detalle
                            </a>
                        <?php endif; ?>
                        
                        <a href="reporte.php?id=<?php echo $recorrido['id']; ?>" 
                           class="btn btn-secondary">
                            📄 Reporte
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                       class="pagination-btn">← Anterior</a>
                <?php endif; ?>
                
                <div class="pagination-info">
                    Página <?php echo $page; ?> de <?php echo $total_pages; ?>
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                       class="pagination-btn">Siguiente →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php
include 'includes/footer.php';
?>