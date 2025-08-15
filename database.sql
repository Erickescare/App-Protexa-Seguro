-- app-protexa-seguro/database.sql
-- Estructura de base de datos para Protexa Seguro

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS protexa_seguro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE protexa_seguro;

-- Tabla principal de recorridos
CREATE TABLE recorridos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    tour_type ENUM('emergency', 'scheduled') NOT NULL,
    location VARCHAR(255) NOT NULL,
    division VARCHAR(100) NOT NULL,
    business VARCHAR(100) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('in_progress', 'completed', 'draft') DEFAULT 'draft',
    total_questions INT DEFAULT 0,
    answered_questions INT DEFAULT 0,
    yes_count INT DEFAULT 0,
    no_count INT DEFAULT 0,
    na_count INT DEFAULT 0,
    critical_findings TEXT NULL,
    general_comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_tour_type (tour_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Tabla de respuestas por categoría
CREATE TABLE respuestas_categorias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recorrido_id INT NOT NULL,
    categoria_id INT NOT NULL,
    categoria_nombre VARCHAR(255) NOT NULL,
    pregunta_numero VARCHAR(10) NOT NULL,
    pregunta_texto TEXT NOT NULL,
    respuesta ENUM('si', 'no', 'na') NOT NULL,
    comentarios TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (recorrido_id) REFERENCES recorridos(id) ON DELETE CASCADE,
    INDEX idx_recorrido_categoria (recorrido_id, categoria_id),
    INDEX idx_respuesta (respuesta)
);

-- Tabla de fotos adjuntas
CREATE TABLE fotos_recorrido (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recorrido_id INT NOT NULL,
    respuesta_id INT NULL,
    categoria_id INT NOT NULL,
    pregunta_numero VARCHAR(10) NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (recorrido_id) REFERENCES recorridos(id) ON DELETE CASCADE,
    FOREIGN KEY (respuesta_id) REFERENCES respuestas_categorias(id) ON DELETE SET NULL,
    INDEX idx_recorrido_id (recorrido_id),
    INDEX idx_categoria_id (categoria_id)
);

-- Tabla de hallazgos críticos
CREATE TABLE hallazgos_criticos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recorrido_id INT NOT NULL,
    categoria_id INT NOT NULL,
    categoria_nombre VARCHAR(255) NOT NULL,
    pregunta_numero VARCHAR(10) NOT NULL,
    descripcion TEXT NOT NULL,
    nivel_prioridad ENUM('alta', 'media', 'baja') NOT NULL,
    status ENUM('pendiente', 'en_proceso', 'resuelto') DEFAULT 'pendiente',
    fecha_limite DATE NULL,
    responsable VARCHAR(255) NULL,
    acciones_correctivas TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (recorrido_id) REFERENCES recorridos(id) ON DELETE CASCADE,
    INDEX idx_recorrido_id (recorrido_id),
    INDEX idx_nivel_prioridad (nivel_prioridad),
    INDEX idx_status (status)
);

-- Tabla de configuración de categorías y preguntas
CREATE TABLE configuracion_preguntas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    categoria_id INT NOT NULL,
    categoria_nombre VARCHAR(255) NOT NULL,
    pregunta_numero VARCHAR(10) NOT NULL,
    pregunta_texto TEXT NOT NULL,
    es_obligatoria BOOLEAN DEFAULT TRUE,
    requiere_foto_si_no BOOLEAN DEFAULT TRUE,
    orden_pregunta INT NOT NULL,
    activa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_categoria_pregunta (categoria_id, pregunta_numero),
    INDEX idx_categoria_orden (categoria_id, orden_pregunta)
);

-- Tabla de borradores (para respaldo)
CREATE TABLE borradores_recorrido (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    tour_type ENUM('emergency', 'scheduled') NOT NULL,
    session_data JSON NOT NULL,
    last_step INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_updated_at (updated_at)
);

-- Insertar configuración inicial de preguntas
INSERT INTO configuracion_preguntas (categoria_id, categoria_nombre, pregunta_numero, pregunta_texto, orden_pregunta) VALUES
-- Categoría 1: Política de Seguridad
(1, 'Política de Seguridad', '1.1', '¿Está disponible, visible y actualizada la Política de Seguridad?', 1),
(1, 'Política de Seguridad', '1.2', '¿Están visibles las 10 Reglas que salvan vidas?', 2),

