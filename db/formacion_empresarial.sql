-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 25-11-2025 a las 05:00:59
-- Versión del servidor: 10.4.28-MariaDB
-- Versión de PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Base de datos: `formacion_empresarial`
--
CREATE DATABASE IF NOT EXISTS `formacion_empresarial` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `formacion_empresarial`;
SET FOREIGN_KEY_CHECKS = 0;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `formacion_empresarial`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE PROCEDURE `sp_crear_version_recurso` (IN `p_id_recurso` INT, IN `p_id_usuario_cambio` INT, IN `p_tipo_cambio` ENUM('actualizacion','restauracion','publicacion','despublicacion'), IN `p_descripcion_cambio` TEXT, IN `p_campos_modificados` JSON, IN `p_datos_anteriores` JSON)   BEGIN
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
    
    
    SELECT COALESCE(MAX(numero_version), 0) + 1 INTO v_numero_version
    FROM recursos_versiones
    WHERE id_recurso = p_id_recurso;
    
    
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
    
    
    INSERT INTO recursos_etiquetas_versiones (id_version, id_etiqueta)
    SELECT LAST_INSERT_ID(), id_etiqueta
    FROM recursos_etiquetas
    WHERE id_recurso = p_id_recurso;
    
    SELECT v_numero_version AS numero_version, LAST_INSERT_ID() AS id_version;
END$$

CREATE PROCEDURE `sp_limpiar_mensajes_antiguos` (IN `p_dias_antiguedad` INT)   BEGIN
    
    DELETE m FROM mensajes m
    INNER JOIN conversaciones c ON m.id_conversacion = c.id_conversacion
    WHERE c.estado = 'archivada'
      AND m.fecha_envio < DATE_SUB(NOW(), INTERVAL p_dias_antiguedad DAY);
    
    SELECT ROW_COUNT() AS mensajes_eliminados;
END$$

CREATE PROCEDURE `sp_marcar_mensajes_leidos` (IN `p_id_conversacion` INT, IN `p_id_usuario` INT, IN `p_rol` VARCHAR(20))   BEGIN
    DECLARE remitente_tipo_opuesto VARCHAR(20);
    
    
    IF p_rol = 'alumno' THEN
        SET remitente_tipo_opuesto = 'instructor';
    ELSE
        SET remitente_tipo_opuesto = 'alumno';
    END IF;
    
    
    UPDATE mensajes
    SET leido = TRUE,
        fecha_leido = NOW()
    WHERE id_conversacion = p_id_conversacion
      AND remitente_tipo = remitente_tipo_opuesto
      AND leido = FALSE;
    
    SELECT ROW_COUNT() AS mensajes_marcados;
END$$

CREATE PROCEDURE `sp_registrar_contacto_producto` (IN `p_id_producto` INT, IN `p_id_usuario` INT, IN `p_tipo_contacto` VARCHAR(50), IN `p_metadata` JSON)   BEGIN
    
    INSERT INTO interacciones_productos 
        (id_producto, id_usuario, tipo_interaccion, metadata)
    VALUES 
        (p_id_producto, p_id_usuario, p_tipo_contacto, p_metadata);
    
    
    UPDATE productos 
    SET total_contactos = total_contactos + 1 
    WHERE id_producto = p_id_producto;
END$$

CREATE PROCEDURE `sp_registrar_descarga` (IN `p_id_recurso` INT, IN `p_id_usuario` INT, IN `p_ip_address` VARCHAR(45), IN `p_user_agent` TEXT)   BEGIN
    DECLARE v_existe INT;
    
    
    SELECT COUNT(*) INTO v_existe 
    FROM recursos 
    WHERE id_recurso = p_id_recurso AND estado = 'publicado';
    
    IF v_existe > 0 THEN
        
        INSERT INTO descargas_recursos (id_recurso, id_usuario, ip_address, user_agent)
        VALUES (p_id_recurso, p_id_usuario, p_ip_address, p_user_agent);
        
        
        INSERT INTO puntos_usuario (id_usuario, puntos_obtenidos, tipo_actividad, referencia_id)
        VALUES (p_id_usuario, 5, 'descarga_recurso', p_id_recurso);
    END IF;
END$$

CREATE PROCEDURE `sp_registrar_vista_producto` (IN `p_id_producto` INT, IN `p_id_usuario` INT, IN `p_ip_address` VARCHAR(45), IN `p_user_agent` VARCHAR(500))   BEGIN
    
    INSERT INTO interacciones_productos 
        (id_producto, id_usuario, tipo_interaccion, ip_address, user_agent)
    VALUES 
        (p_id_producto, p_id_usuario, 'vista', p_ip_address, p_user_agent);
    
    
    UPDATE productos 
    SET total_vistas = total_vistas + 1 
    WHERE id_producto = p_id_producto;
END$$

CREATE PROCEDURE `sp_restaurar_version` (IN `p_id_recurso` INT, IN `p_numero_version` INT, IN `p_id_usuario_restauracion` INT)   BEGIN
    DECLARE v_id_version INT;
    
    
    SELECT id_version INTO v_id_version
    FROM recursos_versiones
    WHERE id_recurso = p_id_recurso AND numero_version = p_numero_version;
    
    IF v_id_version IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Versi??n no encontrada';
    END IF;
    
    
    CALL sp_crear_version_recurso(
        p_id_recurso,
        p_id_usuario_restauracion,
        'restauracion',
        CONCAT('Restauraci??n a versi??n ', p_numero_version),
        JSON_ARRAY('*'),
        NULL
    );
    
    
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
    
    
    DELETE FROM recursos_etiquetas WHERE id_recurso = p_id_recurso;
    
    INSERT INTO recursos_etiquetas (id_recurso, id_etiqueta)
    SELECT p_id_recurso, id_etiqueta
    FROM recursos_etiquetas_versiones
    WHERE id_version = v_id_version;
    
    SELECT 'success' AS status, 'Versi??n restaurada exitosamente' AS message;
END$$

--
-- Funciones
--
CREATE FUNCTION `fn_comparar_versiones` (`p_id_recurso` INT, `p_version_1` INT, `p_version_2` INT) RETURNS LONGTEXT CHARSET utf8mb4 COLLATE utf8mb4_bin DETERMINISTIC READS SQL DATA BEGIN
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas_evaluacion`
--

CREATE TABLE `areas_evaluacion` (
  `id_area` int(11) NOT NULL,
  `id_tipo_diagnostico` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(100) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#667eea',
  `ponderacion` decimal(5,2) DEFAULT 100.00 COMMENT 'Peso del ??rea en el resultado final',
  `orden` int(11) DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='??reas de evaluaci??n de diagn??sticos';

--
-- Volcado de datos para la tabla `areas_evaluacion`
--

INSERT INTO `areas_evaluacion` (`id_area`, `id_tipo_diagnostico`, `nombre`, `descripcion`, `icono`, `color`, `ponderacion`, `orden`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 'Gesti??n y Planificaci??n', 'Eval??a la capacidad de planificaci??n estrat??gica, organizaci??n y liderazgo del negocio.', 'clipboard-list', '#3b82f6', 20.00, 1, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(2, 1, 'Gesti??n Financiera', 'Mide el control financiero, contabilidad, presupuestos y an??lisis de costos.', 'dollar-sign', '#10b981', 20.00, 2, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(3, 1, 'Marketing y Ventas', 'Eval??a estrategias de marketing, posicionamiento de marca y procesos de venta.', 'bullhorn', '#f59e0b', 20.00, 3, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(4, 1, 'Operaciones y Procesos', 'Analiza la eficiencia operativa, cadena de suministro y control de calidad.', 'cog', '#8b5cf6', 20.00, 4, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(5, 1, 'Recursos Humanos', 'Eval??a gesti??n de talento, capacitaci??n, cultura organizacional y estructura.', 'users', '#ec4899', 20.00, 5, '2025-11-15 14:42:58', '2025-11-15 14:42:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificaciones_cursos`
--

