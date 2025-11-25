-- ============================================================================
-- MIGRACIÓN FASE 2A: SISTEMA DE CURSOS BÁSICO
-- ============================================================================
-- Fecha: 15 de Noviembre 2025
-- Descripción: Tablas para gestión de cursos, módulos, lecciones,
--              inscripciones, progreso y calificaciones
-- ============================================================================

USE formacion_empresarial;

-- ============================================================================
-- TABLA: categorias_cursos
-- Descripción: Categorías para organizar los cursos
-- ============================================================================
CREATE TABLE IF NOT EXISTS categorias_cursos (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    slug VARCHAR(120) UNIQUE NOT NULL,
    icono VARCHAR(100),
    color VARCHAR(7) DEFAULT '#667eea',
    orden INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_activo (activo),
    INDEX idx_slug (slug),
    INDEX idx_orden (orden)
) ENGINE=InnoDB COMMENT='Categorías de cursos disponibles';

-- ============================================================================
-- TABLA: cursos
-- Descripción: Cursos disponibles en la plataforma
-- ============================================================================
CREATE TABLE IF NOT EXISTS cursos (
    id_curso INT AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT NOT NULL,
    id_instructor INT NOT NULL,
    
    titulo VARCHAR(200) NOT NULL,
    slug VARCHAR(220) UNIQUE NOT NULL,
    descripcion TEXT,
    descripcion_corta VARCHAR(500),
    
    -- Imagen de portada
    imagen_portada VARCHAR(255),
    
    -- Detalles del curso
    nivel ENUM('principiante', 'intermedio', 'avanzado') DEFAULT 'principiante',
    duracion_estimada INT DEFAULT 0 COMMENT 'Duración en minutos',
    precio DECIMAL(10, 2) DEFAULT 0.00,
    
    -- Estado del curso
    estado ENUM('borrador', 'publicado', 'archivado') DEFAULT 'borrador',
    
    -- Estadísticas
    total_inscripciones INT DEFAULT 0,
    promedio_calificacion DECIMAL(3, 2) DEFAULT 0.00,
    total_calificaciones INT DEFAULT 0,
    
    -- Fechas
    fecha_publicacion DATETIME,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_categoria) REFERENCES categorias_cursos(id_categoria) ON DELETE RESTRICT,
    FOREIGN KEY (id_instructor) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    
    INDEX idx_categoria (id_categoria),
    INDEX idx_instructor (id_instructor),
    INDEX idx_estado (estado),
    INDEX idx_nivel (nivel),
    INDEX idx_slug (slug)
) ENGINE=InnoDB COMMENT='Cursos disponibles en la plataforma';

-- ============================================================================
-- TABLA: modulos
-- Descripción: Módulos que componen un curso
-- ============================================================================
CREATE TABLE IF NOT EXISTS modulos (
    id_modulo INT AUTO_INCREMENT PRIMARY KEY,
    id_curso INT NOT NULL,
    
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    orden INT DEFAULT 0,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    
    INDEX idx_curso (id_curso),
    INDEX idx_orden (orden)
) ENGINE=InnoDB COMMENT='Módulos de los cursos';

-- ============================================================================
-- TABLA: lecciones
-- Descripción: Lecciones dentro de cada módulo
-- ============================================================================
CREATE TABLE IF NOT EXISTS lecciones (
    id_leccion INT AUTO_INCREMENT PRIMARY KEY,
    id_modulo INT NOT NULL,
    
    titulo VARCHAR(200) NOT NULL,
    contenido LONGTEXT,
    
    -- Tipo de contenido
    tipo_contenido ENUM('texto', 'video', 'documento', 'enlace') DEFAULT 'texto',
    url_recurso VARCHAR(500) COMMENT 'URL de video, documento o enlace externo',
    
    orden INT DEFAULT 0,
    duracion_minutos INT DEFAULT 0,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_modulo) REFERENCES modulos(id_modulo) ON DELETE CASCADE,
    
    INDEX idx_modulo (id_modulo),
    INDEX idx_orden (orden)
) ENGINE=InnoDB COMMENT='Lecciones de cada módulo';

-- ============================================================================
-- TABLA: inscripciones
-- Descripción: Registro de usuarios inscritos en cursos
-- ============================================================================
CREATE TABLE IF NOT EXISTS inscripciones (
    id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_curso INT NOT NULL,
    
    -- Progreso
    porcentaje_avance DECIMAL(5, 2) DEFAULT 0.00,
    lecciones_completadas INT DEFAULT 0,
    tiempo_dedicado INT DEFAULT 0 COMMENT 'Tiempo en minutos',
    
    -- Fechas
    fecha_inscripcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_inicio DATETIME COMMENT 'Primera lección vista',
    fecha_finalizacion DATETIME COMMENT 'Todas las lecciones completadas',
    fecha_ultima_actividad DATETIME,
    
    -- Certificado
    certificado_generado BOOLEAN DEFAULT FALSE,
    fecha_certificado DATETIME,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    
    UNIQUE KEY unique_inscripcion (id_usuario, id_curso),
    INDEX idx_usuario (id_usuario),
    INDEX idx_curso (id_curso),
    INDEX idx_fecha_inscripcion (fecha_inscripcion)
) ENGINE=InnoDB COMMENT='Inscripciones de usuarios a cursos';