-- Categoría 2: Rutas de Evacuación y Puntos de Reunión
(2, 'Rutas de Evacuación y Puntos de Reunión', '2.1', '¿Está visible el croquis o mapa de las ruta de evacuación y punto de reunión?', 1),
(2, 'Rutas de Evacuación y Puntos de Reunión', '2.2', '¿Se identifican señaléticas de ruta de evacuación en la instalación? (Flechas verdes en dirección a la ruta de evacuación)', 2),
(2, 'Rutas de Evacuación y Puntos de Reunión', '2.3', '¿Los edificios o instalaciones disponen de las salidas de emergencias? (Puerta identificada como salida de emergencia)', 3),
(2, 'Rutas de Evacuación y Puntos de Reunión', '2.4', '¿Se tienen señalados los puntos de reunión en caso de emergencia? (Área rotulada con circulo verde y leyenda punto de reunión)', 4),

-- Categoría 3: Condiciones de Seguridad en Áreas de Tránsito
(3, 'Condiciones de Seguridad en Áreas de Tránsito', '3.1', '¿Las áreas de tránsito interno (pasos peatonales, viabilidad) se encuentran libres de obstrucción?', 1),
(3, 'Condiciones de Seguridad en Áreas de Tránsito', '3.2', '¿Se dispone de señaléticas que indican el límite máximo de velocidad para vehículos?', 2),
(3, 'Condiciones de Seguridad en Áreas de Tránsito', '3.3', '¿Se encuentran identificadas las áreas de cruce de personal en calles internas de la instalación?', 3),
(3, 'Condiciones de Seguridad en Áreas de Tránsito', '3.4', '¿Se dispone de acceso en banquetas y área de oficinas para personal con capacidades diferentes?', 4),

-- Categoría 4: Contraincendio
(4, 'Contraincendio', '4.1', '¿Se dispone en lugares visibles el organigrama de la brigada contra incendios, de la instalación?', 1),
(4, 'Contraincendio', '4.2', '¿Se dispone de extintores contra incendios, vigentes e identificados en las estaciones para ese fin?', 2),
(4, 'Contraincendio', '4.3', '¿Se dispone de red contra incendio en el sitio de trabajo?', 3),
(4, 'Contraincendio', '4.4', '¿Se dispone de sistema de alarma audible de contraincendios en la instalación?', 4),
(4, 'Contraincendio', '4.5', '¿Se dispone de sistema de alarma visual de contraincendios en la instalación?', 5),
(4, 'Contraincendio', '4.6', '¿Se dispone de detectores de humo y calor en las instalaciones utilizadas como oficinas?', 6),

-- Categoría 5: Seguridad Eléctrica
(5, 'Seguridad Eléctrica', '5.1', '¿Se identifica visualmente daño físico a puertas o gabinete externo de los centros de cargas o tableros eléctricos?', 1),
(5, 'Seguridad Eléctrica', '5.2', '¿Se dispone de señalética de restringido el paso o acceso a personal no autorizado en el área de tableros eléctricos o transformadores?', 2),
(5, 'Seguridad Eléctrica', '5.3', '¿Se dispone de señaléticas de riesgo eléctrico donde se encuentran equipos energizados o centros de carga eléctrica?', 3),
(5, 'Seguridad Eléctrica', '5.4', '¿En áreas administrativas u operativas (almacén, patio de fabricación y/o talleres) se observan tomacorrientes con daños físicos?', 4),
(5, 'Seguridad Eléctrica', '5.5', '¿En áreas administrativas u operativas (almacén, patio de fabricación y/o talleres) se observan luminarias con daños físicos o fuera de servicio?', 5),

-- Categoría 6: Orden y Limpieza
(6, 'Orden y Limpieza', '6.1', '¿En áreas administrativas u operativas (almacén, patio de fabricación y/o talleres) se mantiene limpio y ordenado el sitio?', 1),
(6, 'Orden y Limpieza', '6.2', 'Almacén de materia prima y producto terminado ordenado', 2),
(6, 'Orden y Limpieza', '6.3', 'Limpieza de Techos', 3),
(6, 'Orden y Limpieza', '6.4', 'Limpieza de Paredes, Columnas y Estructuras Metálicas', 4),
(6, 'Orden y Limpieza', '6.5', 'Comedores en buen estado', 5),
(6, 'Orden y Limpieza', '6.6', '¿En áreas de tránsito externo a las áreas administrativas u operativas se mantiene limpio y ordenado el sitio?', 6),

