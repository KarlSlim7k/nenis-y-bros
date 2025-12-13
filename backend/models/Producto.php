<?php

/**
 * Modelo Producto
 * 
 * Gestiona productos y servicios del marketplace
 * Incluye: CRUD, búsqueda, filtros, estadísticas, favoritos, interacciones
 * 
 * NOTA: Este modelo soporta dos esquemas diferentes:
 * - Desarrollo: tabla 'productos' con esquema extendido
 * - Producción: tabla 'productos_vitrina' con esquema simplificado
 * 
 * @package Models
 * @author Nenis y Bros
 * @version 1.1.0
 */

class Producto {
    private $db;
    private $tabla;
    private $esEsquemaProduccion;

    public function __construct() {
        $this->db = Database::getInstance();
        
        // Detectar qué tabla está disponible
        $this->detectarEsquema();
    }
    
    /**
     * Detectar si estamos usando el esquema de producción o desarrollo
     */
    private function detectarEsquema() {
        try {
            // Intentar con productos_vitrina primero (producción)
            $result = $this->db->fetchOne("SHOW TABLES LIKE 'productos_vitrina'");
            if ($result) {
                $this->tabla = 'productos_vitrina';
                $this->esEsquemaProduccion = true;
                return;
            }
        } catch (Exception $e) {
            // Ignorar error
        }
        
        // Fallback a tabla productos (desarrollo)
        $this->tabla = 'productos';
        $this->esEsquemaProduccion = false;
    }

    /**
     * Crear nuevo producto
     * 
     * @param array $datos Datos del producto
     * @param int $idUsuario ID del vendedor
     * @return int ID del producto creado
     */
    public function crear($datos, $idUsuario) {
        // Generar slug único
        $slug = $this->generarSlug($datos['titulo']);

        $query = "
            INSERT INTO productos (
                id_usuario, id_perfil_empresarial, id_categoria,
                titulo, slug, descripcion_corta, descripcion_completa,
                tipo_producto, precio, moneda, precio_anterior,
                control_inventario, cantidad_disponible, unidad_medida,
                etiquetas, caracteristicas,
                contacto_whatsapp, contacto_email, contacto_telefono,
                ubicacion_ciudad, ubicacion_estado, ubicacion_pais,
                estado, destacado,
                meta_titulo, meta_descripcion
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?
            )
        ";

        $params = [
            $idUsuario,
            $datos['id_perfil_empresarial'] ?? null,
            $datos['id_categoria'],
            $datos['titulo'],
            $slug,
            $datos['descripcion_corta'] ?? null,
            $datos['descripcion_completa'] ?? null,
            $datos['tipo_producto'] ?? 'producto_fisico',
            $datos['precio'] ?? 0.00,
            $datos['moneda'] ?? 'MXN',
            $datos['precio_anterior'] ?? null,
            $datos['control_inventario'] ?? false,
            $datos['cantidad_disponible'] ?? null,
            $datos['unidad_medida'] ?? null,
            isset($datos['etiquetas']) ? json_encode($datos['etiquetas']) : null,
            isset($datos['caracteristicas']) ? json_encode($datos['caracteristicas']) : null,
            $datos['contacto_whatsapp'] ?? null,
            $datos['contacto_email'] ?? null,
            $datos['contacto_telefono'] ?? null,
            $datos['ubicacion_ciudad'] ?? null,
            $datos['ubicacion_estado'] ?? null,
            $datos['ubicacion_pais'] ?? 'México',
            $datos['estado'] ?? 'borrador',
            $datos['destacado'] ?? false,
            $datos['meta_titulo'] ?? null,
            $datos['meta_descripcion'] ?? null
        ];

        $idProducto = $this->db->insert($query, $params);

        // Si se publicó directamente, actualizar fecha
        if (($datos['estado'] ?? 'borrador') === 'publicado') {
            $this->db->query(
                "UPDATE productos SET fecha_publicacion = NOW() WHERE id_producto = ?",
                [$idProducto]
            );
        }

        Logger::activity($idUsuario, "Producto creado: {$datos['titulo']} (ID: $idProducto)");

        return $idProducto;
    }

