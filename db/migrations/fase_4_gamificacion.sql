-- ============================================================================
-- MIGRACIÓN FASE 4: GAMIFICACIÓN Y ENGAGEMENT
-- ============================================================================
-- Tablas para sistema de puntos, logros, rankings, rachas y notificaciones
-- ============================================================================

USE formacion_empresarial;

-- ============================================================================
-- TABLA: puntos_usuario
-- Registro de puntos acumulados por usuario
-- ============================================================================
CREATE TABLE IF NOT EXISTS puntos_usuario (
    id_puntos INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    puntos_totales INT DEFAULT 0,
    puntos_disponibles INT DEFAULT 0,
    puntos_gastados INT DEFAULT 0,
    nivel INT DEFAULT 1,
    experiencia INT DEFAULT 0,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario (id_usuario),
    INDEX idx_puntos_totales (puntos_totales),
    INDEX idx_nivel (nivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: transacciones_puntos
-- Historial de transacciones de puntos
-- ============================================================================
CREATE TABLE IF NOT EXISTS transacciones_puntos (
    id_transaccion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    tipo_transaccion ENUM('ganancia', 'gasto', 'ajuste') NOT NULL,
    puntos INT NOT NULL,
    concepto VARCHAR(255) NOT NULL,
    referencia_tipo ENUM('curso', 'leccion', 'diagnostico', 'evaluacion', 'logro', 'racha', 'manual') NOT NULL,
    referencia_id INT NULL,
    fecha_transaccion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    INDEX idx_usuario (id_usuario),
    INDEX idx_fecha (fecha_transaccion),
    INDEX idx_tipo (tipo_transaccion),
    INDEX idx_referencia (referencia_tipo, referencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: logros
-- Catálogo de logros disponibles
-- ============================================================================
CREATE TABLE IF NOT EXISTS logros (
    id_logro INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    icono VARCHAR(50) DEFAULT '🏆',
    categoria ENUM('cursos', 'diagnosticos', 'evaluaciones', 'social', 'tiempo', 'especial') NOT NULL,
    puntos_recompensa INT DEFAULT 0,
    condicion_tipo ENUM('cantidad', 'porcentaje', 'consecutivo', 'especifico', 'combinado') NOT NULL,
    condicion_valor VARCHAR(500) NOT NULL COMMENT 'JSON con configuración de condición',
    es_secreto TINYINT(1) DEFAULT 0,
    es_repetible TINYINT(1) DEFAULT 0,
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_codigo (codigo),
    INDEX idx_categoria (categoria),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: logros_usuarios
-- Logros desbloqueados por usuarios
-- ============================================================================
CREATE TABLE IF NOT EXISTS logros_usuarios (
    id_logro_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_logro INT NOT NULL,
    fecha_obtencion DATETIME DEFAULT CURRENT_TIMESTAMP,
    visto TINYINT(1) DEFAULT 0,
    fecha_visto DATETIME NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_logro) REFERENCES logros(id_logro) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_logro (id_usuario, id_logro, fecha_obtencion),
    INDEX idx_usuario (id_usuario),
    INDEX idx_logro (id_logro),
    INDEX idx_fecha (fecha_obtencion),
    INDEX idx_visto (visto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: rachas_usuario
-- Tracking de rachas de actividad
-- ============================================================================
CREATE TABLE IF NOT EXISTS rachas_usuario (
    id_racha INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    racha_actual INT DEFAULT 0,
    racha_maxima INT DEFAULT 0,
    ultima_actividad DATE NULL,
    fecha_inicio_racha DATE NULL,
    racha_congelada TINYINT(1) DEFAULT 0 COMMENT 'Protección temporal de racha',
    congelaciones_disponibles INT DEFAULT 0,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario (id_usuario),
    INDEX idx_racha_actual (racha_actual),
    INDEX idx_ultima_actividad (ultima_actividad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: notificaciones
-- Sistema de notificaciones
-- ============================================================================
CREATE TABLE IF NOT EXISTS notificaciones (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    tipo ENUM('logro', 'curso', 'evaluacion', 'certificado', 'mentoria', 'sistema', 'racha', 'puntos') NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    icono VARCHAR(50) DEFAULT '🔔',
    url VARCHAR(500) NULL COMMENT 'URL de destino al hacer clic',
    referencia_tipo VARCHAR(50) NULL,
    referencia_id INT NULL,
    leida TINYINT(1) DEFAULT 0,
    fecha_lectura DATETIME NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    INDEX idx_usuario (id_usuario),
    INDEX idx_tipo (tipo),
    INDEX idx_leida (leida),
    INDEX idx_fecha (fecha_creacion),
    INDEX idx_usuario_leida (id_usuario, leida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: preferencias_notificacion
-- Configuración de notificaciones por usuario
-- ============================================================================
CREATE TABLE IF NOT EXISTS preferencias_notificacion (
    id_preferencia INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    tipo_notificacion ENUM('logro', 'curso', 'evaluacion', 'certificado', 'mentoria', 'sistema', 'racha', 'puntos') NOT NULL,
    notificar_app TINYINT(1) DEFAULT 1,
    notificar_email TINYINT(1) DEFAULT 0,
    notificar_push TINYINT(1) DEFAULT 0,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_tipo (id_usuario, tipo_notificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- VISTA: ranking_usuarios
-- Vista para leaderboard optimizada
-- ============================================================================
CREATE OR REPLACE VIEW ranking_usuarios AS
SELECT 
    u.id_usuario,
    u.nombre,
    u.apellido,
    u.foto_perfil,
    u.tipo_usuario,
    COALESCE(p.puntos_totales, 0) as puntos_totales,
    COALESCE(p.nivel, 1) as nivel,
    COALESCE(r.racha_actual, 0) as racha_actual,
    COALESCE(r.racha_maxima, 0) as racha_maxima,
    COUNT(DISTINCT lu.id_logro) as total_logros,
    COUNT(DISTINCT i.id_inscripcion) as cursos_inscritos,
    COUNT(DISTINCT CASE WHEN pl.completado = 1 THEN pl.id_leccion END) as lecciones_completadas,
    RANK() OVER (ORDER BY COALESCE(p.puntos_totales, 0) DESC) as posicion_global
FROM usuarios u
LEFT JOIN puntos_usuario p ON u.id_usuario = p.id_usuario
LEFT JOIN rachas_usuario r ON u.id_usuario = r.id_usuario
LEFT JOIN logros_usuarios lu ON u.id_usuario = lu.id_usuario
LEFT JOIN inscripciones i ON u.id_usuario = i.id_usuario
LEFT JOIN progreso_lecciones pl ON u.id_usuario = pl.id_usuario
WHERE u.estado = 'activo'
GROUP BY u.id_usuario, u.nombre, u.apellido, u.foto_perfil, u.tipo_usuario, 
         p.puntos_totales, p.nivel, r.racha_actual, r.racha_maxima;

-- ============================================================================
-- DATOS INICIALES: Configuración de reglas de puntos
-- ============================================================================
-- Insertar configuración por defecto de preferencias de notificación para usuarios existentes
INSERT IGNORE INTO preferencias_notificacion (id_usuario, tipo_notificacion, notificar_app, notificar_email, notificar_push)
SELECT u.id_usuario, tipo, 1, 0, 0
FROM usuarios u
CROSS JOIN (
    SELECT 'logro' as tipo UNION ALL
    SELECT 'curso' UNION ALL
    SELECT 'evaluacion' UNION ALL
    SELECT 'certificado' UNION ALL
    SELECT 'mentoria' UNION ALL
    SELECT 'sistema' UNION ALL
    SELECT 'racha' UNION ALL
    SELECT 'puntos'
) tipos;

-- ============================================================================
-- DATOS INICIALES: Logros básicos
-- ============================================================================
INSERT INTO logros (codigo, nombre, descripcion, icono, categoria, puntos_recompensa, condicion_tipo, condicion_valor, es_secreto, orden) VALUES
-- Logros de Cursos
('primer_curso', 'Primer Paso', 'Inscríbete en tu primer curso', '🎓', 'cursos', 10, 'cantidad', '{"tipo":"inscripcion","cantidad":1}', 0, 1),
('cursos_5', 'Estudiante Dedicado', 'Inscríbete en 5 cursos', '📚', 'cursos', 50, 'cantidad', '{"tipo":"inscripcion","cantidad":5}', 0, 2),
('cursos_10', 'Aprendiz Voraz', 'Inscríbete en 10 cursos', '🎯', 'cursos', 100, 'cantidad', '{"tipo":"inscripcion","cantidad":10}', 0, 3),
('primer_curso_completado', 'Graduado', 'Completa tu primer curso', '🎉', 'cursos', 50, 'cantidad', '{"tipo":"curso_completado","cantidad":1}', 0, 4),
('cursos_completados_5', 'Maestro en Formación', 'Completa 5 cursos', '🌟', 'cursos', 200, 'cantidad', '{"tipo":"curso_completado","cantidad":5}', 0, 5),

-- Logros de Diagnósticos
('primer_diagnostico', 'Autoconocimiento', 'Realiza tu primer diagnóstico empresarial', '🔍', 'diagnosticos', 25, 'cantidad', '{"tipo":"diagnostico","cantidad":1}', 0, 10),
('diagnosticos_3', 'Analista Empresarial', 'Realiza 3 diagnósticos', '📊', 'diagnosticos', 75, 'cantidad', '{"tipo":"diagnostico","cantidad":3}', 0, 11),
('diagnostico_perfecta', 'Experto Diagnosticado', 'Obtén puntuación perfecta en un diagnóstico', '💎', 'diagnosticos', 150, 'especifico', '{"tipo":"diagnostico","puntuacion":100}', 0, 12),

-- Logros de Evaluaciones
('primera_evaluacion', 'A Prueba', 'Completa tu primera evaluación', '✍️', 'evaluaciones', 20, 'cantidad', '{"tipo":"evaluacion","cantidad":1}', 0, 20),
('evaluaciones_aprobadas_10', 'Examinado Exitoso', 'Aprueba 10 evaluaciones', '✅', 'evaluaciones', 100, 'cantidad', '{"tipo":"evaluacion_aprobada","cantidad":10}', 0, 21),
('evaluacion_perfecta', 'Perfección Académica', 'Obtén 100% en una evaluación', '🏆', 'evaluaciones', 200, 'especifico', '{"tipo":"evaluacion","puntuacion":100}', 0, 22),

-- Logros de Tiempo/Rachas
('racha_7', 'Semana Constante', 'Mantén una racha de 7 días', '🔥', 'tiempo', 50, 'consecutivo', '{"tipo":"racha","dias":7}', 0, 30),
('racha_30', 'Mes Inquebrantable', 'Mantén una racha de 30 días', '🔥🔥', 'tiempo', 300, 'consecutivo', '{"tipo":"racha","dias":30}', 0, 31),
('racha_100', 'Leyenda de Constancia', 'Mantén una racha de 100 días', '🔥🔥🔥', 'tiempo', 1000, 'consecutivo', '{"tipo":"racha","dias":100}', 1, 32),

-- Logros Especiales
('certificado_primero', 'Certificado', 'Obtén tu primer certificado', '📜', 'especial', 100, 'cantidad', '{"tipo":"certificado","cantidad":1}', 0, 40),
('madrugador', 'Madrugador', 'Completa una lección antes de las 6 AM', '🌅', 'especial', 50, 'especifico', '{"tipo":"horario","hora_max":6}', 1, 41),
('nocturno', 'búho Nocturno', 'Completa una lección después de las 11 PM', '🦉', 'especial', 50, 'especifico', '{"tipo":"horario","hora_min":23}', 1, 42);

-- ============================================================================
-- TRIGGERS: Actualización automática de puntos
-- ============================================================================

DELIMITER //

-- Trigger para inicializar puntos de nuevo usuario
CREATE TRIGGER IF NOT EXISTS after_usuario_insert
AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
    INSERT INTO puntos_usuario (id_usuario, puntos_totales, puntos_disponibles, nivel)
    VALUES (NEW.id_usuario, 0, 0, 1);
    
    INSERT INTO rachas_usuario (id_usuario, racha_actual, racha_maxima)
    VALUES (NEW.id_usuario, 0, 0);
END//

-- Trigger para actualizar puntos al insertar transacción
CREATE TRIGGER IF NOT EXISTS after_transaccion_puntos_insert
AFTER INSERT ON transacciones_puntos
FOR EACH ROW
BEGIN
    IF NEW.tipo_transaccion = 'ganancia' THEN
        UPDATE puntos_usuario 
        SET puntos_totales = puntos_totales + NEW.puntos,
            puntos_disponibles = puntos_disponibles + NEW.puntos,
            experiencia = experiencia + NEW.puntos
        WHERE id_usuario = NEW.id_usuario;
    ELSEIF NEW.tipo_transaccion = 'gasto' THEN
        UPDATE puntos_usuario 
        SET puntos_disponibles = puntos_disponibles - NEW.puntos,
            puntos_gastados = puntos_gastados + NEW.puntos
        WHERE id_usuario = NEW.id_usuario;
    END IF;
    
    -- Calcular nivel basado en experiencia
    UPDATE puntos_usuario 
    SET nivel = FLOOR(SQRT(experiencia / 100)) + 1
    WHERE id_usuario = NEW.id_usuario;
END//

DELIMITER ;

-- ============================================================================
-- ÍNDICES ADICIONALES PARA RENDIMIENTO
-- ============================================================================
-- Ya están definidos en las tablas

-- ============================================================================
-- COMENTARIOS Y DOCUMENTACIÓN
-- ============================================================================
ALTER TABLE puntos_usuario COMMENT = 'Puntos acumulados por cada usuario';
ALTER TABLE transacciones_puntos COMMENT = 'Historial de todas las transacciones de puntos';
ALTER TABLE logros COMMENT = 'Catálogo de logros disponibles en el sistema';
ALTER TABLE logros_usuarios COMMENT = 'Registro de logros desbloqueados por usuarios';
ALTER TABLE rachas_usuario COMMENT = 'Tracking de rachas de actividad diaria';
ALTER TABLE notificaciones COMMENT = 'Sistema de notificaciones en app';
ALTER TABLE preferencias_notificacion COMMENT = 'Configuración de notificaciones por usuario';

SELECT 'Migración Fase 4 completada exitosamente' as resultado;