CREATE TABLE `calificaciones_cursos` (
  `id_calificacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `calificacion` int(11) NOT NULL CHECK (`calificacion` between 1 and 5),
  `comentario` text DEFAULT NULL,
  `aprobado` tinyint(1) DEFAULT 1,
  `fecha_moderacion` datetime DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Calificaciones y rese??as de cursos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificaciones_recursos`
--

CREATE TABLE `calificaciones_recursos` (
  `id_calificacion` int(11) NOT NULL,
  `id_recurso` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `calificacion` tinyint(3) UNSIGNED NOT NULL CHECK (`calificacion` between 1 and 5),
  `comentario` text DEFAULT NULL,
  `fecha_calificacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Disparadores `calificaciones_recursos`
--
DELIMITER $$
CREATE TRIGGER `trg_calificacion_delete` AFTER DELETE ON `calificaciones_recursos` FOR EACH ROW BEGIN
    UPDATE recursos 
    SET 
        calificacion_promedio = COALESCE((
            SELECT AVG(calificacion) 
            FROM calificaciones_recursos 
            WHERE id_recurso = OLD.id_recurso
        ), 0),
        total_calificaciones = total_calificaciones - 1
    WHERE id_recurso = OLD.id_recurso;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_calificacion_insert` AFTER INSERT ON `calificaciones_recursos` FOR EACH ROW BEGIN
    UPDATE recursos 
    SET 
        calificacion_promedio = (
            SELECT AVG(calificacion) 
            FROM calificaciones_recursos 
            WHERE id_recurso = NEW.id_recurso
        ),
        total_calificaciones = total_calificaciones + 1
    WHERE id_recurso = NEW.id_recurso;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_calificacion_update` AFTER UPDATE ON `calificaciones_recursos` FOR EACH ROW BEGIN
    UPDATE recursos 
    SET calificacion_promedio = (
        SELECT AVG(calificacion) 
        FROM calificaciones_recursos 
        WHERE id_recurso = NEW.id_recurso
    )
    WHERE id_recurso = NEW.id_recurso;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_cursos`
--

CREATE TABLE `categorias_cursos` (
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `slug` varchar(120) NOT NULL,
  `icono` varchar(100) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#667eea',
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categor??as de cursos disponibles';

--
-- Volcado de datos para la tabla `categorias_cursos`
--

INSERT INTO `categorias_cursos` (`id_categoria`, `nombre`, `descripcion`, `slug`, `icono`, `color`, `orden`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Emprendimiento', 'Cursos sobre c??mo iniciar y gestionar tu propio negocio', 'emprendimiento', 'rocket', '#667eea', 1, 1, '2025-11-15 14:21:15', '2025-11-15 14:21:15'),
(2, 'Finanzas', 'Gesti??n financiera y contabilidad para empresas', 'finanzas', 'dollar-sign', '#4caf50', 2, 1, '2025-11-15 14:21:15', '2025-11-15 14:21:15'),
(3, 'Marketing Digital', 'Estrategias de marketing online y redes sociales', 'marketing-digital', 'bullhorn', '#f44336', 3, 1, '2025-11-15 14:21:15', '2025-11-15 14:21:15'),
(4, 'Liderazgo', 'Desarrollo de habilidades de liderazgo empresarial', 'liderazgo', 'users', '#ff9800', 4, 1, '2025-11-15 14:21:15', '2025-11-15 14:21:15'),
(5, 'Tecnolog??a', 'Herramientas tecnol??gicas para empresas', 'tecnologia', 'laptop', '#2196f3', 5, 1, '2025-11-15 14:21:15', '2025-11-15 14:21:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_logros`
--

CREATE TABLE `categorias_logros` (
  `id_categoria_logro` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(100) DEFAULT NULL,
  `color` varchar(7) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorías de logros y badges';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_productos`
--

CREATE TABLE `categorias_productos` (
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(50) DEFAULT 'shopping-bag',
  `slug` varchar(120) NOT NULL,
  `color_hex` varchar(7) DEFAULT '#667eea',
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `total_productos` int(11) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias_productos`
--

INSERT INTO `categorias_productos` (`id_categoria`, `nombre`, `descripcion`, `icono`, `slug`, `color_hex`, `orden`, `activo`, `total_productos`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Alimentos y Bebidas', 'Productos comestibles, bebidas artesanales, alimentos org├ínicos', 'utensils', 'alimentos-bebidas', '#FF6B6B', 1, 1, 1, '2025-11-19 03:47:50', '2025-11-19 03:48:43'),
(2, 'Artesan├¡as', 'Productos hechos a mano, arte local, decoraci├│n', 'palette', 'artesanias', '#4ECDC4', 2, 1, 0, '2025-11-19 03:47:50', '2025-11-19 03:47:50'),
(3, 'Textiles y Moda', 'Ropa, accesorios, bolsas, textiles artesanales', 'tshirt', 'textiles-moda', '#95E1D3', 3, 1, 0, '2025-11-19 03:47:50', '2025-11-19 03:47:50'),
(4, 'Tecnolog├¡a', 'Servicios tecnol├│gicos, desarrollo, soporte t├®cnico', 'laptop-code', 'tecnologia', '#667eea', 4, 1, 0, '2025-11-19 03:47:50', '2025-11-19 03:47:50'),
(5, 'Consultor├¡a', 'Asesor├¡a profesional, consultor├¡a empresarial', 'briefcase', 'consultoria', '#764ba2', 5, 1, 0, '2025-11-19 03:47:50', '2025-11-19 03:47:50'),
(6, 'Servicios Profesionales', 'Contabilidad, legal, marketing, dise├▒o', 'user-tie', 'servicios-profesionales', '#f093fb', 6, 1, 0, '2025-11-19 03:47:50', '2025-11-19 03:47:50'),
(7, 'Salud y Belleza', 'Cosm├®ticos, productos de cuidado personal', 'spa', 'salud-belleza', '#f5576c', 7, 1, 0, '2025-11-19 03:47:50', '2025-11-19 03:47:50'),
(8, 'Hogar y Jard├¡n', 'Muebles, decoraci├│n, plantas, jardiner├¡a', 'home', 'hogar-jardin', '#43A047', 8, 1, 0, '2025-11-19 03:47:50', '2025-11-19 03:47:50'),
(9, 'Educaci├│n y Capacitaci├│n', 'Cursos privados, talleres, mentor├¡as personalizadas', 'graduation-cap', 'educacion-capacitacion', '#FFA726', 9, 1, 0, '2025-11-19 03:47:50', '2025-11-19 03:47:50'),
(10, 'Otros', 'Productos y servicios diversos', 'box', 'otros', '#9E9E9E', 99, 1, 0, '2025-11-19 03:47:50', '2025-11-19 03:47:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_recursos`
--

CREATE TABLE `categorias_recursos` (
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(50) DEFAULT 'folder',
  `color` varchar(7) DEFAULT '#6366f1',
  `orden` int(11) DEFAULT 0,
  `total_recursos` int(11) DEFAULT 0,
  `activa` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias_recursos`
--

INSERT INTO `categorias_recursos` (`id_categoria`, `nombre`, `slug`, `descripcion`, `icono`, `color`, `orden`, `total_recursos`, `activa`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Art├¡culos y Blogs', 'articulos-blogs', 'Art├¡culos educativos y posts de blog sobre emprendimiento', '­ƒôä', '#3b82f6', 1, 0, 1, '2025-11-20 03:36:39', '2025-11-20 03:36:39'),
(2, 'Ebooks y Gu├¡as', 'ebooks-guias', 'Libros electr├│nicos y gu├¡as completas descargables', '­ƒôÜ', '#8b5cf6', 2, 0, 1, '2025-11-20 03:36:39', '2025-11-20 03:36:39'),
(3, 'Plantillas y Formatos', 'plantillas-formatos', 'Plantillas editables para planes de negocio, presentaciones y m├ís', '­ƒôï', '#10b981', 3, 0, 1, '2025-11-20 03:36:39', '2025-11-20 03:36:39'),
(4, 'Herramientas', 'herramientas', 'Calculadoras, generadores y herramientas interactivas', '­ƒøá´©Å', '#f59e0b', 4, 0, 1, '2025-11-20 03:36:39', '2025-11-20 03:36:39'),
(5, 'Videos Educativos', 'videos-educativos', 'Tutoriales y cursos en video', '­ƒÄÑ', '#ef4444', 5, 0, 1, '2025-11-20 03:36:39', '2025-11-20 03:36:39'),
(6, 'Infograf├¡as', 'infografias', 'Infograf├¡as descargables con informaci├│n visual', '­ƒôè', '#06b6d4', 6, 0, 1, '2025-11-20 03:36:39', '2025-11-20 03:36:39'),
(7, 'Podcasts', 'podcasts', 'Episodios de podcast sobre emprendimiento', '­ƒÄÖ´©Å', '#ec4899', 7, 0, 1, '2025-11-20 03:36:39', '2025-11-20 03:36:39'),
(8, 'Casos de ├ëxito', 'casos-exito', 'Historias reales de emprendedores exitosos', '­ƒÅå', '#14b8a6', 8, 0, 1, '2025-11-20 03:36:39', '2025-11-20 03:36:39');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `certificados`
--

CREATE TABLE `certificados` (
  `id_certificado` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `codigo_certificado` varchar(50) NOT NULL COMMENT 'C├│digo ├║nico de verificaci├│n',
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_emision` datetime NOT NULL DEFAULT current_timestamp(),
  `puntaje_final` decimal(5,2) DEFAULT NULL COMMENT 'Puntuaci├│n final del curso',
  `horas_completadas` int(11) DEFAULT NULL COMMENT 'Horas invertidas',
  `id_instructor` int(11) DEFAULT NULL COMMENT 'Instructor que emite el certificado',
  `nombre_instructor` varchar(200) DEFAULT NULL,
  `archivo_pdf` varchar(255) DEFAULT NULL COMMENT 'Ruta al PDF generado',
  `valido` tinyint(1) DEFAULT 1,
  `fecha_revocacion` datetime DEFAULT NULL,
  `motivo_revocacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Certificados de finalizaci├│n de cursos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_sistema`
--

CREATE TABLE `configuracion_sistema` (
  `id_config` int(11) NOT NULL,
  `clave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `tipo_dato` enum('string','number','boolean','json') DEFAULT 'string',
  `descripcion` text DEFAULT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuración del sistema';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conversaciones`
--

CREATE TABLE `conversaciones` (
  `id_conversacion` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `id_alumno` int(11) NOT NULL,
  `id_instructor` int(11) NOT NULL,
  `tipo_conversacion` enum('instructor','mentoria') DEFAULT 'instructor',
  `estado` enum('activa','archivada') DEFAULT 'activa',
  `ultimo_mensaje_fecha` datetime DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Conversaciones entre alumnos e instructores por curso';

--
-- Volcado de datos para la tabla `conversaciones`
--

INSERT INTO `conversaciones` (`id_conversacion`, `id_curso`, `id_alumno`, `id_instructor`, `tipo_conversacion`, `estado`, `ultimo_mensaje_fecha`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 16, 18, 17, 'instructor', 'archivada', '2025-11-19 20:27:56', '2025-11-18 23:30:01', '2025-11-19 20:28:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos`
--

CREATE TABLE `cursos` (
  `id_curso` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `id_instructor` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `descripcion_corta` varchar(500) DEFAULT NULL,
  `imagen_portada` varchar(255) DEFAULT NULL,
  `nivel` enum('principiante','intermedio','avanzado') DEFAULT 'principiante',
  `duracion_estimada` int(11) DEFAULT 0 COMMENT 'Duraci??n en minutos',
  `precio` decimal(10,2) DEFAULT 0.00,
  `estado` enum('borrador','publicado','archivado') DEFAULT 'borrador',
  `total_inscripciones` int(11) DEFAULT 0,
  `promedio_calificacion` decimal(3,2) DEFAULT 0.00,
  `total_calificaciones` int(11) DEFAULT 0,
  `fecha_publicacion` datetime DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cursos disponibles en la plataforma';

--
-- Volcado de datos para la tabla `cursos`
--

INSERT INTO `cursos` (`id_curso`, `id_categoria`, `id_instructor`, `titulo`, `slug`, `descripcion`, `descripcion_corta`, `imagen_portada`, `nivel`, `duracion_estimada`, `precio`, `estado`, `total_inscripciones`, `promedio_calificacion`, `total_calificaciones`, `fecha_publicacion`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(16, 1, 17, 'Fundamentos de Emprendimiento Digital', 'fundamentos-emprendimiento-digital', 'Aprende los conceptos b??sicos para iniciar tu propio negocio digital. Este curso cubre desde la generaci??n de ideas hasta la validaci??n de mercado.', 'Inicia tu negocio digital desde cero con este curso pr??ctico y completo.', NULL, 'principiante', 480, 0.00, 'publicado', 0, 0.00, 0, '2025-11-15 14:35:01', '2025-11-15 14:35:01', '2025-11-19 20:26:30'),
(17, 1, 1, 'Plan de Negocios Avanzado', 'plan-negocios-avanzado', 'Desarrolla un plan de negocios profesional y completo. Incluye an??lisis financiero, estrategias de marketing y proyecciones.', 'Crea un plan de negocios que atraiga inversores y gu??e tu emprendimiento.', NULL, 'intermedio', 600, 0.00, 'publicado', 0, 0.00, 0, '2025-11-15 14:35:01', '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(18, 2, 1, 'Contabilidad B??sica para Emprendedores', 'contabilidad-basica-emprendedores', 'Domina los conceptos esenciales de contabilidad para llevar las finanzas de tu negocio de forma eficiente.', 'Aprende a gestionar las finanzas de tu empresa sin ser contador.', NULL, 'principiante', 420, 0.00, 'publicado', 0, 0.00, 0, '2025-11-15 14:35:01', '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(19, 3, 1, 'Marketing en Redes Sociales', 'marketing-redes-sociales', 'Estrategias efectivas para promocionar tu negocio en Facebook, Instagram, LinkedIn y TikTok.', 'Domina las redes sociales y atrae clientes a tu negocio.', NULL, 'principiante', 360, 0.00, 'publicado', 0, 0.00, 0, '2025-11-15 14:35:01', '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(20, 4, 1, 'Liderazgo y Gesti??n de Equipos', 'liderazgo-gestion-equipos', 'Desarrolla habilidades de liderazgo efectivo para inspirar y motivar a tu equipo de trabajo.', 'Convi??rtete en el l??der que tu equipo necesita.', NULL, 'intermedio', 540, 0.00, 'publicado', 0, 0.00, 0, '2025-11-15 14:35:01', '2025-11-15 14:35:01', '2025-11-15 14:35:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `descargas_recursos`
--

CREATE TABLE `descargas_recursos` (
  `id_descarga` int(11) NOT NULL,
  `id_recurso` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_descarga` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Disparadores `descargas_recursos`
--
DELIMITER $$
CREATE TRIGGER `trg_descarga_increment` AFTER INSERT ON `descargas_recursos` FOR EACH ROW BEGIN
    UPDATE recursos 
    SET total_descargas = total_descargas + 1 
    WHERE id_recurso = NEW.id_recurso;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `diagnosticos_empresariales`
--

CREATE TABLE `diagnosticos_empresariales` (
  `id_diagnostico` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `objetivo` text DEFAULT NULL,
  `areas_evaluacion` text DEFAULT NULL,
  `tiempo_estimado_minutos` int(11) DEFAULT NULL,
  `icono` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos de diagnósticos empresariales';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `diagnosticos_realizados`
--

CREATE TABLE `diagnosticos_realizados` (
  `id_diagnostico_realizado` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_perfil_empresarial` int(11) DEFAULT NULL,
  `id_tipo_diagnostico` int(11) NOT NULL,
  `estado` enum('en_progreso','completado','abandonado') DEFAULT 'en_progreso',
  `puntaje_total` decimal(5,2) DEFAULT NULL,
  `nivel_madurez` enum('inicial','basico','intermedio','avanzado','experto') DEFAULT NULL,
  `resultados_areas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Puntajes y an??lisis por ??rea' CHECK (json_valid(`resultados_areas`)),
  `areas_fuertes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Top 3 ??reas fuertes' CHECK (json_valid(`areas_fuertes`)),
  `areas_mejora` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Top 3 ??reas a mejorar' CHECK (json_valid(`areas_mejora`)),
  `recomendaciones_generadas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Recomendaciones autom??ticas' CHECK (json_valid(`recomendaciones_generadas`)),
  `tiempo_dedicado` int(11) DEFAULT 0 COMMENT 'Tiempo en minutos',
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_completado` datetime DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Diagn??sticos realizados por usuarios';

--
-- Volcado de datos para la tabla `diagnosticos_realizados`
--

INSERT INTO `diagnosticos_realizados` (`id_diagnostico_realizado`, `id_usuario`, `id_perfil_empresarial`, `id_tipo_diagnostico`, `estado`, `puntaje_total`, `nivel_madurez`, `resultados_areas`, `areas_fuertes`, `areas_mejora`, `recomendaciones_generadas`, `tiempo_dedicado`, `fecha_inicio`, `fecha_completado`, `fecha_actualizacion`) VALUES
(5, 1, 5, 1, 'en_progreso', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 14:55:04', NULL, '2025-11-15 14:55:04'),
(6, 10, NULL, 1, '', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 15:52:14', NULL, '2025-11-15 15:52:14'),
(7, 10, NULL, 1, '', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 15:54:54', NULL, '2025-11-15 15:54:54'),
(8, 10, NULL, 1, '', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 15:54:58', NULL, '2025-11-15 15:54:58'),
(9, 10, NULL, 1, 'en_progreso', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 15:59:50', NULL, '2025-11-15 15:59:50'),
(10, 10, NULL, 1, 'en_progreso', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 16:03:56', NULL, '2025-11-15 16:03:56'),
(11, 10, NULL, 1, 'en_progreso', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 16:04:05', NULL, '2025-11-15 16:04:05'),
(12, 10, NULL, 1, 'en_progreso', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 16:04:08', NULL, '2025-11-15 16:04:08'),
(13, 10, NULL, 1, 'en_progreso', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 16:04:10', NULL, '2025-11-15 16:04:10'),
(14, 10, NULL, 1, 'en_progreso', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 16:04:27', NULL, '2025-11-15 16:04:27'),
(15, 11, NULL, 1, 'en_progreso', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 16:07:02', NULL, '2025-11-15 16:07:02'),
(16, 11, NULL, 1, 'en_progreso', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 16:08:23', NULL, '2025-11-15 16:08:23'),
(17, 11, NULL, 1, 'en_progreso', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-11-15 16:12:51', NULL, '2025-11-15 16:12:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disponibilidad_instructores`
--

CREATE TABLE `disponibilidad_instructores` (
  `id_disponibilidad` int(11) NOT NULL,
  `id_instructor` int(11) NOT NULL,
  `dia_semana` tinyint(4) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Horarios de disponibilidad de instructores';

--
-- Volcado de datos para la tabla `disponibilidad_instructores`
--

INSERT INTO `disponibilidad_instructores` (`id_disponibilidad`, `id_instructor`, `dia_semana`, `hora_inicio`, `hora_fin`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 17, 1, '09:00:00', '12:00:00', 1, '2025-11-19 20:26:05', '2025-11-19 20:26:05'),
(2, 17, 3, '14:00:00', '17:00:00', 1, '2025-11-19 20:26:05', '2025-11-19 20:26:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estado_presencia`
--

CREATE TABLE `estado_presencia` (
  `id_usuario` int(11) NOT NULL,
  `estado` enum('en_linea','ausente','ocupado','desconectado') DEFAULT 'desconectado',
  `ultima_actividad` datetime DEFAULT NULL,
  `mensaje_estado` varchar(100) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Estado en tiempo real de usuarios (en l├¡nea, ausente, etc.)';

--
-- Volcado de datos para la tabla `estado_presencia`
--

INSERT INTO `estado_presencia` (`id_usuario`, `estado`, `ultima_actividad`, `mensaje_estado`, `fecha_actualizacion`) VALUES
(1, 'desconectado', '2025-11-18 23:15:14', NULL, '2025-11-18 23:15:14'),
(2, 'desconectado', '2025-11-18 23:15:14', NULL, '2025-11-18 23:15:14'),
(7, 'desconectado', '2025-11-18 23:15:14', NULL, '2025-11-18 23:15:14'),
(8, 'desconectado', '2025-11-18 23:15:14', NULL, '2025-11-18 23:15:14'),
(9, 'desconectado', '2025-11-18 23:15:14', NULL, '2025-11-18 23:15:14'),
(10, 'desconectado', '2025-11-18 23:15:14', NULL, '2025-11-18 23:15:14'),
(11, 'desconectado', '2025-11-18 23:15:14', NULL, '2025-11-18 23:15:14'),
(12, 'desconectado', '2025-11-18 23:15:14', NULL, '2025-11-18 23:15:14'),
(13, 'desconectado', '2025-11-18 23:15:14', NULL, '2025-11-18 23:15:14'),
(14, 'desconectado', '2025-11-18 23:15:14', NULL, '2025-11-18 23:15:14'),
(15, 'desconectado', '2025-11-18 23:15:14', NULL, '2025-11-18 23:15:14'),
(16, 'desconectado', '2025-11-18 23:15:14', NULL, '2025-11-18 23:15:14'),
(18, 'en_linea', '2025-11-19 20:25:24', 'Disponible para consultas', '2025-11-19 20:25:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `etiquetas_recursos`
--

CREATE TABLE `etiquetas_recursos` (
  `id_etiqueta` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `total_usos` int(11) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `etiquetas_recursos`
--

INSERT INTO `etiquetas_recursos` (`id_etiqueta`, `nombre`, `slug`, `total_usos`, `fecha_creacion`) VALUES
(1, 'Marketing Digital', 'marketing-digital', 0, '2025-11-20 03:36:39'),
(2, 'Finanzas', 'finanzas', 0, '2025-11-20 03:36:39'),
(3, 'Ventas', 'ventas', 0, '2025-11-20 03:36:39'),
(4, 'Liderazgo', 'liderazgo', 0, '2025-11-20 03:36:39'),
(5, 'Productividad', 'productividad', 0, '2025-11-20 03:36:39'),
(6, 'Estrategia', 'estrategia', 0, '2025-11-20 03:36:39'),
(7, 'Innovaci├│n', 'innovacion', 0, '2025-11-20 03:36:39'),
(8, 'Recursos Humanos', 'recursos-humanos', 0, '2025-11-20 03:36:39'),
(9, 'Legal', 'legal', 0, '2025-11-20 03:36:39'),
(10, 'Tecnolog├¡a', 'tecnologia', 0, '2025-11-20 03:36:39'),
(11, 'Startups', 'startups', 0, '2025-11-20 03:36:39'),
(12, 'E-commerce', 'e-commerce', 0, '2025-11-20 03:36:39'),
(13, 'Redes Sociales', 'redes-sociales', 0, '2025-11-20 03:36:39'),
(14, 'SEO', 'seo', 0, '2025-11-20 03:36:39'),
(15, 'Contabilidad', 'contabilidad', 0, '2025-11-20 03:36:39');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluaciones`
--

CREATE TABLE `evaluaciones` (
  `id_evaluacion` int(11) NOT NULL,
  `id_leccion` int(11) DEFAULT NULL,
  `id_curso` int(11) DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo_evaluacion` enum('quiz','examen','practica') NOT NULL DEFAULT 'quiz',
  `duracion_minutos` int(11) DEFAULT NULL COMMENT 'Duraci├│n en minutos (0 = sin l├¡mite)',
  `intentos_permitidos` int(11) DEFAULT 3 COMMENT 'N├║mero de intentos (0 = ilimitados)',
  `puntaje_minimo_aprobacion` decimal(5,2) DEFAULT 70.00 COMMENT 'Porcentaje m├¡nimo para aprobar',
  `mostrar_resultados_inmediatos` tinyint(1) DEFAULT 1,
  `permitir_revision` tinyint(1) DEFAULT 1,
  `barajar_preguntas` tinyint(1) DEFAULT 0,
  `barajar_opciones` tinyint(1) DEFAULT 0,
  `estado` enum('borrador','publicado','archivado') NOT NULL DEFAULT 'borrador',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Evaluaciones y quizzes del sistema';

--
-- Volcado de datos para la tabla `evaluaciones`
--

INSERT INTO `evaluaciones` (`id_evaluacion`, `id_leccion`, `id_curso`, `titulo`, `descripcion`, `tipo_evaluacion`, `duracion_minutos`, `intentos_permitidos`, `puntaje_minimo_aprobacion`, `mostrar_resultados_inmediatos`, `permitir_revision`, `barajar_preguntas`, `barajar_opciones`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(2, 16, NULL, 'Quiz: Fundamentos de Negocios', 'Evaluaci├│n b├ísica sobre conceptos fundamentales de negocios', 'quiz', 15, 3, 70.00, 1, 1, 0, 0, 'publicado', '2025-11-18 11:41:12', '2025-11-18 11:41:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `imagenes_productos`
--

CREATE TABLE `imagenes_productos` (
  `id_imagen` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `url_imagen` varchar(500) NOT NULL,
  `url_thumbnail` varchar(500) DEFAULT NULL,
  `alt_text` varchar(200) DEFAULT NULL,
  `orden` int(11) DEFAULT 0,
  `es_principal` tinyint(1) DEFAULT 0,
  `tamanio_bytes` int(11) DEFAULT NULL,
  `ancho_px` int(11) DEFAULT NULL,
  `alto_px` int(11) DEFAULT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones`
--

CREATE TABLE `inscripciones` (
  `id_inscripcion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `porcentaje_avance` decimal(5,2) DEFAULT 0.00,
  `lecciones_completadas` int(11) DEFAULT 0,
  `tiempo_dedicado` int(11) DEFAULT 0 COMMENT 'Tiempo en minutos',
  `fecha_inscripcion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_inicio` datetime DEFAULT NULL COMMENT 'Primera lecci??n vista',
  `fecha_finalizacion` datetime DEFAULT NULL COMMENT 'Todas las lecciones completadas',
  `fecha_ultima_actividad` datetime DEFAULT NULL,
  `certificado_generado` tinyint(1) DEFAULT 0,
  `fecha_certificado` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inscripciones de usuarios a cursos';

--
-- Volcado de datos para la tabla `inscripciones`
--

INSERT INTO `inscripciones` (`id_inscripcion`, `id_usuario`, `id_curso`, `porcentaje_avance`, `lecciones_completadas`, `tiempo_dedicado`, `fecha_inscripcion`, `fecha_inicio`, `fecha_finalizacion`, `fecha_ultima_actividad`, `certificado_generado`, `fecha_certificado`) VALUES
(2, 2, 16, 33.33, 3, 0, '2025-11-15 14:35:01', '2025-11-15 14:35:02', NULL, '2025-11-15 14:35:02', 0, NULL),
(3, 2, 19, 0.00, 0, 0, '2025-11-15 14:35:01', NULL, NULL, '2025-11-15 14:35:01', 0, NULL),
(4, 18, 16, 0.00, 0, 0, '2025-11-18 23:28:02', NULL, NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones_curso`
--

CREATE TABLE `inscripciones_curso` (
  `id_inscripcion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `fecha_inscripcion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_finalizacion` datetime DEFAULT NULL,
  `estado` enum('inscrito','en_progreso','completado','abandonado') NOT NULL DEFAULT 'inscrito',
  `progreso_porcentaje` decimal(5,2) DEFAULT 0.00,
  `calificacion` int(11) DEFAULT NULL,
  `comentario_calificacion` text DEFAULT NULL,
  `fecha_calificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inscripciones de usuarios a cursos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `intentos_evaluacion`
--

CREATE TABLE `intentos_evaluacion` (
  `id_intento` int(11) NOT NULL,
  `id_evaluacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `puntaje_obtenido` decimal(5,2) DEFAULT 0.00,
  `puntaje_maximo` decimal(5,2) DEFAULT 0.00,
  `porcentaje` decimal(5,2) DEFAULT 0.00,
  `aprobado` tinyint(1) DEFAULT 0,
  `estado` enum('en_progreso','completado','abandonado') NOT NULL DEFAULT 'en_progreso',
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_finalizacion` datetime DEFAULT NULL,
  `tiempo_transcurrido` int(11) DEFAULT NULL COMMENT 'Tiempo en segundos',
  `numero_intento` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Intentos de evaluaci├│n de usuarios';

--
-- Volcado de datos para la tabla `intentos_evaluacion`
--

INSERT INTO `intentos_evaluacion` (`id_intento`, `id_evaluacion`, `id_usuario`, `puntaje_obtenido`, `puntaje_maximo`, `porcentaje`, `aprobado`, `estado`, `fecha_inicio`, `fecha_finalizacion`, `tiempo_transcurrido`, `numero_intento`) VALUES
(1, 2, 13, 0.00, 0.00, 0.00, 0, 'en_progreso', '2025-11-18 11:56:33', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `interacciones_productos`
--

CREATE TABLE `interacciones_productos` (
  `id_interaccion` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `tipo_interaccion` enum('vista','contacto','compartido','click_whatsapp','click_email','click_telefono') NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `referer` varchar(500) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `fecha_interaccion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `interacciones_productos`
--

INSERT INTO `interacciones_productos` (`id_interaccion`, `id_producto`, `id_usuario`, `tipo_interaccion`, `user_agent`, `ip_address`, `referer`, `metadata`, `fecha_interaccion`) VALUES
(1, 1, NULL, 'vista', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; es-MX) WindowsPowerShell/5.1.19041.6456', '::1', NULL, NULL, '2025-11-19 03:55:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lecciones`
--

CREATE TABLE `lecciones` (
  `id_leccion` int(11) NOT NULL,
  `id_modulo` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `contenido` longtext DEFAULT NULL,
  `tipo_contenido` enum('texto','video','documento','enlace') DEFAULT 'texto',
  `url_recurso` varchar(500) DEFAULT NULL COMMENT 'URL de video, documento o enlace externo',
  `orden` int(11) DEFAULT 0,
  `duracion_minutos` int(11) DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lecciones de cada m??dulo';

--
-- Volcado de datos para la tabla `lecciones`
--

INSERT INTO `lecciones` (`id_leccion`, `id_modulo`, `titulo`, `contenido`, `tipo_contenido`, `url_recurso`, `orden`, `duracion_minutos`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(16, 6, 'Bienvenida al curso', '# Bienvenida\n\nEn este curso aprender??s todo lo necesario para iniciar tu negocio digital. ??Comencemos!', 'texto', NULL, 1, 5, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(17, 6, '??Qu?? es el emprendimiento?', '# Definici??n de Emprendimiento\n\nEl emprendimiento es la capacidad de identificar oportunidades y crear soluciones innovadoras...', 'texto', NULL, 2, 15, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(18, 6, 'Mentalidad emprendedora', '# Mindset del Emprendedor\n\n- Resiliencia\n- Visi??n a largo plazo\n- Aprendizaje continuo\n- Toma de riesgos calculados', 'texto', NULL, 3, 20, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(19, 7, 'Identificaci??n de problemas', '# Encuentra Problemas que Resolver\n\nLos mejores negocios nacen de solucionar problemas reales...', 'texto', NULL, 1, 25, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(20, 7, 'An??lisis de mercado', '# ??Existe demanda para tu idea?\n\nAntes de invertir tiempo y dinero, valida que exista un mercado...', 'texto', NULL, 2, 30, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(21, 7, 'Validaci??n de tu idea', '# T??cnicas de Validaci??n\n\n- Encuestas\n- Entrevistas\n- MVP (Producto M??nimo Viable)\n- Landing page de prueba', 'texto', NULL, 3, 35, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(22, 8, 'Canvas de modelo de negocio', '# Business Model Canvas\n\nUna herramienta visual para dise??ar y validar tu modelo de negocio...', 'texto', NULL, 1, 40, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(23, 8, 'Propuesta de valor', '# Tu Propuesta ??nica de Valor\n\n??Qu?? te hace diferente de la competencia?', 'texto', NULL, 2, 30, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(24, 8, 'Canales de distribuci??n', '# C??mo Llegar a tus Clientes\n\n- Venta directa\n- E-commerce\n- Marketplaces\n- Distribuidores', 'texto', NULL, 3, 25, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(25, 9, 'Introducci??n al marketing digital', '# Marketing Digital en el Siglo XXI\n\nEl marketing ha evolucionado...', 'texto', NULL, 1, 15, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(26, 9, 'P??blico objetivo y buyer persona', '# Conoce a tu Cliente Ideal\n\nDefinir tu buyer persona es fundamental...', 'texto', NULL, 2, 25, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(27, 9, 'Estrategia de contenidos', '# Planifica tu Contenido\n\n- Calendario editorial\n- Tipos de contenido\n- Frecuencia de publicaci??n', 'texto', NULL, 3, 30, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(28, 10, 'Optimizaci??n de perfil', '# Perfil Profesional en Instagram\n\n- Foto de perfil\n- Bio atractiva\n- Link en bio\n- Highlights', 'texto', NULL, 1, 20, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(29, 10, 'Creaci??n de contenido visual', '# Dise??o para Instagram\n\nHerramientas: Canva, Adobe Spark, Unfold...', 'texto', NULL, 2, 35, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(30, 10, 'Stories e Instagram Reels', '# Contenido Ef??mero y Videos Cortos\n\nLos Reels son el formato del momento...', 'texto', NULL, 3, 40, '2025-11-15 14:35:01', '2025-11-15 14:35:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logros`
--

CREATE TABLE `logros` (
  `id_logro` int(11) NOT NULL,
  `id_categoria_logro` int(11) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(255) DEFAULT NULL,
  `tipo_logro` enum('curso_completado','cursos_cantidad','puntos_acumulados','tiempo_plataforma','evaluacion_alta','racha_dias','diagnostico_completado','producto_publicado','otro') NOT NULL,
  `criterio_valor` int(11) DEFAULT NULL,
  `puntos_recompensa` int(11) DEFAULT 0,
  `es_secreto` tinyint(1) DEFAULT 0,
  `nivel_dificultad` enum('bronce','plata','oro','platino') DEFAULT 'bronce',
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cat├ílogo de logros disponibles en el sistema';

--
-- Volcado de datos para la tabla `logros`
--

INSERT INTO `logros` (`id_logro`, `id_categoria_logro`, `nombre`, `descripcion`, `icono`, `tipo_logro`, `criterio_valor`, `puntos_recompensa`, `es_secreto`, `nivel_dificultad`, `activo`, `fecha_creacion`) VALUES
(1, NULL, 'Primer Curso', 'Completa tu primer curso', '??', 'curso_completado', 1, 20, 0, 'bronce', 1, '2025-11-18 12:35:36'),
(2, NULL, '5 Cursos Completados', 'Completa 5 cursos', '??', 'cursos_cantidad', 5, 50, 0, 'plata', 1, '2025-11-18 12:35:36'),
(3, NULL, '10 Cursos Completados', 'Completa 10 cursos', '??', 'cursos_cantidad', 10, 100, 0, 'oro', 1, '2025-11-18 12:35:36'),
(4, NULL, 'Primera Evaluaci¾n', 'Aprueba tu primera evaluaci¾n', '??', 'evaluacion_alta', 1, 15, 0, 'bronce', 1, '2025-11-18 12:35:36'),
(5, NULL, 'Racha de 7 DÝas', 'MantÚn una racha de 7 dÝas consecutivos', '??', 'racha_dias', 7, 30, 0, 'plata', 1, '2025-11-18 12:35:36'),
(6, NULL, 'Racha de 30 DÝas', 'MantÚn una racha de 30 dÝas consecutivos', '??', 'racha_dias', 30, 100, 0, 'oro', 1, '2025-11-18 12:35:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logros_usuarios`
--

CREATE TABLE `logros_usuarios` (
  `id_logro_usuario` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_logro` int(11) NOT NULL,
  `fecha_obtencion` datetime NOT NULL DEFAULT current_timestamp(),
  `visto` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de logros desbloqueados por usuarios';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes`
--

CREATE TABLE `mensajes` (
  `id_mensaje` int(11) NOT NULL,
  `id_conversacion` int(11) NOT NULL,
  `id_remitente` int(11) DEFAULT NULL,
  `remitente_tipo` enum('alumno','instructor','mentoria') NOT NULL,
  `contenido` text NOT NULL,
  `tipo_mensaje` enum('texto','archivo','sistema') DEFAULT 'texto',
  `leido` tinyint(1) DEFAULT 0,
  `fecha_leido` datetime DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `fecha_envio` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mensajes individuales de cada conversaci├│n';

--
-- Volcado de datos para la tabla `mensajes`
--

INSERT INTO `mensajes` (`id_mensaje`, `id_conversacion`, `id_remitente`, `remitente_tipo`, `contenido`, `tipo_mensaje`, `leido`, `fecha_leido`, `metadata`, `fecha_envio`) VALUES
(1, 1, 18, 'alumno', 'Hola instructor, necesito ayuda con el m?dulo 1', 'texto', 0, NULL, NULL, '2025-11-19 20:22:09'),
(2, 1, 18, 'alumno', 'Hola instructor, necesito ayuda', 'texto', 0, NULL, NULL, '2025-11-19 20:22:25'),
(3, 1, 18, 'alumno', 'Hola instructor, necesito ayuda', 'texto', 0, NULL, NULL, '2025-11-19 20:23:03'),
(4, 1, 18, 'alumno', 'Hola instructor, necesito ayuda', 'texto', 0, NULL, NULL, '2025-11-19 20:24:31'),
(5, 1, 17, 'instructor', 'Hola! Claro, te ayudo con el m?dulo 1. ?Qu? duda espec?fica tienes?', 'texto', 0, NULL, NULL, '2025-11-19 20:27:02'),
(6, 1, 17, 'instructor', 'Perfecto! El m?dulo 1 cubre los fundamentos del emprendimiento. ?Necesitas ayuda con alg?n concepto en particular?', 'texto', 0, NULL, NULL, '2025-11-19 20:27:56');

--
-- Disparadores `mensajes`
--
DELIMITER $$
CREATE TRIGGER `after_mensaje_insert` AFTER INSERT ON `mensajes` FOR EACH ROW BEGIN
    
    UPDATE conversaciones 
    SET ultimo_mensaje_fecha = NEW.fecha_envio
    WHERE id_conversacion = NEW.id_conversacion;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mentorias`
--

CREATE TABLE `mentorias` (
  `id_mentoria` int(11) NOT NULL,
  `id_mentor` int(11) NOT NULL,
  `id_aprendiz` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `area_enfoque` varchar(100) DEFAULT NULL,
  `fecha_programada` datetime DEFAULT NULL,
  `duracion_minutos` int(11) DEFAULT NULL,
  `modalidad` enum('presencial','virtual','hibrida') DEFAULT 'virtual',
  `url_reunion` varchar(500) DEFAULT NULL,
  `estado` enum('solicitada','confirmada','completada','cancelada') NOT NULL DEFAULT 'solicitada',
  `calificacion_mentor` int(11) DEFAULT NULL,
  `calificacion_aprendiz` int(11) DEFAULT NULL,
  `comentarios_mentor` text DEFAULT NULL,
  `comentarios_aprendiz` text DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sesiones de mentoría';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mentoria_contexto`
--

CREATE TABLE `mentoria_contexto` (
  `id_contexto` int(11) NOT NULL,
  `id_conversacion` int(11) NOT NULL,
  `prompt_sistema` text DEFAULT NULL,
  `tokens_usados` int(11) DEFAULT 0,
  `costo_estimado` decimal(10,4) DEFAULT 0.0000,
  `modelo_ia` varchar(50) DEFAULT NULL,
  `temperatura` decimal(3,2) DEFAULT 0.70,
  `satisfaccion_usuario` tinyint(4) DEFAULT NULL,
  `feedback_usuario` text DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contexto y m├®tricas de conversaciones con MentorIA';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulos`
--

CREATE TABLE `modulos` (
  `id_modulo` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden` int(11) DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M??dulos de los cursos';

--
-- Volcado de datos para la tabla `modulos`
--

INSERT INTO `modulos` (`id_modulo`, `id_curso`, `titulo`, `descripcion`, `orden`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(6, 16, 'Introducci??n al Emprendimiento', 'Conoce los conceptos b??sicos y la mentalidad emprendedora.', 1, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(7, 16, 'Generaci??n de Ideas de Negocio', 'Aprende t??cnicas para identificar oportunidades y generar ideas viables.', 2, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(8, 16, 'Modelo de Negocio', 'Dise??a el modelo de negocio para tu emprendimiento.', 3, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(9, 19, 'Fundamentos de Marketing Digital', 'Conceptos b??sicos que debes dominar.', 1, '2025-11-15 14:35:01', '2025-11-15 14:35:01'),
(10, 19, 'Instagram para Negocios', 'Domina Instagram y atrae clientes.', 2, '2025-11-15 14:35:01', '2025-11-15 14:35:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulos_curso`
--

CREATE TABLE `modulos_curso` (
  `id_modulo` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden` int(11) NOT NULL,
  `duracion_horas` decimal(4,2) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Módulos de cada curso';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo` enum('logro','curso','mentoria','producto','sistema','diagnostico') NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensaje` text NOT NULL,
  `id_referencia` int(11) DEFAULT NULL,
  `tipo_referencia` varchar(50) DEFAULT NULL,
  `url_accion` varchar(500) DEFAULT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_lectura` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sistema de notificaciones en app';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `opciones_pregunta`
--

CREATE TABLE `opciones_pregunta` (
  `id_opcion` int(11) NOT NULL,
  `id_pregunta_evaluacion` int(11) NOT NULL,
  `texto_opcion` text NOT NULL,
  `es_correcta` tinyint(1) DEFAULT 0,
  `orden` int(11) DEFAULT 0,
  `feedback` text DEFAULT NULL COMMENT 'Feedback al seleccionar esta opci├│n'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Opciones de respuesta para preguntas';

--
-- Volcado de datos para la tabla `opciones_pregunta`
--

INSERT INTO `opciones_pregunta` (`id_opcion`, `id_pregunta_evaluacion`, `texto_opcion`, `es_correcta`, `orden`, `feedback`) VALUES
(29, 11, 'Una estrategia para describir c├│mo una empresa crea, entrega y captura valor', 1, 1, NULL),
(30, 11, 'Un documento legal para registrar una empresa', 0, 2, NULL),
(31, 11, 'Un tipo de software empresarial', 0, 3, NULL),
(32, 11, 'Una forma de calcular impuestos', 0, 4, NULL),
(33, 12, 'Describir ├║nicamente los productos', 0, 1, NULL),
(34, 12, 'Planificar la estrategia, operaciones y viabilidad financiera de la empresa', 1, 2, NULL),
(35, 12, 'Calcular el salario de los empleados', 0, 3, NULL),
(36, 12, 'Dise├▒ar el logo de la empresa', 0, 4, NULL),
(37, 13, 'Solo las amenazas del mercado', 0, 1, NULL),
(38, 13, 'Fortalezas, Oportunidades, Debilidades y Amenazas', 1, 2, NULL),
(39, 13, 'Los ingresos y gastos', 0, 3, NULL),
(40, 13, 'El organigrama de la empresa', 0, 4, NULL),
(41, 14, 'Verdadero', 1, 1, NULL),
(42, 14, 'Falso', 0, 2, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `perfiles_empresariales`
--

CREATE TABLE `perfiles_empresariales` (
  `id_perfil` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `nombre_empresa` varchar(200) NOT NULL,
  `logo_empresa` varchar(255) DEFAULT NULL,
  `eslogan` varchar(300) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `sector` varchar(100) DEFAULT NULL COMMENT 'Ej: Tecnolog??a, Comercio, Servicios, Manufactura, etc.',
  `tipo_negocio` enum('emprendimiento','microempresa','peque??a_empresa','mediana_empresa','grande') DEFAULT 'emprendimiento',
  `etapa_negocio` enum('idea','inicio','crecimiento','consolidacion','expansion') DEFAULT 'idea',
  `anio_fundacion` int(11) DEFAULT NULL,
  `numero_empleados` int(11) DEFAULT 0,
  `facturacion_anual` decimal(15,2) DEFAULT NULL,
  `email_empresa` varchar(150) DEFAULT NULL,
  `telefono_empresa` varchar(20) DEFAULT NULL,
  `sitio_web` varchar(255) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `estado` varchar(100) DEFAULT NULL,
  `pais` varchar(100) DEFAULT NULL,
  `codigo_postal` varchar(20) DEFAULT NULL,
  `redes_sociales` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'URLs de redes sociales' CHECK (json_valid(`redes_sociales`)),
  `perfil_publico` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Perfiles empresariales de usuarios';

--
-- Volcado de datos para la tabla `perfiles_empresariales`
--

INSERT INTO `perfiles_empresariales` (`id_perfil`, `id_usuario`, `nombre_empresa`, `logo_empresa`, `eslogan`, `descripcion`, `sector`, `tipo_negocio`, `etapa_negocio`, `anio_fundacion`, `numero_empleados`, `facturacion_anual`, `email_empresa`, `telefono_empresa`, `sitio_web`, `direccion`, `ciudad`, `estado`, `pais`, `codigo_postal`, `redes_sociales`, `perfil_publico`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 2, 'Cafeter??a El Aroma', NULL, NULL, 'Cafeter??a especializada en caf?? de origen con productos de reposter??a artesanal', 'Gastronom??a', 'microempresa', 'inicio', NULL, 5, 50000.00, 'contacto@cafeelaroma.com', '+34 91 555 1234', 'https://cafeelaroma.com', 'Calle Mayor 45', 'Madrid', NULL, 'Espa??a', NULL, '{\"facebook\": \"cafeelaroma\", \"instagram\": \"@cafeelaroma\"}', 1, '2025-11-15 14:51:59', '2025-11-15 14:51:59'),
(3, 1, 'Cafeter??a El Aroma', NULL, NULL, 'Cafeter??a especializada en caf?? de origen con productos de reposter??a artesanal', 'Gastronom??a', 'microempresa', 'inicio', NULL, 5, 50000.00, 'contacto@cafeelaroma.com', '+34 91 555 1234', 'https://cafeelaroma.com', 'Calle Mayor 45', 'Madrid', NULL, 'Espa??a', NULL, '{\"facebook\": \"cafeelaroma\", \"instagram\": \"@cafeelaroma\"}', 1, '2025-11-15 14:53:21', '2025-11-15 14:53:21'),
(4, 2, 'Consultora Digital MG', NULL, NULL, 'Consultora especializada en transformaci??n digital para PYMEs', 'Consultor??a', 'peque??a_empresa', 'crecimiento', NULL, 15, 250000.00, 'info@consultoramg.com', '+34 93 444 5678', 'https://consultoramg.com', NULL, 'Barcelona', NULL, 'Espa??a', NULL, '{\"linkedin\": \"consultora-mg\", \"twitter\": \"@consultoramg\"}', 1, '2025-11-15 14:53:21', '2025-11-15 14:53:21'),
(5, 1, 'Cafeter??a El Aroma', NULL, NULL, 'Cafeter??a especializada en caf?? de origen', 'Gastronom??a', 'microempresa', 'inicio', NULL, 5, 50000.00, 'contacto@cafeelaroma.com', '+34 91 555 1234', 'https://cafeelaroma.com', NULL, 'Madrid', NULL, 'Espa??a', NULL, NULL, 1, '2025-11-15 14:54:22', '2025-11-15 14:54:22'),
(6, 2, 'Consultora Digital MG', NULL, NULL, 'Consultora de transformaci??n digital', 'Consultor??a', 'peque??a_empresa', 'crecimiento', NULL, 15, 250000.00, 'info@consultoramg.com', '+34 93 444 5678', 'https://consultoramg.com', NULL, 'Barcelona', NULL, 'Espa??a', NULL, NULL, 1, '2025-11-15 14:54:22', '2025-11-15 14:54:22'),
(7, 10, 'Test Company 1763243523752', NULL, NULL, 'Empresa de prueba', 'Tecnología', 'emprendimiento', '', NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-11-15 15:52:04', '2025-11-15 15:52:04'),
(8, 11, 'Test Company 1763244414286', NULL, NULL, 'Empresa de prueba', 'Tecnología', 'emprendimiento', '', NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-11-15 16:06:54', '2025-11-15 16:06:54'),
(9, 12, 'Empresa de Prueba', NULL, NULL, NULL, 'TecnologÝa', '', 'crecimiento', 2021, 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-11-15 16:20:43', '2025-11-15 16:20:43');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preferencias_notificacion`
--

CREATE TABLE `preferencias_notificacion` (
  `id_preferencia` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo_notificacion` enum('logro','curso','evaluacion','certificado','mentoria','sistema','racha','puntos') NOT NULL,
  `notificar_app` tinyint(1) DEFAULT 1,
  `notificar_email` tinyint(1) DEFAULT 0,
  `notificar_push` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuraci├│n de notificaciones por usuario';

--
-- Volcado de datos para la tabla `preferencias_notificacion`
--

INSERT INTO `preferencias_notificacion` (`id_preferencia`, `id_usuario`, `tipo_notificacion`, `notificar_app`, `notificar_email`, `notificar_push`) VALUES
(1, 2, 'logro', 1, 0, 0),
(2, 2, 'curso', 1, 0, 0),
(3, 2, 'evaluacion', 1, 0, 0),
(4, 2, 'certificado', 1, 0, 0),
(5, 2, 'mentoria', 1, 0, 0),
(6, 2, 'sistema', 1, 0, 0),
(7, 2, 'racha', 1, 0, 0),
(8, 2, 'puntos', 1, 0, 0),
(9, 7, 'logro', 1, 0, 0),
(10, 7, 'curso', 1, 0, 0),
(11, 7, 'evaluacion', 1, 0, 0),
(12, 7, 'certificado', 1, 0, 0),
(13, 7, 'mentoria', 1, 0, 0),
(14, 7, 'sistema', 1, 0, 0),
(15, 7, 'racha', 1, 0, 0),
(16, 7, 'puntos', 1, 0, 0),
(17, 8, 'logro', 1, 0, 0),
(18, 8, 'curso', 1, 0, 0),
(19, 8, 'evaluacion', 1, 0, 0),
(20, 8, 'certificado', 1, 0, 0),
(21, 8, 'mentoria', 1, 0, 0),
(22, 8, 'sistema', 1, 0, 0),
(23, 8, 'racha', 1, 0, 0),
(24, 8, 'puntos', 1, 0, 0),
(25, 9, 'logro', 1, 0, 0),
(26, 9, 'curso', 1, 0, 0),
(27, 9, 'evaluacion', 1, 0, 0),
(28, 9, 'certificado', 1, 0, 0),
(29, 9, 'mentoria', 1, 0, 0),
(30, 9, 'sistema', 1, 0, 0),
(31, 9, 'racha', 1, 0, 0),
(32, 9, 'puntos', 1, 0, 0),
(33, 10, 'logro', 1, 0, 0),
(34, 10, 'curso', 1, 0, 0),
(35, 10, 'evaluacion', 1, 0, 0),
(36, 10, 'certificado', 1, 0, 0),
(37, 10, 'mentoria', 1, 0, 0),
(38, 10, 'sistema', 1, 0, 0),
(39, 10, 'racha', 1, 0, 0),
(40, 10, 'puntos', 1, 0, 0),
(41, 11, 'logro', 1, 0, 0),
(42, 11, 'curso', 1, 0, 0),
(43, 11, 'evaluacion', 1, 0, 0),
(44, 11, 'certificado', 1, 0, 0),
(45, 11, 'mentoria', 1, 0, 0),
(46, 11, 'sistema', 1, 0, 0),
(47, 11, 'racha', 1, 0, 0),
(48, 11, 'puntos', 1, 0, 0),
(49, 12, 'logro', 1, 0, 0),
(50, 12, 'curso', 1, 0, 0),
(51, 12, 'evaluacion', 1, 0, 0),
(52, 12, 'certificado', 1, 0, 0),
(53, 12, 'mentoria', 1, 0, 0),
(54, 12, 'sistema', 1, 0, 0),
(55, 12, 'racha', 1, 0, 0),
(56, 12, 'puntos', 1, 0, 0),
(57, 13, 'logro', 1, 0, 0),
(58, 13, 'curso', 1, 0, 0),
(59, 13, 'evaluacion', 1, 0, 0),
(60, 13, 'certificado', 1, 0, 0),
(61, 13, 'mentoria', 1, 0, 0),
(62, 13, 'sistema', 1, 0, 0),
(63, 13, 'racha', 1, 0, 0),
(64, 13, 'puntos', 1, 0, 0),
(65, 1, 'logro', 1, 0, 0),
(66, 1, 'curso', 1, 0, 0),
(67, 1, 'evaluacion', 1, 0, 0),
(68, 1, 'certificado', 1, 0, 0),
(69, 1, 'mentoria', 1, 0, 0),
(70, 1, 'sistema', 1, 0, 0),
(71, 1, 'racha', 1, 0, 0),
(72, 1, 'puntos', 1, 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preguntas_diagnostico`
--

CREATE TABLE `preguntas_diagnostico` (
  `id_pregunta` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  `pregunta` text NOT NULL,
  `descripcion_ayuda` text DEFAULT NULL COMMENT 'Texto de ayuda para entender la pregunta',
  `tipo_pregunta` enum('multiple_choice','escala','si_no','texto','numerica') DEFAULT 'multiple_choice',
  `opciones` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array de opciones con sus valores' CHECK (json_valid(`opciones`)),
  `escala_minima` int(11) DEFAULT 1,
  `escala_maxima` int(11) DEFAULT 5,
  `etiqueta_minima` varchar(100) DEFAULT NULL COMMENT 'Ej: Muy malo',
  `etiqueta_maxima` varchar(100) DEFAULT NULL COMMENT 'Ej: Excelente',
  `ponderacion` decimal(5,2) DEFAULT 1.00 COMMENT 'Peso de la pregunta en su ??rea',
  `es_obligatoria` tinyint(1) DEFAULT 1,
  `orden` int(11) DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Preguntas de diagn??sticos';

--
-- Volcado de datos para la tabla `preguntas_diagnostico`
--

INSERT INTO `preguntas_diagnostico` (`id_pregunta`, `id_area`, `pregunta`, `descripcion_ayuda`, `tipo_pregunta`, `opciones`, `escala_minima`, `escala_maxima`, `etiqueta_minima`, `etiqueta_maxima`, `ponderacion`, `es_obligatoria`, `orden`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, '??Tu empresa cuenta con un plan estrat??gico definido (misi??n, visi??n, objetivos)?', NULL, 'escala', NULL, 1, 5, 'No existe', 'Muy completo', 1.00, 1, 1, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(2, 1, '??Qu?? tan claros est??n definidos los roles y responsabilidades en tu organizaci??n?', NULL, 'escala', NULL, 1, 5, 'Nada claros', 'Muy claros', 1.00, 1, 2, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(3, 1, '??Con qu?? frecuencia se realizan reuniones de seguimiento y evaluaci??n de objetivos?', NULL, 'escala', NULL, 1, 5, 'Nunca', 'Semanalmente', 1.00, 1, 3, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(4, 1, '??Tu negocio cuenta con indicadores clave de desempe??o (KPIs) establecidos?', NULL, 'escala', NULL, 1, 5, 'No tenemos', 'S??, muy bien definidos', 1.00, 1, 4, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(5, 2, '??Llevas un registro sistem??tico de ingresos y gastos?', NULL, 'escala', NULL, 1, 5, 'No llevo registro', 'Registro detallado', 1.00, 1, 1, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(6, 2, '??Tu empresa cuenta con un presupuesto anual?', NULL, 'escala', NULL, 1, 5, 'No tenemos', 'S?? y lo seguimos', 1.00, 1, 2, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(7, 2, '??Realizas an??lisis de rentabilidad de productos/servicios?', NULL, 'escala', NULL, 1, 5, 'Nunca', 'Regularmente', 1.00, 1, 3, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(8, 2, '??Tienes control sobre el flujo de caja del negocio?', NULL, 'escala', NULL, 1, 5, 'Sin control', 'Control total', 1.00, 1, 4, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(9, 3, '??Tu negocio tiene una estrategia de marketing definida?', NULL, 'escala', NULL, 1, 5, 'No tenemos', 'Muy bien definida', 1.00, 1, 1, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(10, 3, '??Conoces claramente qui??n es tu cliente ideal (buyer persona)?', NULL, 'escala', NULL, 1, 5, 'No lo s??', 'Muy bien definido', 1.00, 1, 2, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(11, 3, '??Qu?? tan activa es tu presencia en redes sociales?', NULL, 'escala', NULL, 1, 5, 'No tenemos', 'Muy activa', 1.00, 1, 3, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(12, 3, '??Tienes un proceso de ventas estandarizado?', NULL, 'escala', NULL, 1, 5, 'No existe', 'Muy estandarizado', 1.00, 1, 4, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(13, 4, '??Tus procesos operativos est??n documentados?', NULL, 'escala', NULL, 1, 5, 'No documentados', 'Muy bien documentados', 1.00, 1, 1, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(14, 4, '??Utilizas tecnolog??a para mejorar la eficiencia operativa?', NULL, 'escala', NULL, 1, 5, 'No usamos', 'Uso avanzado', 1.00, 1, 2, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(15, 4, '??C??mo eval??as el control de calidad de tus productos/servicios?', NULL, 'escala', NULL, 1, 5, 'Sin control', 'Control riguroso', 1.00, 1, 3, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(16, 4, '??Qu?? tan eficiente es tu cadena de suministro?', NULL, 'escala', NULL, 1, 5, 'Ineficiente', 'Muy eficiente', 1.00, 1, 4, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(17, 5, '??Tu empresa cuenta con descripciones de puestos de trabajo?', NULL, 'escala', NULL, 1, 5, 'No tenemos', 'Muy detalladas', 1.00, 1, 1, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(18, 5, '??Ofreces capacitaci??n y desarrollo a tu equipo?', NULL, 'escala', NULL, 1, 5, 'Nunca', 'Regularmente', 1.00, 1, 2, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(19, 5, '??C??mo eval??as el clima laboral en tu organizaci??n?', NULL, 'escala', NULL, 1, 5, 'Muy malo', 'Excelente', 1.00, 1, 3, '2025-11-15 14:42:58', '2025-11-15 14:42:58'),
(20, 5, '??Tienes un proceso de evaluaci??n de desempe??o establecido?', NULL, 'escala', NULL, 1, 5, 'No existe', 'Muy estructurado', 1.00, 1, 4, '2025-11-15 14:42:58', '2025-11-15 14:42:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preguntas_evaluacion`
--

CREATE TABLE `preguntas_evaluacion` (
  `id_pregunta_evaluacion` int(11) NOT NULL,
  `id_evaluacion` int(11) NOT NULL,
  `pregunta_texto` text NOT NULL,
  `tipo_pregunta` enum('multiple_choice','verdadero_falso','respuesta_corta','texto_libre') NOT NULL DEFAULT 'multiple_choice',
  `puntos` decimal(5,2) DEFAULT 1.00,
  `orden` int(11) DEFAULT 0,
  `explicacion` text DEFAULT NULL COMMENT 'Explicaci├│n de la respuesta correcta',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Preguntas de las evaluaciones';

--
-- Volcado de datos para la tabla `preguntas_evaluacion`
--

INSERT INTO `preguntas_evaluacion` (`id_pregunta_evaluacion`, `id_evaluacion`, `pregunta_texto`, `tipo_pregunta`, `puntos`, `orden`, `explicacion`, `fecha_creacion`) VALUES
(11, 2, '┬┐Qu├® es un modelo de negocio?', 'multiple_choice', 2.00, 1, NULL, '2025-11-18 11:41:12'),
(12, 2, '┬┐Cu├íl es el objetivo principal de un plan de negocios?', 'multiple_choice', 2.00, 2, NULL, '2025-11-18 11:41:12'),
(13, 2, 'El an├ílisis FODA eval├║a:', 'multiple_choice', 2.00, 3, NULL, '2025-11-18 11:41:12'),
(14, 2, 'Un emprendedor debe tener habilidades financieras b├ísicas', 'verdadero_falso', 2.00, 4, 'Es fundamental que los emprendedores comprendan conceptos financieros b├ísicos para gestionar su negocio efectivamente.', '2025-11-18 11:41:14'),
(15, 2, '┬┐Qu├® significa la sigla MVP en el contexto de startups?', 'respuesta_corta', 2.00, 5, 'MVP significa Minimum Viable Product (Producto M├¡nimo Viable)', '2025-11-18 11:41:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prerrequisitos_curso`
--

CREATE TABLE `prerrequisitos_curso` (
  `id_prerrequisito` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL COMMENT 'Curso que tiene el prerrequisito',
  `id_curso_requerido` int(11) NOT NULL COMMENT 'Curso que se debe completar primero',
  `obligatorio` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Prerrequisitos entre cursos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_perfil_empresarial` int(11) DEFAULT NULL,
  `id_categoria` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `descripcion_corta` varchar(500) DEFAULT NULL,
  `descripcion_completa` text DEFAULT NULL,
  `tipo_producto` enum('producto_fisico','servicio','producto_digital','paquete','consultoria') DEFAULT 'producto_fisico',
  `precio` decimal(10,2) DEFAULT 0.00,
  `moneda` varchar(3) DEFAULT 'MXN',
  `precio_anterior` decimal(10,2) DEFAULT NULL,
  `control_inventario` tinyint(1) DEFAULT 0,
  `cantidad_disponible` int(11) DEFAULT NULL,
  `unidad_medida` varchar(50) DEFAULT NULL,
  `etiquetas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`etiquetas`)),
  `caracteristicas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`caracteristicas`)),
  `contacto_whatsapp` varchar(20) DEFAULT NULL,
  `contacto_email` varchar(150) DEFAULT NULL,
  `contacto_telefono` varchar(20) DEFAULT NULL,
  `ubicacion_ciudad` varchar(100) DEFAULT NULL,
  `ubicacion_estado` varchar(100) DEFAULT NULL,
  `ubicacion_pais` varchar(100) DEFAULT 'M├®xico',
  `estado` enum('borrador','publicado','pausado','agotado','archivado') DEFAULT 'borrador',
  `destacado` tinyint(1) DEFAULT 0,
  `total_vistas` int(11) DEFAULT 0,
  `total_contactos` int(11) DEFAULT 0,
  `total_favoritos` int(11) DEFAULT 0,
  `calificacion_promedio` decimal(3,2) DEFAULT 0.00,
  `meta_titulo` varchar(200) DEFAULT NULL,
  `meta_descripcion` varchar(500) DEFAULT NULL,
  `fecha_publicacion` timestamp NULL DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id_producto`, `id_usuario`, `id_perfil_empresarial`, `id_categoria`, `titulo`, `slug`, `descripcion_corta`, `descripcion_completa`, `tipo_producto`, `precio`, `moneda`, `precio_anterior`, `control_inventario`, `cantidad_disponible`, `unidad_medida`, `etiquetas`, `caracteristicas`, `contacto_whatsapp`, `contacto_email`, `contacto_telefono`, `ubicacion_ciudad`, `ubicacion_estado`, `ubicacion_pais`, `estado`, `destacado`, `total_vistas`, `total_contactos`, `total_favoritos`, `calificacion_promedio`, `meta_titulo`, `meta_descripcion`, `fecha_publicacion`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, NULL, 1, 'CafÚ Artesanal Orgßnico', 'cafe-artesanal-organico', 'CafÚ 100% orgßnico de la sierra', 'Delicioso cafÚ orgßnico cultivado en las monta±as de Jalisco. Tostado artesanalmente.', 'producto_fisico', 150.00, 'MXN', NULL, 0, NULL, NULL, NULL, NULL, '523311234567', NULL, NULL, 'Guadalajara', 'Jalisco', 'M├®xico', 'publicado', 1, 0, 0, 0, 0.00, NULL, NULL, NULL, '2025-11-19 03:48:43', '2025-11-19 03:48:43'),
(2, 1, NULL, 1, 'Miel Organica de Abeja', 'miel-organica-de-abeja', NULL, NULL, 'producto_fisico', 120.00, 'MXN', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'México', 'borrador', 0, 0, 0, 0, 0.00, NULL, NULL, NULL, '2025-11-19 04:06:19', '2025-11-19 04:06:19'),
(3, 1, NULL, 1, 'Miel Orgánica de Abeja Premium', 'miel-orgaanica-de-abeja-premium', 'Miel 100% pura y orgánica', NULL, 'producto_fisico', 180.00, 'MXN', NULL, 0, NULL, NULL, NULL, NULL, '523311234567', NULL, NULL, 'Guadalajara', 'Jalisco', 'México', 'borrador', 0, 0, 0, 0, 0.00, NULL, NULL, NULL, '2025-11-19 04:06:53', '2025-11-19 04:06:53');

--
-- Disparadores `productos`
--
DELIMITER $$
CREATE TRIGGER `after_producto_delete` AFTER DELETE ON `productos` FOR EACH ROW BEGIN
    IF OLD.estado = 'publicado' THEN
        UPDATE categorias_productos 
        SET total_productos = total_productos - 1 
        WHERE id_categoria = OLD.id_categoria;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_producto_insert` AFTER INSERT ON `productos` FOR EACH ROW BEGIN
    IF NEW.estado = 'publicado' THEN
        UPDATE categorias_productos 
        SET total_productos = total_productos + 1 
        WHERE id_categoria = NEW.id_categoria;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_producto_update` AFTER UPDATE ON `productos` FOR EACH ROW BEGIN
    
    IF NEW.estado = 'publicado' AND OLD.estado != 'publicado' THEN
        UPDATE categorias_productos 
        SET total_productos = total_productos + 1 
        WHERE id_categoria = NEW.id_categoria;
    END IF;
    
    
    IF OLD.estado = 'publicado' AND NEW.estado != 'publicado' THEN
        UPDATE categorias_productos 
        SET total_productos = total_productos - 1 
        WHERE id_categoria = NEW.id_categoria;
    END IF;
    
    
    IF NEW.id_categoria != OLD.id_categoria AND NEW.estado = 'publicado' THEN
        UPDATE categorias_productos 
        SET total_productos = total_productos - 1 
        WHERE id_categoria = OLD.id_categoria;
        
        UPDATE categorias_productos 
        SET total_productos = total_productos + 1 
        WHERE id_categoria = NEW.id_categoria;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_favoritos`
--

CREATE TABLE `productos_favoritos` (
  `id_favorito` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `fecha_agregado` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Disparadores `productos_favoritos`
--
DELIMITER $$
CREATE TRIGGER `after_favorito_delete` AFTER DELETE ON `productos_favoritos` FOR EACH ROW BEGIN
    UPDATE productos 
    SET total_favoritos = total_favoritos - 1 
    WHERE id_producto = OLD.id_producto;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_favorito_insert` AFTER INSERT ON `productos_favoritos` FOR EACH ROW BEGIN
    UPDATE productos 
    SET total_favoritos = total_favoritos + 1 
    WHERE id_producto = NEW.id_producto;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `progreso_lecciones`
--

CREATE TABLE `progreso_lecciones` (
  `id_progreso` int(11) NOT NULL,
  `id_inscripcion` int(11) NOT NULL,
  `id_leccion` int(11) NOT NULL,
  `completada` tinyint(1) DEFAULT 0,
  `tiempo_dedicado` int(11) DEFAULT 0 COMMENT 'Tiempo en minutos',
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_completado` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Progreso de lecciones por usuario';

--
-- Volcado de datos para la tabla `progreso_lecciones`
--

INSERT INTO `progreso_lecciones` (`id_progreso`, `id_inscripcion`, `id_leccion`, `completada`, `tiempo_dedicado`, `fecha_inicio`, `fecha_completado`) VALUES
(1, 2, 16, 1, 5, '2025-11-15 14:35:02', '2025-11-15 14:35:02'),
(2, 2, 17, 1, 15, '2025-11-15 14:35:02', '2025-11-15 14:35:02'),
(3, 2, 19, 1, 25, '2025-11-15 14:35:02', '2025-11-15 14:35:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `puntos_usuario`
--

CREATE TABLE `puntos_usuario` (
  `id_puntos` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `puntos_totales` int(11) DEFAULT 0,
  `puntos_disponibles` int(11) DEFAULT 0,
  `puntos_gastados` int(11) DEFAULT 0,
  `nivel` int(11) DEFAULT 1,
  `experiencia` int(11) DEFAULT 0,
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Puntos acumulados por cada usuario';

--
-- Volcado de datos para la tabla `puntos_usuario`
--

INSERT INTO `puntos_usuario` (`id_puntos`, `id_usuario`, `puntos_totales`, `puntos_disponibles`, `puntos_gastados`, `nivel`, `experiencia`, `fecha_actualizacion`) VALUES
(1, 14, 0, 0, 0, 1, 0, '2025-11-18 12:20:39'),
(2, 15, 0, 0, 0, 1, 0, '2025-11-18 13:13:03'),
(3, 16, 0, 0, 0, 1, 0, '2025-11-18 20:22:44'),
(4, 17, 0, 0, 0, 1, 0, '2025-11-18 23:26:52'),
(5, 18, 0, 0, 0, 1, 0, '2025-11-18 23:27:25'),
(6, 19, 0, 0, 0, 1, 0, '2025-11-19 21:44:41'),
(7, 2, 0, 0, 0, 1, 0, '2025-11-22 13:18:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `puntos_usuarios`
--

CREATE TABLE `puntos_usuarios` (
  `id_punto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `puntos` int(11) NOT NULL,
  `concepto` varchar(200) NOT NULL,
  `tipo_transaccion` enum('ganancia','canje','ajuste') NOT NULL DEFAULT 'ganancia',
  `id_referencia` int(11) DEFAULT NULL,
  `tipo_referencia` varchar(50) DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de puntos de usuarios';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rachas_usuario`
--

CREATE TABLE `rachas_usuario` (
  `id_racha` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `racha_actual` int(11) DEFAULT 0,
  `racha_maxima` int(11) DEFAULT 0,
  `ultima_actividad` date DEFAULT NULL,
  `fecha_inicio_racha` date DEFAULT NULL,
  `racha_congelada` tinyint(1) DEFAULT 0 COMMENT 'Protecci├│n temporal de racha',
  `congelaciones_disponibles` int(11) DEFAULT 0,
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracking de rachas de actividad diaria';

--
-- Volcado de datos para la tabla `rachas_usuario`
--

INSERT INTO `rachas_usuario` (`id_racha`, `id_usuario`, `racha_actual`, `racha_maxima`, `ultima_actividad`, `fecha_inicio_racha`, `racha_congelada`, `congelaciones_disponibles`, `fecha_actualizacion`) VALUES
(1, 14, 1, 1, '2025-11-18', NULL, 0, 0, '2025-11-18 12:31:46'),
(2, 15, 0, 0, NULL, NULL, 0, 0, '2025-11-18 13:13:03'),
(3, 16, 0, 0, NULL, NULL, 0, 0, '2025-11-18 20:22:44'),
(4, 17, 0, 0, NULL, NULL, 0, 0, '2025-11-18 23:26:52'),
(5, 18, 0, 0, NULL, NULL, 0, 0, '2025-11-18 23:27:25'),
(6, 19, 0, 0, NULL, NULL, 0, 0, '2025-11-19 21:44:41'),
(7, 2, 0, 0, NULL, NULL, 0, 3, '2025-11-22 13:19:00');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `ranking_usuarios`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `ranking_usuarios` (
`id_usuario` int(11)
,`nombre` varchar(100)
,`apellido` varchar(100)
,`foto_perfil` varchar(255)
,`puntos_totales` int(11)
,`nivel` int(11)
,`experiencia` int(11)
,`posicion_global` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recomendaciones_cursos`
--

CREATE TABLE `recomendaciones_cursos` (
  `id_recomendacion` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `puntaje_minimo` decimal(5,2) DEFAULT 0.00,
  `puntaje_maximo` decimal(5,2) DEFAULT 100.00,
  `prioridad` enum('baja','media','alta','critica') DEFAULT 'media',
  `justificacion` text DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recomendaciones de cursos por ??rea y puntaje';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recursos`
--

CREATE TABLE `recursos` (
  `id_recurso` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `id_autor` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `slug` varchar(300) NOT NULL,
  `descripcion` text NOT NULL,
  `tipo_recurso` enum('articulo','ebook','plantilla','herramienta','video','infografia','podcast') NOT NULL DEFAULT 'articulo',
  `tipo_acceso` enum('gratuito','premium','suscripcion') NOT NULL DEFAULT 'gratuito',
  `archivo_url` varchar(500) DEFAULT NULL,
  `archivo_nombre` varchar(255) DEFAULT NULL,
  `archivo_tipo` varchar(100) DEFAULT NULL,
  `archivo_tamanio` bigint(20) UNSIGNED DEFAULT NULL,
  `contenido_texto` longtext DEFAULT NULL,
  `contenido_html` longtext DEFAULT NULL,
  `url_externo` varchar(500) DEFAULT NULL,
  `duracion_minutos` int(10) UNSIGNED DEFAULT NULL,
  `imagen_portada` varchar(500) DEFAULT NULL,
  `imagen_preview` varchar(500) DEFAULT NULL,
  `video_preview` varchar(500) DEFAULT NULL,
  `nivel` enum('principiante','intermedio','avanzado') DEFAULT 'principiante',
  `idioma` varchar(10) DEFAULT 'es',
  `formato` varchar(50) DEFAULT NULL,
  `licencia` varchar(100) DEFAULT 'Uso educativo',
  `total_descargas` int(10) UNSIGNED DEFAULT 0,
  `total_vistas` int(10) UNSIGNED DEFAULT 0,
  `calificacion_promedio` decimal(3,2) DEFAULT 0.00,
  `total_calificaciones` int(10) UNSIGNED DEFAULT 0,
  `estado` enum('borrador','publicado','archivado') DEFAULT 'borrador',
  `destacado` tinyint(1) DEFAULT 0,
  `orden` int(10) UNSIGNED DEFAULT 0,
  `fecha_publicacion` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Disparadores `recursos`
--
DELIMITER $$
CREATE TRIGGER `trg_recursos_delete_categoria` AFTER DELETE ON `recursos` FOR EACH ROW BEGIN
    IF OLD.estado = 'publicado' THEN
        UPDATE categorias_recursos 
        SET total_recursos = total_recursos - 1 
        WHERE id_categoria = OLD.id_categoria;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_recursos_insert_categoria` AFTER INSERT ON `recursos` FOR EACH ROW BEGIN
    IF NEW.estado = 'publicado' THEN
        UPDATE categorias_recursos 
        SET total_recursos = total_recursos + 1 
        WHERE id_categoria = NEW.id_categoria;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_recursos_update_categoria` AFTER UPDATE ON `recursos` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_recursos_version_insert` AFTER INSERT ON `recursos` FOR EACH ROW BEGIN
    
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
        'creacion', 'Versi??n inicial del recurso', JSON_ARRAY('*')
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recursos_aprendizaje`
--

CREATE TABLE `recursos_aprendizaje` (
  `id_recurso` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo_recurso` enum('articulo','ebook','plantilla','herramienta','video','infografia','podcast') NOT NULL,
  `url_recurso` varchar(500) DEFAULT NULL,
  `archivo_recurso` varchar(255) DEFAULT NULL,
  `imagen_portada` varchar(255) DEFAULT NULL,
  `categorias` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`categorias`)),
  `etiquetas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`etiquetas`)),
  `es_gratuito` tinyint(1) DEFAULT 1,
  `nivel` enum('basico','intermedio','avanzado') DEFAULT 'basico',
  `descargas` int(11) DEFAULT 0,
  `vistas` int(11) DEFAULT 0,
  `calificacion_promedio` decimal(3,2) DEFAULT 0.00,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recursos de aprendizaje adicionales';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recursos_etiquetas`
--

CREATE TABLE `recursos_etiquetas` (
  `id_recurso` int(11) NOT NULL,
  `id_etiqueta` int(11) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Disparadores `recursos_etiquetas`
--
DELIMITER $$
CREATE TRIGGER `trg_etiqueta_delete` AFTER DELETE ON `recursos_etiquetas` FOR EACH ROW BEGIN
    UPDATE etiquetas_recursos 
    SET total_usos = total_usos - 1 
    WHERE id_etiqueta = OLD.id_etiqueta;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_etiqueta_insert` AFTER INSERT ON `recursos_etiquetas` FOR EACH ROW BEGIN
    UPDATE etiquetas_recursos 
    SET total_usos = total_usos + 1 
    WHERE id_etiqueta = NEW.id_etiqueta;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recursos_etiquetas_versiones`
--

CREATE TABLE `recursos_etiquetas_versiones` (
  `id_version_etiqueta` int(11) NOT NULL,
  `id_version` int(11) NOT NULL,
  `id_etiqueta` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Etiquetas asociadas a cada versi??n del recurso';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recursos_versiones`
--

CREATE TABLE `recursos_versiones` (
  `id_version` int(11) NOT NULL,
  `id_recurso` int(11) NOT NULL,
  `numero_version` int(11) NOT NULL COMMENT 'Versi??n incremental (1, 2, 3...)',
  `id_usuario_cambio` int(11) NOT NULL,
  `fecha_cambio` datetime NOT NULL DEFAULT current_timestamp(),
  `id_categoria` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo_recurso` enum('articulo','ebook','plantilla','herramienta','video','infografia','podcast','guia','checklist') NOT NULL,
  `tipo_acceso` enum('gratuito','premium','suscripcion') DEFAULT 'gratuito',
  `archivo_url` varchar(500) DEFAULT NULL,
  `archivo_nombre` varchar(255) DEFAULT NULL,
  `archivo_tipo` varchar(100) DEFAULT NULL,
  `archivo_tamanio` int(11) DEFAULT NULL,
  `contenido_texto` text DEFAULT NULL,
  `contenido_html` text DEFAULT NULL,
  `url_externo` varchar(500) DEFAULT NULL,
  `duracion_minutos` int(11) DEFAULT NULL,
  `imagen_portada` varchar(500) DEFAULT NULL,
  `imagen_preview` varchar(500) DEFAULT NULL,
  `video_preview` varchar(500) DEFAULT NULL,
  `nivel` enum('principiante','intermedio','avanzado','experto') DEFAULT 'principiante',
  `idioma` varchar(5) DEFAULT 'es',
  `formato` varchar(50) DEFAULT NULL,
  `licencia` varchar(255) DEFAULT 'Uso educativo',
  `estado` enum('borrador','revision','publicado','archivado') DEFAULT 'borrador',
  `destacado` tinyint(1) DEFAULT 0,
  `fecha_publicacion` datetime DEFAULT NULL,
  `tipo_cambio` enum('creacion','actualizacion','restauracion','publicacion','despublicacion') NOT NULL,
  `descripcion_cambio` text DEFAULT NULL COMMENT 'Descripci??n del cambio realizado',
  `campos_modificados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array de campos que cambiaron: ["titulo", "descripcion"]' CHECK (json_valid(`campos_modificados`)),
  `datos_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Valores anteriores de los campos modificados' CHECK (json_valid(`datos_anteriores`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de versiones de recursos con snapshots completos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas_diagnostico`
--

CREATE TABLE `respuestas_diagnostico` (
  `id_respuesta` int(11) NOT NULL,
  `id_diagnostico_realizado` int(11) NOT NULL,
  `id_pregunta` int(11) NOT NULL,
  `respuesta_valor` decimal(10,2) DEFAULT NULL COMMENT 'Para preguntas num??ricas o de escala',
  `respuesta_texto` text DEFAULT NULL COMMENT 'Para preguntas de texto libre',
  `respuesta_opcion` varchar(255) DEFAULT NULL COMMENT 'Para preguntas de opci??n m??ltiple',
  `respuesta_si_no` tinyint(1) DEFAULT NULL COMMENT 'Para preguntas de s??/no',
  `puntaje_obtenido` decimal(5,2) DEFAULT NULL,
  `fecha_respuesta` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Respuestas a preguntas de diagn??sticos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas_evaluacion`
--

CREATE TABLE `respuestas_evaluacion` (
  `id_respuesta_evaluacion` int(11) NOT NULL,
  `id_intento` int(11) NOT NULL,
  `id_pregunta_evaluacion` int(11) NOT NULL,
  `id_opcion_seleccionada` int(11) DEFAULT NULL COMMENT 'Para preguntas de opci├│n m├║ltiple',
  `respuesta_texto` text DEFAULT NULL COMMENT 'Para preguntas de texto',
  `es_correcta` tinyint(1) DEFAULT NULL,
  `puntos_obtenidos` decimal(5,2) DEFAULT 0.00,
  `fecha_respuesta` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Respuestas de usuarios en evaluaciones';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_diagnostico`
--

CREATE TABLE `tipos_diagnostico` (
  `id_tipo_diagnostico` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `slug` varchar(220) NOT NULL,
  `duracion_estimada` int(11) DEFAULT 30 COMMENT 'Duraci??n estimada en minutos',
  `nivel_detalle` enum('basico','intermedio','avanzado') DEFAULT 'basico',
  `formula_calculo` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Configuraci??n de c??mo calcular el puntaje' CHECK (json_valid(`formula_calculo`)),
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos de diagn??sticos disponibles';

--
-- Volcado de datos para la tabla `tipos_diagnostico`
--

INSERT INTO `tipos_diagnostico` (`id_tipo_diagnostico`, `nombre`, `descripcion`, `slug`, `duracion_estimada`, `nivel_detalle`, `formula_calculo`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Diagn??stico de Madurez Empresarial', 'Eval??a el nivel de desarrollo y madurez de tu negocio en ??reas clave como gesti??n, finanzas, marketing, operaciones y recursos humanos.', 'madurez-empresarial', 25, 'intermedio', NULL, 1, '2025-11-15 14:42:58', '2025-11-15 14:42:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transacciones_puntos`
--

CREATE TABLE `transacciones_puntos` (
  `id_transaccion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo_transaccion` enum('ganancia','gasto','ajuste') NOT NULL,
  `puntos` int(11) NOT NULL,
  `concepto` varchar(255) NOT NULL,
  `referencia_tipo` enum('curso','leccion','diagnostico','evaluacion','logro','racha','manual') NOT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `fecha_transaccion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de todas las transacciones de puntos';

--
-- Disparadores `transacciones_puntos`
--
DELIMITER $$
CREATE TRIGGER `after_transaccion_puntos_insert` AFTER INSERT ON `transacciones_puntos` FOR EACH ROW BEGIN
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
    
    
    UPDATE puntos_usuario 
    SET nivel = FLOOR(SQRT(experiencia / 100)) + 1
    WHERE id_usuario = NEW.id_usuario;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `tipo_usuario` enum('emprendedor','empresario','mentor','administrador') NOT NULL DEFAULT 'emprendedor',
  `estado` enum('activo','inactivo','suspendido') NOT NULL DEFAULT 'activo',
  `foto_perfil` varchar(255) DEFAULT NULL,
  `biografia` text DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `pais` varchar(100) DEFAULT NULL,
  `configuracion_privacidad` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{"perfil_publico": true, "mostrar_email": false, "mostrar_telefono": false, "mostrar_biografia": true, "mostrar_ubicacion": true, "permitir_mensajes": true}' COMMENT 'Configuración de privacidad del usuario' CHECK (json_valid(`configuracion_privacidad`)),
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `ultimo_acceso` datetime DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuarios del sistema de formación empresarial';

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `email`, `telefono`, `password_hash`, `tipo_usuario`, `estado`, `foto_perfil`, `biografia`, `ciudad`, `pais`, `configuracion_privacidad`, `fecha_registro`, `ultimo_acceso`, `fecha_actualizacion`) VALUES
(1, 'Juan', 'P??rez', 'instructor@test.com', NULL, '$2y$12$6oKnkQSN8J1vjzVDVj998eocaMm/OPMDZ6okwdhYbZsz.VGqhWyk.', 'mentor', 'activo', NULL, 'Instructor con 10 a??os de experiencia en emprendimiento.', NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-15 14:34:22', '2025-11-22 12:07:32', '2025-11-22 12:07:32'),
(2, 'Mar??a', 'Gonz??lez', 'emprendedor@test.com', NULL, '$2y$12$6oKnkQSN8J1vjzVDVj998eocaMm/OPMDZ6okwdhYbZsz.VGqhWyk.', 'emprendedor', 'activo', NULL, 'Emprendedora apasionada por aprender.', NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-15 14:34:22', '2025-11-22 14:20:27', '2025-11-22 14:20:27'),
(7, 'Test', 'User', 'test@example.com', NULL, '$2y$12$AVj1Zt6i3BifMJfhaz0wb.XgQDSe17abIBRsTIjUJfN2fqiLEteJ2', 'emprendedor', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-15 15:35:49', '2025-11-15 16:22:51', '2025-11-15 16:22:51'),
(8, 'Test', 'User', 'test1763242661008@example.com', NULL, '$2y$12$jwVALdGK1zfzDQyySeUR0.HsoK83V/n5XLS78Dt544y/eBXPJU0uG', 'emprendedor', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-15 15:37:41', '2025-11-15 15:38:59', '2025-11-15 15:38:59'),
(9, 'Test', 'User', 'test1763242770650@example.com', NULL, '$2y$12$cwpTR2IAXp5drSOFxcjBbOvFedB2k0f6eLO/A6uO1iY9Er4nMoPPy', 'emprendedor', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-15 15:39:31', '2025-11-15 15:39:47', '2025-11-15 15:39:47'),
(10, 'Test', 'User', 'test1763243516255@example.com', NULL, '$2y$12$nRfYIGmoNtM8Sqj0ul7iMuHqf2ozqm4/T9oC3KLNVyru62VJhLi6O', 'emprendedor', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-15 15:51:57', '2025-11-15 16:40:54', '2025-11-15 16:40:54'),
(11, 'Test', 'User', 'test1763244408045@example.com', NULL, '$2y$12$F3Srg/XeyYn5nu1hGQ2T6e4j/VhIJf66ZY5TOrGDLK7SgBoAuE/YC', 'emprendedor', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-15 16:06:48', '2025-11-15 16:13:14', '2025-11-15 16:13:14'),
(12, 'Usuario', 'Prueba', 'prueba@test.com', NULL, '$2y$10$B5FbNUbES5SmaD/t6rex5eFWTQHN04Z3cUaZI1SImyoFthADzqzDe', 'emprendedor', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-15 16:20:18', '2025-11-18 20:34:48', '2025-11-18 20:34:48'),
(13, 'Usuario', 'Prueba', 'eval@test.com', NULL, '$2y$12$uWWmGjYFbR4cOWGW4vuDsu6i2xkEy3O9DEl6opiGodO9dQCKPtr4G', 'emprendedor', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-18 11:54:57', '2025-11-18 11:56:33', '2025-11-18 11:56:33'),
(14, 'Usuario', 'Gamif', 'gam@test.com', NULL, '$2y$12$2DArgyS6Q95NZ40VGL1F8uGGUxVHazv0HEXpkx5aHhF/pTDo5jkzK', 'emprendedor', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-18 12:20:39', '2025-11-18 12:36:51', '2025-11-18 12:36:51'),
(15, 'Usuario', 'Gamificacion', 'gamificacion@test.com', NULL, '$2y$12$AcZrsoXFErFvep7ok52iH.VDuNYd./dJiGJ6HeETAfndoevBH3J9.', 'emprendedor', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-18 13:13:03', '2025-11-18 20:38:10', '2025-11-18 20:38:10'),
(16, 'Test', 'Frontend', 'frontend@test.com', NULL, '$2y$12$6oKnkQSN8J1vjzVDVj998eocaMm/OPMDZ6okwdhYbZsz.VGqhWyk.', 'emprendedor', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-18 20:22:44', NULL, '2025-11-19 23:34:03'),
(17, 'Instructor', 'Test', 'instructor.test@nyd.com', NULL, '$2y$12$6oKnkQSN8J1vjzVDVj998eocaMm/OPMDZ6okwdhYbZsz.VGqhWyk.', 'mentor', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-18 23:26:52', '2025-11-19 20:27:56', '2025-11-19 23:34:03'),
(18, 'Alumno', 'Test', 'alumno.test@nyd.com', NULL, '$2y$12$6oKnkQSN8J1vjzVDVj998eocaMm/OPMDZ6okwdhYbZsz.VGqhWyk.', 'emprendedor', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-18 23:27:25', '2025-11-19 21:09:28', '2025-11-19 23:34:03'),
(19, 'Admin', 'Test', 'admin@test.com', NULL, '$2y$12$6oKnkQSN8J1vjzVDVj998eocaMm/OPMDZ6okwdhYbZsz.VGqhWyk.', 'administrador', 'activo', NULL, NULL, NULL, NULL, '{\"perfil_publico\": true, \"mostrar_email\": false, \"mostrar_telefono\": false, \"mostrar_biografia\": true, \"mostrar_ubicacion\": true, \"permitir_mensajes\": true}', '2025-11-19 21:44:41', '2025-11-24 20:20:02', '2025-11-24 20:20:02');

--
-- Disparadores `usuarios`
--
DELIMITER $$
CREATE TRIGGER `after_usuario_insert` AFTER INSERT ON `usuarios` FOR EACH ROW BEGIN
    INSERT INTO puntos_usuario (id_usuario, puntos_totales, puntos_disponibles, nivel)
    VALUES (NEW.id_usuario, 0, 0, 1);
    
    INSERT INTO rachas_usuario (id_usuario, racha_actual, racha_maxima)
    VALUES (NEW.id_usuario, 0, 0);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vistas_recursos`
--

CREATE TABLE `vistas_recursos` (
  `id_vista` int(11) NOT NULL,
  `id_recurso` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha_vista` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Disparadores `vistas_recursos`
--
DELIMITER $$
CREATE TRIGGER `trg_vista_increment` AFTER INSERT ON `vistas_recursos` FOR EACH ROW BEGIN
    UPDATE recursos 
    SET total_vistas = total_vistas + 1 
    WHERE id_recurso = NEW.id_recurso;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_conversaciones_completas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_conversaciones_completas` (
`id_conversacion` int(11)
,`tipo_conversacion` enum('instructor','mentoria')
,`estado` enum('activa','archivada')
,`ultimo_mensaje_fecha` datetime
,`fecha_creacion` datetime
,`id_curso` int(11)
,`curso_titulo` varchar(200)
,`curso_imagen` varchar(255)
,`id_alumno` int(11)
,`alumno_nombre` varchar(100)
,`alumno_email` varchar(150)
,`alumno_foto` varchar(255)
,`id_instructor` int(11)
,`instructor_nombre` varchar(100)
,`instructor_email` varchar(150)
,`instructor_foto` varchar(255)
,`instructor_estado` enum('en_linea','ausente','ocupado','desconectado')
,`instructor_ultima_actividad` datetime
,`instructor_mensaje_estado` varchar(100)
,`mensajes_no_leidos_alumno` bigint(21)
,`mensajes_no_leidos_instructor` bigint(21)
,`ultimo_mensaje` mediumtext
,`ultimo_mensaje_remitente` varchar(10)
,`total_mensajes` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_cursos_populares`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_cursos_populares` (
  `id_curso` int(11),
  `titulo` varchar(200),
  `nivel` varchar(50),
  `categoria` varchar(100),
  `total_inscritos` int(11),
  `calificacion_promedio` decimal(3,2),
  `inscritos_activos` bigint(21),
  `completados` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_productos_completa`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_productos_completa` (
`id_producto` int(11)
,`titulo` varchar(200)
,`slug` varchar(220)
,`descripcion_corta` varchar(500)
,`tipo_producto` enum('producto_fisico','servicio','producto_digital','paquete','consultoria')
,`precio` decimal(10,2)
,`moneda` varchar(3)
,`precio_anterior` decimal(10,2)
,`estado` enum('borrador','publicado','pausado','agotado','archivado')
,`destacado` tinyint(1)
,`total_vistas` int(11)
,`total_contactos` int(11)
,`total_favoritos` int(11)
,`ubicacion_ciudad` varchar(100)
,`ubicacion_estado` varchar(100)
,`fecha_publicacion` timestamp
,`categoria_nombre` varchar(100)
,`categoria_slug` varchar(120)
,`categoria_color` varchar(7)
,`vendedor_id` int(11)
,`vendedor_nombre` varchar(100)
,`vendedor_email` varchar(150)
,`perfil_id` int(11)
,`nombre_empresa` varchar(200)
,`perfil_logo` varchar(255)
,`imagen_principal` varchar(500)
,`total_imagenes` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_progreso_usuarios`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_progreso_usuarios` (
`id_usuario` int(11)
,`nombre` varchar(100)
,`apellido` varchar(100)
,`email` varchar(150)
,`cursos_inscritos` bigint(21)
,`cursos_completados` bigint(21)
,`puntos_totales` decimal(32,0)
,`logros_obtenidos` bigint(21)
,`fecha_registro` datetime
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_recursos_completos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_recursos_completos` (
`id_recurso` int(11)
,`id_categoria` int(11)
,`id_autor` int(11)
,`titulo` varchar(255)
,`slug` varchar(300)
,`descripcion` text
,`tipo_recurso` enum('articulo','ebook','plantilla','herramienta','video','infografia','podcast')
,`tipo_acceso` enum('gratuito','premium','suscripcion')
,`archivo_url` varchar(500)
,`archivo_nombre` varchar(255)
,`archivo_tipo` varchar(100)
,`archivo_tamanio` bigint(20) unsigned
,`contenido_texto` longtext
,`contenido_html` longtext
,`url_externo` varchar(500)
,`duracion_minutos` int(10) unsigned
,`imagen_portada` varchar(500)
,`imagen_preview` varchar(500)
,`video_preview` varchar(500)
,`nivel` enum('principiante','intermedio','avanzado')
,`idioma` varchar(10)
,`formato` varchar(50)
,`licencia` varchar(100)
,`total_descargas` int(10) unsigned
,`total_vistas` int(10) unsigned
,`calificacion_promedio` decimal(3,2)
,`total_calificaciones` int(10) unsigned
,`estado` enum('borrador','publicado','archivado')
,`destacado` tinyint(1)
,`orden` int(10) unsigned
,`fecha_publicacion` datetime
,`fecha_creacion` timestamp
,`fecha_actualizacion` timestamp
,`categoria_nombre` varchar(100)
,`categoria_slug` varchar(120)
,`categoria_icono` varchar(50)
,`categoria_color` varchar(7)
,`autor_nombre` varchar(100)
,`autor_apellido` varchar(100)
,`autor_foto` varchar(255)
,`etiquetas` mediumtext
,`etiquetas_slugs` mediumtext
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_versiones_actuales`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_versiones_actuales` (
`id_version` int(11)
,`id_recurso` int(11)
,`numero_version` int(11)
,`id_usuario_cambio` int(11)
,`fecha_cambio` datetime
,`id_categoria` int(11)
,`titulo` varchar(255)
,`slug` varchar(255)
,`descripcion` text
,`tipo_recurso` enum('articulo','ebook','plantilla','herramienta','video','infografia','podcast','guia','checklist')
,`tipo_acceso` enum('gratuito','premium','suscripcion')
,`archivo_url` varchar(500)
,`archivo_nombre` varchar(255)
,`archivo_tipo` varchar(100)
,`archivo_tamanio` int(11)
,`contenido_texto` text
,`contenido_html` text
,`url_externo` varchar(500)
,`duracion_minutos` int(11)
,`imagen_portada` varchar(500)
,`imagen_preview` varchar(500)
,`video_preview` varchar(500)
,`nivel` enum('principiante','intermedio','avanzado','experto')
,`idioma` varchar(5)
,`formato` varchar(50)
,`licencia` varchar(255)
,`estado` enum('borrador','revision','publicado','archivado')
,`destacado` tinyint(1)
,`fecha_publicacion` datetime
,`tipo_cambio` enum('creacion','actualizacion','restauracion','publicacion','despublicacion')
,`descripcion_cambio` text
,`campos_modificados` longtext
,`datos_anteriores` longtext
,`nombre_usuario` varchar(100)
,`email_usuario` varchar(150)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_versiones_recursos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_versiones_recursos` (
`id_version` int(11)
,`id_recurso` int(11)
,`titulo_actual` varchar(255)
,`numero_version` int(11)
,`titulo_version` varchar(255)
,`tipo_cambio` enum('creacion','actualizacion','restauracion','publicacion','despublicacion')
,`descripcion_cambio` text
,`campos_modificados` longtext
,`fecha_cambio` datetime
,`id_usuario_cambio` int(11)
,`nombre_usuario` varchar(100)
,`email_usuario` varchar(150)
,`estado_version` enum('borrador','revision','publicado','archivado')
,`estado_actual` enum('borrador','publicado','archivado')
,`cantidad_campos_modificados` int(10)
,`fecha_publicacion_version` datetime
,`segundos_desde_version_anterior` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `ranking_usuarios`
--
DROP TABLE IF EXISTS `ranking_usuarios`;

CREATE VIEW `ranking_usuarios`  AS SELECT `u`.`id_usuario` AS `id_usuario`, `u`.`nombre` AS `nombre`, `u`.`apellido` AS `apellido`, `u`.`foto_perfil` AS `foto_perfil`, `pu`.`puntos_totales` AS `puntos_totales`, `pu`.`nivel` AS `nivel`, `pu`.`experiencia` AS `experiencia`, rank() over ( order by `pu`.`puntos_totales` desc,`pu`.`nivel` desc) AS `posicion_global` FROM (`usuarios` `u` join `puntos_usuario` `pu` on(`u`.`id_usuario` = `pu`.`id_usuario`)) WHERE `u`.`estado` = 'activo' ORDER BY `pu`.`puntos_totales` DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_conversaciones_completas`
--
DROP TABLE IF EXISTS `vista_conversaciones_completas`;

CREATE VIEW `vista_conversaciones_completas`  AS SELECT `c`.`id_conversacion` AS `id_conversacion`, `c`.`tipo_conversacion` AS `tipo_conversacion`, `c`.`estado` AS `estado`, `c`.`ultimo_mensaje_fecha` AS `ultimo_mensaje_fecha`, `c`.`fecha_creacion` AS `fecha_creacion`, `cu`.`id_curso` AS `id_curso`, `cu`.`titulo` AS `curso_titulo`, `cu`.`imagen_portada` AS `curso_imagen`, `a`.`id_usuario` AS `id_alumno`, `a`.`nombre` AS `alumno_nombre`, `a`.`email` AS `alumno_email`, `a`.`foto_perfil` AS `alumno_foto`, `i`.`id_usuario` AS `id_instructor`, `i`.`nombre` AS `instructor_nombre`, `i`.`email` AS `instructor_email`, `i`.`foto_perfil` AS `instructor_foto`, `ep`.`estado` AS `instructor_estado`, `ep`.`ultima_actividad` AS `instructor_ultima_actividad`, `ep`.`mensaje_estado` AS `instructor_mensaje_estado`, (select count(0) from `mensajes` `m` where `m`.`id_conversacion` = `c`.`id_conversacion` and `m`.`remitente_tipo` = 'instructor' and `m`.`leido` = 0) AS `mensajes_no_leidos_alumno`, (select count(0) from `mensajes` `m` where `m`.`id_conversacion` = `c`.`id_conversacion` and `m`.`remitente_tipo` = 'alumno' and `m`.`leido` = 0) AS `mensajes_no_leidos_instructor`, (select `m`.`contenido` from `mensajes` `m` where `m`.`id_conversacion` = `c`.`id_conversacion` order by `m`.`fecha_envio` desc limit 1) AS `ultimo_mensaje`, (select `m`.`remitente_tipo` from `mensajes` `m` where `m`.`id_conversacion` = `c`.`id_conversacion` order by `m`.`fecha_envio` desc limit 1) AS `ultimo_mensaje_remitente`, (select count(0) from `mensajes` `m` where `m`.`id_conversacion` = `c`.`id_conversacion`) AS `total_mensajes` FROM ((((`conversaciones` `c` join `cursos` `cu` on(`c`.`id_curso` = `cu`.`id_curso`)) join `usuarios` `a` on(`c`.`id_alumno` = `a`.`id_usuario`)) join `usuarios` `i` on(`c`.`id_instructor` = `i`.`id_usuario`)) left join `estado_presencia` `ep` on(`i`.`id_usuario` = `ep`.`id_usuario`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_cursos_populares`
--
DROP TABLE IF EXISTS `vista_cursos_populares`;

CREATE VIEW `vista_cursos_populares`  AS SELECT `c`.`id_curso` AS `id_curso`, `c`.`titulo` AS `titulo`, `c`.`nivel` AS `nivel`, `cat`.`nombre` AS `categoria`, `c`.`total_inscripciones` AS `total_inscritos`, `c`.`promedio_calificacion` AS `calificacion_promedio`, count(distinct `ic`.`id_usuario`) AS `inscritos_activos`, count(distinct case when `ic`.`estado` = 'completado' then `ic`.`id_usuario` end) AS `completados` FROM ((`cursos` `c` left join `categorias_cursos` `cat` on(`c`.`id_categoria` = `cat`.`id_categoria`)) left join `inscripciones_curso` `ic` on(`c`.`id_curso` = `ic`.`id_curso`)) WHERE `c`.`estado` = 'publicado' GROUP BY `c`.`id_curso` ORDER BY `c`.`total_inscripciones` DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_productos_completa`
--
DROP TABLE IF EXISTS `vista_productos_completa`;

CREATE VIEW `vista_productos_completa`  AS SELECT `p`.`id_producto` AS `id_producto`, `p`.`titulo` AS `titulo`, `p`.`slug` AS `slug`, `p`.`descripcion_corta` AS `descripcion_corta`, `p`.`tipo_producto` AS `tipo_producto`, `p`.`precio` AS `precio`, `p`.`moneda` AS `moneda`, `p`.`precio_anterior` AS `precio_anterior`, `p`.`estado` AS `estado`, `p`.`destacado` AS `destacado`, `p`.`total_vistas` AS `total_vistas`, `p`.`total_contactos` AS `total_contactos`, `p`.`total_favoritos` AS `total_favoritos`, `p`.`ubicacion_ciudad` AS `ubicacion_ciudad`, `p`.`ubicacion_estado` AS `ubicacion_estado`, `p`.`fecha_publicacion` AS `fecha_publicacion`, `c`.`nombre` AS `categoria_nombre`, `c`.`slug` AS `categoria_slug`, `c`.`color_hex` AS `categoria_color`, `u`.`id_usuario` AS `vendedor_id`, `u`.`nombre` AS `vendedor_nombre`, `u`.`email` AS `vendedor_email`, `pe`.`id_perfil` AS `perfil_id`, `pe`.`nombre_empresa` AS `nombre_empresa`, `pe`.`logo_empresa` AS `perfil_logo`, (select `imagenes_productos`.`url_imagen` from `imagenes_productos` where `imagenes_productos`.`id_producto` = `p`.`id_producto` and `imagenes_productos`.`es_principal` = 1 limit 1) AS `imagen_principal`, (select count(0) from `imagenes_productos` where `imagenes_productos`.`id_producto` = `p`.`id_producto`) AS `total_imagenes` FROM (((`productos` `p` join `categorias_productos` `c` on(`p`.`id_categoria` = `c`.`id_categoria`)) join `usuarios` `u` on(`p`.`id_usuario` = `u`.`id_usuario`)) left join `perfiles_empresariales` `pe` on(`p`.`id_perfil_empresarial` = `pe`.`id_perfil`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_progreso_usuarios`
--
DROP TABLE IF EXISTS `vista_progreso_usuarios`;

CREATE VIEW `vista_progreso_usuarios`  AS SELECT `u`.`id_usuario` AS `id_usuario`, `u`.`nombre` AS `nombre`, `u`.`apellido` AS `apellido`, `u`.`email` AS `email`, count(distinct `ic`.`id_curso`) AS `cursos_inscritos`, count(distinct case when `ic`.`estado` = 'completado' then `ic`.`id_curso` end) AS `cursos_completados`, sum(coalesce(`pu`.`puntos`,0)) AS `puntos_totales`, count(distinct `lu`.`id_logro`) AS `logros_obtenidos`, `u`.`fecha_registro` AS `fecha_registro` FROM (((`usuarios` `u` left join `inscripciones_curso` `ic` on(`u`.`id_usuario` = `ic`.`id_usuario`)) left join `puntos_usuarios` `pu` on(`u`.`id_usuario` = `pu`.`id_usuario` and `pu`.`tipo_transaccion` = 'ganancia')) left join `logros_usuarios` `lu` on(`u`.`id_usuario` = `lu`.`id_usuario`)) GROUP BY `u`.`id_usuario` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_recursos_completos`
--
DROP TABLE IF EXISTS `vista_recursos_completos`;

CREATE VIEW `vista_recursos_completos`  AS SELECT `r`.`id_recurso` AS `id_recurso`, `r`.`id_categoria` AS `id_categoria`, `r`.`id_autor` AS `id_autor`, `r`.`titulo` AS `titulo`, `r`.`slug` AS `slug`, `r`.`descripcion` AS `descripcion`, `r`.`tipo_recurso` AS `tipo_recurso`, `r`.`tipo_acceso` AS `tipo_acceso`, `r`.`archivo_url` AS `archivo_url`, `r`.`archivo_nombre` AS `archivo_nombre`, `r`.`archivo_tipo` AS `archivo_tipo`, `r`.`archivo_tamanio` AS `archivo_tamanio`, `r`.`contenido_texto` AS `contenido_texto`, `r`.`contenido_html` AS `contenido_html`, `r`.`url_externo` AS `url_externo`, `r`.`duracion_minutos` AS `duracion_minutos`, `r`.`imagen_portada` AS `imagen_portada`, `r`.`imagen_preview` AS `imagen_preview`, `r`.`video_preview` AS `video_preview`, `r`.`nivel` AS `nivel`, `r`.`idioma` AS `idioma`, `r`.`formato` AS `formato`, `r`.`licencia` AS `licencia`, `r`.`total_descargas` AS `total_descargas`, `r`.`total_vistas` AS `total_vistas`, `r`.`calificacion_promedio` AS `calificacion_promedio`, `r`.`total_calificaciones` AS `total_calificaciones`, `r`.`estado` AS `estado`, `r`.`destacado` AS `destacado`, `r`.`orden` AS `orden`, `r`.`fecha_publicacion` AS `fecha_publicacion`, `r`.`fecha_creacion` AS `fecha_creacion`, `r`.`fecha_actualizacion` AS `fecha_actualizacion`, `c`.`nombre` AS `categoria_nombre`, `c`.`slug` AS `categoria_slug`, `c`.`icono` AS `categoria_icono`, `c`.`color` AS `categoria_color`, `u`.`nombre` AS `autor_nombre`, `u`.`apellido` AS `autor_apellido`, `u`.`foto_perfil` AS `autor_foto`, group_concat(distinct `e`.`nombre` order by `e`.`nombre` ASC separator ', ') AS `etiquetas`, group_concat(distinct `e`.`slug` order by `e`.`nombre` ASC separator ',') AS `etiquetas_slugs` FROM ((((`recursos` `r` left join `categorias_recursos` `c` on(`r`.`id_categoria` = `c`.`id_categoria`)) left join `usuarios` `u` on(`r`.`id_autor` = `u`.`id_usuario`)) left join `recursos_etiquetas` `re` on(`r`.`id_recurso` = `re`.`id_recurso`)) left join `etiquetas_recursos` `e` on(`re`.`id_etiqueta` = `e`.`id_etiqueta`)) GROUP BY `r`.`id_recurso` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_versiones_actuales`
--
DROP TABLE IF EXISTS `vista_versiones_actuales`;

CREATE VIEW `vista_versiones_actuales`  AS SELECT `rv`.`id_version` AS `id_version`, `rv`.`id_recurso` AS `id_recurso`, `rv`.`numero_version` AS `numero_version`, `rv`.`id_usuario_cambio` AS `id_usuario_cambio`, `rv`.`fecha_cambio` AS `fecha_cambio`, `rv`.`id_categoria` AS `id_categoria`, `rv`.`titulo` AS `titulo`, `rv`.`slug` AS `slug`, `rv`.`descripcion` AS `descripcion`, `rv`.`tipo_recurso` AS `tipo_recurso`, `rv`.`tipo_acceso` AS `tipo_acceso`, `rv`.`archivo_url` AS `archivo_url`, `rv`.`archivo_nombre` AS `archivo_nombre`, `rv`.`archivo_tipo` AS `archivo_tipo`, `rv`.`archivo_tamanio` AS `archivo_tamanio`, `rv`.`contenido_texto` AS `contenido_texto`, `rv`.`contenido_html` AS `contenido_html`, `rv`.`url_externo` AS `url_externo`, `rv`.`duracion_minutos` AS `duracion_minutos`, `rv`.`imagen_portada` AS `imagen_portada`, `rv`.`imagen_preview` AS `imagen_preview`, `rv`.`video_preview` AS `video_preview`, `rv`.`nivel` AS `nivel`, `rv`.`idioma` AS `idioma`, `rv`.`formato` AS `formato`, `rv`.`licencia` AS `licencia`, `rv`.`estado` AS `estado`, `rv`.`destacado` AS `destacado`, `rv`.`fecha_publicacion` AS `fecha_publicacion`, `rv`.`tipo_cambio` AS `tipo_cambio`, `rv`.`descripcion_cambio` AS `descripcion_cambio`, `rv`.`campos_modificados` AS `campos_modificados`, `rv`.`datos_anteriores` AS `datos_anteriores`, `u`.`nombre` AS `nombre_usuario`, `u`.`email` AS `email_usuario` FROM (`recursos_versiones` `rv` join `usuarios` `u` on(`rv`.`id_usuario_cambio` = `u`.`id_usuario`)) WHERE (`rv`.`id_recurso`,`rv`.`numero_version`) in (select `recursos_versiones`.`id_recurso`,max(`recursos_versiones`.`numero_version`) from `recursos_versiones` group by `recursos_versiones`.`id_recurso`) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_versiones_recursos`
--
DROP TABLE IF EXISTS `vista_versiones_recursos`;

CREATE VIEW `vista_versiones_recursos`  AS SELECT `rv`.`id_version` AS `id_version`, `rv`.`id_recurso` AS `id_recurso`, `r`.`titulo` AS `titulo_actual`, `rv`.`numero_version` AS `numero_version`, `rv`.`titulo` AS `titulo_version`, `rv`.`tipo_cambio` AS `tipo_cambio`, `rv`.`descripcion_cambio` AS `descripcion_cambio`, `rv`.`campos_modificados` AS `campos_modificados`, `rv`.`fecha_cambio` AS `fecha_cambio`, `rv`.`id_usuario_cambio` AS `id_usuario_cambio`, `u`.`nombre` AS `nombre_usuario`, `u`.`email` AS `email_usuario`, `rv`.`estado` AS `estado_version`, `r`.`estado` AS `estado_actual`, json_length(`rv`.`campos_modificados`) AS `cantidad_campos_modificados`, `rv`.`fecha_publicacion` AS `fecha_publicacion_version`, timestampdiff(SECOND,lag(`rv`.`fecha_cambio`,1) over ( partition by `rv`.`id_recurso` order by `rv`.`numero_version`),`rv`.`fecha_cambio`) AS `segundos_desde_version_anterior` FROM ((`recursos_versiones` `rv` join `recursos` `r` on(`rv`.`id_recurso` = `r`.`id_recurso`)) join `usuarios` `u` on(`rv`.`id_usuario_cambio` = `u`.`id_usuario`)) ORDER BY `rv`.`id_recurso` DESC, `rv`.`numero_version` DESC ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `areas_evaluacion`
--
ALTER TABLE `areas_evaluacion`
  ADD PRIMARY KEY (`id_area`),
  ADD KEY `idx_diagnostico` (`id_tipo_diagnostico`),
  ADD KEY `idx_orden` (`orden`);

--
-- Indices de la tabla `calificaciones_cursos`
--
ALTER TABLE `calificaciones_cursos`
  ADD PRIMARY KEY (`id_calificacion`),
  ADD UNIQUE KEY `unique_calificacion` (`id_usuario`,`id_curso`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_curso` (`id_curso`),
  ADD KEY `idx_calificacion` (`calificacion`),
  ADD KEY `idx_aprobado` (`aprobado`);

--
-- Indices de la tabla `calificaciones_recursos`
--
ALTER TABLE `calificaciones_recursos`
  ADD PRIMARY KEY (`id_calificacion`),
  ADD UNIQUE KEY `unique_calificacion` (`id_recurso`,`id_usuario`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `idx_recurso` (`id_recurso`),
  ADD KEY `idx_calificacion` (`calificacion`),
  ADD KEY `idx_calificaciones_stats` (`id_recurso`,`calificacion`);

--
-- Indices de la tabla `categorias_cursos`
--
ALTER TABLE `categorias_cursos`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_orden` (`orden`);

--
-- Indices de la tabla `categorias_logros`
--
ALTER TABLE `categorias_logros`
  ADD PRIMARY KEY (`id_categoria_logro`);

--
-- Indices de la tabla `categorias_productos`
--
ALTER TABLE `categorias_productos`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_orden` (`orden`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indices de la tabla `categorias_recursos`
--
ALTER TABLE `categorias_recursos`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_activa` (`activa`),
  ADD KEY `idx_orden` (`orden`);

--
-- Indices de la tabla `certificados`
--
ALTER TABLE `certificados`
  ADD PRIMARY KEY (`id_certificado`),
  ADD UNIQUE KEY `codigo_certificado` (`codigo_certificado`),
  ADD UNIQUE KEY `unique_usuario_curso` (`id_usuario`,`id_curso`),
  ADD KEY `id_instructor` (`id_instructor`),
  ADD KEY `idx_codigo` (`codigo_certificado`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_curso` (`id_curso`),
  ADD KEY `idx_fecha` (`fecha_emision`);

--
-- Indices de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  ADD PRIMARY KEY (`id_config`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indices de la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  ADD PRIMARY KEY (`id_conversacion`),
  ADD UNIQUE KEY `unique_conversacion` (`id_curso`,`id_alumno`,`id_instructor`,`tipo_conversacion`),
  ADD KEY `idx_alumno` (`id_alumno`),
  ADD KEY `idx_instructor` (`id_instructor`),
  ADD KEY `idx_curso` (`id_curso`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_tipo` (`tipo_conversacion`),
  ADD KEY `idx_ultimo_mensaje` (`ultimo_mensaje_fecha`),
  ADD KEY `idx_alumno_estado` (`id_alumno`,`estado`),
  ADD KEY `idx_instructor_estado` (`id_instructor`,`estado`);

--
-- Indices de la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_categoria` (`id_categoria`),
  ADD KEY `idx_instructor` (`id_instructor`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_nivel` (`nivel`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indices de la tabla `descargas_recursos`
--
ALTER TABLE `descargas_recursos`
  ADD PRIMARY KEY (`id_descarga`),
  ADD KEY `idx_recurso` (`id_recurso`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_fecha` (`fecha_descarga`),
  ADD KEY `idx_descargas_stats` (`id_recurso`,`fecha_descarga`);

--
-- Indices de la tabla `diagnosticos_empresariales`
--
ALTER TABLE `diagnosticos_empresariales`
  ADD PRIMARY KEY (`id_diagnostico`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `diagnosticos_realizados`
--
ALTER TABLE `diagnosticos_realizados`
  ADD PRIMARY KEY (`id_diagnostico_realizado`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_perfil` (`id_perfil_empresarial`),
  ADD KEY `idx_tipo` (`id_tipo_diagnostico`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_completado` (`fecha_completado`);

--
-- Indices de la tabla `disponibilidad_instructores`
--
ALTER TABLE `disponibilidad_instructores`
  ADD PRIMARY KEY (`id_disponibilidad`),
  ADD UNIQUE KEY `unique_instructor_dia` (`id_instructor`,`dia_semana`,`hora_inicio`,`hora_fin`),
  ADD KEY `idx_instructor` (`id_instructor`),
  ADD KEY `idx_dia` (`dia_semana`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `estado_presencia`
--
ALTER TABLE `estado_presencia`
  ADD PRIMARY KEY (`id_usuario`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_ultima_actividad` (`ultima_actividad`);

--
-- Indices de la tabla `etiquetas_recursos`
--
ALTER TABLE `etiquetas_recursos`
  ADD PRIMARY KEY (`id_etiqueta`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_total_usos` (`total_usos`);

--
-- Indices de la tabla `evaluaciones`
--
ALTER TABLE `evaluaciones`
  ADD PRIMARY KEY (`id_evaluacion`),
  ADD KEY `idx_leccion` (`id_leccion`),
  ADD KEY `idx_curso` (`id_curso`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `imagenes_productos`
--
ALTER TABLE `imagenes_productos`
  ADD PRIMARY KEY (`id_imagen`),
  ADD KEY `idx_producto` (`id_producto`),
  ADD KEY `idx_principal` (`es_principal`),
  ADD KEY `idx_orden` (`orden`);

--
-- Indices de la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD PRIMARY KEY (`id_inscripcion`),
  ADD UNIQUE KEY `unique_inscripcion` (`id_usuario`,`id_curso`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_curso` (`id_curso`),
  ADD KEY `idx_fecha_inscripcion` (`fecha_inscripcion`);

--
-- Indices de la tabla `inscripciones_curso`
--
ALTER TABLE `inscripciones_curso`
  ADD PRIMARY KEY (`id_inscripcion`),
  ADD UNIQUE KEY `uk_usuario_curso` (`id_usuario`,`id_curso`),
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_inscripcion` (`fecha_inscripcion`),
  ADD KEY `idx_inscripcion_usuario_estado` (`id_usuario`,`estado`);

--
-- Indices de la tabla `intentos_evaluacion`
--
ALTER TABLE `intentos_evaluacion`
  ADD PRIMARY KEY (`id_intento`),
  ADD KEY `idx_evaluacion_usuario` (`id_evaluacion`,`id_usuario`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_fecha` (`fecha_inicio`);

--
-- Indices de la tabla `interacciones_productos`
--
ALTER TABLE `interacciones_productos`
  ADD PRIMARY KEY (`id_interaccion`),
  ADD KEY `idx_producto` (`id_producto`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_tipo` (`tipo_interaccion`),
  ADD KEY `idx_fecha` (`fecha_interaccion`);

--
-- Indices de la tabla `lecciones`
--
ALTER TABLE `lecciones`
  ADD PRIMARY KEY (`id_leccion`),
  ADD KEY `idx_modulo` (`id_modulo`),
  ADD KEY `idx_orden` (`orden`);

--
-- Indices de la tabla `logros`
--
ALTER TABLE `logros`
  ADD PRIMARY KEY (`id_logro`),
  ADD KEY `id_categoria_logro` (`id_categoria_logro`),
  ADD KEY `idx_tipo_logro` (`tipo_logro`);

--
-- Indices de la tabla `logros_usuarios`
--
ALTER TABLE `logros_usuarios`
  ADD PRIMARY KEY (`id_logro_usuario`),
  ADD UNIQUE KEY `uk_usuario_logro` (`id_usuario`,`id_logro`),
  ADD KEY `id_logro` (`id_logro`),
  ADD KEY `idx_fecha_obtencion` (`fecha_obtencion`);

--
-- Indices de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `idx_conversacion` (`id_conversacion`),
  ADD KEY `idx_fecha` (`fecha_envio`),
  ADD KEY `idx_leido` (`leido`),
  ADD KEY `idx_remitente` (`id_remitente`),
  ADD KEY `idx_conversacion_fecha` (`id_conversacion`,`fecha_envio`);

--
-- Indices de la tabla `mentorias`
--
ALTER TABLE `mentorias`
  ADD PRIMARY KEY (`id_mentoria`),
  ADD KEY `idx_mentor` (`id_mentor`),
  ADD KEY `idx_aprendiz` (`id_aprendiz`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `mentoria_contexto`
--
ALTER TABLE `mentoria_contexto`
  ADD PRIMARY KEY (`id_contexto`),
  ADD KEY `idx_conversacion` (`id_conversacion`),
  ADD KEY `idx_modelo` (`modelo_ia`),
  ADD KEY `idx_fecha` (`fecha_creacion`);

--
-- Indices de la tabla `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id_modulo`),
  ADD KEY `idx_curso` (`id_curso`),
  ADD KEY `idx_orden` (`orden`);

--
-- Indices de la tabla `modulos_curso`
--
ALTER TABLE `modulos_curso`
  ADD PRIMARY KEY (`id_modulo`),
  ADD KEY `idx_curso_orden` (`id_curso`,`orden`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `idx_usuario_leida` (`id_usuario`,`leida`),
  ADD KEY `idx_fecha_creacion` (`fecha_creacion`);

--
-- Indices de la tabla `opciones_pregunta`
--
ALTER TABLE `opciones_pregunta`
  ADD PRIMARY KEY (`id_opcion`),
  ADD KEY `idx_pregunta` (`id_pregunta_evaluacion`);

--
-- Indices de la tabla `perfiles_empresariales`
--
ALTER TABLE `perfiles_empresariales`
  ADD PRIMARY KEY (`id_perfil`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_sector` (`sector`),
  ADD KEY `idx_tipo` (`tipo_negocio`),
  ADD KEY `idx_etapa` (`etapa_negocio`),
  ADD KEY `idx_publico` (`perfil_publico`);

--
-- Indices de la tabla `preferencias_notificacion`
--
ALTER TABLE `preferencias_notificacion`
  ADD PRIMARY KEY (`id_preferencia`),
  ADD UNIQUE KEY `unique_usuario_tipo` (`id_usuario`,`tipo_notificacion`);

--
-- Indices de la tabla `preguntas_diagnostico`
--
ALTER TABLE `preguntas_diagnostico`
  ADD PRIMARY KEY (`id_pregunta`),
  ADD KEY `idx_area` (`id_area`),
  ADD KEY `idx_tipo` (`tipo_pregunta`),
  ADD KEY `idx_orden` (`orden`);

--
-- Indices de la tabla `preguntas_evaluacion`
--
ALTER TABLE `preguntas_evaluacion`
  ADD PRIMARY KEY (`id_pregunta_evaluacion`),
  ADD KEY `idx_evaluacion` (`id_evaluacion`),
  ADD KEY `idx_orden` (`orden`);

--
-- Indices de la tabla `prerrequisitos_curso`
--
ALTER TABLE `prerrequisitos_curso`
  ADD PRIMARY KEY (`id_prerrequisito`),
  ADD UNIQUE KEY `unique_prerrequisito` (`id_curso`,`id_curso_requerido`),
  ADD KEY `id_curso_requerido` (`id_curso_requerido`),
  ADD KEY `idx_curso` (`id_curso`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id_producto`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `id_perfil_empresarial` (`id_perfil_empresarial`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_categoria` (`id_categoria`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_destacado` (`destacado`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_precio` (`precio`),
  ADD KEY `idx_fecha_publicacion` (`fecha_publicacion`),
  ADD KEY `idx_tipo` (`tipo_producto`),
  ADD KEY `idx_ubicacion` (`ubicacion_estado`,`ubicacion_ciudad`),
  ADD KEY `idx_destacado_estado` (`destacado`,`estado`,`fecha_publicacion`);
ALTER TABLE `productos` ADD FULLTEXT KEY `idx_busqueda` (`titulo`,`descripcion_corta`,`descripcion_completa`);

--
-- Indices de la tabla `productos_favoritos`
--
ALTER TABLE `productos_favoritos`
  ADD PRIMARY KEY (`id_favorito`),
  ADD UNIQUE KEY `unique_favorito` (`id_usuario`,`id_producto`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_producto` (`id_producto`),
  ADD KEY `idx_usuario_fecha` (`id_usuario`,`fecha_agregado`);

--
-- Indices de la tabla `progreso_lecciones`
--
ALTER TABLE `progreso_lecciones`
  ADD PRIMARY KEY (`id_progreso`),
  ADD UNIQUE KEY `unique_progreso` (`id_inscripcion`,`id_leccion`),
  ADD KEY `idx_inscripcion` (`id_inscripcion`),
  ADD KEY `idx_leccion` (`id_leccion`),
  ADD KEY `idx_completada` (`completada`);

--
-- Indices de la tabla `puntos_usuario`
--
ALTER TABLE `puntos_usuario`
  ADD PRIMARY KEY (`id_puntos`),
  ADD UNIQUE KEY `unique_usuario` (`id_usuario`),
  ADD KEY `idx_puntos_totales` (`puntos_totales`),
  ADD KEY `idx_nivel` (`nivel`);

--
-- Indices de la tabla `puntos_usuarios`
--
ALTER TABLE `puntos_usuarios`
  ADD PRIMARY KEY (`id_punto`),
  ADD KEY `idx_usuario_fecha` (`id_usuario`,`fecha_registro`);

--
-- Indices de la tabla `rachas_usuario`
--
ALTER TABLE `rachas_usuario`
  ADD PRIMARY KEY (`id_racha`),
  ADD UNIQUE KEY `unique_usuario` (`id_usuario`),
  ADD KEY `idx_racha_actual` (`racha_actual`),
  ADD KEY `idx_ultima_actividad` (`ultima_actividad`);

--
-- Indices de la tabla `recomendaciones_cursos`
--
ALTER TABLE `recomendaciones_cursos`
  ADD PRIMARY KEY (`id_recomendacion`),
  ADD KEY `idx_area` (`id_area`),
  ADD KEY `idx_curso` (`id_curso`),
  ADD KEY `idx_prioridad` (`prioridad`);

--
-- Indices de la tabla `recursos`
--
ALTER TABLE `recursos`
  ADD PRIMARY KEY (`id_recurso`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_categoria` (`id_categoria`),
  ADD KEY `idx_autor` (`id_autor`),
  ADD KEY `idx_tipo_recurso` (`tipo_recurso`),
  ADD KEY `idx_tipo_acceso` (`tipo_acceso`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_destacado` (`destacado`),
  ADD KEY `idx_fecha_publicacion` (`fecha_publicacion`),
  ADD KEY `idx_nivel` (`nivel`),
  ADD KEY `idx_recursos_busqueda_avanzada` (`estado`,`tipo_acceso`,`tipo_recurso`,`fecha_publicacion`);
ALTER TABLE `recursos` ADD FULLTEXT KEY `idx_busqueda` (`titulo`,`descripcion`);

--
-- Indices de la tabla `recursos_aprendizaje`
--
ALTER TABLE `recursos_aprendizaje`
  ADD PRIMARY KEY (`id_recurso`),
  ADD KEY `idx_tipo_recurso` (`tipo_recurso`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `recursos_etiquetas`
--
ALTER TABLE `recursos_etiquetas`
  ADD PRIMARY KEY (`id_recurso`,`id_etiqueta`),
  ADD KEY `idx_etiqueta` (`id_etiqueta`);

--
-- Indices de la tabla `recursos_etiquetas_versiones`
--
ALTER TABLE `recursos_etiquetas_versiones`
  ADD PRIMARY KEY (`id_version_etiqueta`),
  ADD UNIQUE KEY `uk_version_etiqueta` (`id_version`,`id_etiqueta`),
  ADD KEY `idx_version` (`id_version`),
  ADD KEY `idx_etiqueta` (`id_etiqueta`);

--
-- Indices de la tabla `recursos_versiones`
--
ALTER TABLE `recursos_versiones`
  ADD PRIMARY KEY (`id_version`),
  ADD UNIQUE KEY `uk_recurso_numero` (`id_recurso`,`numero_version`),
  ADD KEY `idx_recurso` (`id_recurso`),
  ADD KEY `idx_recurso_version` (`id_recurso`,`numero_version`),
  ADD KEY `idx_fecha` (`fecha_cambio`),
  ADD KEY `idx_usuario` (`id_usuario_cambio`),
  ADD KEY `idx_tipo_cambio` (`tipo_cambio`),
  ADD KEY `fk_version_categoria` (`id_categoria`),
  ADD KEY `idx_versiones_recurso_fecha` (`id_recurso`,`fecha_cambio`),
  ADD KEY `idx_versiones_tipo_cambio_fecha` (`tipo_cambio`,`fecha_cambio`);

--
-- Indices de la tabla `respuestas_diagnostico`
--
ALTER TABLE `respuestas_diagnostico`
  ADD PRIMARY KEY (`id_respuesta`),
  ADD UNIQUE KEY `unique_respuesta` (`id_diagnostico_realizado`,`id_pregunta`),
  ADD KEY `idx_diagnostico` (`id_diagnostico_realizado`),
  ADD KEY `idx_pregunta` (`id_pregunta`);

--
-- Indices de la tabla `respuestas_evaluacion`
--
ALTER TABLE `respuestas_evaluacion`
  ADD PRIMARY KEY (`id_respuesta_evaluacion`),
  ADD KEY `id_opcion_seleccionada` (`id_opcion_seleccionada`),
  ADD KEY `idx_intento` (`id_intento`),
  ADD KEY `idx_pregunta` (`id_pregunta_evaluacion`);

--
-- Indices de la tabla `tipos_diagnostico`
--
ALTER TABLE `tipos_diagnostico`
  ADD PRIMARY KEY (`id_tipo_diagnostico`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `transacciones_puntos`
--
ALTER TABLE `transacciones_puntos`
  ADD PRIMARY KEY (`id_transaccion`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_fecha` (`fecha_transaccion`),
  ADD KEY `idx_tipo` (`tipo_transaccion`),
  ADD KEY `idx_referencia` (`referencia_tipo`,`referencia_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_tipo_usuario` (`tipo_usuario`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `vistas_recursos`
--
ALTER TABLE `vistas_recursos`
  ADD PRIMARY KEY (`id_vista`),
  ADD KEY `idx_recurso` (`id_recurso`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_fecha` (`fecha_vista`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `areas_evaluacion`
--
ALTER TABLE `areas_evaluacion`
  MODIFY `id_area` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `calificaciones_cursos`
--
ALTER TABLE `calificaciones_cursos`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calificaciones_recursos`
--
ALTER TABLE `calificaciones_recursos`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categorias_cursos`
--
ALTER TABLE `categorias_cursos`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `categorias_logros`
--
ALTER TABLE `categorias_logros`
  MODIFY `id_categoria_logro` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categorias_productos`
--
ALTER TABLE `categorias_productos`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `categorias_recursos`
--
ALTER TABLE `categorias_recursos`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `certificados`
--
ALTER TABLE `certificados`
  MODIFY `id_certificado` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  MODIFY `id_config` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  MODIFY `id_conversacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `descargas_recursos`
--
ALTER TABLE `descargas_recursos`
  MODIFY `id_descarga` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `diagnosticos_empresariales`
--
ALTER TABLE `diagnosticos_empresariales`
  MODIFY `id_diagnostico` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `diagnosticos_realizados`
--
ALTER TABLE `diagnosticos_realizados`
  MODIFY `id_diagnostico_realizado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `disponibilidad_instructores`
--
ALTER TABLE `disponibilidad_instructores`
  MODIFY `id_disponibilidad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `etiquetas_recursos`
--
ALTER TABLE `etiquetas_recursos`
  MODIFY `id_etiqueta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `evaluaciones`
--
ALTER TABLE `evaluaciones`
  MODIFY `id_evaluacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `imagenes_productos`
--
ALTER TABLE `imagenes_productos`
  MODIFY `id_imagen` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  MODIFY `id_inscripcion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `inscripciones_curso`
--
ALTER TABLE `inscripciones_curso`
  MODIFY `id_inscripcion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `intentos_evaluacion`
--
ALTER TABLE `intentos_evaluacion`
  MODIFY `id_intento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `interacciones_productos`
--
ALTER TABLE `interacciones_productos`
  MODIFY `id_interaccion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `lecciones`
--
ALTER TABLE `lecciones`
  MODIFY `id_leccion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `logros`
--
ALTER TABLE `logros`
  MODIFY `id_logro` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `logros_usuarios`
--
ALTER TABLE `logros_usuarios`
  MODIFY `id_logro_usuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  MODIFY `id_mensaje` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `mentorias`
--
ALTER TABLE `mentorias`
  MODIFY `id_mentoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mentoria_contexto`
--
ALTER TABLE `mentoria_contexto`
  MODIFY `id_contexto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `modulos`
--
ALTER TABLE `modulos`
  MODIFY `id_modulo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `modulos_curso`
--
ALTER TABLE `modulos_curso`
  MODIFY `id_modulo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `opciones_pregunta`
--
ALTER TABLE `opciones_pregunta`
  MODIFY `id_opcion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de la tabla `perfiles_empresariales`
--
ALTER TABLE `perfiles_empresariales`
  MODIFY `id_perfil` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `preferencias_notificacion`
--
ALTER TABLE `preferencias_notificacion`
  MODIFY `id_preferencia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT de la tabla `preguntas_diagnostico`
--
ALTER TABLE `preguntas_diagnostico`
  MODIFY `id_pregunta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `preguntas_evaluacion`
--
ALTER TABLE `preguntas_evaluacion`
  MODIFY `id_pregunta_evaluacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `prerrequisitos_curso`
--
ALTER TABLE `prerrequisitos_curso`
  MODIFY `id_prerrequisito` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `productos_favoritos`
--
ALTER TABLE `productos_favoritos`
  MODIFY `id_favorito` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `progreso_lecciones`
--
ALTER TABLE `progreso_lecciones`
  MODIFY `id_progreso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `puntos_usuario`
--
ALTER TABLE `puntos_usuario`
  MODIFY `id_puntos` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `puntos_usuarios`
--
ALTER TABLE `puntos_usuarios`
  MODIFY `id_punto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rachas_usuario`
--
ALTER TABLE `rachas_usuario`
  MODIFY `id_racha` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `recomendaciones_cursos`
--
ALTER TABLE `recomendaciones_cursos`
  MODIFY `id_recomendacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recursos`
--
ALTER TABLE `recursos`
  MODIFY `id_recurso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recursos_aprendizaje`
--
ALTER TABLE `recursos_aprendizaje`
  MODIFY `id_recurso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recursos_etiquetas_versiones`
--
ALTER TABLE `recursos_etiquetas_versiones`
  MODIFY `id_version_etiqueta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recursos_versiones`
--
ALTER TABLE `recursos_versiones`
  MODIFY `id_version` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `respuestas_diagnostico`
--
ALTER TABLE `respuestas_diagnostico`
  MODIFY `id_respuesta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `respuestas_evaluacion`
--
ALTER TABLE `respuestas_evaluacion`
  MODIFY `id_respuesta_evaluacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipos_diagnostico`
--
ALTER TABLE `tipos_diagnostico`
  MODIFY `id_tipo_diagnostico` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `transacciones_puntos`
--
ALTER TABLE `transacciones_puntos`
  MODIFY `id_transaccion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `vistas_recursos`
--
ALTER TABLE `vistas_recursos`
  MODIFY `id_vista` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `areas_evaluacion`
--
ALTER TABLE `areas_evaluacion`
  ADD CONSTRAINT `areas_evaluacion_ibfk_1` FOREIGN KEY (`id_tipo_diagnostico`) REFERENCES `tipos_diagnostico` (`id_tipo_diagnostico`) ON DELETE CASCADE;

--
-- Filtros para la tabla `calificaciones_cursos`
--
ALTER TABLE `calificaciones_cursos`
  ADD CONSTRAINT `calificaciones_cursos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `calificaciones_cursos_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE;

--
-- Filtros para la tabla `calificaciones_recursos`
--
ALTER TABLE `calificaciones_recursos`
  ADD CONSTRAINT `calificaciones_recursos_ibfk_1` FOREIGN KEY (`id_recurso`) REFERENCES `recursos` (`id_recurso`) ON DELETE CASCADE,
  ADD CONSTRAINT `calificaciones_recursos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `certificados`
--
ALTER TABLE `certificados`
  ADD CONSTRAINT `certificados_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificados_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificados_ibfk_3` FOREIGN KEY (`id_instructor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  ADD CONSTRAINT `conversaciones_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversaciones_ibfk_2` FOREIGN KEY (`id_alumno`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversaciones_ibfk_3` FOREIGN KEY (`id_instructor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD CONSTRAINT `cursos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias_cursos` (`id_categoria`),
  ADD CONSTRAINT `cursos_ibfk_2` FOREIGN KEY (`id_instructor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `descargas_recursos`
--
ALTER TABLE `descargas_recursos`
  ADD CONSTRAINT `descargas_recursos_ibfk_1` FOREIGN KEY (`id_recurso`) REFERENCES `recursos` (`id_recurso`) ON DELETE CASCADE,
  ADD CONSTRAINT `descargas_recursos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `diagnosticos_realizados`
--
ALTER TABLE `diagnosticos_realizados`
  ADD CONSTRAINT `diagnosticos_realizados_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `diagnosticos_realizados_ibfk_2` FOREIGN KEY (`id_perfil_empresarial`) REFERENCES `perfiles_empresariales` (`id_perfil`) ON DELETE SET NULL,
  ADD CONSTRAINT `diagnosticos_realizados_ibfk_3` FOREIGN KEY (`id_tipo_diagnostico`) REFERENCES `tipos_diagnostico` (`id_tipo_diagnostico`) ON DELETE CASCADE;

--
-- Filtros para la tabla `disponibilidad_instructores`
--
ALTER TABLE `disponibilidad_instructores`
  ADD CONSTRAINT `disponibilidad_instructores_ibfk_1` FOREIGN KEY (`id_instructor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `estado_presencia`
--
ALTER TABLE `estado_presencia`
  ADD CONSTRAINT `estado_presencia_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `evaluaciones`
--
ALTER TABLE `evaluaciones`
  ADD CONSTRAINT `evaluaciones_ibfk_1` FOREIGN KEY (`id_leccion`) REFERENCES `lecciones` (`id_leccion`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluaciones_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE;

--
-- Filtros para la tabla `imagenes_productos`
--
ALTER TABLE `imagenes_productos`
  ADD CONSTRAINT `imagenes_productos_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE;

--
-- Filtros para la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD CONSTRAINT `inscripciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscripciones_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE;

--
-- Filtros para la tabla `inscripciones_curso`
--
ALTER TABLE `inscripciones_curso`
  ADD CONSTRAINT `inscripciones_curso_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscripciones_curso_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE;

--
-- Filtros para la tabla `intentos_evaluacion`
--
ALTER TABLE `intentos_evaluacion`
  ADD CONSTRAINT `intentos_evaluacion_ibfk_1` FOREIGN KEY (`id_evaluacion`) REFERENCES `evaluaciones` (`id_evaluacion`) ON DELETE CASCADE,
  ADD CONSTRAINT `intentos_evaluacion_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `interacciones_productos`
--
ALTER TABLE `interacciones_productos`
  ADD CONSTRAINT `interacciones_productos_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE,
  ADD CONSTRAINT `interacciones_productos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `lecciones`
--
ALTER TABLE `lecciones`
  ADD CONSTRAINT `lecciones_ibfk_1` FOREIGN KEY (`id_modulo`) REFERENCES `modulos` (`id_modulo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `logros`
--
ALTER TABLE `logros`
  ADD CONSTRAINT `logros_ibfk_1` FOREIGN KEY (`id_categoria_logro`) REFERENCES `categorias_logros` (`id_categoria_logro`) ON DELETE SET NULL;

--
-- Filtros para la tabla `logros_usuarios`
--
ALTER TABLE `logros_usuarios`
  ADD CONSTRAINT `logros_usuarios_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `logros_usuarios_ibfk_2` FOREIGN KEY (`id_logro`) REFERENCES `logros` (`id_logro`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD CONSTRAINT `mensajes_ibfk_1` FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`) ON DELETE CASCADE,
  ADD CONSTRAINT `mensajes_ibfk_2` FOREIGN KEY (`id_remitente`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `mentorias`
--
ALTER TABLE `mentorias`
  ADD CONSTRAINT `mentorias_ibfk_1` FOREIGN KEY (`id_mentor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `mentorias_ibfk_2` FOREIGN KEY (`id_aprendiz`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mentoria_contexto`
--
ALTER TABLE `mentoria_contexto`
  ADD CONSTRAINT `mentoria_contexto_ibfk_1` FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `modulos`
--
ALTER TABLE `modulos`
  ADD CONSTRAINT `modulos_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE;

--
-- Filtros para la tabla `modulos_curso`
--
ALTER TABLE `modulos_curso`
  ADD CONSTRAINT `modulos_curso_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `opciones_pregunta`
--
ALTER TABLE `opciones_pregunta`
  ADD CONSTRAINT `opciones_pregunta_ibfk_1` FOREIGN KEY (`id_pregunta_evaluacion`) REFERENCES `preguntas_evaluacion` (`id_pregunta_evaluacion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `perfiles_empresariales`
--
ALTER TABLE `perfiles_empresariales`
  ADD CONSTRAINT `perfiles_empresariales_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `preferencias_notificacion`
--
ALTER TABLE `preferencias_notificacion`
  ADD CONSTRAINT `preferencias_notificacion_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `preguntas_diagnostico`
--
ALTER TABLE `preguntas_diagnostico`
  ADD CONSTRAINT `preguntas_diagnostico_ibfk_1` FOREIGN KEY (`id_area`) REFERENCES `areas_evaluacion` (`id_area`) ON DELETE CASCADE;

--
-- Filtros para la tabla `preguntas_evaluacion`
--
ALTER TABLE `preguntas_evaluacion`
  ADD CONSTRAINT `preguntas_evaluacion_ibfk_1` FOREIGN KEY (`id_evaluacion`) REFERENCES `evaluaciones` (`id_evaluacion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `prerrequisitos_curso`
--
ALTER TABLE `prerrequisitos_curso`
  ADD CONSTRAINT `prerrequisitos_curso_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE,
  ADD CONSTRAINT `prerrequisitos_curso_ibfk_2` FOREIGN KEY (`id_curso_requerido`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`id_perfil_empresarial`) REFERENCES `perfiles_empresariales` (`id_perfil`) ON DELETE SET NULL,
  ADD CONSTRAINT `productos_ibfk_3` FOREIGN KEY (`id_categoria`) REFERENCES `categorias_productos` (`id_categoria`);

--
-- Filtros para la tabla `productos_favoritos`
--
ALTER TABLE `productos_favoritos`
  ADD CONSTRAINT `productos_favoritos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `productos_favoritos_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE;

--
-- Filtros para la tabla `progreso_lecciones`
--
ALTER TABLE `progreso_lecciones`
  ADD CONSTRAINT `progreso_lecciones_ibfk_1` FOREIGN KEY (`id_inscripcion`) REFERENCES `inscripciones` (`id_inscripcion`) ON DELETE CASCADE,
  ADD CONSTRAINT `progreso_lecciones_ibfk_2` FOREIGN KEY (`id_leccion`) REFERENCES `lecciones` (`id_leccion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `puntos_usuario`
--
ALTER TABLE `puntos_usuario`
  ADD CONSTRAINT `puntos_usuario_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `puntos_usuarios`
--
ALTER TABLE `puntos_usuarios`
  ADD CONSTRAINT `puntos_usuarios_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `rachas_usuario`
--
ALTER TABLE `rachas_usuario`
  ADD CONSTRAINT `rachas_usuario_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `recomendaciones_cursos`
--
ALTER TABLE `recomendaciones_cursos`
  ADD CONSTRAINT `recomendaciones_cursos_ibfk_1` FOREIGN KEY (`id_area`) REFERENCES `areas_evaluacion` (`id_area`) ON DELETE CASCADE,
  ADD CONSTRAINT `recomendaciones_cursos_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE;

--
-- Filtros para la tabla `recursos`
--
ALTER TABLE `recursos`
  ADD CONSTRAINT `recursos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias_recursos` (`id_categoria`),
  ADD CONSTRAINT `recursos_ibfk_2` FOREIGN KEY (`id_autor`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `recursos_etiquetas`
--
ALTER TABLE `recursos_etiquetas`
  ADD CONSTRAINT `recursos_etiquetas_ibfk_1` FOREIGN KEY (`id_recurso`) REFERENCES `recursos` (`id_recurso`) ON DELETE CASCADE,
  ADD CONSTRAINT `recursos_etiquetas_ibfk_2` FOREIGN KEY (`id_etiqueta`) REFERENCES `etiquetas_recursos` (`id_etiqueta`) ON DELETE CASCADE;

--
-- Filtros para la tabla `recursos_etiquetas_versiones`
--
ALTER TABLE `recursos_etiquetas_versiones`
  ADD CONSTRAINT `fk_ver_etiq_etiqueta` FOREIGN KEY (`id_etiqueta`) REFERENCES `etiquetas_recursos` (`id_etiqueta`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ver_etiq_version` FOREIGN KEY (`id_version`) REFERENCES `recursos_versiones` (`id_version`) ON DELETE CASCADE;

--
-- Filtros para la tabla `recursos_versiones`
--
ALTER TABLE `recursos_versiones`
  ADD CONSTRAINT `fk_version_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias_recursos` (`id_categoria`),
  ADD CONSTRAINT `fk_version_recurso` FOREIGN KEY (`id_recurso`) REFERENCES `recursos` (`id_recurso`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_version_usuario` FOREIGN KEY (`id_usuario_cambio`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `respuestas_diagnostico`
--
ALTER TABLE `respuestas_diagnostico`
  ADD CONSTRAINT `respuestas_diagnostico_ibfk_1` FOREIGN KEY (`id_diagnostico_realizado`) REFERENCES `diagnosticos_realizados` (`id_diagnostico_realizado`) ON DELETE CASCADE,
  ADD CONSTRAINT `respuestas_diagnostico_ibfk_2` FOREIGN KEY (`id_pregunta`) REFERENCES `preguntas_diagnostico` (`id_pregunta`) ON DELETE CASCADE;

--
-- Filtros para la tabla `respuestas_evaluacion`
--
ALTER TABLE `respuestas_evaluacion`
  ADD CONSTRAINT `respuestas_evaluacion_ibfk_1` FOREIGN KEY (`id_intento`) REFERENCES `intentos_evaluacion` (`id_intento`) ON DELETE CASCADE,
  ADD CONSTRAINT `respuestas_evaluacion_ibfk_2` FOREIGN KEY (`id_pregunta_evaluacion`) REFERENCES `preguntas_evaluacion` (`id_pregunta_evaluacion`) ON DELETE CASCADE,
  ADD CONSTRAINT `respuestas_evaluacion_ibfk_3` FOREIGN KEY (`id_opcion_seleccionada`) REFERENCES `opciones_pregunta` (`id_opcion`) ON DELETE SET NULL;

--
-- Filtros para la tabla `transacciones_puntos`
--
ALTER TABLE `transacciones_puntos`
  ADD CONSTRAINT `transacciones_puntos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `vistas_recursos`
--
ALTER TABLE `vistas_recursos`
  ADD CONSTRAINT `vistas_recursos_ibfk_1` FOREIGN KEY (`id_recurso`) REFERENCES `recursos` (`id_recurso`) ON DELETE CASCADE,
  ADD CONSTRAINT `vistas_recursos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

SET FOREIGN_KEY_CHECKS = 1;
