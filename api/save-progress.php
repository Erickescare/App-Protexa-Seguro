<?php
// app-protexa-seguro/api/save-progress.php - VERSION CORREGIDA
header('Content-Type: application/json');
require_once '../config.php';
setSecurityHeaders();

// Debug inicial
error_log("save-progress.php: Iniciando...");

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("save-progress.php: Método no permitido: " . $_SERVER['REQUEST_METHOD']);
    jsonResponse(['error' => 'Método no permitido'], 405);
}

// Verificar autenticación WordPress
if (!checkWPAuth()) {
    error_log("save-progress.php: checkWPAuth() falló");
    
    // Verificar si WordPress está disponible
    if (defined('WP_PATH') && file_exists(WP_PATH)) {
        error_log("save-progress.php: WordPress path existe: " . WP_PATH);
        
        // Intentar cargar WordPress
        try {
            require_once WP_PATH;
            if (!is_user_logged_in()) {
                error_log("save-progress.php: Usuario no logueado en WordPress");
                jsonResponse(['error' => 'No autorizado - Usuario no logueado'], 401);
            }
        } catch (Exception $e) {
            error_log("save-progress.php: Error cargando WordPress: " . $e->getMessage());
            jsonResponse(['error' => 'Error de autenticación'], 401);
        }
    } else {
        error_log("save-progress.php: WordPress path no existe: " . (defined('WP_PATH') ? WP_PATH : 'WP_PATH no definido'));
        jsonResponse(['error' => 'WordPress no disponible'], 401);
    }
}

$user = getWPUser();
if (!$user) {
    error_log("save-progress.php: getWPUser() retornó null");
    jsonResponse(['error' => 'Usuario no válido'], 401);
}

