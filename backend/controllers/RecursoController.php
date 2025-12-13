<?php

/**
 * RecursoController
 * 
 * Controlador para gestionar recursos descargables, categorías,
 * búsqueda, descargas, calificaciones y estadísticas
 */
class RecursoController {
    private $recursoModel;
    private $categoriaModel;
    
    public function __construct() {
        $this->recursoModel = new Recurso();
        $this->categoriaModel = new CategoriaRecurso();
    }
    
    // =========================================================================
    // CATEGORÍAS
    // =========================================================================
    
    /**
     * GET /api/v1/recursos/categorias
     * Listar todas las categorías
     */
    public function listarCategorias() {
        try {
            $includeInactivas = isset($_GET['incluir_inactivas']) && $_GET['incluir_inactivas'] === 'true';
            $conEstadisticas = isset($_GET['con_estadisticas']) && $_GET['con_estadisticas'] === 'true';
            
            if ($conEstadisticas) {
                $categorias = $this->categoriaModel->getWithStats();
            } else {
                $categorias = $this->categoriaModel->getAll($includeInactivas);
            }
            
            Response::success($categorias, 'Categorías obtenidas exitosamente');
        } catch (Exception $e) {
            Logger::error('Error al listar categorías: ' . $e->getMessage());
            Response::error('Error al obtener categorías', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/categorias/{id}
     * Obtener categoría por ID
     */
    public function obtenerCategoria($id) {
        try {
            $categoria = $this->categoriaModel->getById($id);
            
            if (!$categoria) {
                Response::error('Categoría no encontrada', 404);
            }
            
            Response::success($categoria);
        } catch (Exception $e) {
            Logger::error('Error al obtener categoría: ' . $e->getMessage());
            Response::error('Error al obtener categoría', 500);
        }
    }
    
    /**
     * POST /api/v1/recursos/categorias
     * Crear nueva categoría (solo admin)
     */
    public function crearCategoria() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para crear categorías', 403);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $rules = [
                'nombre' => 'required|min:3|max:100',
                'slug' => 'required|min:3|max:120',
                'descripcion' => 'max:1000'
            ];
            
            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            $idCategoria = $this->categoriaModel->create($data);
            
            if (!$idCategoria) {
                Response::error('Error al crear categoría', 500);
            }
            
            Logger::activity($usuario['id_usuario'], 'crear_categoria', $idCategoria);
            Response::success(['id_categoria' => $idCategoria], 'Categoría creada exitosamente', 201);
            
        } catch (Exception $e) {
            Logger::error('Error al crear categoría: ' . $e->getMessage());
            Response::error('Error al crear categoría', 500);
        }
    }
    
    /**
     * PUT /api/v1/recursos/categorias/{id}
     * Actualizar categoría (solo admin)
     */
    public function actualizarCategoria($id) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para actualizar categorías', 403);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $success = $this->categoriaModel->update($id, $data);
            
            if (!$success) {
                Response::error('Error al actualizar categoría', 500);
            }
            
