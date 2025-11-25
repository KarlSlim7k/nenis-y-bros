-- ============================================================================
-- MIGRACIÓN: FASE 6.1 - BIBLIOTECA DE RECURSOS
-- ============================================================================
-- Sistema de gestión de recursos descargables con categorización,
-- etiquetado, tracking de descargas y estadísticas
-- ============================================================================

USE formacion_empresarial;

-- ============================================================================
-- TABLA: categorias_recursos
-- ============================================================================
CREATE TABLE IF NOT EXISTS categorias_recursos (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    descripcion TEXT,
    icono VARCHAR(50) DEFAULT 'folder',
    color VARCHAR(7) DEFAULT '#6366f1',
    orden INT DEFAULT 0,
    total_recursos INT DEFAULT 0,
    activa BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_activa (activa),
    INDEX idx_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: recursos
-- ============================================================================
CREATE TABLE IF NOT EXISTS recursos (
    id_recurso INT AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT NOT NULL,
    id_autor INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(300) NOT NULL UNIQUE,
    descripcion TEXT NOT NULL,
    tipo_recurso ENUM('articulo', 'ebook', 'plantilla', 'herramienta', 'video', 'infografia', 'podcast') NOT NULL DEFAULT 'articulo',
    tipo_acceso ENUM('gratuito', 'premium', 'suscripcion') NOT NULL DEFAULT 'gratuito',
    
    -- Archivo
    archivo_url VARCHAR(500),
    archivo_nombre VARCHAR(255),
    archivo_tipo VARCHAR(100),
    archivo_tamanio BIGINT UNSIGNED,
    
    -- Contenido adicional
    contenido_texto LONGTEXT,
    contenido_html LONGTEXT,
    url_externo VARCHAR(500),
    duracion_minutos INT UNSIGNED,
    
    -- Imagen y multimedia
    imagen_portada VARCHAR(500),
    imagen_preview VARCHAR(500),
    video_preview VARCHAR(500),
    
    -- Metadatos
    nivel ENUM('principiante', 'intermedio', 'avanzado') DEFAULT 'principiante',
    idioma VARCHAR(10) DEFAULT 'es',
    formato VARCHAR(50),
    licencia VARCHAR(100) DEFAULT 'Uso educativo',
    
    -- Estadísticas
    total_descargas INT UNSIGNED DEFAULT 0,
    total_vistas INT UNSIGNED DEFAULT 0,
    calificacion_promedio DECIMAL(3,2) DEFAULT 0.00,
    total_calificaciones INT UNSIGNED DEFAULT 0,
    
    -- Estado y orden
    estado ENUM('borrador', 'publicado', 'archivado') DEFAULT 'borrador',
    destacado BOOLEAN DEFAULT FALSE,
    orden INT UNSIGNED DEFAULT 0,
    
    -- Fechas
    fecha_publicacion DATETIME,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_categoria) REFERENCES categorias_recursos(id_categoria) ON DELETE RESTRICT,
    FOREIGN KEY (id_autor) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    
    INDEX idx_categoria (id_categoria),
    INDEX idx_autor (id_autor),
    INDEX idx_tipo_recurso (tipo_recurso),
    INDEX idx_tipo_acceso (tipo_acceso),
    INDEX idx_estado (estado),
    INDEX idx_destacado (destacado),
    INDEX idx_fecha_publicacion (fecha_publicacion),
    INDEX idx_nivel (nivel),
    FULLTEXT idx_busqueda (titulo, descripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: etiquetas_recursos
-- ============================================================================
CREATE TABLE IF NOT EXISTS etiquetas_recursos (
    id_etiqueta INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(60) NOT NULL UNIQUE,
    total_usos INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_total_usos (total_usos DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: recursos_etiquetas (Many-to-Many)
-- ============================================================================
CREATE TABLE IF NOT EXISTS recursos_etiquetas (
    id_recurso INT NOT NULL,
    id_etiqueta INT NOT NULL,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_recurso, id_etiqueta),
    FOREIGN KEY (id_recurso) REFERENCES recursos(id_recurso) ON DELETE CASCADE,
    FOREIGN KEY (id_etiqueta) REFERENCES etiquetas_recursos(id_etiqueta) ON DELETE CASCADE,
    INDEX idx_etiqueta (id_etiqueta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: descargas_recursos
-- ============================================================================
CREATE TABLE IF NOT EXISTS descargas_recursos (
    id_descarga INT AUTO_INCREMENT PRIMARY KEY,
    id_recurso INT NOT NULL,
    id_usuario INT NOT NULL,
    fecha_descarga TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (id_recurso) REFERENCES recursos(id_recurso) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    INDEX idx_recurso (id_recurso),
    INDEX idx_usuario (id_usuario),
    INDEX idx_fecha (fecha_descarga)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: vistas_recursos (tracking de visualizaciones)
-- ============================================================================
CREATE TABLE IF NOT EXISTS vistas_recursos (
    id_vista INT AUTO_INCREMENT PRIMARY KEY,
    id_recurso INT NOT NULL,
    id_usuario INT,
    fecha_vista TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (id_recurso) REFERENCES recursos(id_recurso) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    INDEX idx_recurso (id_recurso),
    INDEX idx_usuario (id_usuario),
    INDEX idx_fecha (fecha_vista)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: calificaciones_recursos
-- ============================================================================
CREATE TABLE IF NOT EXISTS calificaciones_recursos (
    id_calificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_recurso INT NOT NULL,
    id_usuario INT NOT NULL,
    calificacion TINYINT UNSIGNED NOT NULL CHECK (calificacion BETWEEN 1 AND 5),
    comentario TEXT,
    fecha_calificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_calificacion (id_recurso, id_usuario),
    FOREIGN KEY (id_recurso) REFERENCES recursos(id_recurso) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    INDEX idx_recurso (id_recurso),
    INDEX idx_calificacion (calificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TRIGGERS
-- ============================================================================

-- Trigger: Actualizar total_recursos en categorias_recursos
DELIMITER //
CREATE TRIGGER trg_recursos_insert_categoria 
AFTER INSERT ON recursos
FOR EACH ROW
BEGIN
    IF NEW.estado = 'publicado' THEN
        UPDATE categorias_recursos 
        SET total_recursos = total_recursos + 1 
        WHERE id_categoria = NEW.id_categoria;
    END IF;
END//

CREATE TRIGGER trg_recursos_update_categoria 
AFTER UPDATE ON recursos
FOR EACH ROW
BEGIN
    IF OLD.estado != 'publicado' AND NEW.estado = 'publicado' THEN
        UPDATE categorias_recursos 
        SET total_recursos = total_recursos + 1 
        WHERE id_categoria = NEW.id_categoria;
    ELSEIF OLD.estado = 'publicado' AND NEW.estado != 'publicado' THEN
        UPDATE categorias_recursos 
        SET total_recursos = total_recursos - 1 
        WHERE id_categoria = NEW.id_categoria;
    END IF;
    
    IF OLD.id_categoria != NEW.id_categoria THEN
        IF OLD.estado = 'publicado' THEN
            UPDATE categorias_recursos 
            SET total_recursos = total_recursos - 1 
            WHERE id_categoria = OLD.id_categoria;
        END IF;
        IF NEW.estado = 'publicado' THEN
            UPDATE categorias_recursos 
            SET total_recursos = total_recursos + 1 
            WHERE id_categoria = NEW.id_categoria;
        END IF;
    END IF;
END//

CREATE TRIGGER trg_recursos_delete_categoria 
AFTER DELETE ON recursos
FOR EACH ROW
BEGIN
    IF OLD.estado = 'publicado' THEN
        UPDATE categorias_recursos 
        SET total_recursos = total_recursos - 1 
        WHERE id_categoria = OLD.id_categoria;
    END IF;
END//

-- Trigger: Incrementar total_descargas en recursos
CREATE TRIGGER trg_descarga_increment 
AFTER INSERT ON descargas_recursos
FOR EACH ROW
BEGIN
    UPDATE recursos 
    SET total_descargas = total_descargas + 1 
    WHERE id_recurso = NEW.id_recurso;
END//

-- Trigger: Incrementar total_vistas en recursos
CREATE TRIGGER trg_vista_increment 
AFTER INSERT ON vistas_recursos
FOR EACH ROW
BEGIN
    UPDATE recursos 
    SET total_vistas = total_vistas + 1 
    WHERE id_recurso = NEW.id_recurso;
END//

-- Trigger: Actualizar calificación promedio
CREATE TRIGGER trg_calificacion_insert 
AFTER INSERT ON calificaciones_recursos
FOR EACH ROW
BEGIN
    UPDATE recursos 
    SET 
        calificacion_promedio = (
            SELECT AVG(calificacion) 
            FROM calificaciones_recursos 
            WHERE id_recurso = NEW.id_recurso
        ),
        total_calificaciones = total_calificaciones + 1
    WHERE id_recurso = NEW.id_recurso;
END//

CREATE TRIGGER trg_calificacion_update 
AFTER UPDATE ON calificaciones_recursos
FOR EACH ROW
BEGIN
    UPDATE recursos 
    SET calificacion_promedio = (
        SELECT AVG(calificacion) 
        FROM calificaciones_recursos 
        WHERE id_recurso = NEW.id_recurso
    )
    WHERE id_recurso = NEW.id_recurso;
END//

CREATE TRIGGER trg_calificacion_delete 
AFTER DELETE ON calificaciones_recursos
FOR EACH ROW
BEGIN
    UPDATE recursos 
    SET 
        calificacion_promedio = COALESCE((
            SELECT AVG(calificacion) 
            FROM calificaciones_recursos 
            WHERE id_recurso = OLD.id_recurso
        ), 0),
        total_calificaciones = total_calificaciones - 1
    WHERE id_recurso = OLD.id_recurso;
END//

-- Trigger: Actualizar total_usos en etiquetas
CREATE TRIGGER trg_etiqueta_insert 
AFTER INSERT ON recursos_etiquetas
FOR EACH ROW
BEGIN
    UPDATE etiquetas_recursos 
    SET total_usos = total_usos + 1 
    WHERE id_etiqueta = NEW.id_etiqueta;
END//

CREATE TRIGGER trg_etiqueta_delete 
AFTER DELETE ON recursos_etiquetas
FOR EACH ROW
BEGIN
    UPDATE etiquetas_recursos 
    SET total_usos = total_usos - 1 
    WHERE id_etiqueta = OLD.id_etiqueta;
END//

DELIMITER ;

-- ============================================================================
-- VISTA: recursos_completos (JOIN optimizado)
-- ============================================================================
CREATE OR REPLACE VIEW vista_recursos_completos AS
SELECT 
    r.*,
    c.nombre AS categoria_nombre,
    c.slug AS categoria_slug,
    c.icono AS categoria_icono,
    c.color AS categoria_color,
    u.nombre AS autor_nombre,
    u.apellido AS autor_apellido,
    u.foto_perfil AS autor_foto,
    GROUP_CONCAT(DISTINCT e.nombre ORDER BY e.nombre SEPARATOR ', ') AS etiquetas,
    GROUP_CONCAT(DISTINCT e.slug ORDER BY e.nombre SEPARATOR ',') AS etiquetas_slugs
FROM recursos r
LEFT JOIN categorias_recursos c ON r.id_categoria = c.id_categoria
LEFT JOIN usuarios u ON r.id_autor = u.id_usuario
LEFT JOIN recursos_etiquetas re ON r.id_recurso = re.id_recurso
LEFT JOIN etiquetas_recursos e ON re.id_etiqueta = e.id_etiqueta
GROUP BY r.id_recurso;

-- ============================================================================
-- DATOS INICIALES: Categorías de Recursos
-- ============================================================================
INSERT INTO categorias_recursos (nombre, slug, descripcion, icono, color, orden) VALUES
('Artículos y Blogs', 'articulos-blogs', 'Artículos educativos y posts de blog sobre emprendimiento', '📄', '#3b82f6', 1),
('Ebooks y Guías', 'ebooks-guias', 'Libros electrónicos y guías completas descargables', '📚', '#8b5cf6', 2),
('Plantillas y Formatos', 'plantillas-formatos', 'Plantillas editables para planes de negocio, presentaciones y más', '📋', '#10b981', 3),
('Herramientas', 'herramientas', 'Calculadoras, generadores y herramientas interactivas', '🛠️', '#f59e0b', 4),
('Videos Educativos', 'videos-educativos', 'Tutoriales y cursos en video', '🎥', '#ef4444', 5),
('Infografías', 'infografias', 'Infografías descargables con información visual', '📊', '#06b6d4', 6),
('Podcasts', 'podcasts', 'Episodios de podcast sobre emprendimiento', '🎙️', '#ec4899', 7),
('Casos de Éxito', 'casos-exito', 'Historias reales de emprendedores exitosos', '🏆', '#14b8a6', 8);

-- ============================================================================
-- DATOS INICIALES: Etiquetas Comunes
-- ============================================================================
INSERT INTO etiquetas_recursos (nombre, slug) VALUES
('Marketing Digital', 'marketing-digital'),
('Finanzas', 'finanzas'),
('Ventas', 'ventas'),
('Liderazgo', 'liderazgo'),
('Productividad', 'productividad'),
('Estrategia', 'estrategia'),
('Innovación', 'innovacion'),
('Recursos Humanos', 'recursos-humanos'),
('Legal', 'legal'),
('Tecnología', 'tecnologia'),
('Startups', 'startups'),
('E-commerce', 'e-commerce'),
('Redes Sociales', 'redes-sociales'),
('SEO', 'seo'),
('Contabilidad', 'contabilidad');

-- ============================================================================
-- STORED PROCEDURE: Registrar Descarga
-- ============================================================================
DELIMITER //
CREATE PROCEDURE sp_registrar_descarga(
    IN p_id_recurso INT,
    IN p_id_usuario INT,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT
)
BEGIN
    DECLARE v_existe INT;
    
    -- Verificar que el recurso existe y está publicado
    SELECT COUNT(*) INTO v_existe 
    FROM recursos 
    WHERE id_recurso = p_id_recurso AND estado = 'publicado';
    
    IF v_existe > 0 THEN
        -- Registrar descarga
        INSERT INTO descargas_recursos (id_recurso, id_usuario, ip_address, user_agent)
        VALUES (p_id_recurso, p_id_usuario, p_ip_address, p_user_agent);
        
        -- Otorgar puntos al usuario (+5 por descarga)
        INSERT INTO puntos_usuario (id_usuario, puntos_obtenidos, tipo_actividad, referencia_id)
        VALUES (p_id_usuario, 5, 'descarga_recurso', p_id_recurso);
    END IF;
END//
DELIMITER ;

-- ============================================================================
-- CONFIGURACIÓN DE PUNTOS: Actividades de Recursos
-- ============================================================================
-- Las actividades de recursos se registran directamente en puntos_usuario
-- con los siguientes valores:
-- - descarga_recurso: 5 puntos
-- - calificar_recurso: 3 puntos  
-- - publicar_recurso: 50 puntos (admin/instructor)

-- ============================================================================
-- ÍNDICES ADICIONALES PARA PERFORMANCE
-- ============================================================================
CREATE INDEX idx_recursos_busqueda_avanzada ON recursos(estado, tipo_acceso, tipo_recurso, fecha_publicacion);
CREATE INDEX idx_descargas_stats ON descargas_recursos(id_recurso, fecha_descarga);
CREATE INDEX idx_calificaciones_stats ON calificaciones_recursos(id_recurso, calificacion);

-- ============================================================================
-- FIN DE MIGRACIÓN
-- ============================================================================
