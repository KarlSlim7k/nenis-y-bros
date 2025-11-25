<?php

/**
 * Modelo CategoriaProducto
 * 
 * Gestiona las categorías de productos del marketplace
 * 
 * @package Models
 * @author Nenis y Bros
 * @version 1.0.0
 */

class CategoriaProducto {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener todas las categorías activas
     * 
     * @param bool $soloActivas Solo categorías activas
     * @return array Lista de categorías
     */
    public function getAll($soloActivas = true) {
        $whereClause = $soloActivas ? "WHERE activo = TRUE" : "";
        
        $query = "
            SELECT 
                id_categoria,
                nombre,
                descripcion,
                icono,
                slug,
                color_hex,
                orden,
                activo,
                total_productos,
                fecha_creacion,
                fecha_actualizacion
            FROM categorias_productos
            $whereClause
            ORDER BY orden ASC, nombre ASC
        ";
        
        return $this->db->fetchAll($query);
    }

    /**
     * Obtener categoría por ID
     * 
     * @param int $idCategoria
     * @return array|null Datos de la categoría
     */
    public function getById($idCategoria) {
        $query = "
            SELECT 
                id_categoria,
                nombre,
                descripcion,
                icono,
                slug,
                color_hex,
                orden,
                activo,
                total_productos,
                fecha_creacion,
                fecha_actualizacion
            FROM categorias_productos
            WHERE id_categoria = ?
        ";
        
        return $this->db->fetchOne($query, [$idCategoria]);
    }

    /**
     * Obtener categoría por slug
     * 
     * @param string $slug
     * @return array|null Datos de la categoría
     */
    public function getBySlug($slug) {
        $query = "
            SELECT 
                id_categoria,
                nombre,
                descripcion,
                icono,
                slug,
                color_hex,
                orden,
                activo,
                total_productos,
                fecha_creacion,
                fecha_actualizacion
            FROM categorias_productos
            WHERE slug = ? AND activo = TRUE
        ";
        
        return $this->db->fetchOne($query, [$slug]);
    }

