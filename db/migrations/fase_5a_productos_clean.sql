-- ============================================================================
-- LIMPIEZA COMPLETA DE FASE 5A
-- ============================================================================
SET FOREIGN_KEY_CHECKS=0;

-- Eliminar procedimientos
DROP PROCEDURE IF EXISTS sp_registrar_vista_producto;
DROP PROCEDURE IF EXISTS sp_registrar_contacto_producto;

-- Eliminar triggers
DROP TRIGGER IF EXISTS after_producto_insert;
DROP TRIGGER IF EXISTS after_producto_update;
DROP TRIGGER IF EXISTS after_producto_delete;
DROP TRIGGER IF EXISTS after_favorito_insert;
DROP TRIGGER IF EXISTS after_favorito_delete;

-- Eliminar vista
DROP VIEW IF EXISTS vista_productos_completa;

-- Eliminar tablas en orden inverso de dependencias
DROP TABLE IF EXISTS interacciones_productos;
DROP TABLE IF EXISTS productos_favoritos;
DROP TABLE IF EXISTS imagenes_productos;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS categorias_productos;

SET FOREIGN_KEY_CHECKS=1;

-- Ahora ejecutar el script principal
SOURCE C:/xampp/htdocs/nenis_y_bros/db/migrations/fase_5a_productos.sql;
