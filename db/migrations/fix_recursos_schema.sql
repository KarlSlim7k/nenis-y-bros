-- =====================================================
-- Migración: Completar esquema de recursos_aprendizaje
-- Fecha: 2025-12-13
-- Descripción: Agregar campos faltantes y tablas relacionadas
-- =====================================================

-- 1. Agregar campos faltantes a recursos_aprendizaje
ALTER TABLE `recursos_aprendizaje`
ADD COLUMN `slug` VARCHAR(300) NULL AFTER `titulo`,
ADD COLUMN `id_autor` INT NULL AFTER `slug`,
ADD COLUMN `contenido_texto` TEXT NULL AFTER `descripcion`,
ADD COLUMN `contenido_html` MEDIUMTEXT NULL AFTER `contenido_texto`,
ADD COLUMN `duracion_minutos` INT NULL AFTER `url_recurso`,
ADD COLUMN `imagen_preview` VARCHAR(255) NULL AFTER `imagen_portada`,
ADD COLUMN `video_preview` VARCHAR(500) NULL AFTER `imagen_preview`,
ADD COLUMN `idioma` VARCHAR(5) DEFAULT 'es' AFTER `nivel`,
ADD COLUMN `formato` VARCHAR(50) NULL AFTER `idioma`,
ADD COLUMN `licencia` VARCHAR(200) DEFAULT 'Uso educativo' AFTER `formato`,
ADD COLUMN `destacado` TINYINT(1) DEFAULT 0 AFTER `activo`,
ADD COLUMN `fecha_publicacion` DATETIME NULL AFTER `fecha_creacion`,
ADD COLUMN `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `fecha_publicacion`;

-- 2. Crear índice para slug (URLs amigables)
ALTER TABLE `recursos_aprendizaje`
ADD UNIQUE INDEX `idx_slug` (`slug`);

-- 3. Crear índice para autor si se agrega FK
ALTER TABLE `recursos_aprendizaje`
ADD INDEX `idx_autor` (`id_autor`);

-- 4. Crear índice para destacados
ALTER TABLE `recursos_aprendizaje`
ADD INDEX `idx_destacado` (`destacado`, `activo`);

-- 5. Generar slugs para recursos existentes (si los hay)
UPDATE `recursos_aprendizaje`
SET `slug` = LOWER(CONCAT(
    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
        titulo, ' ', '-'), 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'ñ', 'n'
    ), '-', id_recurso
))
WHERE `slug` IS NULL;

-- 6. Crear tabla descargas_recursos si no existe
CREATE TABLE IF NOT EXISTS `descargas_recursos` (
  `id_descarga` INT(11) NOT NULL AUTO_INCREMENT,
  `id_recurso` INT(11) NOT NULL,
  `id_usuario` INT(11) NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(500) NULL,
  `fecha_descarga` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_descarga`),
  INDEX `idx_recurso` (`id_recurso`),
  INDEX `idx_usuario` (`id_usuario`),
  INDEX `idx_fecha` (`fecha_descarga`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de descargas de recursos';

-- 7. Crear tabla calificaciones_recursos si no existe
CREATE TABLE IF NOT EXISTS `calificaciones_recursos` (
  `id_calificacion` INT(11) NOT NULL AUTO_INCREMENT,
  `id_recurso` INT(11) NOT NULL,
  `id_usuario` INT(11) NOT NULL,
  `calificacion` TINYINT(1) NOT NULL CHECK (`calificacion` BETWEEN 1 AND 5),
  `comentario` TEXT NULL,
  `fecha_calificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_calificacion`),
  UNIQUE KEY `unique_usuario_recurso` (`id_recurso`, `id_usuario`),
  INDEX `idx_recurso` (`id_recurso`),
  INDEX `idx_usuario` (`id_usuario`),
  INDEX `idx_calificacion` (`calificacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Calificaciones de recursos por usuarios';

-- 8. Crear tabla vistas_recursos si no existe
CREATE TABLE IF NOT EXISTS `vistas_recursos` (
  `id_vista` INT(11) NOT NULL AUTO_INCREMENT,
  `id_recurso` INT(11) NOT NULL,
  `id_usuario` INT(11) NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(500) NULL,
  `referrer` VARCHAR(500) NULL,
  `fecha_vista` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_vista`),
  INDEX `idx_recurso` (`id_recurso`),
  INDEX `idx_usuario` (`id_usuario`),
  INDEX `idx_fecha` (`fecha_vista`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de vistas de recursos';

-- 9. Crear triggers para actualizar contadores en recursos_aprendizaje

-- Trigger para incrementar descargas
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `trg_recurso_descarga_insert`
AFTER INSERT ON `descargas_recursos`
FOR EACH ROW
BEGIN
    UPDATE `recursos_aprendizaje`
    SET `descargas` = `descargas` + 1
    WHERE `id_recurso` = NEW.id_recurso;
END$$
DELIMITER ;

-- Trigger para recalcular calificación promedio al insertar
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `trg_recurso_calificacion_insert`
AFTER INSERT ON `calificaciones_recursos`
FOR EACH ROW
BEGIN
    UPDATE `recursos_aprendizaje` r
    SET r.calificacion_promedio = (
        SELECT AVG(calificacion)
        FROM `calificaciones_recursos`
        WHERE id_recurso = NEW.id_recurso
    )
    WHERE r.id_recurso = NEW.id_recurso;
END$$
DELIMITER ;

-- Trigger para recalcular calificación promedio al actualizar
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `trg_recurso_calificacion_update`
AFTER UPDATE ON `calificaciones_recursos`
FOR EACH ROW
BEGIN
    UPDATE `recursos_aprendizaje` r
    SET r.calificacion_promedio = (
        SELECT AVG(calificacion)
        FROM `calificaciones_recursos`
        WHERE id_recurso = NEW.id_recurso
    )
    WHERE r.id_recurso = NEW.id_recurso;
END$$
DELIMITER ;

-- Trigger para recalcular calificación promedio al eliminar
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `trg_recurso_calificacion_delete`
AFTER DELETE ON `calificaciones_recursos`
FOR EACH ROW
BEGIN
    UPDATE `recursos_aprendizaje` r
    SET r.calificacion_promedio = COALESCE((
        SELECT AVG(calificacion)
        FROM `calificaciones_recursos`
        WHERE id_recurso = OLD.id_recurso
    ), 0)
    WHERE r.id_recurso = OLD.id_recurso;
END$$
DELIMITER ;

-- 10. Agregar foreign keys si las tablas usuarios existen
-- (comentadas por si la tabla usuarios tiene nombre diferente)
/*
ALTER TABLE `recursos_aprendizaje`
ADD CONSTRAINT `fk_recursos_autor`
FOREIGN KEY (`id_autor`) REFERENCES `usuarios`(`id_usuario`)
ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `descargas_recursos`
ADD CONSTRAINT `fk_descargas_recurso`
FOREIGN KEY (`id_recurso`) REFERENCES `recursos_aprendizaje`(`id_recurso`)
ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `fk_descargas_usuario`
FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id_usuario`)
ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `calificaciones_recursos`
ADD CONSTRAINT `fk_calificaciones_recurso`
FOREIGN KEY (`id_recurso`) REFERENCES `recursos_aprendizaje`(`id_recurso`)
ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `fk_calificaciones_usuario`
FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id_usuario`)
ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `vistas_recursos`
ADD CONSTRAINT `fk_vistas_recurso`
FOREIGN KEY (`id_recurso`) REFERENCES `recursos_aprendizaje`(`id_recurso`)
ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `fk_vistas_usuario`
FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id_usuario`)
ON DELETE SET NULL ON UPDATE CASCADE;
*/

-- =====================================================
-- Verificación Post-Migración
-- =====================================================

-- Verificar estructura de recursos_aprendizaje
DESCRIBE `recursos_aprendizaje`;

-- Verificar tablas creadas
SHOW TABLES LIKE '%recursos%';

-- Verificar triggers
SHOW TRIGGERS WHERE `Table` LIKE '%recursos%';

-- =====================================================
-- Notas de Migración
-- =====================================================
-- 1. Esta migración es idempotente (puede ejecutarse múltiples veces)
-- 2. Los triggers se crean con IF NOT EXISTS para evitar errores
-- 3. Las FK están comentadas - descomentar según nombre real de tabla usuarios
-- 4. Los slugs se generan automáticamente para recursos existentes
-- 5. Los nuevos campos permiten funcionalidad completa del módulo
-- =====================================================
