<?php

/**
 * Controlador de Productos
 * 
 * Gestiona las operaciones del marketplace de productos
 * Endpoints: CRUD productos, categorías, favoritos, interacciones
 * 
 * @package Controllers
 * @author Nenis y Bros
 * @version 1.0.0
 */

class ProductoController {
    private $productoModel;
    private $categoriaModel;

    public function __construct() {
        $this->productoModel = new Producto();
        $this->categoriaModel = new CategoriaProducto();
    }

    // ========================================================================
    // CATEGORÍAS
    // ========================================================================

    /**
     * GET /productos/categorias
     * Obtener todas las categorías con estadísticas
     */
    public function getCategorias() {
        try {
            $categorias = $this->categoriaModel->getConEstadisticas();
            Response::success($categorias);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * POST /productos/categorias
     * Crear nueva categoría (solo admin)
     */
    public function crearCategoria() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validar
            $validator = new Validator($data, [
                'nombre' => 'required|min:3|max:100'
            ]);

            if (!$validator->validate()) {
                Response::validationError($validator->getErrors());
                return;
            }

            $idCategoria = $this->categoriaModel->crear($data);

            Response::success([
                'id_categoria' => $idCategoria,
                'mensaje' => 'Categoría creada exitosamente'
            ], 201);

        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    // ========================================================================
    // PRODUCTOS - CRUD
    // ========================================================================

    /**
     * GET /productos
     * Buscar y filtrar productos públicos
     */
    public function buscarProductos() {
        try {
            $filtros = [
                'q' => $_GET['q'] ?? null,
                'categoria' => $_GET['categoria'] ?? null,
                'tipo' => $_GET['tipo'] ?? null,
                'precio_min' => $_GET['precio_min'] ?? null,
                'precio_max' => $_GET['precio_max'] ?? null,
                'estado' => $_GET['estado'] ?? null,
                'ciudad' => $_GET['ciudad'] ?? null,
                'destacados' => isset($_GET['destacados']),
                'orden' => $_GET['orden'] ?? 'recientes'
            ];

            $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
            $porPagina = isset($_GET['por_pagina']) ? max(1, min(50, (int)$_GET['por_pagina'])) : 20;

            $resultado = $this->productoModel->buscar($filtros, $pagina, $porPagina);

            Response::success($resultado);

        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /productos/{id}
     * Obtener producto por ID con información completa
     */
    public function getProducto($idProducto) {
        try {
            $user = AuthMiddleware::getCurrentUser();
            $idUsuario = $user ? $user['id_usuario'] : null;
            
            $producto = $this->productoModel->getById($idProducto, $idUsuario);

            if (!$producto) {
                Response::notFound('Producto no encontrado');
                return;
            }

            // Registrar vista (solo si está publicado) - ignorar errores
            if ($producto['estado'] === 'publicado') {
                try {
                    $this->productoModel->registrarInteraccion(
                        $idProducto,
                        'vista',
                        $idUsuario
                    );
                } catch (Exception $e) {
                    // Ignorar errores de registro de interacción
                    // La tabla puede no existir en algunos entornos
                }
            }

            Response::success($producto);

        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /productos/slug/{slug}
     * Obtener producto por slug
     */
    public function getProductoPorSlug($slug) {
        try {
            $user = AuthMiddleware::getCurrentUser();
            $idUsuario = $user ? $user['id_usuario'] : null;
            
            $producto = $this->productoModel->getBySlug($slug, $idUsuario);

            if (!$producto) {
                Response::notFound('Producto no encontrado');
                return;
            }

            // Registrar vista - ignorar errores
            if ($producto['estado'] === 'publicado') {
                try {
                    $this->productoModel->registrarInteraccion(
                        $producto['id_producto'],
                        'vista',
                        $idUsuario
                    );
                } catch (Exception $e) {
                    // Ignorar errores de registro de interacción
                }
            }

            Response::success($producto);

        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * POST /productos
     * Crear nuevo producto (requiere autenticación)
     */
    public function crearProducto() {
        try {
            // Debug: Verificar que el body raw existe
            global $_RAW_BODY;
            if (!isset($_RAW_BODY)) {
                $_RAW_BODY = '';
            }
            
            $user = AuthMiddleware::requireAuth();
            $idUsuario = $user['id_usuario'];
            
            // Obtener datos del body y asegurar codificación UTF-8
            $rawInput = $_RAW_BODY ?: file_get_contents('php://input');
            
            // Convertir a UTF-8 si es necesario
            if (!mb_check_encoding($rawInput, 'UTF-8')) {
                $rawInput = mb_convert_encoding($rawInput, 'UTF-8', 'auto');
            }
            
            $data = json_decode($rawInput, true);
            
            // Si json_decode falla, intentar limpiar el string
            if (json_last_error() !== JSON_ERROR_NONE) {
                $rawInput = utf8_encode($rawInput);
                $data = json_decode($rawInput, true);
            }
            
            $data = $data ?: [];

            // Validar
            $validator = new Validator($data, [
                'titulo' => 'required|min:5|max:200',
                'id_categoria' => 'required|integer',
                'tipo_producto' => 'required',
                'precio' => 'required|numeric|min:0'
            ]);

            if (!$validator->validate()) {
                Response::validationError($validator->getErrors());
                return;
            }

            $idProducto = $this->productoModel->crear($data, $idUsuario);

            // Registrar puntos (si está publicado)
            if (($data['estado'] ?? 'borrador') === 'publicado') {
                $puntosModel = new PuntosUsuario();
                $puntosModel->otorgarPuntos($idUsuario, 'publicar_producto', 'producto', $idProducto);
            }

            Response::success([
                'id_producto' => $idProducto,
                'mensaje' => 'Producto creado exitosamente'
            ], 201);

        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * PUT /productos/{id}
     * Actualizar producto existente
     */
    public function actualizarProducto($idProducto) {
        try {
            $user = AuthMiddleware::requireAuth();
            $idUsuario = $user['id_usuario'];
            $data = json_decode(file_get_contents('php://input'), true);

            // Validar campos si están presentes
            $reglas = [];
            if (isset($data['titulo'])) {
                $reglas['titulo'] = 'min:5|max:200';
            }
            if (isset($data['precio'])) {
                $reglas['precio'] = 'numeric|min:0';
            }

            if (!empty($reglas)) {
                $validator = new Validator($data, $reglas);
                if (!$validator->validate()) {
                    Response::validationError($validator->getErrors());
                    return;
                }
            }

            $resultado = $this->productoModel->actualizar($idProducto, $data, $idUsuario);

            if ($resultado) {
                Response::success(['mensaje' => 'Producto actualizado exitosamente']);
            } else {
                Response::error('No se pudo actualizar el producto');
            }

        } catch (Exception $e) {
            Response::error($e->getMessage(), 403);
        }
    }

    /**
     * DELETE /productos/{id}
     * Eliminar producto
     */
    public function eliminarProducto($idProducto) {
        try {
            $user = AuthMiddleware::requireAuth();
            $idUsuario = $user['id_usuario'];
            $resultado = $this->productoModel->eliminar($idProducto, $idUsuario);

            if ($resultado) {
                Response::success(['mensaje' => 'Producto eliminado exitosamente']);
            } else {
                Response::error('No se pudo eliminar el producto');
            }

        } catch (Exception $e) {
            Response::error($e->getMessage(), 403);
        }
    }

    /**
     * PATCH /productos/{id}/estado
     * Cambiar estado del producto
     */
    public function cambiarEstado($idProducto) {
        try {
            $user = AuthMiddleware::requireAuth();
            
            if (!$user) {
                Response::unauthorized('Sesión no válida');
                return;
            }
            
            $idUsuario = $user['id_usuario'];
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['estado'])) {
                Response::validationError(['estado' => ['El estado es requerido']]);
                return;
            }

            $resultado = $this->productoModel->cambiarEstado(
                $idProducto,
                $data['estado'],
                $idUsuario
            );

            if ($resultado) {
                // Registrar puntos si se publica - ignorar errores
                if ($data['estado'] === 'publicado') {
                    try {
                        $puntosModel = new PuntosUsuario();
                        $puntosModel->otorgarPuntos($idUsuario, 'publicar_producto', 'producto', $idProducto);
                    } catch (Exception $e) {
                        // Ignorar error de puntos
                    }
                }

                Response::success(['mensaje' => 'Estado actualizado exitosamente']);
            } else {
                Response::error('No se pudo cambiar el estado');
            }

        } catch (Exception $e) {
            Logger::error("Error cambiando estado producto $idProducto: " . $e->getMessage());
            Response::error($e->getMessage(), 400);
        }
    }

    // ========================================================================
    // MIS PRODUCTOS
    // ========================================================================

    /**
     * GET /productos/mis-productos
     * Obtener productos del vendedor autenticado
     */
    public function getMisProductos() {
        try {
            $user = AuthMiddleware::requireAuth();
            $idUsuario = $user['id_usuario'];
            $estado = $_GET['estado'] ?? null;

            $productos = $this->productoModel->getMisProductos($idUsuario, $estado);

            Response::success([
                'productos' => $productos,
                'total' => count($productos)
            ]);

        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /productos/estadisticas-vendedor
     * Obtener estadísticas del vendedor
     */
    public function getEstadisticasVendedor() {
        try {
            $user = AuthMiddleware::requireAuth();
            $idUsuario = $user['id_usuario'];
            $stats = $this->productoModel->getEstadisticasVendedor($idUsuario);

            Response::success($stats);

        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    // ========================================================================
    // IMÁGENES
    // ========================================================================

    /**
     * POST /productos/{id}/imagenes
     * Agregar imagen al producto
     */
    public function agregarImagen($idProducto) {
        try {
            $user = AuthMiddleware::requireAuth();
            $idUsuario = $user['id_usuario'];

            // Verificar que el producto existe y pertenece al usuario
            $producto = $this->productoModel->getById($idProducto);
            if (!$producto || $producto['id_usuario'] != $idUsuario) {
                Response::error('No tienes permiso para modificar este producto', 403);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['url_imagen'])) {
                Response::validationError(['url_imagen' => ['La URL de la imagen es requerida']]);
                return;
            }

            $idImagen = $this->productoModel->agregarImagen($idProducto, $data['url_imagen'], $data);

            Response::success([
                'id_imagen' => $idImagen,
                'mensaje' => 'Imagen agregada exitosamente'
            ], 201);

        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * DELETE /productos/imagenes/{id}
     * Eliminar imagen
     */
    public function eliminarImagen($idImagen) {
        try {
            $user = AuthMiddleware::requireAuth();
            $idUsuario = $user['id_usuario'];
            $resultado = $this->productoModel->eliminarImagen($idImagen, $idUsuario);

            if ($resultado) {
                Response::success(['mensaje' => 'Imagen eliminada exitosamente']);
            } else {
                Response::error('No se pudo eliminar la imagen');
            }

        } catch (Exception $e) {
            Response::error($e->getMessage(), 403);
        }
    }

    /**
     * PATCH /productos/imagenes/{id}/principal
     * Establecer imagen como principal
     */
    public function establecerImagenPrincipal($idImagen) {
        try {
            $user = AuthMiddleware::requireAuth();
            $idUsuario = $user['id_usuario'];
            $resultado = $this->productoModel->establecerImagenPrincipal($idImagen, $idUsuario);

            if ($resultado) {
                Response::success(['mensaje' => 'Imagen principal actualizada']);
            } else {
                Response::error('No se pudo actualizar la imagen principal');
            }

        } catch (Exception $e) {
            Response::error($e->getMessage(), 403);
        }
    }

    // ========================================================================
    // FAVORITOS
    // ========================================================================

    /**
     * POST /productos/{id}/favorito
     * Agregar/quitar de favoritos
     */
    public function toggleFavorito($idProducto) {
        try {
            $user = AuthMiddleware::requireAuth();
            $idUsuario = $user['id_usuario'];
            $resultado = $this->productoModel->toggleFavorito($idProducto, $idUsuario);

            Response::success($resultado);

        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /productos/favoritos
     * Obtener productos favoritos del usuario
     */
    public function getFavoritos() {
        try {
            $user = AuthMiddleware::requireAuth();
            $idUsuario = $user['id_usuario'];
            $favoritos = $this->productoModel->getFavoritos($idUsuario);

            Response::success([
                'favoritos' => $favoritos,
                'total' => count($favoritos)
            ]);

        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    // ========================================================================
    // INTERACCIONES
    // ========================================================================

    /**
     * POST /productos/{id}/contacto
     * Registrar contacto con el vendedor
     */
    public function registrarContacto($idProducto) {
        try {
            $user = AuthMiddleware::getCurrentUser();
            $idUsuario = $user ? $user['id_usuario'] : null;
            $data = json_decode(file_get_contents('php://input'), true);

            $tipoContacto = $data['tipo'] ?? 'contacto';
            $metadata = $data['metadata'] ?? [];

            $this->productoModel->registrarInteraccion(
                $idProducto,
                $tipoContacto,
                $idUsuario,
                $metadata
            );

            // Registrar puntos para el vendedor
            $producto = $this->productoModel->getById($idProducto);
            if ($producto && $idUsuario != $producto['id_usuario']) {
                $puntosModel = new PuntosUsuario();
                $puntosModel->otorgarPuntos($producto['id_usuario'], 'recibir_contacto', 'producto', $idProducto);
            }

            Response::success(['mensaje' => 'Contacto registrado exitosamente']);

        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