    /**
     * Actualizar producto existente
     * 
     * @param int $idProducto
     * @param array $datos Datos a actualizar
     * @param int $idUsuario ID del usuario (para verificar permisos)
     * @return bool Éxito de la operación
     */
    public function actualizar($idProducto, $datos, $idUsuario) {
        // Verificar propiedad
        $producto = $this->getById($idProducto);
        if (!$producto || $producto['id_usuario'] != $idUsuario) {
            throw new Exception("No tienes permiso para editar este producto");
        }

        $campos = [];
        $params = [];

        $camposPermitidos = [
            'id_categoria', 'titulo', 'descripcion_corta', 'descripcion_completa',
            'tipo_producto', 'precio', 'moneda', 'precio_anterior',
            'control_inventario', 'cantidad_disponible', 'unidad_medida',
            'contacto_whatsapp', 'contacto_email', 'contacto_telefono',
            'ubicacion_ciudad', 'ubicacion_estado', 'ubicacion_pais',
            'estado', 'meta_titulo', 'meta_descripcion'
        ];

        foreach ($camposPermitidos as $campo) {
            if (isset($datos[$campo])) {
                $campos[] = "$campo = ?";
                $params[] = $datos[$campo];
            }
        }

        // Manejar JSON
        if (isset($datos['etiquetas'])) {
            $campos[] = "etiquetas = ?";
            $params[] = json_encode($datos['etiquetas']);
        }
        if (isset($datos['caracteristicas'])) {
            $campos[] = "caracteristicas = ?";
            $params[] = json_encode($datos['caracteristicas']);
        }

        // Si cambia a publicado, actualizar fecha
        if (isset($datos['estado']) && $datos['estado'] === 'publicado' && $producto['estado'] !== 'publicado') {
            $campos[] = "fecha_publicacion = NOW()";
        }

        if (empty($campos)) {
            return false;
        }

        $params[] = $idProducto;

        $query = "UPDATE productos SET " . implode(", ", $campos) . " WHERE id_producto = ?";
        $resultado = $this->db->query($query, $params);

        if ($resultado) {
            Logger::activity($idUsuario, "Producto actualizado: {$producto['titulo']} (ID: $idProducto)");
        }

        return $resultado;
    }

    /**
     * Eliminar producto (solo el propietario)
     * 
     * @param int $idProducto
     * @param int $idUsuario ID del usuario (para verificar permisos)
     * @return bool Éxito de la operación
     */
    public function eliminar($idProducto, $idUsuario) {
        // Verificar propiedad
        $producto = $this->getById($idProducto);
        if (!$producto || $producto['id_usuario'] != $idUsuario) {
            throw new Exception("No tienes permiso para eliminar este producto");
        }

        $query = "DELETE FROM productos WHERE id_producto = ?";
        $resultado = $this->db->query($query, [$idProducto]);

        if ($resultado) {
            Logger::activity($idUsuario, "Producto eliminado: {$producto['titulo']} (ID: $idProducto)");
        }

        return $resultado;
    }

    /**
     * Obtener producto por ID con toda la información
     * 
     * @param int $idProducto
     * @param int|null $idUsuario Para verificar si es favorito
     * @return array|null Datos completos del producto
     */
    public function getById($idProducto, $idUsuario = null) {
        if ($this->esEsquemaProduccion) {
            return $this->getByIdProduccion($idProducto, $idUsuario);
        }
        return $this->getByIdDesarrollo($idProducto, $idUsuario);
    }
    