-- Categoría 7: Equipo de Protección Personal
(7, 'Equipo de Protección Personal', '7.1', '¿En zonas de trabajo (almacén, patio de fabricación y/o talleres) se utiliza overol de trabajo, casco de protección, lentes de seguridad y calzado de seguridad adecuado a la actividad?', 1),
(7, 'Equipo de Protección Personal', '7.2', '¿En zonas de ruido (almacén, patio de fabricación y/o talleres) se utiliza protección auditiva?', 2);

-- Crear tabla de configuración de la aplicación
CREATE TABLE configuracion_app (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar configuración inicial
INSERT INTO configuracion_app (config_key, config_value, description) VALUES
('app_version', '1.0.0', 'Versión actual de la aplicación'),
('max_photo_size', '5242880', 'Tamaño máximo de foto en bytes (5MB)'),
('max_photos_per_question', '5', 'Número máximo de fotos por pregunta'),
('auto_save_interval', '30', 'Intervalo de guardado automático en segundos'),
('offline_sync_enabled', 'true', 'Habilitar sincronización offline'),
('emergency_notification_emails', '', 'Emails para notificaciones de emergencia'),
('require_location_gps', 'false', 'Requerir ubicación GPS para recorridos');

-- Crear índices adicionales para optimización
CREATE INDEX idx_recorridos_user_date ON recorridos(user_id, created_at);
CREATE INDEX idx_respuestas_categoria_respuesta ON respuestas_categorias(categoria_id, respuesta);
CREATE INDEX idx_fotos_recorrido_fecha ON fotos_recorrido(recorrido_id, created_at);
CREATE INDEX idx_hallazgos_fecha_prioridad ON hallazgos_criticos(created_at, nivel_prioridad);

-- Crear vistas útiles para reportes
CREATE VIEW vista_resumen_recorridos AS
SELECT 
    r.id,
    r.user_name,
    r.tour_type,
    r.location,
    r.division,
    r.business,
    r.status,
    r.total_questions,
    r.answered_questions,
    r.yes_count,
    r.no_count,
    r.na_count,
    r.created_at,
    r.completed_at,
    ROUND((r.answered_questions / r.total_questions) * 100, 2) as porcentaje_completado,
    COUNT(hc.id) as hallazgos_criticos_count
FROM recorridos r
LEFT JOIN hallazgos_criticos hc ON r.id = hc.recorrido_id
GROUP BY r.id;

CREATE VIEW vista_hallazgos_pendientes AS
SELECT 
    hc.*,
    r.user_name,
    r.location,
    r.division,
    r.tour_type,
    r.created_at as fecha_recorrido
FROM hallazgos_criticos hc
JOIN recorridos r ON hc.recorrido_id = r.id
WHERE hc.status = 'pendiente'
ORDER BY hc.nivel_prioridad DESC, hc.created_at ASC;

-- Crear función para calcular estadísticas de usuario
DELIMITER //
CREATE FUNCTION calcular_estadisticas_usuario(user_id_param INT)
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total_recorridos INT DEFAULT 0;
    DECLARE recorridos_mes INT DEFAULT 0;
    DECLARE ultimo_recorrido DATE DEFAULT NULL;
    DECLARE resultado JSON;
    
    SELECT COUNT(*) INTO total_recorridos
    FROM recorridos 
    WHERE user_id = user_id_param AND status = 'completed';
    
    SELECT COUNT(*) INTO recorridos_mes
    FROM recorridos 
    WHERE user_id = user_id_param 
    AND status = 'completed'
    AND MONTH(created_at) = MONTH(NOW()) 
    AND YEAR(created_at) = YEAR(NOW());
    
    SELECT DATE(created_at) INTO ultimo_recorrido
    FROM recorridos 
    WHERE user_id = user_id_param AND status = 'completed'
    ORDER BY created_at DESC 
    LIMIT 1;
    
    SET resultado = JSON_OBJECT(
        'total_recorridos', total_recorridos,
        'recorridos_mes', recorridos_mes,
        'ultimo_recorrido', ultimo_recorrido
    );
    
    RETURN resultado;
END //
DELIMITER ;

-- Crear procedimiento para limpiar borradores antiguos
DELIMITER //
CREATE PROCEDURE limpiar_borradores_antiguos()
BEGIN
    DELETE FROM borradores_recorrido 
    WHERE updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    DELETE FROM recorridos 
    WHERE status = 'draft' 
    AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END //
DELIMITER ;

-- Crear evento para ejecutar limpieza automática (opcional)
-- CREATE EVENT IF NOT EXISTS limpiar_borradores_evento
-- ON SCHEDULE EVERY 1 DAY
-- STARTS TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '02:00:00')
-- DO CALL limpiar_borradores_antiguos();

