<?php
// app-protexa-seguro/admin/recorridos.php
require_once '../config.php';
setSecurityHeaders();

// Verificar autenticación y permisos de administrador
if (!checkWPAuth()) {
    header('Location: ../index.php');
    exit;
}

$user = getWPUser();
if (!$user) {
    header('Location: ../index.php');
    exit;
}

// Verificar si el usuario tiene rol de administrador en WordPress
$is_admin = false;
if (function_exists('current_user_can')) {
    $is_admin = current_user_can('manage_options') || current_user_can('administrator');
}

if (!$is_admin) {
    wp_die('No tienes permisos para acceder a esta área.');
}

// Filtros y paginación
$filter_status = $_GET['status'] ?? 'all';
$filter_type = $_GET['type'] ?? 'all';
$filter_user = $_GET['user'] ?? '';
$filter_location = $_GET['location'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Construir query con filtros
$where_conditions = ["1=1"];
$params = [];

if ($filter_status !== 'all') {
    $where_conditions[] = "r.status = ?";
    $params[] = $filter_status;
}

if ($filter_type !== 'all') {
    $where_conditions[] = "r.tour_type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_user)) {
    $where_conditions[] = "r.user_name LIKE ?";
    $params[] = '%' . $filter_user . '%';
}

if (!empty($filter_location)) {
    $where_conditions[] = "r.location LIKE ?";
    $params[] = '%' . $filter_location . '%';
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(r.created_at) >= ?";
    $params[] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(r.created_at) <= ?";
    $params[] = $filter_date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Validar campo de ordenamiento
$allowed_sort_fields = ['created_at', 'completed_at', 'user_name', 'location', 'status', 'tour_type'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'created_at';
}

$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

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
        ORDER BY r.$sort_by $sort_order
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $recorridos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas generales
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completados,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as borradores,
            SUM(CASE WHEN tour_type = 'emergency' THEN 1 ELSE 0 END) as emergencias,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as hoy,
            SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as esta_semana,
            COUNT(DISTINCT user_id) as usuarios_activos
        FROM " . getTableName('recorridos') . " 
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener usuarios para filtro
    $stmt = $pdo->prepare("
        SELECT DISTINCT user_name 
        FROM " . getTableName('recorridos') . " 
        ORDER BY user_name
    ");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Obtener ubicaciones para filtro
    $stmt = $pdo->prepare("
        SELECT DISTINCT location 
        FROM " . getTableName('recorridos') . " 
        ORDER BY location
    ");
    $stmt->execute();
    $ubicaciones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Hallazgos críticos pendientes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as criticos_pendientes
        FROM " . getTableName('hallazgos_criticos') . " 
        WHERE status = 'pendiente'
    ");
    $stmt->execute();
    $criticos_pendientes = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $recorridos = [];
    $stats = [];
    $message = 'Error al cargar datos: ' . $e->getMessage();
    $message_type = 'error';
}

// Configurar variables para templates
$page_title = APP_NAME . ' - Administración de Recorridos';
$page_description = 'Panel de administración para supervisar recorridos de seguridad';
$header_title = 'Admin - Recorridos';
$show_footer_stats = false;
$additional_css = ['assets/css/admin.css'];

include '../includes/head.php';
include '../includes/header.php';
?>

<!-- Main Content -->
<main class="main-content admin-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="admin-header">
            <div class="admin-title">
                <h1>🛡️ Administración de Recorridos</h1>
                <p>Panel de control para supervisión y seguimiento</p>
            </div>
            
            <div class="admin-actions">
                <a href="estadisticas.php" class="btn btn-secondary">📊 Estadísticas</a>
                <a href="hallazgos.php" class="btn btn-warning">
                    ⚠️ Hallazgos Críticos 
                    <?php if ($criticos_pendientes > 0): ?>
                    <span class="badge badge-danger"><?php echo $criticos_pendientes; ?></span>
                    <?php endif; ?>
                </a>
                <a href="exportar.php" class="btn btn-primary">📤 Exportar</a>
            </div>
        </div>

        <!-- Alert for Critical Findings -->
        <?php if ($criticos_pendientes > 0): ?>
        <div class="alert alert-warning">
            <h3>⚠️ Atención Requerida</h3>
            <p>Hay <strong><?php echo $criticos_pendientes; ?></strong> hallazgos críticos pendientes de atención.</p>
            <a href="hallazgos.php" class="btn btn-sm btn-warning">Ver Hallazgos</a>
        </div>
        <?php endif; ?>

        <!-- Stats Dashboard -->
        <div class="admin-stats">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-icon">📋</span>
                    <span class="stat-title">Total</span>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-change">Todos los recorridos</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-header">
                    <span class="stat-icon">✅</span>
                    <span class="stat-title">Completados</span>
                </div>
                <div class="stat-value"><?php echo number_format($stats['completados']); ?></div>
                <div class="stat-change">
                    <?php echo $stats['total'] > 0 ? round(($stats['completados'] / $stats['total']) * 100) : 0; ?>% del total
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-header">
                    <span class="stat-icon">🚨</span>
                    <span class="stat-title">Emergencias</span>
                </div>
                <div class="stat-value"><?php echo number_format($stats['emergencias']); ?></div>
                <div class="stat-change">Requieren atención</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-header">
                    <span class="stat-icon">📅</span>
                    <span class="stat-title">Hoy</span>
                </div>
                <div class="stat-value"><?php echo number_format($stats['hoy']); ?></div>
                <div class="stat-change">Recorridos de hoy</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-icon">👥</span>
                    <span class="stat-title">Usuarios</span>
                </div>
                <div class="stat-value"><?php echo number_format($stats['usuarios_activos']); ?></div>
                <div class="stat-change">Usuarios activos</div>
            </div>
            
            <div class="stat-card secondary">
                <div class="stat-header">
                    <span class="stat-icon">📝</span>
                    <span class="stat-title">Borradores</span>
                </div>
                <div class="stat-value"><?php echo number_format($stats['borradores']); ?></div>
                <div class="stat-change">Pendientes</div>
            </div>
        </div>

        <!-- Advanced Filters -->
        <div class="admin-filters">
            <form method="get" class="filters-form">
                <div class="filters-row">
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
                        <label for="user">Usuario:</label>
                        <select name="user" id="user">
                            <option value="">Todos</option>
                            <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?php echo htmlspecialchars($usuario); ?>" 
                                    <?php echo $filter_user === $usuario ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usuario); ?>
                            </option>
                            <?php endforeach; ?>
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
                </div>
                
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="date_from">Desde:</label>
                        <input type="date" name="date_from" id="date_from" value="<?php echo $filter_date_from; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">Hasta:</label>
                        <input type="date" name="date_to" id="date_to" value="<?php echo $filter_date_to; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort">Ordenar por:</label>
                        <select name="sort" id="sort">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Fecha Creación</option>
                            <option value="completed_at" <?php echo $sort_by === 'completed_at' ? 'selected' : ''; ?>>Fecha Completado</option>
                            <option value="user_name" <?php echo $sort_by === 'user_name' ? 'selected' : ''; ?>>Usuario</option>
                            <option value="location" <?php echo $sort_by === 'location' ? 'selected' : ''; ?>>Ubicación</option>
                            <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Estado</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="order">Orden:</label>
                        <select name="order" id="order">
                            <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Descendente</option>
                            <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Ascendente</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                        <a href="recorridos.php" class="btn btn-secondary">🔄 Limpiar</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Info -->
        <div class="results-header">
            <div class="results-info">
                <h3>Resultados</h3>
                <p>Mostrando <?php echo count($recorridos); ?> de <?php echo number_format($total_records); ?> recorridos</p>
                <?php if ($total_pages > 1): ?>
                <p>Página <?php echo $page; ?> de <?php echo $total_pages; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="results-actions">
                <button onclick="selectAll()" class="btn btn-sm btn-secondary">Seleccionar Todo</button>
                <button onclick="exportSelected()" class="btn btn-sm btn-primary">📤 Exportar Seleccionados</button>
            </div>
        </div>

        <!-- Recorridos Table -->
        <?php if (empty($recorridos)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <h3>No se encontraron recorridos</h3>
                <p>No hay recorridos que coincidan con los filtros seleccionados.</p>
            </div>
        <?php else: ?>
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="checkbox-col">
                                <input type="checkbox" id="select-all" onchange="toggleSelectAll()">
                            </th>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Ubicación</th>
                            <th>División</th>
                            <th>Progreso</th>
                            <th>Críticos</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recorridos as $recorrido): ?>
                        <tr class="recorrido-row" data-id="<?php echo $recorrido['id']; ?>">
                            <td>
                                <input type="checkbox" name="selected[]" value="<?php echo $recorrido['id']; ?>" class="row-checkbox">
                            </td>
                            
                            <td class="id-col">
                                <a href="../ver-recorrido.php?id=<?php echo $recorrido['id']; ?>" class="id-link">
                                    #<?php echo $recorrido['id']; ?>
                                </a>
                            </td>
                            
                            <td class="user-col">
                                <div class="user-info">
                                    <strong><?php echo htmlspecialchars($recorrido['user_name']); ?></strong>
                                    <small>ID: <?php echo $recorrido['user_id']; ?></small>
                                </div>
                            </td>
                            
                            <td class="type-col">
                                <?php if ($recorrido['tour_type'] === 'emergency'): ?>
                                    <span class="badge badge-danger">🚨 Emergencia</span>
                                <?php else: ?>
                                    <span class="badge badge-primary">📋 Programado</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="status-col">
                                <?php
                                $status_config = [
                                    'completed' => ['✅', 'Completado', 'success'],
                                    'draft' => ['📝', 'Borrador', 'warning'],
                                    'in_progress' => ['⏳', 'En Progreso', 'info']
                                ];
                                $config = $status_config[$recorrido['status']] ?? ['❓', 'Desconocido', 'secondary'];
                                ?>
                                <span class="badge badge-<?php echo $config[2]; ?>">
                                    <?php echo $config[0]; ?> <?php echo $config[1]; ?>
                                </span>
                            </td>
                            
                            <td class="location-col">
                                <strong><?php echo htmlspecialchars($recorrido['location']); ?></strong>
                                <small><?php echo htmlspecialchars($recorrido['business']); ?></small>
                            </td>
                            
                            <td class="division-col">
                                <?php echo htmlspecialchars($recorrido['division']); ?>
                            </td>
                            
                            <td class="progress-col">
                                <div class="progress-info">
                                    <span class="progress-text"><?php echo $recorrido['porcentaje_completado']; ?>%</span>
                                    <div class="mini-progress-bar">
                                        <div class="mini-progress-fill" style="width: <?php echo $recorrido['porcentaje_completado']; ?>%"></div>
                                    </div>
                                    <small><?php echo $recorrido['answered_questions']; ?>/<?php echo $recorrido['total_questions']; ?></small>
                                </div>
                            </td>
                            
                            <td class="critical-col">
                                <?php if ($recorrido['hallazgos_criticos'] > 0): ?>
                                    <span class="badge badge-danger">
                                        ⚠️ <?php echo $recorrido['hallazgos_criticos']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="date-col">
                                <div class="date-info">
                                    <strong><?php echo date('d/m/Y', strtotime($recorrido['created_at'])); ?></strong>
                                    <small><?php echo date('H:i', strtotime($recorrido['created_at'])); ?></small>
                                    <?php if ($recorrido['completed_at']): ?>
                                    <small class="completed-date">
                                        Fin: <?php echo date('d/m H:i', strtotime($recorrido['completed_at'])); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td class="actions-col">
                                <div class="action-buttons">
                                    <a href="../ver-recorrido.php?id=<?php echo $recorrido['id']; ?>" 
                                       class="btn btn-xs btn-outline" title="Ver detalle">👁️</a>
                                    
                                    <a href="../reporte.php?id=<?php echo $recorrido['id']; ?>" 
                                       class="btn btn-xs btn-secondary" title="Generar reporte">📄</a>
                                    
                                    <?php if ($recorrido['hallazgos_criticos'] > 0): ?>
                                    <a href="hallazgos.php?recorrido=<?php echo $recorrido['id']; ?>" 
                                       class="btn btn-xs btn-warning" title="Ver hallazgos">⚠️</a>
                                    <?php endif; ?>
                                    
                                    <div class="dropdown">
                                        <button class="btn btn-xs btn-secondary dropdown-toggle" data-toggle="dropdown">⋮</button>
                                        <div class="dropdown-menu">
                                            <a href="editar-recorrido.php?id=<?php echo $recorrido['id']; ?>" class="dropdown-item">✏️ Editar</a>
                                            <a href="../recorrido.php?id=<?php echo $recorrido['id']; ?>" class="dropdown-item">📝 Continuar</a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0)" onclick="deleteRecorrido(<?php echo $recorrido['id']; ?>)" 
                                               class="dropdown-item text-danger">🗑️ Eliminar</a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="admin-pagination">
                <div class="pagination-info">
                    Mostrando <?php echo ($offset + 1); ?>-<?php echo min($offset + $per_page, $total_records); ?> 
                    de <?php echo number_format($total_records); ?> registros
                </div>
                
                <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                           class="pagination-btn">⏮️ Primera</a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                           class="pagination-btn">← Anterior</a>
                    <?php endif; ?>
                    
                    <span class="pagination-current">
                        Página <?php echo $page; ?> de <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                           class="pagination-btn">Siguiente →</a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" 
                           class="pagination-btn">⏭️ Última</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php
// Scripts específicos para admin
$inline_scripts = "
    function toggleSelectAll() {
        const selectAll = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
    }
    
    function selectAll() {
        document.getElementById('select-all').checked = true;
        toggleSelectAll();
    }
    
    function exportSelected() {
        const selected = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) {
            alert('Selecciona al menos un recorrido para exportar');
            return;
        }
        window.location.href = 'exportar.php?ids=' + selected.join(',');
    }
    
    function deleteRecorrido(id) {
        if (confirm('¿Estás seguro de que deseas eliminar este recorrido? Esta acción no se puede deshacer.')) {
            fetch('delete-recorrido.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error eliminando recorrido');
            });
        }
    }
    
    // Auto-refresh cada 5 minutos
    setInterval(() => {
        if (confirm('¿Actualizar datos? (Se ejecuta automáticamente cada 5 minutos)')) {
            location.reload();
        }
    }, 300000);
    
    // Dropdown functionality
    document.addEventListener('click', function(e) {
        if (e.target.matches('.dropdown-toggle')) {
            e.preventDefault();
            const dropdown = e.target.closest('.dropdown');
            const menu = dropdown.querySelector('.dropdown-menu');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        } else {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });
";

include '../includes/footer.php';
?>