    /**
     * getById para esquema de producción
     */
    private function getByIdProduccion($idProducto, $idUsuario = null) {
        $query = "
            SELECT 
                p.*,
                p.nombre as titulo,
                p.vistas as total_vistas,
                c.nombre AS categoria,
                c.nombre AS categoria_nombre,
                u.nombre AS vendedor_nombre,
                u.nombre AS nombre_usuario,
                u.email AS vendedor_email,
                u.email AS email_usuario,
                u.telefono AS telefono_usuario,
                pe.nombre_empresa
            FROM {$this->tabla} p
            LEFT JOIN categorias_productos c ON p.id_categoria_producto = c.id_categoria
            LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
            LEFT JOIN perfiles_empresariales pe ON p.id_perfil = pe.id_perfil
            WHERE p.id_producto = ?
        ";

        $producto = $this->db->fetchOne($query, [$idProducto]);

        if ($producto) {
            // Procesar imagenes JSON
            if (!empty($producto['imagenes'])) {
                $imagenes = json_decode($producto['imagenes'], true);
                if (is_array($imagenes) && count($imagenes) > 0) {
                    $producto['imagen_principal'] = $imagenes[0];
                    $producto['imagenes_array'] = $imagenes;
                }
            }
            $producto['imagen_principal'] = $producto['imagen_principal'] ?? null;
            
            // Adaptar campos para compatibilidad
            $producto['etiquetas'] = !empty($producto['etiquetas']) ? json_decode($producto['etiquetas'], true) : [];
            $producto['num_favoritos'] = 0; // No tenemos esta tabla en producción
            $producto['num_contactos'] = $producto['contactos_recibidos'] ?? 0;
        }

        return $producto;
    }
    
    /**
     * getById para esquema de desarrollo
     */
    private function getByIdDesarrollo($idProducto, $idUsuario = null) {
        $esFavoritoSubquery = "";
        if ($idUsuario) {
            $esFavoritoSubquery = ", EXISTS(
                SELECT 1 FROM productos_favoritos 
                WHERE id_producto = p.id_producto AND id_usuario = $idUsuario
            ) AS es_favorito";
        }

        $query = "
            SELECT 
                p.*,
                c.nombre AS categoria_nombre,
                c.slug AS categoria_slug,
                c.color_hex AS categoria_color,
                u.nombre AS vendedor_nombre,
                u.email AS vendedor_email,
                u.foto_perfil AS vendedor_foto,
                pe.nombre_empresa,
                pe.logo_empresa AS perfil_logo,
                pe.sector AS perfil_sector,
                (SELECT COUNT(*) FROM imagenes_productos WHERE id_producto = p.id_producto) AS total_imagenes
                $esFavoritoSubquery
            FROM productos p
            INNER JOIN categorias_productos c ON p.id_categoria = c.id_categoria
            INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
            LEFT JOIN perfiles_empresariales pe ON p.id_perfil_empresarial = pe.id_perfil
            WHERE p.id_producto = ?
        ";

        $producto = $this->db->fetchOne($query, [$idProducto]);

        if ($producto) {
            // Decodificar JSON (manejar NULL)
            $producto['etiquetas'] = $producto['etiquetas'] ? json_decode($producto['etiquetas'], true) : [];
            $producto['caracteristicas'] = $producto['caracteristicas'] ? json_decode($producto['caracteristicas'], true) : [];
            
            // Obtener imágenes
            $producto['imagenes'] = $this->getImagenes($idProducto);
        }