            Logger::activity($usuario['id_usuario'], 'actualizar_categoria', $id);
            Response::success(null, 'Categoría actualizada exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al actualizar categoría: ' . $e->getMessage());
            Response::error('Error al actualizar categoría', 500);
        }
    }
    
    /**
     * DELETE /api/v1/recursos/categorias/{id}
     * Eliminar categoría (solo admin, solo si no tiene recursos)
     */
    public function eliminarCategoria($id) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para eliminar categorías', 403);
            }
            
            $success = $this->categoriaModel->delete($id);
            
            if (!$success) {
                Response::error('No se puede eliminar la categoría. Puede tener recursos asociados.', 400);
            }
            
            Logger::activity($usuario['id_usuario'], 'eliminar_categoria', $id);
            Response::success(null, 'Categoría eliminada exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al eliminar categoría: ' . $e->getMessage());
            Response::error('Error al eliminar categoría', 500);
        }
    }
    
    // =========================================================================
    // RECURSOS
    // =========================================================================
    
    /**
     * GET /api/v1/recursos
     * Listar recursos con filtros y paginación
     */
    public function listarRecursos() {
        try {
            $filters = [
                'categoria' => $_GET['categoria'] ?? null,
                'tipo_recurso' => $_GET['tipo_recurso'] ?? null,
                'tipo_acceso' => $_GET['tipo_acceso'] ?? null,
                'nivel' => $_GET['nivel'] ?? null,
                'destacado' => $_GET['destacado'] ?? null,
                'etiqueta' => $_GET['etiqueta'] ?? null,
                'buscar' => $_GET['buscar'] ?? null,
                'orden' => $_GET['orden'] ?? 'recientes'
            ];
            
            $page = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
            $limit = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
            
            $resultado = $this->recursoModel->getAll($filters, $page, $limit);
            
            Response::success($resultado);
        } catch (Exception $e) {
            Logger::error('Error al listar recursos: ' . $e->getMessage());
            Response::error('Error al obtener recursos', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/destacados
     * Obtener recursos destacados
     */
    public function listarDestacados() {
        try {
            $limit = isset($_GET['limite']) ? (int)$_GET['limite'] : 6;
            
            $resultado = $this->recursoModel->getAll(['destacado' => true], 1, $limit);
            
            Response::success($resultado['recursos']);
        } catch (Exception $e) {
            Logger::error('Error al listar recursos destacados: ' . $e->getMessage());
            Response::error('Error al obtener recursos destacados', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/{id}
     * Obtener recurso por ID
     */
    public function obtenerRecurso($id) {
        try {
            // Obtener usuario si está autenticado (opcional)
            $idUsuario = null;
            try {
                $usuario = AuthMiddleware::authenticate();
                $idUsuario = $usuario['id_usuario'];
            } catch (Exception $e) {
                // Usuario no autenticado, continuar sin ID
            }
            
            $recurso = $this->recursoModel->getById($id, true, $idUsuario);
            
            if (!$recurso) {
                Response::error('Recurso no encontrado', 404);
            }
            
            // Si usuario está autenticado, verificar si ya lo descargó
            if ($idUsuario) {
                $recurso['ya_descargado'] = $this->recursoModel->yaDescargo($id, $idUsuario);
            }
            
            Response::success($recurso);
        } catch (Exception $e) {
            Logger::error('Error al obtener recurso: ' . $e->getMessage());
            Response::error('Error al obtener recurso', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/slug/{slug}
     * Obtener recurso por slug
     */
    public function obtenerRecursoPorSlug($slug) {
        try {
            // Obtener usuario si está autenticado (opcional)
            $idUsuario = null;
            try {
                $usuario = AuthMiddleware::authenticate();
                $idUsuario = $usuario['id_usuario'];
            } catch (Exception $e) {
                // Usuario no autenticado
            }
            
            $recurso = $this->recursoModel->getBySlug($slug, true, $idUsuario);
            
            if (!$recurso) {
                Response::error('Recurso no encontrado', 404);
            }
            
            // Si usuario está autenticado, verificar si ya lo descargó
            if ($idUsuario) {
                $recurso['ya_descargado'] = $this->recursoModel->yaDescargo($recurso['id_recurso'], $idUsuario);
            }
            
            Response::success($recurso);
        } catch (Exception $e) {
            Logger::error('Error al obtener recurso: ' . $e->getMessage());
            Response::error('Error al obtener recurso', 500);
        }
    }
    
    /**
     * POST /api/v1/recursos
     * Crear nuevo recurso (admin o instructor)
     */
    public function crearRecurso() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if (!in_array($usuario['rol'], ['administrador', 'instructor'])) {
                Response::error('No tienes permisos para crear recursos', 403);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $rules = [
                'id_categoria' => 'required|integer',
                'titulo' => 'required|min:5|max:255',
                'slug' => 'required|min:5|max:300',
                'descripcion' => 'required|min:20',
                'tipo_recurso' => 'required|in:articulo,ebook,plantilla,herramienta,video,infografia,podcast'
            ];
            
            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Asignar autor
            $data['id_autor'] = $usuario['id_usuario'];
            
            $idRecurso = $this->recursoModel->create($data);
            
            if (!$idRecurso) {
                Response::error('Error al crear recurso', 500);
            }
            
            // Otorgar puntos por publicar recurso si está publicado
            if (isset($data['estado']) && $data['estado'] === 'publicado') {
                $puntosQuery = "
                    INSERT INTO puntos_usuario (id_usuario, puntos_obtenidos, tipo_actividad, referencia_id)
                    VALUES (?, 50, 'publicar_recurso', ?)
                ";
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare($puntosQuery);
                $stmt->execute([$usuario['id_usuario'], $idRecurso]);
            }
            
            Logger::activity($usuario['id_usuario'], 'crear_recurso', $idRecurso);
            Response::success(['id_recurso' => $idRecurso], 'Recurso creado exitosamente', 201);
            
        } catch (Exception $e) {
            Logger::error('Error al crear recurso: ' . $e->getMessage());
            Response::error('Error al crear recurso', 500);
        }
    }
    
    /**
     * PUT /api/v1/recursos/{id}
     * Actualizar recurso
     */
    public function actualizarRecurso($id) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            // Obtener recurso para verificar permisos
            $recurso = $this->recursoModel->getById($id);
            if (!$recurso) {
                Response::error('Recurso no encontrado', 404);
            }
            
            // Solo admin o el autor pueden actualizar
            if ($usuario['rol'] !== 'administrador' && $recurso['id_autor'] != $usuario['id_usuario']) {
                Response::error('No tienes permisos para actualizar este recurso', 403);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Extraer descripción del cambio si se proporcionó
            $descripcionCambio = $data['descripcion_cambio'] ?? null;
            unset($data['descripcion_cambio']); // No es campo del recurso
            
            $success = $this->recursoModel->update($id, $data, $usuario['id_usuario'], $descripcionCambio);
            
            if (!$success) {
                Response::error('Error al actualizar recurso', 500);
            }
            
            Logger::activity($usuario['id_usuario'], 'actualizar_recurso', $id);
            Response::success(null, 'Recurso actualizado exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al actualizar recurso: ' . $e->getMessage());
            Response::error('Error al actualizar recurso', 500);
        }
    }
    
    /**
     * DELETE /api/v1/recursos/{id}
     * Eliminar recurso
     */
    public function eliminarRecurso($id) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            // Obtener recurso para verificar permisos
            $recurso = $this->recursoModel->getById($id);
            if (!$recurso) {
                Response::error('Recurso no encontrado', 404);
            }
            
            // Solo admin o el autor pueden eliminar
            if ($usuario['rol'] !== 'administrador' && $recurso['id_autor'] != $usuario['id_usuario']) {
                Response::error('No tienes permisos para eliminar este recurso', 403);
            }
            
            $success = $this->recursoModel->delete($id);
            
            if (!$success) {
                Response::error('Error al eliminar recurso', 500);
            }
            
            Logger::activity($usuario['id_usuario'], 'eliminar_recurso', $id);
            Response::success(null, 'Recurso eliminado exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al eliminar recurso: ' . $e->getMessage());
            Response::error('Error al eliminar recurso', 500);
        }
    }
    
    // =========================================================================
    // DESCARGAS
    // =========================================================================
    
    /**
     * POST /api/v1/recursos/{id}/descargar
     * Registrar descarga de recurso
     */
    public function descargarRecurso($id) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            $recurso = $this->recursoModel->getById($id);
            
            if (!$recurso) {
                Response::error('Recurso no encontrado', 404);
            }
            
            if ($recurso['estado'] !== 'publicado') {
                Response::error('Este recurso no está disponible para descarga', 400);
            }
            
            // Verificar acceso (premium, suscripción, etc.)
            if ($recurso['tipo_acceso'] === 'premium' && $usuario['rol'] === 'estudiante') {
                // Aquí podrías verificar si el usuario tiene acceso premium
                // Por ahora permitimos todas las descargas
            }
            
            // Registrar descarga (otorga puntos automáticamente vía stored procedure)
            $this->recursoModel->registrarDescarga($id, $usuario['id_usuario']);
            
            Logger::activity($usuario['id_usuario'], 'descargar_recurso', $id);
            
            // Retornar URL de descarga
            Response::success([
                'archivo_url' => $recurso['archivo_url'],
                'archivo_nombre' => $recurso['archivo_nombre'],
                'puntos_obtenidos' => 5
            ], 'Descarga registrada exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al descargar recurso: ' . $e->getMessage());
            Response::error('Error al procesar descarga', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/mis-descargas
     * Obtener recursos descargados por el usuario
     */
    public function misDescargas() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            $page = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
            $limit = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
            
            $resultado = $this->recursoModel->getDescargasUsuario($usuario['id_usuario'], $page, $limit);
            
            Response::success($resultado);
        } catch (Exception $e) {
            Logger::error('Error al obtener descargas: ' . $e->getMessage());
            Response::error('Error al obtener descargas', 500);
        }
    }
    
    // =========================================================================
    // CALIFICACIONES
    // =========================================================================
    
    /**
     * POST /api/v1/recursos/{id}/calificar
     * Calificar un recurso
     */
    public function calificarRecurso($id) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $rules = [
                'calificacion' => 'required|integer|min:1|max:5',
                'comentario' => 'max:500'
            ];
            
            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            $resultado = $this->recursoModel->calificar(
                $id,
                $usuario['id_usuario'],
                $data['calificacion'],
                $data['comentario'] ?? null
            );
            
            if (!$resultado['success']) {
                Response::error($resultado['message'], 400);
            }
            
            Logger::activity($usuario['id_usuario'], 'calificar_recurso', $id);
            Response::success(null, 'Calificación registrada exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al calificar recurso: ' . $e->getMessage());
            Response::error('Error al calificar recurso', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/{id}/calificaciones
     * Obtener calificaciones de un recurso
     */
    public function obtenerCalificaciones($id) {
        try {
            $limit = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
            
            $calificaciones = $this->recursoModel->getCalificaciones($id, $limit);
            
            Response::success($calificaciones);
        } catch (Exception $e) {
            Logger::error('Error al obtener calificaciones: ' . $e->getMessage());
            Response::error('Error al obtener calificaciones', 500);
        }
    }
    
    // =========================================================================
    // BÚSQUEDA
    // =========================================================================
    
    /**
     * GET /api/v1/recursos/buscar
     * Búsqueda avanzada de recursos
     */
    public function buscarRecursos() {
        try {
            $termino = $_GET['q'] ?? '';
            
            $filters = [
                'categoria' => $_GET['categoria'] ?? null,
                'tipo_recurso' => $_GET['tipo_recurso'] ?? null
            ];
            
            $page = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
            $limit = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
            
            $resultado = $this->recursoModel->buscarFullText($termino, $filters, $page, $limit);
            
            Response::success($resultado);
        } catch (Exception $e) {
            Logger::error('Error al buscar recursos: ' . $e->getMessage());
            Response::error('Error al buscar recursos', 500);
        }
    }
    
    // =========================================================================
    // RELACIONADOS Y RECOMENDACIONES
    // =========================================================================
    
    /**
     * GET /api/v1/recursos/{id}/relacionados
     * Obtener recursos relacionados
     */
    public function obtenerRelacionados($id) {
        try {
            $limit = isset($_GET['limite']) ? (int)$_GET['limite'] : 6;
            
            $relacionados = $this->recursoModel->getRelacionados($id, $limit);
            
            Response::success($relacionados);
        } catch (Exception $e) {
            Logger::error('Error al obtener relacionados: ' . $e->getMessage());
            Response::error('Error al obtener recursos relacionados', 500);
        }
    }
    
    // =========================================================================
    // ESTADÍSTICAS
    // =========================================================================
    
    /**
     * GET /api/v1/recursos/estadisticas
     * Obtener estadísticas globales de recursos
     */
    public function estadisticas() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver estadísticas', 403);
            }
            
            // Cachear estadísticas por 5 minutos (se actualizan con menos frecuencia)
            $stats = Cache::getInstance()->remember('recursos:stats:global', function() {
                return $this->recursoModel->getEstadisticas();
            }, 300);
            
            Response::success($stats);
        } catch (Exception $e) {
            Logger::error('Error al obtener estadísticas: ' . $e->getMessage());
            Response::error('Error al obtener estadísticas', 500);
        }
    }
    
    // =========================================================================
    // VERSIONADO
    // =========================================================================
    
    /**
     * GET /api/v1/recursos/{id}/versiones
     * Obtener historial de versiones de un recurso
     */
    public function obtenerVersiones($id) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            
            require_once __DIR__ . '/../models/RecursoVersion.php';
            $versionModel = new RecursoVersion();
            
            $historial = $versionModel->getHistorial($id, $page, $limit);
            
            Response::success($historial);
        } catch (Exception $e) {
            Logger::error('Error al obtener versiones: ' . $e->getMessage());
            Response::error('Error al obtener historial de versiones', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/{id}/versiones/{numero}
     * Obtener versión específica de un recurso
     */
    public function obtenerVersion($id, $numeroVersion) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            require_once __DIR__ . '/../models/RecursoVersion.php';
            $versionModel = new RecursoVersion();
            
            $version = $versionModel->getVersion($id, $numeroVersion);
            
            if (!$version) {
                Response::error('Versión no encontrada', 404);
            }
            
            Response::success($version);
        } catch (Exception $e) {
            Logger::error('Error al obtener versión: ' . $e->getMessage());
            Response::error('Error al obtener versión', 500);
        }
    }
    
    /**
     * POST /api/v1/recursos/{id}/versiones/{numero}/restaurar
     * Restaurar recurso a una versión anterior
     */
    public function restaurarVersion($id, $numeroVersion) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            // Obtener recurso para verificar permisos
            $recurso = $this->recursoModel->getById($id);
            if (!$recurso) {
                Response::error('Recurso no encontrado', 404);
            }
            
            // Solo admin o el autor pueden restaurar
            if ($usuario['rol'] !== 'administrador' && $recurso['id_autor'] != $usuario['id_usuario']) {
                Response::error('No tienes permisos para restaurar versiones', 403);
            }
            
            require_once __DIR__ . '/../models/RecursoVersion.php';
            $versionModel = new RecursoVersion();
            
            $result = $versionModel->restaurarVersion($id, $numeroVersion, $usuario['id_usuario']);
            
            if (!$result) {
                Response::error('Error al restaurar versión', 500);
            }
            
            Logger::activity($usuario['id_usuario'], 'restaurar_version_recurso', $id, [
                'version' => $numeroVersion
            ]);
            
            Response::success($result, 'Versión restaurada exitosamente');
        } catch (Exception $e) {
            Logger::error('Error al restaurar versión: ' . $e->getMessage());
            Response::error('Error al restaurar versión: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/{id}/versiones/comparar?v1={num}&v2={num}
     * Comparar dos versiones de un recurso
     */
    public function compararVersiones($id) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            $version1 = isset($_GET['v1']) ? (int)$_GET['v1'] : null;
            $version2 = isset($_GET['v2']) ? (int)$_GET['v2'] : null;
            
            if (!$version1 || !$version2) {
                Response::error('Debes especificar dos versiones (v1 y v2)', 400);
            }
            
            require_once __DIR__ . '/../models/RecursoVersion.php';
            $versionModel = new RecursoVersion();
            
            $comparacion = $versionModel->compararVersiones($id, $version1, $version2);
            
            if (!$comparacion) {
                Response::error('No se pudieron comparar las versiones', 404);
            }
            
            Response::success($comparacion);
        } catch (Exception $e) {
            Logger::error('Error al comparar versiones: ' . $e->getMessage());
            Response::error('Error al comparar versiones', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/versiones/estadisticas
     * Estadísticas globales de versionado
     */
    public function estadisticasVersionado() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver estadísticas', 403);
            }
            
            require_once __DIR__ . '/../models/RecursoVersion.php';
            $versionModel = new RecursoVersion();
            
            $stats = $versionModel->getEstadisticasGlobales();
            
            Response::success($stats);
        } catch (Exception $e) {
            Logger::error('Error al obtener estadísticas de versionado: ' . $e->getMessage());
            Response::error('Error al obtener estadísticas', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/versiones/recientes
     * Cambios recientes en recursos (timeline)
     */
    public function cambiosRecientes() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver el timeline', 403);
            }
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            require_once __DIR__ . '/../models/RecursoVersion.php';
            $versionModel = new RecursoVersion();
            
            $cambios = $versionModel->getCambiosRecientes($limit);
            
            Response::success($cambios);
        } catch (Exception $e) {
            Logger::error('Error al obtener cambios recientes: ' . $e->getMessage());
            Response::error('Error al obtener timeline', 500);
        }
    }
    
    // =========================================================================
    // ANALYTICS AVANZADO
    // =========================================================================
    
    /**
     * GET /api/v1/recursos/analytics/descargas-tiempo
     * Descargas agrupadas por tiempo
     */
    public function analyticsDescargasTiempo() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver analytics', 403);
            }
            
            $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
            $agrupacion = $_GET['agrupacion'] ?? 'day'; // hour, day, week, month, year
            
            $datos = $this->recursoModel->getDescargasPorTiempo($fechaDesde, $fechaHasta, $agrupacion);
            
            Response::success([
                'datos' => $datos,
                'filtros' => [
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta,
                    'agrupacion' => $agrupacion
                ]
            ]);
        } catch (Exception $e) {
            Logger::error('Error en analytics descargas-tiempo: ' . $e->getMessage());
            Response::error('Error al obtener datos', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/analytics/mas-descargados
     * Recursos más descargados
     */
    public function analyticsMasDescargados() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver analytics', 403);
            }
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $fechaDesde = $_GET['fecha_desde'] ?? null;
            $fechaHasta = $_GET['fecha_hasta'] ?? null;
            
            $datos = $this->recursoModel->getRecursosMasDescargados($limit, $fechaDesde, $fechaHasta);
            
            Response::success($datos);
        } catch (Exception $e) {
            Logger::error('Error en analytics mas-descargados: ' . $e->getMessage());
            Response::error('Error al obtener datos', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/analytics/mas-vistos
     * Recursos más vistos
     */
    public function analyticsMasVistos() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver analytics', 403);
            }
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $fechaDesde = $_GET['fecha_desde'] ?? null;
            $fechaHasta = $_GET['fecha_hasta'] ?? null;
            
            $datos = $this->recursoModel->getRecursosMasVistos($limit, $fechaDesde, $fechaHasta);
            
            Response::success($datos);
        } catch (Exception $e) {
            Logger::error('Error en analytics mas-vistos: ' . $e->getMessage());
            Response::error('Error al obtener datos', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/analytics/mejor-calificados
     * Recursos mejor calificados
     */
    public function analyticsMejorCalificados() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver analytics', 403);
            }
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $minCalificaciones = isset($_GET['min_calificaciones']) ? (int)$_GET['min_calificaciones'] : 5;
            
            $datos = $this->recursoModel->getRecursosMejorCalificados($limit, $minCalificaciones);
            
            Response::success($datos);
        } catch (Exception $e) {
            Logger::error('Error en analytics mejor-calificados: ' . $e->getMessage());
            Response::error('Error al obtener datos', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/analytics/tasa-conversion
     * Tasa de conversión vistas -> descargas
     */
    public function analyticsTasaConversion() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver analytics', 403);
            }
            
            $fechaDesde = $_GET['fecha_desde'] ?? null;
            $fechaHasta = $_GET['fecha_hasta'] ?? null;
            
            $datos = $this->recursoModel->getTasaConversion($fechaDesde, $fechaHasta);
            
            Response::success($datos);
        } catch (Exception $e) {
            Logger::error('Error en analytics tasa-conversion: ' . $e->getMessage());
            Response::error('Error al obtener datos', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/analytics/distribucion-categoria
     * Distribución de recursos por categoría
     */
    public function analyticsDistribucionCategoria() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver analytics', 403);
            }
            
            // Cachear distribución por 15 minutos
            $datos = Cache::getInstance()->remember('analytics:distribucion:categoria', 900, function() {
                return $this->recursoModel->getDistribucionPorCategoria();
            });
            
            Response::success($datos);
        } catch (Exception $e) {
            Logger::error('Error en analytics distribucion-categoria: ' . $e->getMessage());
            Response::error('Error al obtener datos', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/analytics/distribucion-tipo
     * Distribución de recursos por tipo
     */
    public function analyticsDistribucionTipo() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver analytics', 403);
            }
            
            // Cachear distribución por 15 minutos
            $datos = Cache::getInstance()->remember('analytics:distribucion:tipo', 900, function() {
                return $this->recursoModel->getDistribucionPorTipo();
            });
            
            Response::success($datos);
        } catch (Exception $e) {
            Logger::error('Error en analytics distribucion-tipo: ' . $e->getMessage());
            Response::error('Error al obtener datos', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/analytics/tendencias
     * Tendencias comparadas con período anterior
     */
    public function analyticsTendencias() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver analytics', 403);
            }
            
            $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days'));
            $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
            
            $datos = $this->recursoModel->getTendencias($fechaDesde, $fechaHasta);
            
            Response::success($datos);
        } catch (Exception $e) {
            Logger::error('Error en analytics tendencias: ' . $e->getMessage());
            Response::error('Error al obtener datos', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/analytics/usuarios-activos
     * Usuarios más activos
     */
    public function analyticsUsuariosActivos() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver analytics', 403);
            }
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $fechaDesde = $_GET['fecha_desde'] ?? null;
            $fechaHasta = $_GET['fecha_hasta'] ?? null;
            
            $datos = $this->recursoModel->getUsuariosMasActivos($limit, $fechaDesde, $fechaHasta);
            
            Response::success($datos);
        } catch (Exception $e) {
            Logger::error('Error en analytics usuarios-activos: ' . $e->getMessage());
            Response::error('Error al obtener datos', 500);
        }
    }
    
    /**
     * GET /api/v1/recursos/analytics/dashboard
     * Dashboard completo con todas las métricas principales
     */
    public function analyticsDashboard() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['rol'] !== 'administrador') {
                Response::error('No tienes permisos para ver analytics', 403);
            }
            
            $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
            
            // Cachear dashboard completo por 5 minutos
            $cacheKey = 'analytics:dashboard:' . md5($fechaDesde . $fechaHasta);
            $dashboard = Cache::getInstance()->remember($cacheKey, 300, function() use ($fechaDesde, $fechaHasta) {
                return [
                    'estadisticas_generales' => $this->recursoModel->getEstadisticas(),
                    'descargas_tiempo' => $this->recursoModel->getDescargasPorTiempo($fechaDesde, $fechaHasta, 'day'),
                    'mas_descargados' => $this->recursoModel->getRecursosMasDescargados(5, $fechaDesde, $fechaHasta),
                    'mas_vistos' => $this->recursoModel->getRecursosMasVistos(5, $fechaDesde, $fechaHasta),
                    'mejor_calificados' => $this->recursoModel->getRecursosMejorCalificados(5),
                    'distribucion_categoria' => $this->recursoModel->getDistribucionPorCategoria(),
                    'distribucion_tipo' => $this->recursoModel->getDistribucionPorTipo(),
                    'tendencias' => $this->recursoModel->getTendencias($fechaDesde, $fechaHasta),
                    'usuarios_activos' => $this->recursoModel->getUsuariosMasActivos(5, $fechaDesde, $fechaHasta)
                ];
            });
            
            Response::success($dashboard);
        } catch (Exception $e) {
            Logger::error('Error en analytics dashboard: ' . $e->getMessage());
            Response::error('Error al obtener dashboard', 500);
        }
    }
    
    // =========================================================================
    // DESCARGA SEGURA CON URL FIRMADA
    // =========================================================================
    
    /**
     * GET /api/v1/recursos/download/{token}
     * Descargar recurso usando URL firmada temporal
     */
    public function descargarRecursoSeguro($token) {
        try {
            // Verificar firma y obtener parámetros
            $optimizer = FileOptimizer::getInstance();
            $params = $optimizer->verificarUrlFirmada($token);
            
            if (!$params) {
                Response::error('URL de descarga inválida o expirada', 403);
            }
            
            // Validar que el archivo existe
            $filePath = $params['file'];
            if (!file_exists($filePath)) {
                Response::error('Archivo no encontrado', 404);
            }
            
            // Registrar descarga si hay id_recurso
            if (isset($params['id_recurso'])) {
                $this->recursoModel->registrarDescarga($params['id_recurso'], $params['id_usuario'] ?? null);
            }
            
            // Log de actividad
            if (isset($params['id_usuario'])) {
                Logger::activity($params['id_usuario'], 'descargar_recurso_seguro', $params['id_recurso'] ?? 0);
            }
            
            // Obtener información del archivo
            $fileInfo = $optimizer->getFileInfo($filePath);
            
            // Headers de seguridad y descarga
            header('Content-Type: ' . $fileInfo['mime_type']);
            header('Content-Length: ' . $fileInfo['size']);
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('X-Content-Type-Options: nosniff');
            
            // Limpiar buffer de salida
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Enviar archivo
            readfile($filePath);
            exit;
            
        } catch (Exception $e) {
            Logger::error('Error en descarga segura: ' . $e->getMessage());
            Response::error('Error al descargar archivo', 500);
        }
    }
    
    /**
     * POST /api/v1/recursos/{id}/generar-url-descarga
     * Generar URL de descarga temporal firmada
     */
    public function generarUrlDescarga($id) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            // Obtener recurso
            $recurso = $this->recursoModel->getById($id);
            if (!$recurso) {
                Response::error('Recurso no encontrado', 404);
            }
            
            if ($recurso['estado'] !== 'publicado') {
                Response::error('El recurso no está disponible', 403);
            }
            
            // Verificar que tenga archivo
            if (empty($recurso['url_archivo'])) {
                Response::error('El recurso no tiene archivo disponible', 404);
            }
            
            // Construir ruta absoluta del archivo
            $uploadsDir = dirname(__DIR__, 2) . '/uploads/recursos/';
            $filePath = $uploadsDir . basename($recurso['url_archivo']);
            
            if (!file_exists($filePath)) {
                Response::error('Archivo no encontrado en el servidor', 404);
            }
            
            // Generar URL firmada (válida por 1 hora)
            $optimizer = FileOptimizer::getInstance();
            $token = $optimizer->generarUrlFirmada($filePath, 3600, [
                'id_recurso' => $id,
                'id_usuario' => $usuario['id_usuario']
            ]);
            
            // Construir URL completa
            $baseUrl = defined('APP_URL') ? APP_URL : 'http://localhost/nenis_y_bros';
            $downloadUrl = $baseUrl . '/api/v1/recursos/download/' . $token;
            
            Response::success([
                'url_descarga' => $downloadUrl,
                'expira_en_segundos' => 3600,
                'expira_en' => date('Y-m-d H:i:s', time() + 3600)
            ], 'URL de descarga generada exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al generar URL de descarga: ' . $e->getMessage());
            Response::error('Error al generar URL de descarga', 500);
        }
    }
    
    /**
     * POST /api/v1/recursos/optimizar-imagen
     * Optimizar imagen subida (usar desde frontend al subir archivos)
     */
    public function optimizarImagen() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if (!in_array($usuario['rol'], ['administrador', 'instructor'])) {
                Response::error('No tienes permisos para subir recursos', 403);
            }
            
            // Verificar que se envió un archivo
            if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                Response::error('No se recibió ninguna imagen válida', 400);
            }
            
            $file = $_FILES['imagen'];
            
            // Validar tipo de archivo
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                Response::error('Tipo de archivo no permitido. Solo se aceptan imágenes JPEG, PNG, GIF o WebP', 400);
            }
            
            // Validar tamaño (máx 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                Response::error('La imagen es demasiado grande. Máximo 10MB', 400);
            }
            
            // Crear directorio de destino
            $uploadsDir = dirname(__DIR__, 2) . '/uploads/recursos/';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            
            // Generar nombre único
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('recurso_') . '.' . $extension;
            $originalPath = $uploadsDir . $filename;
            
            // Mover archivo temporal
            if (!move_uploaded_file($file['tmp_name'], $originalPath)) {
                Response::error('Error al guardar archivo', 500);
            }
            
            // Optimizar imagen
            $optimizer = FileOptimizer::getInstance();
            $optimizedPath = $optimizer->optimizarImagen($originalPath);
            
            // Generar thumbnail
            $thumbnailPath = $optimizer->generarThumbnail($optimizedPath);
            
            // Generar versión WebP si es posible
            $webpPath = $optimizer->generarWebP($optimizedPath);
            
            // Generar srcset para imágenes responsivas
            $srcset = $optimizer->generarSrcSet($optimizedPath);
            
            // Obtener información del archivo optimizado
            $fileInfo = $optimizer->getFileInfo($optimizedPath);
            
            // URLs relativas
            $baseUrl = '/uploads/recursos/';
            $result = [
                'url_original' => $baseUrl . basename($optimizedPath),
                'url_thumbnail' => $thumbnailPath ? $baseUrl . basename($thumbnailPath) : null,
                'url_webp' => $webpPath ? $baseUrl . basename($webpPath) : null,
                'srcset' => array_map(function($path) use ($baseUrl) {
                    return $baseUrl . basename($path);
                }, $srcset),
                'tamanio_bytes' => $fileInfo['size'],
                'tamanio_formateado' => $fileInfo['size_formatted'],
                'dimensiones' => $fileInfo['width'] . 'x' . $fileInfo['height'],
                'mime_type' => $fileInfo['mime_type']
            ];
            
            Logger::activity($usuario['id_usuario'], 'optimizar_imagen', 0, [
                'nombre_archivo' => $filename,
                'tamanio_original' => $file['size'],
                'tamanio_optimizado' => $fileInfo['size']
            ]);
            
            Response::success($result, 'Imagen optimizada exitosamente', 201);
            
        } catch (Exception $e) {
            Logger::error('Error al optimizar imagen: ' . $e->getMessage());
            Response::error('Error al optimizar imagen', 500);
        }
    }
}
