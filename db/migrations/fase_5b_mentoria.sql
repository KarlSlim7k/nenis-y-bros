-- ============================================================================
-- MIGRACIÓN FASE 5B: SISTEMA DE MENTORÍA Y ASISTENTE VIRTUAL (MentorIA)
-- ============================================================================
-- Fecha: 18 de Noviembre 2025
-- Descripción: Sistema de chat en tiempo real entre instructores y alumnos
--              con respaldo de asistente virtual con IA (MentorIA)
-- ============================================================================

USE formacion_empresarial;

-- ============================================================================
-- TABLA: conversaciones
-- Gestiona las conversaciones entre alumnos e instructores
-- ============================================================================
CREATE TABLE IF NOT EXISTS conversaciones (
    id_conversacion INT PRIMARY KEY AUTO_INCREMENT,
    id_curso INT NOT NULL,
    id_alumno INT NOT NULL,
    id_instructor INT NOT NULL,
    tipo_conversacion ENUM('instructor', 'mentoria') DEFAULT 'instructor',
    estado ENUM('activa', 'archivada') DEFAULT 'activa',
    ultimo_mensaje_fecha DATETIME,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    FOREIGN KEY (id_alumno) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_instructor) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    
    INDEX idx_alumno (id_alumno),
    INDEX idx_instructor (id_instructor),
    INDEX idx_curso (id_curso),
    INDEX idx_estado (estado),
    INDEX idx_tipo (tipo_conversacion),
    INDEX idx_ultimo_mensaje (ultimo_mensaje_fecha),
    
    -- Prevenir conversaciones duplicadas
    UNIQUE KEY unique_conversacion (id_curso, id_alumno, id_instructor, tipo_conversacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: mensajes
-- Almacena todos los mensajes de las conversaciones
-- ============================================================================
CREATE TABLE IF NOT EXISTS mensajes (
    id_mensaje INT PRIMARY KEY AUTO_INCREMENT,
    id_conversacion INT NOT NULL,
    id_remitente INT,  -- NULL si es MentorIA
    remitente_tipo ENUM('alumno', 'instructor', 'mentoria') NOT NULL,
    contenido TEXT NOT NULL,
    tipo_mensaje ENUM('texto', 'archivo', 'sistema') DEFAULT 'texto',
    leido BOOLEAN DEFAULT FALSE,
    fecha_leido DATETIME,
    metadata JSON,  -- Para adjuntos, referencias, etc.
    fecha_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_conversacion) REFERENCES conversaciones(id_conversacion) ON DELETE CASCADE,
    FOREIGN KEY (id_remitente) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    
    INDEX idx_conversacion (id_conversacion),
    INDEX idx_fecha (fecha_envio),
    INDEX idx_leido (leido),
    INDEX idx_remitente (id_remitente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: disponibilidad_instructores
-- Horarios de disponibilidad de instructores
-- ============================================================================
CREATE TABLE IF NOT EXISTS disponibilidad_instructores (
    id_disponibilidad INT PRIMARY KEY AUTO_INCREMENT,
    id_instructor INT NOT NULL,
    dia_semana TINYINT NOT NULL,  -- 0=Domingo, 1=Lunes, 2=Martes... 6=Sábado
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_instructor) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    
    UNIQUE KEY unique_instructor_dia (id_instructor, dia_semana, hora_inicio, hora_fin),
    INDEX idx_instructor (id_instructor),
    INDEX idx_dia (dia_semana),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: estado_presencia
-- Estado en tiempo real de los usuarios (en línea, ausente, etc.)
-- ============================================================================
CREATE TABLE IF NOT EXISTS estado_presencia (
    id_usuario INT PRIMARY KEY,
    estado ENUM('en_linea', 'ausente', 'ocupado', 'desconectado') DEFAULT 'desconectado',
    ultima_actividad DATETIME,
    mensaje_estado VARCHAR(100),  -- Ej: "Volveré en 30 min"
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    
    INDEX idx_estado (estado),
    INDEX idx_ultima_actividad (ultima_actividad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: mentoria_contexto
-- Contexto y métricas de las conversaciones con MentorIA
-- ============================================================================
CREATE TABLE IF NOT EXISTS mentoria_contexto (
    id_contexto INT PRIMARY KEY AUTO_INCREMENT,
    id_conversacion INT NOT NULL,
    prompt_sistema TEXT,  -- Contexto enviado a la IA
    tokens_usados INT DEFAULT 0,
    costo_estimado DECIMAL(10,4) DEFAULT 0,
    modelo_ia VARCHAR(50),  -- gpt-4, claude-3.5-sonnet, gemini-pro, etc.
    temperatura DECIMAL(3,2) DEFAULT 0.7,
    satisfaccion_usuario TINYINT,  -- 1-5, NULL si no calificado
    feedback_usuario TEXT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_conversacion) REFERENCES conversaciones(id_conversacion) ON DELETE CASCADE,
    
    INDEX idx_conversacion (id_conversacion),
    INDEX idx_modelo (modelo_ia),
    INDEX idx_fecha (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TRIGGERS: Actualización automática de último mensaje
-- ============================================================================

DELIMITER $$

DROP TRIGGER IF EXISTS after_mensaje_insert$$
CREATE TRIGGER after_mensaje_insert
AFTER INSERT ON mensajes
FOR EACH ROW
BEGIN
    -- Actualizar fecha del último mensaje en la conversación
    UPDATE conversaciones 
    SET ultimo_mensaje_fecha = NEW.fecha_envio
    WHERE id_conversacion = NEW.id_conversacion;
END$$

DELIMITER ;

-- ============================================================================
-- VISTA: conversaciones_completas
-- Vista optimizada con toda la información de conversaciones
-- ============================================================================

CREATE OR REPLACE VIEW vista_conversaciones_completas AS
SELECT 
    c.id_conversacion,
    c.tipo_conversacion,
    c.estado,
    c.ultimo_mensaje_fecha,
    c.fecha_creacion,
    
    -- Información del curso
    cu.id_curso,
    cu.titulo AS curso_titulo,
    cu.imagen_portada AS curso_imagen,
    
    -- Información del alumno
    a.id_usuario AS id_alumno,
    a.nombre AS alumno_nombre,
    a.email AS alumno_email,
    a.foto_perfil AS alumno_foto,
    
    -- Información del instructor
    i.id_usuario AS id_instructor,
    i.nombre AS instructor_nombre,
    i.email AS instructor_email,
    i.foto_perfil AS instructor_foto,
    
    -- Estado de presencia del instructor
    ep.estado AS instructor_estado,
    ep.ultima_actividad AS instructor_ultima_actividad,
    ep.mensaje_estado AS instructor_mensaje_estado,
    
    -- Contar mensajes no leídos por alumno
    (SELECT COUNT(*) FROM mensajes m 
     WHERE m.id_conversacion = c.id_conversacion 
     AND m.remitente_tipo = 'instructor' 
     AND m.leido = FALSE) AS mensajes_no_leidos_alumno,
    
    -- Contar mensajes no leídos por instructor
    (SELECT COUNT(*) FROM mensajes m 
     WHERE m.id_conversacion = c.id_conversacion 
     AND m.remitente_tipo = 'alumno' 
     AND m.leido = FALSE) AS mensajes_no_leidos_instructor,
    
    -- Último mensaje
    (SELECT m.contenido FROM mensajes m 
     WHERE m.id_conversacion = c.id_conversacion 
     ORDER BY m.fecha_envio DESC LIMIT 1) AS ultimo_mensaje,
    
    -- Remitente del último mensaje
    (SELECT m.remitente_tipo FROM mensajes m 
     WHERE m.id_conversacion = c.id_conversacion 
     ORDER BY m.fecha_envio DESC LIMIT 1) AS ultimo_mensaje_remitente,
    
    -- Total de mensajes
    (SELECT COUNT(*) FROM mensajes m 
     WHERE m.id_conversacion = c.id_conversacion) AS total_mensajes
    
FROM conversaciones c
INNER JOIN cursos cu ON c.id_curso = cu.id_curso
INNER JOIN usuarios a ON c.id_alumno = a.id_usuario
INNER JOIN usuarios i ON c.id_instructor = i.id_usuario
LEFT JOIN estado_presencia ep ON i.id_usuario = ep.id_usuario;

-- ============================================================================
-- STORED PROCEDURE: Marcar mensajes como leídos
-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_marcar_mensajes_leidos$$

CREATE PROCEDURE sp_marcar_mensajes_leidos(
    IN p_id_conversacion INT,
    IN p_id_usuario INT,
    IN p_rol VARCHAR(20)  -- 'alumno' o 'instructor'
)
BEGIN
    DECLARE remitente_tipo_opuesto VARCHAR(20);
    
    -- Determinar el tipo de remitente opuesto
    IF p_rol = 'alumno' THEN
        SET remitente_tipo_opuesto = 'instructor';
    ELSE
        SET remitente_tipo_opuesto = 'alumno';
    END IF;
    
    -- Marcar como leídos los mensajes del remitente opuesto
    UPDATE mensajes
    SET leido = TRUE,
        fecha_leido = NOW()
    WHERE id_conversacion = p_id_conversacion
      AND remitente_tipo = remitente_tipo_opuesto
      AND leido = FALSE;
    
    SELECT ROW_COUNT() AS mensajes_marcados;
END$$

DELIMITER ;

-- ============================================================================
-- STORED PROCEDURE: Limpiar mensajes antiguos (mantenimiento)
-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_limpiar_mensajes_antiguos$$

CREATE PROCEDURE sp_limpiar_mensajes_antiguos(
    IN p_dias_antiguedad INT
)
BEGIN
    -- Eliminar mensajes de conversaciones archivadas y antiguas
    DELETE m FROM mensajes m
    INNER JOIN conversaciones c ON m.id_conversacion = c.id_conversacion
    WHERE c.estado = 'archivada'
      AND m.fecha_envio < DATE_SUB(NOW(), INTERVAL p_dias_antiguedad DAY);
    
    SELECT ROW_COUNT() AS mensajes_eliminados;
END$$

DELIMITER ;

-- ============================================================================
-- DATOS INICIALES: Configuración por defecto
-- ============================================================================

-- Insertar estados de presencia para usuarios existentes que no lo tengan
INSERT INTO estado_presencia (id_usuario, estado, ultima_actividad)
SELECT id_usuario, 'desconectado', NOW()
FROM usuarios
WHERE id_usuario NOT IN (SELECT id_usuario FROM estado_presencia);

-- ============================================================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ============================================================================

-- Índice compuesto para búsquedas frecuentes
ALTER TABLE conversaciones 
ADD INDEX idx_alumno_estado (id_alumno, estado);

ALTER TABLE conversaciones 
ADD INDEX idx_instructor_estado (id_instructor, estado);

-- Índice para ordenar mensajes
ALTER TABLE mensajes 
ADD INDEX idx_conversacion_fecha (id_conversacion, fecha_envio DESC);

-- ============================================================================
-- COMENTARIOS EN TABLAS Y COLUMNAS
-- ============================================================================

ALTER TABLE conversaciones COMMENT = 'Conversaciones entre alumnos e instructores por curso';
ALTER TABLE mensajes COMMENT = 'Mensajes individuales de cada conversación';
ALTER TABLE disponibilidad_instructores COMMENT = 'Horarios de disponibilidad de instructores';
ALTER TABLE estado_presencia COMMENT = 'Estado en tiempo real de usuarios (en línea, ausente, etc.)';
ALTER TABLE mentoria_contexto COMMENT = 'Contexto y métricas de conversaciones con MentorIA';

-- ============================================================================
-- VERIFICACIÓN DE TABLAS CREADAS
-- ============================================================================

SELECT 
    TABLE_NAME, 
    TABLE_ROWS, 
    CREATE_TIME,
    TABLE_COMMENT
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'formacion_empresarial'
  AND TABLE_NAME IN (
      'conversaciones', 
      'mensajes', 
      'disponibilidad_instructores',
      'estado_presencia',
      'mentoria_contexto'
  )
ORDER BY TABLE_NAME;

-- ============================================================================
-- FIN DE LA MIGRACIÓN
-- ============================================================================
