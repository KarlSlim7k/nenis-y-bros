-- =====================================================
-- FASE 6B: Sistema de Versionado de Recursos
-- =====================================================
-- Fecha: 2025-11-19
-- Descripción: Sistema completo de control de versiones para recursos
--              con historial de cambios, restauración y comparación

USE formacion_empresarial;

-- =====================================================
-- TABLA: recursos_versiones
-- =====================================================
-- Almacena el historial completo de cambios de cada recurso
CREATE TABLE IF NOT EXISTS recursos_versiones (
    id_version INT AUTO_INCREMENT PRIMARY KEY,
    id_recurso INT NOT NULL,
    numero_version INT NOT NULL COMMENT 'Versión incremental (1, 2, 3...)',
    
    -- Usuario que realizó el cambio
    id_usuario_cambio INT NOT NULL,
    fecha_cambio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Snapshot completo del recurso en esta versión
    id_categoria INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    descripcion TEXT,
    tipo_recurso ENUM('articulo', 'ebook', 'plantilla', 'herramienta', 'video', 'infografia', 'podcast', 'guia', 'checklist') NOT NULL,
    tipo_acceso ENUM('gratuito', 'premium', 'suscripcion') DEFAULT 'gratuito',
    
    -- Archivos (snapshot)
    archivo_url VARCHAR(500),
    archivo_nombre VARCHAR(255),
    archivo_tipo VARCHAR(100),
    archivo_tamanio INT,
    
    -- Contenido
    contenido_texto TEXT,
    contenido_html TEXT,
    url_externo VARCHAR(500),
    duracion_minutos INT,
    
    -- Medios
    imagen_portada VARCHAR(500),
    imagen_preview VARCHAR(500),
    video_preview VARCHAR(500),
    
    -- Metadatos
    nivel ENUM('principiante', 'intermedio', 'avanzado', 'experto') DEFAULT 'principiante',
    idioma VARCHAR(5) DEFAULT 'es',
    formato VARCHAR(50),
    licencia VARCHAR(255) DEFAULT 'Uso educativo',
    
    -- Estado en esta versión
    estado ENUM('borrador', 'revision', 'publicado', 'archivado') DEFAULT 'borrador',
    destacado BOOLEAN DEFAULT FALSE,
    fecha_publicacion DATETIME,
    
    -- Metadatos de cambio
    tipo_cambio ENUM('creacion', 'actualizacion', 'restauracion', 'publicacion', 'despublicacion') NOT NULL,
    descripcion_cambio TEXT COMMENT 'Descripción del cambio realizado',
    campos_modificados JSON COMMENT 'Array de campos que cambiaron: ["titulo", "descripcion"]',
    datos_anteriores JSON COMMENT 'Valores anteriores de los campos modificados',
    
    -- Índices
    INDEX idx_recurso (id_recurso),
    INDEX idx_recurso_version (id_recurso, numero_version),
    INDEX idx_fecha (fecha_cambio),
    INDEX idx_usuario (id_usuario_cambio),
    INDEX idx_tipo_cambio (tipo_cambio),
    
    -- Claves foráneas
    CONSTRAINT fk_version_recurso FOREIGN KEY (id_recurso) 
        REFERENCES recursos(id_recurso) ON DELETE CASCADE,
    CONSTRAINT fk_version_usuario FOREIGN KEY (id_usuario_cambio) 
        REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_version_categoria FOREIGN KEY (id_categoria) 
        REFERENCES categorias_recursos(id_categoria) ON DELETE RESTRICT,
    
    -- Constraint único: un recurso no puede tener dos versiones con el mismo número
    UNIQUE KEY uk_recurso_numero (id_recurso, numero_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historial de versiones de recursos con snapshots completos';

-- =====================================================
-- TABLA: recursos_etiquetas_versiones
-- =====================================================
-- Histórico de etiquetas por versión
CREATE TABLE IF NOT EXISTS recursos_etiquetas_versiones (
    id_version_etiqueta INT AUTO_INCREMENT PRIMARY KEY,
    id_version INT NOT NULL,
    id_etiqueta INT NOT NULL,
    
    INDEX idx_version (id_version),
    INDEX idx_etiqueta (id_etiqueta),
    
    CONSTRAINT fk_ver_etiq_version FOREIGN KEY (id_version) 
        REFERENCES recursos_versiones(id_version) ON DELETE CASCADE,
    CONSTRAINT fk_ver_etiq_etiqueta FOREIGN KEY (id_etiqueta) 
        REFERENCES etiquetas_recursos(id_etiqueta) ON DELETE CASCADE,
    
    UNIQUE KEY uk_version_etiqueta (id_version, id_etiqueta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Etiquetas asociadas a cada versión del recurso';

-- =====================================================
-- TRIGGER: Crear versión automáticamente en INSERT
-- =====================================================
DROP TRIGGER IF EXISTS trg_recursos_version_insert;

DELIMITER $$
CREATE TRIGGER trg_recursos_version_insert
AFTER INSERT ON recursos
FOR EACH ROW
BEGIN
    -- Crear primera versión (versión 1)
    INSERT INTO recursos_versiones (
        id_recurso, numero_version, id_usuario_cambio, fecha_cambio,
        id_categoria, titulo, slug, descripcion, tipo_recurso, tipo_acceso,
        archivo_url, archivo_nombre, archivo_tipo, archivo_tamanio,
        contenido_texto, contenido_html, url_externo, duracion_minutos,
        imagen_portada, imagen_preview, video_preview,
        nivel, idioma, formato, licencia,
        estado, destacado, fecha_publicacion,
        tipo_cambio, descripcion_cambio, campos_modificados
    ) VALUES (
        NEW.id_recurso, 1, NEW.id_autor, NOW(),
        NEW.id_categoria, NEW.titulo, NEW.slug, NEW.descripcion, NEW.tipo_recurso, NEW.tipo_acceso,
        NEW.archivo_url, NEW.archivo_nombre, NEW.archivo_tipo, NEW.archivo_tamanio,
        NEW.contenido_texto, NEW.contenido_html, NEW.url_externo, NEW.duracion_minutos,
        NEW.imagen_portada, NEW.imagen_preview, NEW.video_preview,
        NEW.nivel, NEW.idioma, NEW.formato, NEW.licencia,
        NEW.estado, NEW.destacado, NEW.fecha_publicacion,
        'creacion', 'Versión inicial del recurso', JSON_ARRAY('*')
    );
END$$
DELIMITER ;

-- =====================================================
-- STORED PROCEDURE: Crear nueva versión en UPDATE
-- =====================================================
DROP PROCEDURE IF EXISTS sp_crear_version_recurso;

DELIMITER $$
CREATE PROCEDURE sp_crear_version_recurso(
    IN p_id_recurso INT,
    IN p_id_usuario_cambio INT,
    IN p_tipo_cambio ENUM('actualizacion', 'restauracion', 'publicacion', 'despublicacion'),
    IN p_descripcion_cambio TEXT,
    IN p_campos_modificados JSON,
    IN p_datos_anteriores JSON
)
BEGIN
    DECLARE v_numero_version INT;
    DECLARE v_id_categoria INT;
    DECLARE v_titulo VARCHAR(255);
    DECLARE v_slug VARCHAR(255);
    DECLARE v_descripcion TEXT;
    DECLARE v_tipo_recurso ENUM('articulo', 'ebook', 'plantilla', 'herramienta', 'video', 'infografia', 'podcast', 'guia', 'checklist');
    DECLARE v_tipo_acceso ENUM('gratuito', 'premium', 'suscripcion');
    DECLARE v_archivo_url VARCHAR(500);
    DECLARE v_archivo_nombre VARCHAR(255);
    DECLARE v_archivo_tipo VARCHAR(100);
    DECLARE v_archivo_tamanio INT;
    DECLARE v_contenido_texto TEXT;
    DECLARE v_contenido_html TEXT;
    DECLARE v_url_externo VARCHAR(500);
    DECLARE v_duracion_minutos INT;
    DECLARE v_imagen_portada VARCHAR(500);
    DECLARE v_imagen_preview VARCHAR(500);
    DECLARE v_video_preview VARCHAR(500);
    DECLARE v_nivel ENUM('principiante', 'intermedio', 'avanzado', 'experto');
    DECLARE v_idioma VARCHAR(5);
    DECLARE v_formato VARCHAR(50);
    DECLARE v_licencia VARCHAR(255);
    DECLARE v_estado ENUM('borrador', 'revision', 'publicado', 'archivado');
    DECLARE v_destacado BOOLEAN;
    DECLARE v_fecha_publicacion DATETIME;
    
    -- Obtener el siguiente número de versión
    SELECT COALESCE(MAX(numero_version), 0) + 1 INTO v_numero_version
    FROM recursos_versiones
    WHERE id_recurso = p_id_recurso;
    
    -- Obtener datos actuales del recurso
    SELECT 
        id_categoria, titulo, slug, descripcion, tipo_recurso, tipo_acceso,
        archivo_url, archivo_nombre, archivo_tipo, archivo_tamanio,
        contenido_texto, contenido_html, url_externo, duracion_minutos,
        imagen_portada, imagen_preview, video_preview,
        nivel, idioma, formato, licencia,
        estado, destacado, fecha_publicacion
    INTO
        v_id_categoria, v_titulo, v_slug, v_descripcion, v_tipo_recurso, v_tipo_acceso,
        v_archivo_url, v_archivo_nombre, v_archivo_tipo, v_archivo_tamanio,
        v_contenido_texto, v_contenido_html, v_url_externo, v_duracion_minutos,
        v_imagen_portada, v_imagen_preview, v_video_preview,
        v_nivel, v_idioma, v_formato, v_licencia,
        v_estado, v_destacado, v_fecha_publicacion
    FROM recursos
    WHERE id_recurso = p_id_recurso;
    
    -- Crear nueva versión con snapshot completo
    INSERT INTO recursos_versiones (
        id_recurso, numero_version, id_usuario_cambio, fecha_cambio,
        id_categoria, titulo, slug, descripcion, tipo_recurso, tipo_acceso,
        archivo_url, archivo_nombre, archivo_tipo, archivo_tamanio,
        contenido_texto, contenido_html, url_externo, duracion_minutos,
        imagen_portada, imagen_preview, video_preview,
        nivel, idioma, formato, licencia,
        estado, destacado, fecha_publicacion,
        tipo_cambio, descripcion_cambio, campos_modificados, datos_anteriores
    ) VALUES (
        p_id_recurso, v_numero_version, p_id_usuario_cambio, NOW(),
        v_id_categoria, v_titulo, v_slug, v_descripcion, v_tipo_recurso, v_tipo_acceso,
        v_archivo_url, v_archivo_nombre, v_archivo_tipo, v_archivo_tamanio,
        v_contenido_texto, v_contenido_html, v_url_externo, v_duracion_minutos,
        v_imagen_portada, v_imagen_preview, v_video_preview,
        v_nivel, v_idioma, v_formato, v_licencia,
        v_estado, v_destacado, v_fecha_publicacion,
        p_tipo_cambio, p_descripcion_cambio, p_campos_modificados, p_datos_anteriores
    );
    
    -- Copiar etiquetas actuales a la versión
    INSERT INTO recursos_etiquetas_versiones (id_version, id_etiqueta)
    SELECT LAST_INSERT_ID(), id_etiqueta
    FROM recursos_etiquetas
    WHERE id_recurso = p_id_recurso;
    
    SELECT v_numero_version AS numero_version, LAST_INSERT_ID() AS id_version;
END$$
DELIMITER ;

-- =====================================================
-- STORED PROCEDURE: Restaurar versión anterior
-- =====================================================
DROP PROCEDURE IF EXISTS sp_restaurar_version;

DELIMITER $$
CREATE PROCEDURE sp_restaurar_version(
    IN p_id_recurso INT,
    IN p_numero_version INT,
    IN p_id_usuario_restauracion INT
)
BEGIN
    DECLARE v_id_version INT;
    
    -- Verificar que la versión existe
    SELECT id_version INTO v_id_version
    FROM recursos_versiones
    WHERE id_recurso = p_id_recurso AND numero_version = p_numero_version;
    
    IF v_id_version IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Versión no encontrada';
    END IF;
    
    -- Crear versión actual antes de restaurar (backup)
    CALL sp_crear_version_recurso(
        p_id_recurso,
        p_id_usuario_restauracion,
        'restauracion',
        CONCAT('Restauración a versión ', p_numero_version),
        JSON_ARRAY('*'),
        NULL
    );
    
    -- Restaurar datos del recurso desde la versión
    UPDATE recursos r
    INNER JOIN recursos_versiones v ON r.id_recurso = v.id_recurso
    SET
        r.id_categoria = v.id_categoria,
        r.titulo = v.titulo,
        r.slug = v.slug,
        r.descripcion = v.descripcion,
        r.tipo_recurso = v.tipo_recurso,
        r.tipo_acceso = v.tipo_acceso,
        r.archivo_url = v.archivo_url,
        r.archivo_nombre = v.archivo_nombre,
        r.archivo_tipo = v.archivo_tipo,
        r.archivo_tamanio = v.archivo_tamanio,
        r.contenido_texto = v.contenido_texto,
        r.contenido_html = v.contenido_html,
        r.url_externo = v.url_externo,
        r.duracion_minutos = v.duracion_minutos,
        r.imagen_portada = v.imagen_portada,
        r.imagen_preview = v.imagen_preview,
        r.video_preview = v.video_preview,
        r.nivel = v.nivel,
        r.idioma = v.idioma,
        r.formato = v.formato,
        r.licencia = v.licencia,
        r.estado = v.estado,
        r.destacado = v.destacado,
        r.fecha_publicacion = v.fecha_publicacion,
        r.fecha_actualizacion = NOW()
    WHERE v.id_version = v_id_version
    AND r.id_recurso = p_id_recurso;
    
    -- Restaurar etiquetas de esa versión
    DELETE FROM recursos_etiquetas WHERE id_recurso = p_id_recurso;
    
    INSERT INTO recursos_etiquetas (id_recurso, id_etiqueta)
    SELECT p_id_recurso, id_etiqueta
    FROM recursos_etiquetas_versiones
    WHERE id_version = v_id_version;
    
    SELECT 'success' AS status, 'Versión restaurada exitosamente' AS message;
END$$
DELIMITER ;

-- =====================================================
-- VISTA: Historial de versiones con información de usuario
-- =====================================================
CREATE OR REPLACE VIEW vista_versiones_recursos AS
SELECT 
    rv.id_version,
    rv.id_recurso,
    r.titulo AS titulo_actual,
    rv.numero_version,
    rv.titulo AS titulo_version,
    rv.tipo_cambio,
    rv.descripcion_cambio,
    rv.campos_modificados,
    rv.fecha_cambio,
    rv.id_usuario_cambio,
    u.nombre AS nombre_usuario,
    u.email AS email_usuario,
    rv.estado AS estado_version,
    r.estado AS estado_actual,
    -- Información de tamaño de cambio
    JSON_LENGTH(rv.campos_modificados) AS cantidad_campos_modificados,
    -- Metadatos útiles
    rv.fecha_publicacion AS fecha_publicacion_version,
    TIMESTAMPDIFF(SECOND, 
        LAG(rv.fecha_cambio) OVER (PARTITION BY rv.id_recurso ORDER BY rv.numero_version),
        rv.fecha_cambio
    ) AS segundos_desde_version_anterior
FROM recursos_versiones rv
INNER JOIN recursos r ON rv.id_recurso = r.id_recurso
INNER JOIN usuarios u ON rv.id_usuario_cambio = u.id_usuario
ORDER BY rv.id_recurso DESC, rv.numero_version DESC;

-- =====================================================
-- VISTA: Última versión de cada recurso
-- =====================================================
CREATE OR REPLACE VIEW vista_versiones_actuales AS
SELECT 
    rv.*,
    u.nombre AS nombre_usuario,
    u.email AS email_usuario
FROM recursos_versiones rv
INNER JOIN usuarios u ON rv.id_usuario_cambio = u.id_usuario
WHERE (rv.id_recurso, rv.numero_version) IN (
    SELECT id_recurso, MAX(numero_version)
    FROM recursos_versiones
    GROUP BY id_recurso
);

-- =====================================================
-- FUNCIÓN: Obtener diferencias entre versiones
-- =====================================================
DROP FUNCTION IF EXISTS fn_comparar_versiones;

DELIMITER $$
CREATE FUNCTION fn_comparar_versiones(
    p_id_recurso INT,
    p_version_1 INT,
    p_version_2 INT
) RETURNS JSON
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_resultado JSON;
    
    SELECT JSON_OBJECT(
        'version_1', JSON_OBJECT(
            'numero', v1.numero_version,
            'titulo', v1.titulo,
            'descripcion', v1.descripcion,
            'estado', v1.estado,
            'fecha', v1.fecha_cambio
        ),
        'version_2', JSON_OBJECT(
            'numero', v2.numero_version,
            'titulo', v2.titulo,
            'descripcion', v2.descripcion,
            'estado', v2.estado,
            'fecha', v2.fecha_cambio
        ),
        'diferencias', JSON_OBJECT(
            'titulo_cambio', v1.titulo != v2.titulo,
            'descripcion_cambio', v1.descripcion != v2.descripcion,
            'estado_cambio', v1.estado != v2.estado,
            'categoria_cambio', v1.id_categoria != v2.id_categoria,
            'archivo_cambio', v1.archivo_url != v2.archivo_url
        )
    ) INTO v_resultado
    FROM recursos_versiones v1
    CROSS JOIN recursos_versiones v2
    WHERE v1.id_recurso = p_id_recurso AND v1.numero_version = p_version_1
    AND v2.id_recurso = p_id_recurso AND v2.numero_version = p_version_2;
    
    RETURN v_resultado;
END$$
DELIMITER ;

-- =====================================================
-- ÍNDICES ADICIONALES para optimizar consultas
-- =====================================================
CREATE INDEX idx_versiones_recurso_fecha ON recursos_versiones(id_recurso, fecha_cambio DESC);
CREATE INDEX idx_versiones_tipo_cambio_fecha ON recursos_versiones(tipo_cambio, fecha_cambio DESC);

-- =====================================================
-- DATOS DE PRUEBA: Crear versiones para recursos existentes
-- =====================================================
-- Nota: El trigger creará automáticamente versión 1 para recursos nuevos
-- Para recursos existentes, crear versión inicial manualmente

INSERT INTO recursos_versiones (
    id_recurso, numero_version, id_usuario_cambio, fecha_cambio,
    id_categoria, titulo, slug, descripcion, tipo_recurso, tipo_acceso,
    archivo_url, archivo_nombre, archivo_tipo, archivo_tamanio,
    contenido_texto, contenido_html, url_externo, duracion_minutos,
    imagen_portada, imagen_preview, video_preview,
    nivel, idioma, formato, licencia,
    estado, destacado, fecha_publicacion,
    tipo_cambio, descripcion_cambio, campos_modificados
)
SELECT 
    r.id_recurso, 
    1 AS numero_version,
    r.id_autor AS id_usuario_cambio,
    r.fecha_creacion AS fecha_cambio,
    r.id_categoria, r.titulo, r.slug, r.descripcion, r.tipo_recurso, r.tipo_acceso,
    r.archivo_url, r.archivo_nombre, r.archivo_tipo, r.archivo_tamanio,
    r.contenido_texto, r.contenido_html, r.url_externo, r.duracion_minutos,
    r.imagen_portada, r.imagen_preview, r.video_preview,
    r.nivel, r.idioma, r.formato, r.licencia,
    r.estado, r.destacado, r.fecha_publicacion,
    'creacion' AS tipo_cambio,
    'Versión inicial (migración)' AS descripcion_cambio,
    JSON_ARRAY('*') AS campos_modificados
FROM recursos r
WHERE NOT EXISTS (
    SELECT 1 FROM recursos_versiones rv
    WHERE rv.id_recurso = r.id_recurso
);

-- Copiar etiquetas actuales a las versiones iniciales
INSERT INTO recursos_etiquetas_versiones (id_version, id_etiqueta)
SELECT rv.id_version, re.id_etiqueta
FROM recursos_versiones rv
INNER JOIN recursos_etiquetas re ON rv.id_recurso = re.id_recurso
WHERE rv.numero_version = 1
AND NOT EXISTS (
    SELECT 1 FROM recursos_etiquetas_versiones rev
    WHERE rev.id_version = rv.id_version AND rev.id_etiqueta = re.id_etiqueta
);

-- =====================================================
-- VERIFICACIÓN
-- =====================================================
SELECT 
    'Versiones creadas:' AS info,
    COUNT(*) AS total
FROM recursos_versiones;

SELECT 
    'Recursos con versiones:' AS info,
    COUNT(DISTINCT id_recurso) AS total
FROM recursos_versiones;

-- Mostrar ejemplo de historial
SELECT * FROM vista_versiones_recursos LIMIT 5;

-- =====================================================
-- FIN DE MIGRACIÓN
-- =====================================================
