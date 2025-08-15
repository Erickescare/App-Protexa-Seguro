<?php
// app-protexa-seguro/api/upload-photo.php
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
    // Validar parámetros
    $tour_id = $_POST['tour_id'] ?? null;
    $question_id = $_POST['question_id'] ?? null;
    
    if (!$tour_id || !$question_id) {
        jsonResponse(['error' => 'Parámetros requeridos: tour_id, question_id'], 400);
    }
    
    // Verificar que el recorrido pertenece al usuario
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM " . getTableName('recorridos') . " WHERE id = ? AND user_id = ?");
    $stmt->execute([$tour_id, $user['id']]);
    
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Recorrido no encontrado'], 404);
    }
    
    // Verificar que se subió un archivo
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'No se recibió ninguna foto válida'], 400);
    }
    
    $file = $_FILES['photo'];
    
    // Validaciones
    $max_size = 5 * 1024 * 1024; // 5MB
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    
    if ($file['size'] > $max_size) {
        jsonResponse(['error' => 'La imagen es demasiado grande (máximo 5MB)'], 400);
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        jsonResponse(['error' => 'Tipo de archivo no permitido. Solo JPG, JPEG y PNG'], 400);
    }
    
    // Crear directorio de destino
    $upload_dir = UPLOAD_PATH . $tour_id . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generar nombre único para el archivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $file_path = $upload_dir . $filename;
    
    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        jsonResponse(['error' => 'Error al guardar la imagen'], 500);
    }
    
    // Extraer información de la pregunta
    $parts = explode('_', $question_id);
    $categoria_id = intval($parts[0]);
    $pregunta_numero = $parts[1] ?? '';
    
    // Guardar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO " . getTableName('fotos_recorrido') . " 
        (recorrido_id, categoria_id, pregunta_numero, filename, original_filename, file_path, file_size, mime_type, description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $description = $_POST['description'] ?? '';
    
    $stmt->execute([
        $tour_id,
        $categoria_id,
        $pregunta_numero,
        $filename,
        $file['name'],
        $file_path,
        $file['size'],
        $file['type'],
        $description
    ]);
    
    $photo_id = $pdo->lastInsertId();
    
    // Crear thumbnail (opcional)
    $thumbnail_path = null;
    if (extension_loaded('gd')) {
        $thumbnail_path = createThumbnail($file_path, $upload_dir . 'thumb_' . $filename);
    }
    
    // Log de auditoría
    $stmt = $pdo->prepare("
        INSERT INTO " . getTableName('logs_sistema') . " (user_id, action, table_name, record_id, new_values, ip_address)
        VALUES (?, 'UPLOAD_PHOTO', ?, ?, ?, ?)
    ");
    
    $log_data = json_encode([
        'tour_id' => $tour_id,
        'question_id' => $question_id,
        'filename' => $filename,
        'file_size' => $file['size']
    ]);
    
    $stmt->execute([
        $user['id'],
        getTableName('fotos_recorrido'),
        $photo_id,
        $log_data,
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Foto subida correctamente',
        'photo_id' => $photo_id,
        'filename' => $filename,
        'url' => UPLOAD_URL . $tour_id . '/' . $filename,
        'thumbnail_url' => $thumbnail_path ? UPLOAD_URL . $tour_id . '/thumb_' . $filename : null,
        'file_size' => $file['size']
    ]);
    
} catch (Exception $e) {
    error_log("Error en upload-photo.php: " . $e->getMessage());
    jsonResponse(['error' => 'Error interno del servidor'], 500);
}

function createThumbnail($source_path, $thumbnail_path, $max_width = 300, $max_height = 300) {
    try {
        // Obtener información de la imagen
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            return false;
        }
        
        $width = $image_info[0];
        $height = $image_info[1];
        $type = $image_info[2];
        
        // Calcular nuevas dimensiones manteniendo proporción
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);
        
        // Crear imagen desde el archivo fuente
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source_image = imagecreatefromjpeg($source_path);
                break;
            case IMAGETYPE_PNG:
                $source_image = imagecreatefrompng($source_path);
                break;
            default:
                return false;
        }
        
        if (!$source_image) {
            return false;
        }
        
        // Crear imagen de destino
        $thumbnail_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preservar transparencia para PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($thumbnail_image, false);
            imagesavealpha($thumbnail_image, true);
            $transparent = imagecolorallocatealpha($thumbnail_image, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail_image, 0, 0, $new_width, $new_height, $transparent);
        }
        
        // Redimensionar
        imagecopyresampled(
            $thumbnail_image, $source_image,
            0, 0, 0, 0,
            $new_width, $new_height, $width, $height
        );
        
        // Guardar thumbnail
        $result = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($thumbnail_image, $thumbnail_path, 85);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($thumbnail_image, $thumbnail_path, 8);
                break;
        }
        
        // Limpiar memoria
        imagedestroy($source_image);
        imagedestroy($thumbnail_image);
        
        return $result ? $thumbnail_path : false;
        
    } catch (Exception $e) {
        error_log("Error creando thumbnail: " . $e->getMessage());
        return false;
    }
}
?>