-- Crear tabla de logs para auditoría
CREATE TABLE logs_sistema (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NULL,
    record_id INT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_action (user_id, action),
    INDEX idx_created_at (created_at),
    INDEX idx_table_record (table_name, record_id)
);

-- Trigger para auditoría en recorridos
DELIMITER //
CREATE TRIGGER tr_recorridos_insert 
AFTER INSERT ON recorridos
FOR EACH ROW
BEGIN
    INSERT INTO logs_sistema (user_id, action, table_name, record_id, new_values)
    VALUES (NEW.user_id, 'INSERT', 'recorridos', NEW.id, 
            JSON_OBJECT('tour_type', NEW.tour_type, 'location', NEW.location, 'status', NEW.status));
END //

CREATE TRIGGER tr_recorridos_update 
AFTER UPDATE ON recorridos
FOR EACH ROW
BEGIN
    INSERT INTO logs_sistema (user_id, action, table_name, record_id, old_values, new_values)
    VALUES (NEW.user_id, 'UPDATE', 'recorridos', NEW.id,
            JSON_OBJECT('status', OLD.status, 'answered_questions', OLD.answered_questions),
            JSON_OBJECT('status', NEW.status, 'answered_questions', NEW.answered_questions));
END //
DELIMITER ;

-- Crear usuarios y permisos (ejemplo)
-- CREATE USER 'protexa_app'@'localhost' IDENTIFIED BY 'password_seguro_aqui';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON protexa_seguro.* TO 'protexa_app'@'localhost';
-- FLUSH PRIVILEGES;

-- Datos de ejemplo para testing (opcional)
-- INSERT INTO recorridos (user_id, user_name, tour_type, location, division, business, reason, status) VALUES
-- (1, 'Juan Pérez', 'scheduled', 'Planta Norte', 'produccion', 'manufactura', 'inspeccion_rutinaria_mensual', 'completed'),
-- (1, 'Juan Pérez', 'emergency', 'Almacén 3', 'almacenes', 'distribucion', 'condicion_insegura_identificada', 'completed'),
-- (2, 'María García', 'scheduled', 'Oficinas Centrales', 'oficinas', 'corporativo', 'auditoria_programada', 'in_progress');

-- Comentarios finales
/*
NOTAS IMPORTANTES PARA LA IMPLEMENTACIÓN:

1. SEGURIDAD:
   - Cambiar las contraseñas por defecto
   - Configurar SSL para conexiones de base de datos
   - Implementar backups regulares
   - Monitorear logs de sistema

2. PERFORMANCE:
   - Los índices están optimizados para consultas frecuentes
   - Considerar particionamiento si hay gran volumen de datos
   - Configurar limpieza automática de datos antiguos

3. MANTENIMIENTO:
   - Ejecutar ANALYZE TABLE mensualmente
   - Monitorear espacio de almacenamiento para fotos
   - Revisar logs de errores regularmente

4. ESCALABILIDAD:
   - La estructura soporta múltiples usuarios y ubicaciones
   - Se puede extender fácilmente para nuevas categorías
   - JSON fields permiten flexibilidad en configuraciones

5. INTEGRACIONES:
   - La tabla logs_sistema facilita auditorías
   - Las vistas simplifican reportes
   - Estructura compatible con herramientas de BI
*/