        return $producto;
    }

    /**
     * Obtener producto por slug
     * 
     * @param string $slug
     * @param int|null $idUsuario
     * @return array|null
     */
    public function getBySlug($slug, $idUsuario = null) {
        $query = "SELECT id_producto FROM productos WHERE slug = ?";
        $result = $this->db->fetchOne($query, [$slug]);
        
        if ($result) {
            return $this->getById($result['id_producto'], $idUsuario);
        }
        
        return null;
    }

    /**
     * Buscar y filtrar productos (para catálogo público)
     * 
     * @param array $filtros Filtros de búsqueda
     * @param int $pagina Página actual
     * @param int $porPagina Resultados por página
     * @return array ['productos' => [], 'total' => int, 'paginas' => int]
     */
    public function buscar($filtros = [], $pagina = 1, $porPagina = 20) {
        // Usar método específico según el esquema
        if ($this->esEsquemaProduccion) {
            return $this->buscarProduccion($filtros, $pagina, $porPagina);
        }
        return $this->buscarDesarrollo($filtros, $pagina, $porPagina);
    }
    
    /**
     * Búsqueda para esquema de producción (productos_vitrina)
     */
    private function buscarProduccion($filtros = [], $pagina = 1, $porPagina = 20) {
        $where = [];
        $params = [];

        // Filtro por estado - admin puede ver todos, usuarios solo publicados
        if (!empty($filtros['estado'])) {
            $where[] = "p.estado = ?";
            $params[] = $filtros['estado'];
        } else {
            $where[] = "p.estado = 'publicado'";
        }

        // Filtro por categoría
        if (!empty($filtros['categoria'])) {
            $where[] = "p.id_categoria_producto = ?";
            $params[] = $filtros['categoria'];
        }

        // Filtro por rango de precio
        if (!empty($filtros['precio_min'])) {
            $where[] = "p.precio >= ?";
            $params[] = $filtros['precio_min'];
        }
        if (!empty($filtros['precio_max'])) {
            $where[] = "p.precio <= ?";
            $params[] = $filtros['precio_max'];
        }

        // Búsqueda por texto (LIKE simple para compatibilidad)
        if (!empty($filtros['q']) || !empty($filtros['search'])) {
            $searchTerm = '%' . ($filtros['q'] ?? $filtros['search']) . '%';
            $where[] = "(p.nombre LIKE ? OR p.descripcion LIKE ? OR p.descripcion_corta LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Solo productos destacados
        if (!empty($filtros['destacados'])) {
            $where[] = "p.destacado = 1";
        }

        // Vendedor específico
        if (!empty($filtros['vendedor'])) {
            $where[] = "p.id_usuario = ?";
            $params[] = $filtros['vendedor'];
        }

        $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

        // Orden
        $orderBy = "ORDER BY p.fecha_creacion DESC";
        if (!empty($filtros['orden'])) {
            switch ($filtros['orden']) {
                case 'precio_asc':
                    $orderBy = "ORDER BY p.precio ASC";
                    break;
                case 'precio_desc':
                    $orderBy = "ORDER BY p.precio DESC";
                    break;
                case 'populares':
                    $orderBy = "ORDER BY p.vistas DESC";
                    break;
                case 'recientes':
                    $orderBy = "ORDER BY p.fecha_creacion DESC";
                    break;
            }
        }

        // Contar total
        $queryCount = "SELECT COUNT(*) as total FROM {$this->tabla} p $whereClause";
        $totalResult = $this->db->fetchOne($queryCount, $params);
        $total = (int)($totalResult['total'] ?? 0);

        // Calcular paginación
        $offset = ($pagina - 1) * $porPagina;
        $totalPaginas = $total > 0 ? ceil($total / $porPagina) : 0;

        // Obtener productos
        $query = "
            SELECT 
                p.id_producto,
                p.nombre,
                p.nombre as titulo,
                p.descripcion,
                p.descripcion_corta,
                p.precio,
                p.moneda,
                p.estado,
                p.destacado,
                p.vistas,
                p.vistas as total_vistas,
                p.fecha_creacion,
                p.fecha_publicacion,
                p.imagenes,
                c.nombre AS categoria,
                c.nombre AS categoria_nombre,
                u.nombre AS vendedor_nombre,
                u.nombre AS nombre_usuario,
                u.email AS email_usuario,
                pe.nombre_empresa
            FROM {$this->tabla} p
            LEFT JOIN categorias_productos c ON p.id_categoria_producto = c.id_categoria
            LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
            LEFT JOIN perfiles_empresariales pe ON p.id_perfil = pe.id_perfil
            $whereClause
            $orderBy
            LIMIT $porPagina OFFSET $offset
        ";

        $productos = $this->db->fetchAll($query, $params);
        
        // Procesar productos para extraer imagen principal del JSON
        foreach ($productos as &$producto) {
            if (!empty($producto['imagenes'])) {
                $imagenes = json_decode($producto['imagenes'], true);
                if (is_array($imagenes) && count($imagenes) > 0) {
                    $producto['imagen_principal'] = $imagenes[0];
                }
            }
            $producto['imagen_principal'] = $producto['imagen_principal'] ?? null;
        }

        return [
            'productos' => $productos,
            'total' => $total,
            'pagina_actual' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => $totalPaginas
        ];
    }
    
    /**
     * Búsqueda para esquema de desarrollo (productos)
     */
    private function buscarDesarrollo($filtros = [], $pagina = 1, $porPagina = 20) {
        $where = ["p.estado = 'publicado'"];
        $params = [];

        // Filtro por categoría
        if (!empty($filtros['categoria'])) {
            $where[] = "p.id_categoria = ?";
            $params[] = $filtros['categoria'];
        }

        // Filtro por tipo de producto
        if (!empty($filtros['tipo'])) {
            $where[] = "p.tipo_producto = ?";
            $params[] = $filtros['tipo'];
        }

        // Filtro por rango de precio
        if (!empty($filtros['precio_min'])) {
            $where[] = "p.precio >= ?";
            $params[] = $filtros['precio_min'];
        }
        if (!empty($filtros['precio_max'])) {
            $where[] = "p.precio <= ?";
            $params[] = $filtros['precio_max'];
        }

        // Filtro por ubicación
        if (!empty($filtros['estado'])) {
            $where[] = "p.ubicacion_estado = ?";
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['ciudad'])) {
            $where[] = "p.ubicacion_ciudad = ?";
            $params[] = $filtros['ciudad'];
        }

        // Búsqueda por texto (FULLTEXT)
        if (!empty($filtros['q'])) {
            $where[] = "MATCH(p.titulo, p.descripcion_corta, p.descripcion_completa) AGAINST(? IN NATURAL LANGUAGE MODE)";
            $params[] = $filtros['q'];
        }

        // Solo productos destacados
        if (!empty($filtros['destacados'])) {
            $where[] = "p.destacado = TRUE";
        }

        // Vendedor específico
        if (!empty($filtros['vendedor'])) {
            $where[] = "p.id_usuario = ?";
            $params[] = $filtros['vendedor'];
        }

        $whereClause = "WHERE " . implode(" AND ", $where);

        // Orden
        $orderBy = "ORDER BY p.fecha_publicacion DESC";
        if (!empty($filtros['orden'])) {
            switch ($filtros['orden']) {
                case 'precio_asc':
                    $orderBy = "ORDER BY p.precio ASC";
                    break;
                case 'precio_desc':
                    $orderBy = "ORDER BY p.precio DESC";
                    break;
                case 'populares':
                    $orderBy = "ORDER BY p.total_vistas DESC";
                    break;
                case 'recientes':
                    $orderBy = "ORDER BY p.fecha_publicacion DESC";
                    break;
            }
        }

        // Contar total
        $queryCount = "SELECT COUNT(*) as total FROM productos p $whereClause";
        $totalResult = $this->db->fetchOne($queryCount, $params);
        $total = $totalResult['total'];

        // Calcular paginación
        $offset = ($pagina - 1) * $porPagina;
        $totalPaginas = ceil($total / $porPagina);

        // Obtener productos
        $query = "
            SELECT 
                p.id_producto,
                p.titulo,
                p.slug,
                p.descripcion_corta,
                p.tipo_producto,
                p.precio,
                p.moneda,
                p.precio_anterior,
                p.destacado,
                p.total_vistas,
                p.total_favoritos,
                p.ubicacion_ciudad,
                p.ubicacion_estado,
                p.fecha_publicacion,
                c.nombre AS categoria_nombre,
                c.slug AS categoria_slug,
                c.color_hex AS categoria_color,
                u.nombre AS vendedor_nombre,
                pe.nombre_empresa,
                (SELECT url_imagen FROM imagenes_productos 
                 WHERE id_producto = p.id_producto AND es_principal = TRUE 
                 LIMIT 1) AS imagen_principal
            FROM productos p
            INNER JOIN categorias_productos c ON p.id_categoria = c.id_categoria
            INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
            LEFT JOIN perfiles_empresariales pe ON p.id_perfil_empresarial = pe.id_perfil
            $whereClause
            $orderBy
            LIMIT $porPagina OFFSET $offset
        ";

        $productos = $this->db->fetchAll($query, $params);

        return [
            'productos' => $productos,
            'total' => $total,
            'pagina_actual' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => $totalPaginas
        ];
    }

    /**
     * Obtener productos del vendedor
     * 
     * @param int $idUsuario
     * @param string|null $estado Filtrar por estado
     * @return array Lista de productos
     */
    public function getMisProductos($idUsuario, $estado = null) {
        $where = "WHERE p.id_usuario = ?";
        $params = [$idUsuario];

        if ($estado) {
            $where .= " AND p.estado = ?";
            $params[] = $estado;
        }

        $query = "
            SELECT 
                p.id_producto,
                p.titulo,
                p.slug,
                p.tipo_producto,
                p.precio,
                p.moneda,
                p.estado,
                p.destacado,
                p.total_vistas,
                p.total_contactos,
                p.total_favoritos,
                p.fecha_publicacion,
                p.fecha_creacion,
                c.nombre AS categoria_nombre,
                (SELECT url_imagen FROM imagenes_productos 
                 WHERE id_producto = p.id_producto AND es_principal = TRUE 
                 LIMIT 1) AS imagen_principal,
                (SELECT COUNT(*) FROM imagenes_productos 
                 WHERE id_producto = p.id_producto) AS total_imagenes
            FROM productos p
            INNER JOIN categorias_productos c ON p.id_categoria = c.id_categoria
            $where
            ORDER BY p.fecha_creacion DESC
        ";

        return $this->db->fetchAll($query, $params);
    }

    /**
     * Cambiar estado del producto
     * 
     * @param int $idProducto
     * @param string $nuevoEstado
     * @param int $idUsuario Para verificar permisos (admin puede cambiar cualquiera)
     * @return bool
     */
    public function cambiarEstado($idProducto, $nuevoEstado, $idUsuario) {
        $producto = $this->getById($idProducto);
        
        if (!$producto) {
            throw new Exception("Producto no encontrado");
        }
        
        // Verificar permisos: dueño del producto o admin
        $user = AuthMiddleware::getCurrentUser();
        $esAdmin = $user && ($user['tipo_usuario'] === 'administrador' || $user['tipo_usuario'] === 'admin');
        
        if ($producto['id_usuario'] != $idUsuario && !$esAdmin) {
            throw new Exception("No tienes permiso para modificar este producto");
        }

        // Estados válidos según esquema
        $estadosValidos = $this->esEsquemaProduccion 
            ? ['borrador', 'publicado', 'pausado', 'agotado', 'pendiente', 'rechazado']
            : ['borrador', 'publicado', 'pausado', 'agotado', 'archivado'];
            
        if (!in_array($nuevoEstado, $estadosValidos)) {
            throw new Exception("Estado no válido");
        }

        $query = "UPDATE {$this->tabla} SET estado = ? WHERE id_producto = ?";
        $resultado = $this->db->query($query, [$nuevoEstado, $idProducto]);

        // Si se publica por primera vez
        if ($nuevoEstado === 'publicado') {
            $fechaCol = $this->esEsquemaProduccion ? 'fecha_publicacion' : 'fecha_publicacion';
            $this->db->query(
                "UPDATE {$this->tabla} SET $fechaCol = NOW() WHERE id_producto = ? AND $fechaCol IS NULL",
                [$idProducto]
            );
        }

        if ($resultado) {
            $titulo = $producto['titulo'] ?? $producto['nombre'] ?? 'Producto #' . $idProducto;
            Logger::activity($idUsuario, "Producto cambiado a $nuevoEstado: $titulo");
        }

        return $resultado;
    }

    /**
     * Gestión de imágenes del producto
     */

    /**
     * Agregar imagen al producto
     * 
     * @param int $idProducto
     * @param string $urlImagen
     * @param array $opciones Thumbnail, alt_text, orden, etc
     * @return int ID de la imagen creada
     */
    public function agregarImagen($idProducto, $urlImagen, $opciones = []) {
        // Si no hay imágenes, esta será la principal
        $queryCheck = "SELECT COUNT(*) as count FROM imagenes_productos WHERE id_producto = ?";
        $result = $this->db->fetchOne($queryCheck, [$idProducto]);
        $esPrimera = $result['count'] == 0;

        $query = "
            INSERT INTO imagenes_productos 
                (id_producto, url_imagen, url_thumbnail, alt_text, orden, es_principal)
            VALUES (?, ?, ?, ?, ?, ?)
        ";

        $params = [
            $idProducto,
            $urlImagen,
            $opciones['url_thumbnail'] ?? null,
            $opciones['alt_text'] ?? null,
            $opciones['orden'] ?? 0,
            $esPrimera ? true : ($opciones['es_principal'] ?? false)
        ];

        return $this->db->insert($query, $params);
    }

    /**
     * Obtener imágenes del producto
     * 
     * @param int $idProducto
     * @return array Lista de imágenes
     */
    public function getImagenes($idProducto) {
        $query = "
            SELECT 
                id_imagen,
                url_imagen,
                url_thumbnail,
                alt_text,
                orden,
                es_principal,
                fecha_subida
            FROM imagenes_productos
            WHERE id_producto = ?
            ORDER BY es_principal DESC, orden ASC, id_imagen ASC
        ";

        return $this->db->fetchAll($query, [$idProducto]);
    }

    /**
     * Eliminar imagen
     * 
     * @param int $idImagen
     * @param int $idUsuario Para verificar permisos
     * @return bool
     */
    public function eliminarImagen($idImagen, $idUsuario) {
        // Verificar permisos
        $query = "
            SELECT p.id_usuario, p.id_producto
            FROM imagenes_productos i
            INNER JOIN productos p ON i.id_producto = p.id_producto
            WHERE i.id_imagen = ?
        ";
        $result = $this->db->fetchOne($query, [$idImagen]);

        if (!$result || $result['id_usuario'] != $idUsuario) {
            throw new Exception("No tienes permiso para eliminar esta imagen");
        }

        $queryDelete = "DELETE FROM imagenes_productos WHERE id_imagen = ?";
        return $this->db->query($queryDelete, [$idImagen]);
    }

    /**
     * Establecer imagen principal
     * 
     * @param int $idImagen
     * @param int $idUsuario
     * @return bool
     */
    public function establecerImagenPrincipal($idImagen, $idUsuario) {
        // Verificar permisos y obtener ID del producto
        $query = "
            SELECT p.id_usuario, p.id_producto
            FROM imagenes_productos i
            INNER JOIN productos p ON i.id_producto = p.id_producto
            WHERE i.id_imagen = ?
        ";
        $result = $this->db->fetchOne($query, [$idImagen]);

        if (!$result || $result['id_usuario'] != $idUsuario) {
            throw new Exception("No tienes permiso para modificar esta imagen");
        }

        // Quitar principal de todas
        $this->db->query(
            "UPDATE imagenes_productos SET es_principal = FALSE WHERE id_producto = ?",
            [$result['id_producto']]
        );

        // Establecer nueva principal
        return $this->db->query(
            "UPDATE imagenes_productos SET es_principal = TRUE WHERE id_imagen = ?",
            [$idImagen]
        );
    }

    /**
     * Sistema de favoritos
     */

    /**
     * Agregar/quitar de favoritos
     * 
     * @param int $idProducto
     * @param int $idUsuario
     * @return array ['accion' => 'agregado'|'eliminado', 'total' => int]
     */
    public function toggleFavorito($idProducto, $idUsuario) {
        $queryCheck = "
            SELECT id_favorito 
            FROM productos_favoritos 
            WHERE id_producto = ? AND id_usuario = ?
        ";
        $existe = $this->db->fetchOne($queryCheck, [$idProducto, $idUsuario]);

        if ($existe) {
            // Eliminar
            $this->db->query(
                "DELETE FROM productos_favoritos WHERE id_producto = ? AND id_usuario = ?",
                [$idProducto, $idUsuario]
            );
            $accion = 'eliminado';
        } else {
            // Agregar
            $this->db->insert(
                "INSERT INTO productos_favoritos (id_producto, id_usuario) VALUES (?, ?)",
                [$idProducto, $idUsuario]
            );
            $accion = 'agregado';
        }

        // Obtener nuevo total
        $queryTotal = "SELECT total_favoritos FROM productos WHERE id_producto = ?";
        $result = $this->db->fetchOne($queryTotal, [$idProducto]);

        return [
            'accion' => $accion,
            'total' => $result['total_favoritos']
        ];
    }

    /**
     * Obtener productos favoritos del usuario
     * 
     * @param int $idUsuario
     * @return array Lista de productos
     */
    public function getFavoritos($idUsuario) {
        $query = "
            SELECT 
                p.id_producto,
                p.titulo,
                p.slug,
                p.descripcion_corta,
                p.precio,
                p.moneda,
                p.estado,
                c.nombre AS categoria_nombre,
                u.nombre AS vendedor_nombre,
                (SELECT url_imagen FROM imagenes_productos 
                 WHERE id_producto = p.id_producto AND es_principal = TRUE 
                 LIMIT 1) AS imagen_principal,
                f.fecha_agregado
            FROM productos_favoritos f
            INNER JOIN productos p ON f.id_producto = p.id_producto
            INNER JOIN categorias_productos c ON p.id_categoria = c.id_categoria
            INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
            WHERE f.id_usuario = ? AND p.estado = 'publicado'
            ORDER BY f.fecha_agregado DESC
        ";

        return $this->db->fetchAll($query, [$idUsuario]);
    }

    /**
     * Registrar interacción
     * 
     * @param int $idProducto
     * @param string $tipo 'vista', 'contacto', etc
     * @param int|null $idUsuario
     * @param array $metadata Información adicional
     * @return bool
     */
    public function registrarInteraccion($idProducto, $tipo, $idUsuario = null, $metadata = []) {
        $query = "
            INSERT INTO interacciones_productos 
                (id_producto, id_usuario, tipo_interaccion, metadata, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ";

        $params = [
            $idProducto,
            $idUsuario,
            $tipo,
            !empty($metadata) ? json_encode($metadata) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        return $this->db->insert($query, $params) > 0;
    }

    /**
     * Obtener estadísticas del vendedor
     * 
     * @param int $idUsuario
     * @return array Estadísticas generales
     */
    public function getEstadisticasVendedor($idUsuario) {
        $query = "
            SELECT 
                COUNT(*) as total_productos,
                SUM(CASE WHEN estado = 'publicado' THEN 1 ELSE 0 END) as productos_publicados,
                SUM(total_vistas) as total_vistas,
                SUM(total_contactos) as total_contactos,
                SUM(total_favoritos) as total_favoritos
            FROM productos
            WHERE id_usuario = ?
        ";

        return $this->db->fetchOne($query, [$idUsuario]);
    }

    /**
     * Generar slug único
     * 
     * @param string $texto
     * @return string
     */
    private function generarSlug($texto) {
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

        $slugOriginal = $slug;
        $contador = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $slugOriginal . '-' . $contador;
            $contador++;
        }

        return $slug;
    }

    /**
     * Verificar si slug existe
     * 
     * @param string $slug
     * @return bool
     */
    private function slugExists($slug) {
        $query = "SELECT COUNT(*) as count FROM productos WHERE slug = ?";
        $result = $this->db->fetchOne($query, [$slug]);
        return $result['count'] > 0;
    }
}
