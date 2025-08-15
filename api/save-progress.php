<?php
// app-protexa-seguro/api/save-progress.php
header('Content-Type: application/json');
require_once '../config.php';
setSecurityHeaders();

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

// Verificar autenticación
if (!checkWPAuth()) {
    jsonResponse(['error' => 'No autorizado'], 401);
}

$user = getWPUser();
if (!$user) {
    jsonResponse(['error' => 'Usuario no válido'], 401);
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Obtener datos del request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        // Si no hay JSON, intentar con FormData
        $data = $_POST;
    }
    
    $tour_id = $data['tour_id'] ?? $_POST['tour_id'] ?? null;
    $tour_data = $data['tourData'] ?? null;
    $is_draft = ($data['isDraft'] ?? 'true') === 'true';
    
    if (!$tour_id) {
        jsonResponse(['error' => 'ID de recorrido requerido'], 400);
    }
    
    // Verificar que el recorrido pertenece al usuario
    $stmt = $pdo->prepare("SELECT * FROM " . getTableName('recorridos') . " WHERE id = ? AND user_id = ?");
    $stmt->execute([$tour_id, $user['id']]);
    $recorrido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recorrido) {
        jsonResponse(['error' => 'Recorrido no encontrado'], 404);
    }
    
    // Procesar datos del formulario
    if ($tour_data) {
        $total_questions = 0;
        $answered_questions = 0;
        $yes_count = 0;
        $no_count = 0;
        $na_count = 0;
        
        foreach ($tour_data as $step_key => $step_data) {
            if (strpos($step_key, 'step_') === 0) {
                foreach ($step_data as $field_name => $field_value) {
                    // Procesar respuestas de preguntas
                    if (strpos($field_name, '_') !== false && in_array($field_value, ['si', 'no', 'na'])) {
                        $parts = explode('_', $field_name);
                        if (count($parts) >= 2) {
                            $categoria_id = intval($parts[0]);
                            $pregunta_numero = $parts[1];
                            
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
            $total_questions,
            $answered_questions,
            $yes_count,
            $no_count,
            $na_count,
            $status,
            $completed_at,
            $tour_id
        ]);
    }
    
    // Procesar fotos subidas
    if (isset($_FILES['photos']) && !empty($_FILES['photos']['tmp_name'])) {
        $upload_dir = UPLOAD_PATH . $tour_id . '/';
        
        // Crear directorio si no existe
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        foreach ($_FILES['photos']['tmp_name'] as $question_id => $files) {
            if (is_array($files)) {
                foreach ($files as $index => $tmp_name) {
                    if (!empty($tmp_name)) {
                        $original_name = $_FILES['photos']['name'][$question_id][$index];
                        $file_size = $_FILES['photos']['size'][$question_id][$index];
                        $mime_type = $_FILES['photos']['type'][$question_id][$index];
                        
                        // Validar archivo
                        if (!in_array($mime_type, ['image/jpeg', 'image/png', 'image/jpg'])) {
                            continue;
                        }
                        
                        if ($file_size > 5 * 1024 * 1024) { // 5MB max
                            continue;
                        }
                        
                        // Generar nombre único
                        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
                        $filename = uniqid() . '_' . time() . '.' . $extension;
                        $file_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            // Extraer categoria_id y pregunta_numero del question_id
                            $parts = explode('_', $question_id);
                            $categoria_id = intval($parts[0]);
                            $pregunta_numero = $parts[1] ?? '';
                            
                            // Guardar en base de datos
                            $stmt = $pdo->prepare("
                                INSERT INTO " . getTableName('fotos_recorrido') . " 
                                (recorrido_id, categoria_id, pregunta_numero, filename, original_filename, file_path, file_size, mime_type)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            $stmt->execute([
                                $tour_id,
                                $categoria_id,
                                $pregunta_numero,
                                $filename,
                                $original_name,
                                $file_path,
                                $file_size,
                                $mime_type
                            ]);
                        }
                    }
                }
            }
        }
    }
    
    // Procesar hallazgos críticos
    if (!$is_draft && isset($data['priority_level']) && $data['priority_level'] !== 'none') {
        $priority_level = $data['priority_level'];
        $critical_description = $data['critical_description'] ?? '';
        
        if (!empty($critical_description)) {
            $stmt = $pdo->prepare("
                INSERT INTO " . getTableName('hallazgos_criticos') . " 
                (recorrido_id, categoria_id, categoria_nombre, pregunta_numero, descripcion, nivel_prioridad)
                VALUES (?, 0, 'General', 'GENERAL', ?, ?)
            ");
            
            $stmt->execute([$tour_id, $critical_description, $priority_level]);
            
            // Actualizar recorrido con hallazgos críticos
            $stmt = $pdo->prepare("
                UPDATE " . getTableName('recorridos') . " SET critical_findings = ? WHERE id = ?
            ");
            $stmt->execute([$critical_description, $tour_id]);
        }
    }
    
    // Guardar comentarios finales
    if (!$is_draft && isset($data['final_comments'])) {
        $stmt = $pdo->prepare("
            UPDATE recorridos SET general_comments = ? WHERE id = ?
        ");
        $stmt->execute([$data['final_comments'], $tour_id]);
    }
    
    $pdo->commit();
    
    // Log de auditoría
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (user_id, action, table_name, record_id, new_values, ip_address)
        VALUES (?, ?, 'recorridos', ?, ?, ?)
    ");
    
    $log_data = json_encode([
        'is_draft' => $is_draft,
        'total_questions' => $total_questions ?? 0,
        'answered_questions' => $answered_questions ?? 0
    ]);
    
    $stmt->execute([
        $user['id'],
        $is_draft ? 'SAVE_DRAFT' : 'COMPLETE_TOUR',
        getTableName('recorridos'),
        $tour_id,
        $log_data,
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    
    // Enviar notificaciones si es emergencia y está completo
    if (!$is_draft && $recorrido['tour_type'] === 'emergency') {
        // Aquí podrías agregar lógica para enviar emails de notificación
        // sendEmergencyNotification($tour_id, $user);
    }
    
    jsonResponse([
        'success' => true,
        'message' => $is_draft ? 'Borrador guardado correctamente' : 'Recorrido completado exitosamente',
        'tour_id' => $tour_id,
        'status' => $is_draft ? 'draft' : 'completed',
        'redirect_url' => $is_draft ? 'borrador-guardado.php?id=' . $tour_id : 'success.php?id=' . $tour_id,
        'stats' => [
            'total_questions' => $total_questions ?? 0,
            'answered_questions' => $answered_questions ?? 0,
            'yes_count' => $yes_count ?? 0,
            'no_count' => $no_count ?? 0,
            'na_count' => $na_count ?? 0
        ]
    ]);
    
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error en save-progress.php: " . $e->getMessage());
    
    jsonResponse([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ], 500);
}

function sendEmergencyNotification($tour_id, $user) {
    // Implementar lógica de notificación por email
    // Esta función se puede expandir según las necesidades
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT r.*, GROUP_CONCAT(hc.descripcion SEPARATOR '\n') as hallazgos
            FROM " . getTableName('recorridos') . " r
            LEFT JOIN " . getTableName('hallazgos_criticos') . " hc ON r.id = hc.recorrido_id
            WHERE r.id = ?
            GROUP BY r.id
        ");
        $stmt->execute([$tour_id]);
        $recorrido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($recorrido) {
            $subject = "EMERGENCIA: Recorrido de Seguridad - " . $recorrido['location'];
            $message = "Se ha completado un recorrido de EMERGENCIA:\n\n";
            $message .= "Ubicación: " . $recorrido['location'] . "\n";
            $message .= "Inspector: " . $recorrido['user_name'] . "\n";
            $message .= "Fecha: " . date('d/m/Y H:i', strtotime($recorrido['created_at'])) . "\n";
            $message .= "División: " . $recorrido['division'] . "\n";
            $message .= "Motivo: " . $recorrido['reason'] . "\n\n";
            
            if ($recorrido['hallazgos']) {
                $message .= "HALLAZGOS CRÍTICOS:\n" . $recorrido['hallazgos'] . "\n\n";
            }
            
            $message .= "Revisar inmediatamente en el sistema.";
            
            // Obtener emails de configuración
            $stmt = $pdo->prepare("
                SELECT config_value FROM " . getTableName('configuracion_app') . " 
                WHERE config_key = 'emergency_notification_emails'
            ");
            $stmt->execute();
            $emails_config = $stmt->fetchColumn();
            
            if ($emails_config) {
                $emails = explode(',', $emails_config);
                foreach ($emails as $email) {
                    $email = trim($email);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        // Usar wp_mail si está disponible, sino mail()
                        if (function_exists('wp_mail')) {
                            wp_mail($email, $subject, $message);
                        } else {
                            mail($email, $subject, $message);
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error enviando notificación de emergencia: " . $e->getMessage());
    }
}
?>