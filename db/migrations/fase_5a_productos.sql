-- ============================================================================
-- FASE 5A: VITRINA DE PRODUCTOS - MIGRACIÓN DE BASE DE DATOS
-- Sistema de Formación Empresarial - Nenis y Bros
-- Fecha: 18 de noviembre de 2025
-- ============================================================================

-- Descripción:
-- Este script crea las tablas necesarias para el marketplace de productos:
-- 1. categorias_productos - Categorías de productos
-- 2. productos - Productos y servicios publicados
-- 3. imagenes_productos - Galería multimedia de productos
-- 4. productos_favoritos - Productos guardados por usuarios
-- 5. interacciones_productos - Registro de vistas y contactos

-- ============================================================================
-- TABLA 1: CATEGORÍAS DE PRODUCTOS
-- ============================================================================
CREATE TABLE IF NOT EXISTS categorias_productos (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    icono VARCHAR(50) DEFAULT 'shopping-bag', -- nombre de icono (Font Awesome, etc)
    slug VARCHAR(120) NOT NULL UNIQUE, -- URL friendly (ej: "alimentos-organicos")
    color_hex VARCHAR(7) DEFAULT '#667eea', -- color de badge (ej: "#FF5733")
    orden INT DEFAULT 0, -- para ordenamiento personalizado
    activo BOOLEAN DEFAULT TRUE,
    total_productos INT DEFAULT 0, -- contador cache
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_activo (activo),
    INDEX idx_orden (orden),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA 2: PRODUCTOS
-- ============================================================================
CREATE TABLE IF NOT EXISTS productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL, -- vendedor/publicador
    id_perfil_empresarial INT NULL, -- opcional: vincular a negocio
    id_categoria INT NOT NULL,
    
    -- Información básica
    titulo VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE, -- generado automáticamente
    descripcion_corta VARCHAR(500), -- para cards/listados
    descripcion_completa TEXT, -- HTML permitido
    
    -- Tipo y precios
    tipo_producto ENUM('producto_fisico', 'servicio', 'producto_digital', 'paquete', 'consultoria') DEFAULT 'producto_fisico',
    precio DECIMAL(10,2) DEFAULT 0.00,
    moneda VARCHAR(3) DEFAULT 'MXN', -- ISO 4217 (MXN, USD, EUR)
    precio_anterior DECIMAL(10,2) NULL, -- para mostrar descuento
    
    -- Inventario (opcional)
    control_inventario BOOLEAN DEFAULT FALSE,
    cantidad_disponible INT NULL,
    unidad_medida VARCHAR(50) NULL, -- "unidades", "kg", "horas", etc
    
    -- Etiquetas y características
    etiquetas JSON NULL, -- ["hecho_a_mano", "organico", "local"]
    caracteristicas JSON NULL, -- [{"nombre": "Material", "valor": "Algodón"}]
    
    -- Contacto y ubicación
    contacto_whatsapp VARCHAR(20) NULL,
    contacto_email VARCHAR(150) NULL,
    contacto_telefono VARCHAR(20) NULL,
    ubicacion_ciudad VARCHAR(100) NULL,
    ubicacion_estado VARCHAR(100) NULL,
    ubicacion_pais VARCHAR(100) DEFAULT 'México',
    
    -- Estados y visibilidad
    estado ENUM('borrador', 'publicado', 'pausado', 'agotado', 'archivado') DEFAULT 'borrador',
    destacado BOOLEAN DEFAULT FALSE, -- productos premium/featured
    
    -- Estadísticas
    total_vistas INT DEFAULT 0,
    total_contactos INT DEFAULT 0,
    total_favoritos INT DEFAULT 0,
    calificacion_promedio DECIMAL(3,2) DEFAULT 0.00, -- para futuro
    
    -- SEO
    meta_titulo VARCHAR(200) NULL,
    meta_descripcion VARCHAR(500) NULL,
    
    -- Fechas
    fecha_publicacion TIMESTAMP NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_perfil_empresarial) REFERENCES perfiles_empresariales(id_perfil) ON DELETE SET NULL,
    FOREIGN KEY (id_categoria) REFERENCES categorias_productos(id_categoria) ON DELETE RESTRICT,
    
    INDEX idx_usuario (id_usuario),
    INDEX idx_categoria (id_categoria),
    INDEX idx_estado (estado),
    INDEX idx_destacado (destacado),
    INDEX idx_slug (slug),
    INDEX idx_precio (precio),
    INDEX idx_fecha_publicacion (fecha_publicacion),
    INDEX idx_tipo (tipo_producto),
    FULLTEXT INDEX idx_busqueda (titulo, descripcion_corta, descripcion_completa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA 3: IMÁGENES DE PRODUCTOS
-- ============================================================================
CREATE TABLE IF NOT EXISTS imagenes_productos (
    id_imagen INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    url_imagen VARCHAR(500) NOT NULL,
    url_thumbnail VARCHAR(500) NULL, -- miniatura optimizada
    alt_text VARCHAR(200) NULL, -- accesibilidad
    orden INT DEFAULT 0, -- primera imagen = principal
    es_principal BOOLEAN DEFAULT FALSE,
    tamanio_bytes INT NULL,
    ancho_px INT NULL,
    alto_px INT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE,
    
    INDEX idx_producto (id_producto),
    INDEX idx_principal (es_principal),
    INDEX idx_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA 4: PRODUCTOS FAVORITOS
-- ============================================================================
CREATE TABLE IF NOT EXISTS productos_favoritos (
    id_favorito INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_producto INT NOT NULL,
    fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE,
    
    UNIQUE KEY unique_favorito (id_usuario, id_producto),
    INDEX idx_usuario (id_usuario),
    INDEX idx_producto (id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA 5: INTERACCIONES CON PRODUCTOS
-- ============================================================================
CREATE TABLE IF NOT EXISTS interacciones_productos (
    id_interaccion INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    id_usuario INT NULL, -- NULL si no está autenticado
    tipo_interaccion ENUM('vista', 'contacto', 'compartido', 'click_whatsapp', 'click_email', 'click_telefono') NOT NULL,
    
    -- Metadata
    user_agent VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL, -- IPv6 compatible
    referer VARCHAR(500) NULL, -- de dónde viene
    
    -- Detalles adicionales (JSON flexible)
    metadata JSON NULL, -- {"mensaje": "texto", "origen": "listado"}
    
    fecha_interaccion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    
    INDEX idx_producto (id_producto),
    INDEX idx_usuario (id_usuario),
    INDEX idx_tipo (tipo_interaccion),
    INDEX idx_fecha (fecha_interaccion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATOS INICIALES: CATEGORÍAS DE PRODUCTOS
-- ============================================================================
INSERT INTO categorias_productos (nombre, descripcion, icono, slug, color_hex, orden) VALUES
('Alimentos y Bebidas', 'Productos comestibles, bebidas artesanales, alimentos orgánicos', 'utensils', 'alimentos-bebidas', '#FF6B6B', 1),
('Artesanías', 'Productos hechos a mano, arte local, decoración', 'palette', 'artesanias', '#4ECDC4', 2),
('Textiles y Moda', 'Ropa, accesorios, bolsas, textiles artesanales', 'tshirt', 'textiles-moda', '#95E1D3', 3),
('Tecnología', 'Servicios tecnológicos, desarrollo, soporte técnico', 'laptop-code', 'tecnologia', '#667eea', 4),
('Consultoría', 'Asesoría profesional, consultoría empresarial', 'briefcase', 'consultoria', '#764ba2', 5),
('Servicios Profesionales', 'Contabilidad, legal, marketing, diseño', 'user-tie', 'servicios-profesionales', '#f093fb', 6),
('Salud y Belleza', 'Cosméticos, productos de cuidado personal', 'spa', 'salud-belleza', '#f5576c', 7),
('Hogar y Jardín', 'Muebles, decoración, plantas, jardinería', 'home', 'hogar-jardin', '#43A047', 8),
('Educación y Capacitación', 'Cursos privados, talleres, mentorías personalizadas', 'graduation-cap', 'educacion-capacitacion', '#FFA726', 9),
('Otros', 'Productos y servicios diversos', 'box', 'otros', '#9E9E9E', 99)
ON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion);

-- ============================================================================
-- TRIGGER: Actualizar contador de productos en categoría
-- ============================================================================
DELIMITER $$

CREATE TRIGGER after_producto_insert
AFTER INSERT ON productos
FOR EACH ROW
BEGIN
    IF NEW.estado = 'publicado' THEN
        UPDATE categorias_productos 
        SET total_productos = total_productos + 1 
        WHERE id_categoria = NEW.id_categoria;
    END IF;
END$$

CREATE TRIGGER after_producto_update
AFTER UPDATE ON productos
FOR EACH ROW
BEGIN
    -- Si cambió a publicado
    IF NEW.estado = 'publicado' AND OLD.estado != 'publicado' THEN
        UPDATE categorias_productos 
        SET total_productos = total_productos + 1 
        WHERE id_categoria = NEW.id_categoria;
    END IF;
    
    -- Si dejó de estar publicado
    IF OLD.estado = 'publicado' AND NEW.estado != 'publicado' THEN
        UPDATE categorias_productos 
        SET total_productos = total_productos - 1 
        WHERE id_categoria = NEW.id_categoria;
    END IF;
    
    -- Si cambió de categoría (y está publicado)
    IF NEW.id_categoria != OLD.id_categoria AND NEW.estado = 'publicado' THEN
        UPDATE categorias_productos 
        SET total_productos = total_productos - 1 
        WHERE id_categoria = OLD.id_categoria;
        
        UPDATE categorias_productos 
        SET total_productos = total_productos + 1 
        WHERE id_categoria = NEW.id_categoria;
    END IF;
END$$

CREATE TRIGGER after_producto_delete
AFTER DELETE ON productos
FOR EACH ROW
BEGIN
    IF OLD.estado = 'publicado' THEN
        UPDATE categorias_productos 
        SET total_productos = total_productos - 1 
        WHERE id_categoria = OLD.id_categoria;
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- TRIGGER: Actualizar contador de favoritos
-- ============================================================================
DELIMITER $$

CREATE TRIGGER after_favorito_insert
AFTER INSERT ON productos_favoritos
FOR EACH ROW
BEGIN
    UPDATE productos 
    SET total_favoritos = total_favoritos + 1 
    WHERE id_producto = NEW.id_producto;
END$$

CREATE TRIGGER after_favorito_delete
AFTER DELETE ON productos_favoritos
FOR EACH ROW
BEGIN
    UPDATE productos 
    SET total_favoritos = total_favoritos - 1 
    WHERE id_producto = OLD.id_producto;
END$$

DELIMITER ;

-- ============================================================================
-- VISTA: Productos con información completa
-- ============================================================================
CREATE OR REPLACE VIEW vista_productos_completa AS
SELECT 
    p.id_producto,
    p.titulo,
    p.slug,
    p.descripcion_corta,
    p.tipo_producto,
    p.precio,
    p.moneda,
    p.precio_anterior,
    p.estado,
    p.destacado,
    p.total_vistas,
    p.total_contactos,
    p.total_favoritos,
    p.ubicacion_ciudad,
    p.ubicacion_estado,
    p.fecha_publicacion,
    
    -- Categoría
    c.nombre AS categoria_nombre,
    c.slug AS categoria_slug,
    c.color_hex AS categoria_color,
    
    -- Vendedor
    u.id_usuario AS vendedor_id,
    u.nombre AS vendedor_nombre,
    u.email AS vendedor_email,
    
    -- Perfil empresarial (si existe)
    pe.id_perfil AS perfil_id,
    pe.nombre_empresa,
    pe.logo_empresa AS perfil_logo,
    
    -- Imagen principal
    (SELECT url_imagen FROM imagenes_productos 
     WHERE id_producto = p.id_producto AND es_principal = TRUE 
     LIMIT 1) AS imagen_principal,
    
    -- Total de imágenes
    (SELECT COUNT(*) FROM imagenes_productos 
     WHERE id_producto = p.id_producto) AS total_imagenes

FROM productos p
INNER JOIN categorias_productos c ON p.id_categoria = c.id_categoria
INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
LEFT JOIN perfiles_empresariales pe ON p.id_perfil_empresarial = pe.id_perfil;

-- ============================================================================
-- PROCEDIMIENTOS ALMACENADOS
-- ============================================================================
DELIMITER $$

-- ============================================================================
-- PROCEDIMIENTO: Registrar vista de producto
-- ============================================================================
DROP PROCEDURE IF EXISTS sp_registrar_vista_producto$$

CREATE PROCEDURE sp_registrar_vista_producto(
    IN p_id_producto INT,
    IN p_id_usuario INT,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent VARCHAR(500)
)
BEGIN
    -- Registrar interacción
    INSERT INTO interacciones_productos 
        (id_producto, id_usuario, tipo_interaccion, ip_address, user_agent)
    VALUES 
        (p_id_producto, p_id_usuario, 'vista', p_ip_address, p_user_agent);
    
    -- Actualizar contador
    UPDATE productos 
    SET total_vistas = total_vistas + 1 
    WHERE id_producto = p_id_producto;
END$$

-- ============================================================================
-- PROCEDIMIENTO: Registrar contacto de producto
-- ============================================================================
DROP PROCEDURE IF EXISTS sp_registrar_contacto_producto$$

CREATE PROCEDURE sp_registrar_contacto_producto(
    IN p_id_producto INT,
    IN p_id_usuario INT,
    IN p_tipo_contacto VARCHAR(50),
    IN p_metadata JSON
)
BEGIN
    -- Registrar interacción
    INSERT INTO interacciones_productos 
        (id_producto, id_usuario, tipo_interaccion, metadata)
    VALUES 
        (p_id_producto, p_id_usuario, p_tipo_contacto, p_metadata);
    
    -- Actualizar contador
    UPDATE productos 
    SET total_contactos = total_contactos + 1 
    WHERE id_producto = p_id_producto;
END$$

DELIMITER ;

-- ============================================================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ============================================================================

-- Búsqueda por ubicación
ALTER TABLE productos ADD INDEX idx_ubicacion (ubicacion_estado, ubicacion_ciudad);

-- Productos destacados activos
ALTER TABLE productos ADD INDEX idx_destacado_estado (destacado, estado, fecha_publicacion DESC);

-- Favoritos por usuario
ALTER TABLE productos_favoritos ADD INDEX idx_usuario_fecha (id_usuario, fecha_agregado DESC);

-- ============================================================================
-- PERMISOS Y NOTAS
-- ============================================================================

-- Nota: Este script debe ejecutarse después de las migraciones de:
-- - fase_1: usuarios
-- - fase_3: perfiles_empresariales

-- Para importar en MySQL:
-- mysql -u root -p nyd_db < fase_5a_productos.sql

-- Verificación post-migración:
-- SELECT COUNT(*) FROM categorias_productos; -- Debe ser 10
-- SHOW TABLES LIKE '%producto%'; -- Debe mostrar 5 tablas
-- SELECT * FROM vista_productos_completa LIMIT 1; -- Debe funcionar

-- ============================================================================
-- FIN DE MIGRACIÓN FASE 5A
-- ============================================================================