    /**
     * Crear nueva categoría
     * 
     * @param array $datos Datos de la categoría
     * @return int ID de la categoría creada
     */
    public function crear($datos) {
        // Generar slug si no se proporciona
        if (empty($datos['slug'])) {
            $datos['slug'] = $this->generarSlug($datos['nombre']);
        }

        $query = "
            INSERT INTO categorias_productos 
                (nombre, descripcion, icono, slug, color_hex, orden, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        $params = [
            $datos['nombre'],
            $datos['descripcion'] ?? null,
            $datos['icono'] ?? 'box',
            $datos['slug'],
            $datos['color_hex'] ?? '#667eea',
            $datos['orden'] ?? 0,
            $datos['activo'] ?? true
        ];

        $idCategoria = $this->db->insert($query, $params);

        Logger::activity(0, "Nueva categoría de producto creada: {$datos['nombre']} (ID: $idCategoria)");

        return $idCategoria;
    }

    /**
     * Actualizar categoría existente
     * 
     * @param int $idCategoria
     * @param array $datos Datos a actualizar
     * @return bool Éxito de la operación
     */
    public function actualizar($idCategoria, $datos) {
        $campos = [];
        $params = [];

        if (isset($datos['nombre'])) {
            $campos[] = "nombre = ?";
            $params[] = $datos['nombre'];
        }
        if (isset($datos['descripcion'])) {
            $campos[] = "descripcion = ?";
            $params[] = $datos['descripcion'];
        }
        if (isset($datos['icono'])) {
            $campos[] = "icono = ?";
            $params[] = $datos['icono'];
        }
        if (isset($datos['slug'])) {
            $campos[] = "slug = ?";
            $params[] = $datos['slug'];
        }
        if (isset($datos['color_hex'])) {
            $campos[] = "color_hex = ?";
            $params[] = $datos['color_hex'];
        }
        if (isset($datos['orden'])) {
            $campos[] = "orden = ?";
            $params[] = $datos['orden'];
        }
        if (isset($datos['activo'])) {
            $campos[] = "activo = ?";
            $params[] = $datos['activo'];
        }

        if (empty($campos)) {
            return false;
        }

        $params[] = $idCategoria;

        $query = "
            UPDATE categorias_productos 
            SET " . implode(", ", $campos) . "
            WHERE id_categoria = ?
        ";

        $resultado = $this->db->query($query, $params);

        if ($resultado) {
            Logger::activity(0, "Categoría actualizada: ID $idCategoria");
        }

        return $resultado;
    }

    /**
     * Eliminar categoría (solo si no tiene productos)
     * 
     * @param int $idCategoria
     * @return bool Éxito de la operación
     */
    public function eliminar($idCategoria) {
        // Verificar si tiene productos
        $categoria = $this->getById($idCategoria);
        
        if (!$categoria) {
            return false;
        }

        if ($categoria['total_productos'] > 0) {
            throw new Exception("No se puede eliminar una categoría con productos asociados");
        }

        $query = "DELETE FROM categorias_productos WHERE id_categoria = ?";
        $resultado = $this->db->query($query, [$idCategoria]);

        if ($resultado) {
            Logger::activity(0, "Categoría eliminada: {$categoria['nombre']} (ID: $idCategoria)");
        }

        return $resultado;
    }

    /**
     * Obtener categorías con conteo de productos publicados
     * 
     * @return array Lista de categorías con estadísticas
     */
    public function getConEstadisticas() {
        $query = "
            SELECT 
                c.id_categoria,
                c.nombre,
                c.descripcion,
                c.icono,
                c.slug,
                c.color_hex,
                c.total_productos,
                COUNT(DISTINCT p.id_producto) AS productos_publicados,
                COUNT(DISTINCT p.id_usuario) AS vendedores_unicos
            FROM categorias_productos c
            LEFT JOIN productos p ON c.id_categoria = p.id_categoria 
                AND p.estado = 'publicado'
            WHERE c.activo = TRUE
            GROUP BY c.id_categoria
            ORDER BY c.orden ASC, c.nombre ASC
        ";
        
        return $this->db->fetchAll($query);
    }

    /**
     * Generar slug único a partir de un texto
     * 
     * @param string $texto
     * @return string Slug único
     */
    private function generarSlug($texto) {
        // Convertir a minúsculas y reemplazar caracteres especiales
        $slug = strtolower($texto);
        $slug = preg_replace('/[áàäâ]/', 'a', $slug);
        $slug = preg_replace('/[éèëê]/', 'e', $slug);
        $slug = preg_replace('/[íìïî]/', 'i', $slug);
        $slug = preg_replace('/[óòöô]/', 'o', $slug);
        $slug = preg_replace('/[úùüû]/', 'u', $slug);
        $slug = preg_replace('/ñ/', 'n', $slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Verificar unicidad
        $slugOriginal = $slug;
        $contador = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $slugOriginal . '-' . $contador;
            $contador++;
        }

        return $slug;
    }

    /**
     * Verificar si un slug ya existe
     * 
     * @param string $slug
     * @return bool
     */
    private function slugExists($slug) {
        $query = "SELECT COUNT(*) as count FROM categorias_productos WHERE slug = ?";
        $result = $this->db->fetchOne($query, [$slug]);
        return $result['count'] > 0;
    }

    /**
     * Actualizar orden de categorías
     * 
     * @param array $orden Array con id_categoria => orden
     * @return bool
     */
    public function actualizarOrden($orden) {
        $this->db->query("START TRANSACTION");
        
        try {
            $query = "UPDATE categorias_productos SET orden = ? WHERE id_categoria = ?";
            
            foreach ($orden as $idCategoria => $nuevoOrden) {
                $this->db->query($query, [$nuevoOrden, $idCategoria]);
            }
            
            $this->db->query("COMMIT");
            Logger::activity(0, "Orden de categorías actualizado");
            return true;
            
        } catch (Exception $e) {
            $this->db->query("ROLLBACK");
            Logger::error("Error actualizando orden de categorías: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Activar/desactivar categoría
     * 
     * @param int $idCategoria
     * @param bool $activo
     * @return bool
     */
    public function cambiarEstado($idCategoria, $activo) {
        $query = "UPDATE categorias_productos SET activo = ? WHERE id_categoria = ?";
        $resultado = $this->db->query($query, [$activo, $idCategoria]);

        if ($resultado) {
            $estado = $activo ? 'activada' : 'desactivada';
            Logger::activity(0, "Categoría $estado: ID $idCategoria");
        }

        return $resultado;
    }
}