error_log("save-progress.php: Usuario autenticado: " . $user['id'] . " - " . $user['username']);

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Obtener datos del request
    $input = file_get_contents('php://input');
    error_log("save-progress.php: Input recibido: " . substr($input, 0, 200) . "...");
    
    $data = json_decode($input, true);
    
    if (!$data && !empty($_POST)) {
        // Si no hay JSON, usar $_POST
        $data = $_POST;
        error_log("save-progress.php: Usando $_POST data");
    }
    
    if (!$data) {
        error_log("save-progress.php: No se recibieron datos");
        jsonResponse(['error' => 'No se recibieron datos'], 400);
    }
    
    $tour_id = $data['tour_id'] ?? $_POST['tour_id'] ?? null;
    $tour_data = $data['tourData'] ?? $data['data'] ?? null;
    $is_draft = ($data['isDraft'] ?? 'true') === 'true';
    
    error_log("save-progress.php: tour_id=$tour_id, is_draft=$is_draft");
    
    if (!$tour_id) {
        error_log("save-progress.php: ID de recorrido faltante");
        jsonResponse(['error' => 'ID de recorrido requerido'], 400);
    }
    
    // Verificar que el recorrido pertenece al usuario
    $stmt = $pdo->prepare("SELECT * FROM " . getTableName('recorridos') . " WHERE id = ? AND user_id = ?");
    $stmt->execute([$tour_id, $user['id']]);
    $recorrido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recorrido) {
        error_log("save-progress.php: Recorrido no encontrado para tour_id=$tour_id, user_id=" . $user['id']);
        jsonResponse(['error' => 'Recorrido no encontrado o no autorizado'], 404);
    }
    
    error_log("save-progress.php: Recorrido encontrado: " . $recorrido['location']);
    
    // Procesar datos del formulario si existen
    $total_questions = 0;
    $answered_questions = 0;
    $yes_count = 0;
    $no_count = 0;
    $na_count = 0;
    
    if ($tour_data) {
        error_log("save-progress.php: Procesando tour_data");
        
        foreach ($tour_data as $step_key => $step_data) {
            if (strpos($step_key, 'step_') === 0 && is_array($step_data)) {
                foreach ($step_data as $field_name => $field_value) {
                    // Procesar respuestas de preguntas (formato: categoria_pregunta)
                    if (preg_match('/^(\d+)_(.+)$/', $field_name, $matches) && in_array($field_value, ['si', 'no', 'na'])) {
                        $categoria_id = intval($matches[1]);
                        $pregunta_numero = $matches[2];
                        
                        $total_questions++;
                        $answered_questions++;
                        
                        // Contar respuestas
                        switch ($field_value) {
                            case 'si': $yes_count++; break;
                            case 'no': $no_count++; break;
                            case 'na': $na_count++; break;
                        }
                        
                        // Obtener texto de la pregunta
                        $stmt = $pdo->prepare("
                            SELECT pregunta_texto, categoria_nombre 
                            FROM " . getTableName('configuracion_preguntas') . " 
                            WHERE categoria_id = ? AND pregunta_numero = ?
                        ");
                        $stmt->execute([$categoria_id, $pregunta_numero]);
                        $pregunta_info = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($pregunta_info) {
                            // Insertar o actualizar respuesta
                            $stmt = $pdo->prepare("
                                INSERT INTO " . getTableName('respuestas_categorias') . " 
                                (recorrido_id, categoria_id, categoria_nombre, pregunta_numero, pregunta_texto, respuesta, comentarios)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE
                                respuesta = VALUES(respuesta),
                                comentarios = VALUES(comentarios)
                            ");
                            
                            $comentario_key = "comments[{$field_name}]";
                            $comentarios = $step_data[$comentario_key] ?? '';
                            
                            $stmt->execute([
                                $tour_id,
                                $categoria_id,
                                $pregunta_info['categoria_nombre'],
                                $pregunta_numero,
                                $pregunta_info['pregunta_texto'],
                                $field_value,
                                $comentarios
                            ]);
                        }
                    }
                }
            }
        }
    }
    
    // Actualizar estadísticas del recorrido
    $status = $is_draft ? 'draft' : 'completed';
    $completed_at = $is_draft ? null : date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("
        UPDATE " . getTableName('recorridos') . " SET
            total_questions = ?,
            answered_questions = ?,
            yes_count = ?,
            no_count = ?,
            na_count = ?,
            status = ?,
            completed_at = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        max($total_questions, $recorrido['total_questions'] ?? 0),
        max($answered_questions, $recorrido['answered_questions'] ?? 0),
        max($yes_count, $recorrido['yes_count'] ?? 0),
        max($no_count, $recorrido['no_count'] ?? 0),
        max($na_count, $recorrido['na_count'] ?? 0),
        $status,
        $completed_at,
        $tour_id
    ]);
    
    $pdo->commit();
    
    error_log("save-progress.php: Guardado exitoso para tour_id=$tour_id");
    
    // Log de auditoría
    try {
        $stmt = $pdo->prepare("
            INSERT INTO " . getTableName('logs_sistema') . " (user_id, action, table_name, record_id, new_values, ip_address)
            VALUES (?, ?, 'recorridos', ?, ?, ?)
        ");
        
        $log_data = json_encode([
            'is_draft' => $is_draft,
            'total_questions' => $total_questions,
            'answered_questions' => $answered_questions,
            'yes_count' => $yes_count,
            'no_count' => $no_count,
            'na_count' => $na_count
        ]);
        
        $stmt->execute([
            $user['id'],
            $is_draft ? 'SAVE_DRAFT' : 'COMPLETE_TOUR',
            $tour_id,
            $log_data,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("save-progress.php: Error en log de auditoría: " . $e->getMessage());
        // No fallar por error en logs
    }
    
    // Respuesta exitosa
    $response = [
        'success' => true,
        'message' => $is_draft ? 'Borrador guardado correctamente' : 'Recorrido completado exitosamente',
        'tour_id' => $tour_id,
        'status' => $status,
        'stats' => [
            'total_questions' => $total_questions,
            'answered_questions' => $answered_questions,
            'yes_count' => $yes_count,
            'no_count' => $no_count,
            'na_count' => $na_count
        ]
    ];
    
    if ($is_draft) {
        $response['redirect_url'] = 'borrador-guardado.php?id=' . $tour_id;
    } else {
        $response['redirect_url'] = 'success.php?id=' . $tour_id;
    }
    
    error_log("save-progress.php: Enviando respuesta exitosa");
    jsonResponse($response);
    
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error en save-progress.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    jsonResponse([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage(),
        'debug' => [
            'tour_id' => $tour_id ?? null,
            'user_id' => isset($user) ? $user['id'] : null,
            'is_draft' => $is_draft ?? null
        ]
    ], 500);
}

// Función auxiliar para debug de autenticación
function debugAuth() {
    $debug = [
        'wp_path_defined' => defined('WP_PATH'),
        'wp_path_exists' => defined('WP_PATH') ? file_exists(WP_PATH) : false,
        'session_id' => session_id(),
        'cookies' => array_keys($_COOKIE),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
    ];
    
    error_log("save-progress.php DEBUG AUTH: " . json_encode($debug));
    return $debug;
}

// Para debugging adicional si es necesario
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    $debug_info = debugAuth();
    jsonResponse(['debug' => $debug_info]);
}
?>