-- ============================================================================
-- TABLA: progreso_lecciones
-- Descripción: Seguimiento de lecciones completadas por usuario
-- ============================================================================
CREATE TABLE IF NOT EXISTS progreso_lecciones (
    id_progreso INT AUTO_INCREMENT PRIMARY KEY,
    id_inscripcion INT NOT NULL,
    id_leccion INT NOT NULL,
    
    completada BOOLEAN DEFAULT FALSE,
    tiempo_dedicado INT DEFAULT 0 COMMENT 'Tiempo en minutos',
    
    fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_completado DATETIME,
    
    FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id_inscripcion) ON DELETE CASCADE,
    FOREIGN KEY (id_leccion) REFERENCES lecciones(id_leccion) ON DELETE CASCADE,
    
    UNIQUE KEY unique_progreso (id_inscripcion, id_leccion),
    INDEX idx_inscripcion (id_inscripcion),
    INDEX idx_leccion (id_leccion),
    INDEX idx_completada (completada)
) ENGINE=InnoDB COMMENT='Progreso de lecciones por usuario';

-- ============================================================================
-- TABLA: calificaciones_cursos
-- Descripción: Calificaciones y reseñas de cursos por usuarios
-- ============================================================================
CREATE TABLE IF NOT EXISTS calificaciones_cursos (
    id_calificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_curso INT NOT NULL,
    
    calificacion INT NOT NULL CHECK (calificacion BETWEEN 1 AND 5),
    comentario TEXT,
    
    -- Moderación
    aprobado BOOLEAN DEFAULT TRUE,
    fecha_moderacion DATETIME,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    
    UNIQUE KEY unique_calificacion (id_usuario, id_curso),
    INDEX idx_usuario (id_usuario),
    INDEX idx_curso (id_curso),
    INDEX idx_calificacion (calificacion),
    INDEX idx_aprobado (aprobado)
) ENGINE=InnoDB COMMENT='Calificaciones y reseñas de cursos';

-- ============================================================================
-- TRIGGERS: Actualizar estadísticas automáticamente
-- ============================================================================

-- Trigger para actualizar promedio de calificación al insertar
DELIMITER //
CREATE TRIGGER after_calificacion_insert
AFTER INSERT ON calificaciones_cursos
FOR EACH ROW
BEGIN
    UPDATE cursos
    SET total_calificaciones = (
        SELECT COUNT(*) FROM calificaciones_cursos WHERE id_curso = NEW.id_curso AND aprobado = TRUE
    ),
    promedio_calificacion = (
        SELECT AVG(calificacion) FROM calificaciones_cursos WHERE id_curso = NEW.id_curso AND aprobado = TRUE
    )
    WHERE id_curso = NEW.id_curso;
END//

-- Trigger para actualizar promedio de calificación al actualizar
CREATE TRIGGER after_calificacion_update
AFTER UPDATE ON calificaciones_cursos
FOR EACH ROW
BEGIN
    UPDATE cursos
    SET total_calificaciones = (
        SELECT COUNT(*) FROM calificaciones_cursos WHERE id_curso = NEW.id_curso AND aprobado = TRUE
    ),
    promedio_calificacion = (
        SELECT AVG(calificacion) FROM calificaciones_cursos WHERE id_curso = NEW.id_curso AND aprobado = TRUE
    )
    WHERE id_curso = NEW.id_curso;
END//

-- Trigger para actualizar total de inscripciones
CREATE TRIGGER after_inscripcion_insert
AFTER INSERT ON inscripciones
FOR EACH ROW
BEGIN
    UPDATE cursos
    SET total_inscripciones = (
        SELECT COUNT(*) FROM inscripciones WHERE id_curso = NEW.id_curso
    )
    WHERE id_curso = NEW.id_curso;
END//

DELIMITER ;

-- ============================================================================
-- DATOS DE PRUEBA (SEED)
-- ============================================================================

-- Categorías de ejemplo
INSERT INTO categorias_cursos (nombre, descripcion, slug, icono, color, orden, activo) VALUES
('Emprendimiento', 'Cursos sobre cómo iniciar y gestionar tu propio negocio', 'emprendimiento', 'rocket', '#667eea', 1, TRUE),
('Finanzas', 'Gestión financiera y contabilidad para empresas', 'finanzas', 'dollar-sign', '#4caf50', 2, TRUE),
('Marketing Digital', 'Estrategias de marketing online y redes sociales', 'marketing-digital', 'bullhorn', '#f44336', 3, TRUE),
('Liderazgo', 'Desarrollo de habilidades de liderazgo empresarial', 'liderazgo', 'users', '#ff9800', 4, TRUE),
('Tecnología', 'Herramientas tecnológicas para empresas', 'tecnologia', 'laptop', '#2196f3', 5, TRUE);

-- Verificación
SELECT 'Migración Fase 2A completada exitosamente' AS resultado;
SELECT COUNT(*) AS categorias_creadas FROM categorias_cursos;

-- ============================================================================
-- ROLLBACK (en caso de necesitar revertir)
-- ============================================================================
/*
DROP TRIGGER IF EXISTS after_calificacion_insert;
DROP TRIGGER IF EXISTS after_calificacion_update;
DROP TRIGGER IF EXISTS after_inscripcion_insert;

DROP TABLE IF EXISTS calificaciones_cursos;
DROP TABLE IF EXISTS progreso_lecciones;
DROP TABLE IF EXISTS inscripciones;
DROP TABLE IF EXISTS lecciones;
DROP TABLE IF EXISTS modulos;
DROP TABLE IF EXISTS cursos;
DROP TABLE IF EXISTS categorias_cursos;
*/
