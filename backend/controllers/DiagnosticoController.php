<?php
/**
 * ============================================================================
 * CONTROLADOR: DIAGNOSTICO
 * ============================================================================
 * Gestiona diagnósticos empresariales, ejecución y resultados
 * Fase 3 - Perfiles Empresariales y Diagnósticos
 * ============================================================================
 */

class DiagnosticoController {
    
    private $tipoDiagnosticoModel;
    private $diagnosticoRealizadoModel;
    private $motorRecomendaciones;
    private $authMiddleware;
    
    public function __construct() {
        $this->tipoDiagnosticoModel = new TipoDiagnostico();
        $this->diagnosticoRealizadoModel = new DiagnosticoRealizado();
        $this->motorRecomendaciones = new MotorRecomendaciones();
        $this->authMiddleware = new AuthMiddleware();
    }
    
    /**
     * GET /api/v1/diagnosticos/tipos
     * Listar tipos de diagnósticos disponibles
     */
    public function tiposDisponibles() {
        $this->authMiddleware->requireAuth();
        
        $tipos = $this->tipoDiagnosticoModel->findAll();
        Response::success($tipos);
    }
    
    /**
     * GET /api/v1/diagnosticos/tipos/{id}
     * Ver detalles de un tipo de diagnóstico (con áreas y preguntas)
     */
    public function verTipoDiagnostico($id) {
        $this->authMiddleware->requireAuth();
        
        // Permitir forzar inclusión de detalles via query string
        $withDetails = isset($_GET['withDetails']) ? ($_GET['withDetails'] === 'true' || $_GET['withDetails'] === '1') : true;
        
        $diagnostico = $this->tipoDiagnosticoModel->findById($id, $withDetails);
        
        if (!$diagnostico) {
            Response::error('Tipo de diagnóstico no encontrado', 404);
        }
        
        Response::success($diagnostico);
    }
    
    /**
     * GET /api/v1/diagnosticos/tipos/slug/{slug}
     * Ver diagnóstico por slug
     */
    public function verTipoDiagnosticoPorSlug($slug) {
        $this->authMiddleware->requireAuth();
        
        $diagnostico = $this->tipoDiagnosticoModel->findBySlug($slug, true);
        
        if (!$diagnostico) {
            Response::error('Tipo de diagnóstico no encontrado', 404);
        }
        
        Response::success($diagnostico);
    }
    
    /**
     * POST /api/v1/diagnosticos/iniciar
     * Iniciar nuevo diagnóstico
     */
    public function iniciar() {
        $user = $this->authMiddleware->requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar entrada
        $rules = [
            'id_tipo_diagnostico' => 'required|integer',
            'id_perfil_empresarial' => 'integer'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        // Verificar que el tipo de diagnóstico existe
        $tipo = $this->tipoDiagnosticoModel->findById($data['id_tipo_diagnostico']);
        if (!$tipo) {
            Response::error('Tipo de diagnóstico no válido', 400);
        }
        
        // Agregar usuario actual
        $data['id_usuario'] = $user['id_usuario'];
        
        try {
            $diagnosticoId = $this->diagnosticoRealizadoModel->create($data);
            
            if ($diagnosticoId) {
                $diagnostico = $this->diagnosticoRealizadoModel->findById($diagnosticoId);
                
                // Obtener preguntas para el diagnóstico
                $areas = $this->tipoDiagnosticoModel->getAreasWithQuestions($data['id_tipo_diagnostico']);
                
                Response::success([
                    'mensaje' => 'Diagnóstico iniciado exitosamente',
                    'diagnostico' => $diagnostico,
                    'areas' => $areas
                ], 201);
            }
            
            Response::error('Error al iniciar diagnóstico', 500);
        } catch (Exception $e) {
            Response::error('Error al iniciar diagnóstico: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/diagnosticos/{id}
     * Ver diagnóstico específico con respuestas y progreso
     */
    public function show($id) {
        $user = $this->authMiddleware->requireAuth();
        
        $diagnostico = $this->diagnosticoRealizadoModel->findById($id);
        
        if (!$diagnostico) {
            Response::error('Diagnóstico no encontrado', 404);
        }
        
        // Verificar permisos
        if (!$this->diagnosticoRealizadoModel->belongsToUser($id, $user['id_usuario']) && $user['rol'] !== 'admin') {
            Response::error('No tienes permiso para ver este diagnóstico', 403);
        }
        
        Response::success($diagnostico);
    }
    
    /**
     * GET /api/v1/diagnosticos/mis-diagnosticos
     * Listar diagnósticos del usuario autenticado
     */
    public function misDiagnosticos() {
        $user = $this->authMiddleware->requireAuth();
        
        $filtros = [
            'estado' => $_GET['estado'] ?? null,
            'tipo_diagnostico' => $_GET['tipo_diagnostico'] ?? null
        ];
        
        $diagnosticos = $this->diagnosticoRealizadoModel->findByUser($user['id_usuario'], $filtros);
        Response::success($diagnosticos);
    }
    
    /**
     * POST /api/v1/diagnosticos/{id}/responder
     * Guardar respuesta a una pregunta
     */
    public function responder($id) {
        $user = $this->authMiddleware->requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Verificar que el diagnóstico existe y pertenece al usuario
        if (!$this->diagnosticoRealizadoModel->belongsToUser($id, $user['id_usuario'])) {
            Response::error('Diagnóstico no encontrado o no tienes permiso', 404);
        }
        
        // Validar entrada
        $rules = [
            'id_pregunta' => 'required|integer',
            'valor_numerico' => 'required|numeric',
            'valor_texto' => 'string'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $resultado = $this->diagnosticoRealizadoModel->saveRespuesta(
                $id,
                $data['id_pregunta'],
                $data['valor_numerico'],
                $data['valor_texto'] ?? null
            );
            
            if ($resultado) {
                // Obtener progreso actualizado
                $progreso = $this->diagnosticoRealizadoModel->getProgreso($id);
                
                Response::success([
                    'mensaje' => 'Respuesta guardada',
                    'progreso' => $progreso
                ]);
            }
            
            Response::error('Error al guardar respuesta', 500);
        } catch (Exception $e) {
            Response::error('Error al guardar respuesta: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/v1/diagnosticos/{id}/respuestas-multiples
     * Guardar múltiples respuestas a la vez
     */
    public function responderMultiples($id) {
        $user = $this->authMiddleware->requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Verificar permisos
        if (!$this->diagnosticoRealizadoModel->belongsToUser($id, $user['id_usuario'])) {
            Response::error('Diagnóstico no encontrado o no tienes permiso', 404);
        }
        
        // Validar estructura
        if (!isset($data['respuestas']) || !is_array($data['respuestas'])) {
            Response::error('Formato inválido. Se espera un array "respuestas"', 400);
        }
        
        $guardadas = 0;
        $errores = [];
        
        try {
            foreach ($data['respuestas'] as $respuesta) {
                if (!isset($respuesta['id_pregunta']) || !isset($respuesta['valor_numerico'])) {
                    $errores[] = 'Respuesta inválida: faltan campos requeridos';
                    continue;
                }
                
                $resultado = $this->diagnosticoRealizadoModel->saveRespuesta(
                    $id,
                    $respuesta['id_pregunta'],
                    $respuesta['valor_numerico'],
                    $respuesta['valor_texto'] ?? null
                );
                
                if ($resultado) {
                    $guardadas++;
                } else {
                    $errores[] = "Error al guardar pregunta {$respuesta['id_pregunta']}";
                }
            }
            
            $progreso = $this->diagnosticoRealizadoModel->getProgreso($id);
            
            Response::success([
                'mensaje' => "$guardadas respuestas guardadas",
                'guardadas' => $guardadas,
                'errores' => $errores,
                'progreso' => $progreso
            ]);
        } catch (Exception $e) {
            Response::error('Error al guardar respuestas: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/v1/diagnosticos/{id}/finalizar
     * Finalizar diagnóstico y calcular resultados
     */
    public function finalizar($id) {
        $user = $this->authMiddleware->requireAuth();
        
        // Verificar permisos
        if (!$this->diagnosticoRealizadoModel->belongsToUser($id, $user['id_usuario'])) {
            Response::error('Diagnóstico no encontrado o no tienes permiso', 404);
        }
        
        // Verificar que está completo
        $progreso = $this->diagnosticoRealizadoModel->getProgreso($id);
        if (!$progreso['completo']) {
            Response::error("Diagnóstico incompleto. Respondidas {$progreso['respondidas']} de {$progreso['total']} preguntas", 400);
        }
        
        try {
            $resultados = $this->diagnosticoRealizadoModel->finalizarYCalcular($id);
            
            // Generar recomendaciones automáticamente
            $recomendaciones = $this->motorRecomendaciones->generarRecomendaciones($id);
            
            // Obtener diagnóstico actualizado con resultados
            $diagnostico = $this->diagnosticoRealizadoModel->findById($id);
            
            Response::success([
                'mensaje' => 'Diagnóstico finalizado y calculado exitosamente',
                'diagnostico' => $diagnostico,
                'resultados' => $resultados,
                'recomendaciones' => $recomendaciones
            ]);
        } catch (Exception $e) {
            Response::error('Error al finalizar diagnóstico: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/diagnosticos/{id}/resultados
     * Ver resultados de un diagnóstico completado
     */
    public function resultados($id) {
        $user = $this->authMiddleware->requireAuth();
        
        $diagnostico = $this->diagnosticoRealizadoModel->findById($id);
        
        if (!$diagnostico) {
            Response::error('Diagnóstico no encontrado', 404);
        }
        
        // Verificar permisos
        if (!$this->diagnosticoRealizadoModel->belongsToUser($id, $user['id_usuario']) && $user['rol'] !== 'admin') {
            Response::error('No tienes permiso para ver estos resultados', 403);
        }
        
        if ($diagnostico['estado'] !== 'completado') {
            Response::error('El diagnóstico aún no ha sido completado', 400);
        }
        
        // Intentar obtener recomendaciones guardadas, si no existen, generarlas
        $recomendaciones = $this->motorRecomendaciones->obtenerRecomendaciones($id);
        
        if (!$recomendaciones) {
            // Generar recomendaciones si no existen
            $recomendaciones = $this->motorRecomendaciones->generarRecomendaciones($id);
        }
        
        Response::success([
            'diagnostico' => $diagnostico,
            'puntaje_total' => $diagnostico['puntaje_total'],
            'nivel_madurez' => $diagnostico['nivel_madurez'],
            'resultados_areas' => $diagnostico['resultados_areas'],
            'recomendaciones' => $recomendaciones
        ]);
    }
    
    /**
     * GET /api/v1/diagnosticos/{idActual}/comparar/{idAnterior}
     * Comparar dos diagnósticos
     */
    public function comparar($idActual, $idAnterior) {
        $user = $this->authMiddleware->requireAuth();
        
        // Verificar permisos para ambos diagnósticos
        if (!$this->diagnosticoRealizadoModel->belongsToUser($idActual, $user['id_usuario']) ||
            !$this->diagnosticoRealizadoModel->belongsToUser($idAnterior, $user['id_usuario'])) {
            Response::error('No tienes permiso para comparar estos diagnósticos', 403);
        }
        
        try {
            $comparacion = $this->diagnosticoRealizadoModel->compararDiagnosticos($idActual, $idAnterior);
            
            if (!$comparacion) {
                Response::error('No se pudo realizar la comparación', 500);
            }
            
            Response::success($comparacion);
        } catch (Exception $e) {
            Response::error('Error al comparar diagnósticos: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/v1/diagnosticos/{id}/recomendaciones
     * Generar o regenerar recomendaciones para un diagnóstico completado
     */
    public function generarRecomendaciones($id) {
        $user = $this->authMiddleware->requireAuth();
        
        // Verificar permisos
        if (!$this->diagnosticoRealizadoModel->belongsToUser($id, $user['id_usuario']) && $user['rol'] !== 'admin') {
            Response::error('No tienes permiso para este diagnóstico', 403);
        }
        
        // Verificar que esté completado
        $diagnostico = $this->diagnosticoRealizadoModel->findById($id);
        if (!$diagnostico || $diagnostico['estado'] !== 'completado') {
            Response::error('El diagnóstico debe estar completado para generar recomendaciones', 400);
        }
        
        try {
            $recomendaciones = $this->motorRecomendaciones->generarRecomendaciones($id);
            
            Response::success([
                'mensaje' => 'Recomendaciones generadas exitosamente',
                'recomendaciones' => $recomendaciones
            ]);
        } catch (Exception $e) {
            Response::error('Error al generar recomendaciones: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/v1/diagnosticos/{id}
     * Cancelar/eliminar diagnóstico
     */
    public function delete($id) {
        $user = $this->authMiddleware->requireAuth();
        
        // Verificar permisos
        if (!$this->diagnosticoRealizadoModel->belongsToUser($id, $user['id_usuario']) && $user['rol'] !== 'admin') {
            Response::error('No tienes permiso para eliminar este diagnóstico', 403);
        }
        
        try {
            $resultado = $this->diagnosticoRealizadoModel->delete($id);
            
            if ($resultado) {
                Response::success(['mensaje' => 'Diagnóstico cancelado exitosamente']);
            }
            
            Response::error('Error al cancelar diagnóstico', 500);
        } catch (Exception $e) {
            Response::error('Error: ' . $e->getMessage(), 500);
        }
    }
    
    // =========================================================================
    // ADMIN: GESTIÓN DE TIPOS DE DIAGNÓSTICOS
    // =========================================================================
    
    /**
     * POST /api/v1/diagnosticos/tipos
     * Crear nuevo tipo de diagnóstico (Solo admin)
     */
    public function createTipo() {
        $user = $this->authMiddleware->requireAuth();
        
        // Verificar permisos de administrador
        if ($user['tipo_usuario'] !== 'administrador') {
            Response::error('No tienes permisos para realizar esta acción', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar campos requeridos
        $rules = [
            'nombre' => 'required|string|min:5'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        // Generar slug único
        $data['slug'] = $this->tipoDiagnosticoModel->generateUniqueSlug($data['nombre']);
        
        try {
            $tipoId = $this->tipoDiagnosticoModel->create($data);
            
            if ($tipoId) {
                $tipo = $this->tipoDiagnosticoModel->findById($tipoId);
                Response::success([
                    'tipo' => $tipo
                ], 'Tipo de diagnóstico creado exitosamente', 201);
            }
            
            Response::error('Error al crear tipo de diagnóstico', 500);
        } catch (Exception $e) {
            Response::error('Error al crear tipo: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /api/v1/diagnosticos/tipos/{id}
     * Actualizar tipo de diagnóstico (Solo admin)
     */
    public function updateTipo($id) {
        $user = $this->authMiddleware->requireAuth();
        
        // Verificar permisos de administrador
        if ($user['tipo_usuario'] !== 'administrador') {
            Response::error('No tienes permisos para realizar esta acción', 403);
        }
        
        // Verificar que el tipo existe
        $tipoExistente = $this->tipoDiagnosticoModel->findById($id);
        if (!$tipoExistente) {
            Response::error('Tipo de diagnóstico no encontrado', 404);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Si se cambia el nombre, regenerar slug
        if (isset($data['nombre']) && $data['nombre'] !== $tipoExistente['nombre']) {
            $data['slug'] = $this->tipoDiagnosticoModel->generateUniqueSlug($data['nombre'], $id);
        }
        
        try {
            // Construir query de actualización dinámicamente
            $updates = [];
            $params = [];
            $allowedFields = ['nombre', 'descripcion', 'slug', 'duracion_estimada', 'activo', 'nivel_detalle'];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                Response::error('No hay campos para actualizar', 400);
            }
            
            $params[] = $id;
            $query = "UPDATE tipos_diagnostico SET " . implode(', ', $updates) . " WHERE id_tipo_diagnostico = ?";
            
            $db = Database::getInstance();
            $result = $db->query($query, $params);
            
            if ($result) {
                $tipoActualizado = $this->tipoDiagnosticoModel->findById($id);
                Response::success([
                    'tipo' => $tipoActualizado
                ], 'Tipo actualizado exitosamente');
            }
            
            Response::error('Error al actualizar tipo', 500);
        } catch (Exception $e) {
            Response::error('Error al actualizar: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/v1/diagnosticos/tipos/{id}
     * Eliminar tipo de diagnóstico (Solo admin)
     */
    public function deleteTipo($id) {
        $user = $this->authMiddleware->requireAuth();
        
        // Verificar permisos de administrador
        if ($user['tipo_usuario'] !== 'administrador') {
            Response::error('No tienes permisos para realizar esta acción', 403);
        }
        
        // Verificar que el tipo existe
        $tipo = $this->tipoDiagnosticoModel->findById($id);
        if (!$tipo) {
            Response::error('Tipo de diagnóstico no encontrado', 404);
        }
        
        try {
            $db = Database::getInstance();
            $query = "DELETE FROM tipos_diagnostico WHERE id_tipo_diagnostico = ?";
            $result = $db->query($query, [$id]);
            
            if ($result) {
                Response::success(['mensaje' => 'Tipo eliminado exitosamente']);
            }
            
            Response::error('Error al eliminar tipo', 500);
        } catch (Exception $e) {
            Response::error('Error al eliminar: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/diagnosticos/admin/realizados
     * Listar todos los diagnósticos realizados (Solo admin)
     */
    public function adminListarRealizados() {
        $user = $this->authMiddleware->requireAuth();
        
        // Verificar permisos de administrador
        if ($user['tipo_usuario'] !== 'administrador') {
            Response::error('No tienes permisos para realizar esta acción', 403);
        }
        
        $estado = $_GET['estado'] ?? null;
        $tipo = $_GET['tipo'] ?? null;
        
        try {
            $db = Database::getInstance();
            
            $where = [];
            $params = [];
            
            if ($estado) {
                $where[] = "dr.estado = ?";
                $params[] = $estado;
            }
            
            if ($tipo) {
                // Usar id_diagnostico que es la FK real en la tabla
                $where[] = "dr.id_diagnostico = ?";
                $params[] = $tipo;
            }
            
            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            // Nota: La tabla usa id_diagnostico como FK hacia tipos_diagnostico
            // y no tiene id_perfil_empresarial, usamos un subquery para el perfil
            $query = "SELECT 
                dr.*,
                td.nombre as tipo_diagnostico,
                u.nombre as usuario_nombre,
                u.email as usuario_email,
                (SELECT pe.nombre_empresa FROM perfiles_empresariales pe 
                 WHERE pe.id_usuario = dr.id_usuario LIMIT 1) as nombre_empresa
            FROM diagnosticos_realizados dr
            INNER JOIN tipos_diagnostico td ON dr.id_diagnostico = td.id_tipo_diagnostico
            INNER JOIN usuarios u ON dr.id_usuario = u.id_usuario
            $whereClause
            ORDER BY dr.fecha_inicio DESC";
            
            $diagnosticos = $db->fetchAll($query, $params);
            
            // Agregar progreso a cada diagnóstico
            foreach ($diagnosticos as &$diag) {
                $diag['progreso'] = $this->diagnosticoRealizadoModel->getProgreso($diag['id_diagnostico_realizado']);
            }
            
            Response::success($diagnosticos);
        } catch (Exception $e) {
            Response::error('Error al listar diagnósticos: ' . $e->getMessage(), 500);
        }
    }
    
    // =========================================================================
    // ADMIN: GESTIÓN DE ÁREAS
    // =========================================================================
    
    /**
     * POST /api/v1/diagnosticos/areas
     * Crear nueva área de evaluación (Solo admin)
     */
    public function createArea() {
        $user = $this->authMiddleware->requireAuth();
        
        if ($user['tipo_usuario'] !== 'administrador') {
            Response::error('No tienes permisos para realizar esta acción', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $rules = [
            'id_tipo_diagnostico' => 'required|integer',
            'nombre' => 'required|string|min:3'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $areaId = $this->tipoDiagnosticoModel->createArea($data);
            
            if ($areaId) {
                $db = Database::getInstance();
                $query = "SELECT * FROM areas_evaluacion WHERE id_area = ?";
                $area = $db->fetchOne($query, [$areaId]);
                
                Response::success([
                    'area' => $area
                ], 'Área creada exitosamente', 201);
            }
            
            Response::error('Error al crear área', 500);
        } catch (Exception $e) {
            Response::error('Error al crear área: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /api/v1/diagnosticos/areas/{id}
     * Actualizar área de evaluación (Solo admin)
     */
    public function updateArea($id) {
        $user = $this->authMiddleware->requireAuth();
        
        if ($user['tipo_usuario'] !== 'administrador') {
            Response::error('No tienes permisos para realizar esta acción', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $db = Database::getInstance();
            
            // Verificar que existe
            $query = "SELECT * FROM areas_evaluacion WHERE id_area = ?";
            $area = $db->fetchOne($query, [$id]);
            
            if (!$area) {
                Response::error('Área no encontrada', 404);
            }
            
            // Actualizar
            $updates = [];
            $params = [];
            $allowedFields = ['nombre', 'descripcion', 'icono', 'color', 'ponderacion', 'orden'];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                Response::error('No hay campos para actualizar', 400);
            }
            
            $params[] = $id;
            $query = "UPDATE areas_evaluacion SET " . implode(', ', $updates) . " WHERE id_area = ?";
            
            $result = $db->query($query, $params);
            
            if ($result) {
                $areaActualizada = $db->fetchOne("SELECT * FROM areas_evaluacion WHERE id_area = ?", [$id]);
                Response::success([
                    'area' => $areaActualizada
                ], 'Área actualizada exitosamente');
            }
            
            Response::error('Error al actualizar área', 500);
        } catch (Exception $e) {
            Response::error('Error al actualizar área: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/v1/diagnosticos/areas/{id}
     * Eliminar área de evaluación (Solo admin)
     */
    public function deleteArea($id) {
        $user = $this->authMiddleware->requireAuth();
        
        if ($user['tipo_usuario'] !== 'administrador') {
            Response::error('No tienes permisos para realizar esta acción', 403);
        }
        
        try {
            $db = Database::getInstance();
            
            // Verificar que existe
            $query = "SELECT * FROM areas_evaluacion WHERE id_area = ?";
            $area = $db->fetchOne($query, [$id]);
            
            if (!$area) {
                Response::error('Área no encontrada', 404);
            }
            
            // Eliminar (las preguntas se eliminan en cascada)
            $query = "DELETE FROM areas_evaluacion WHERE id_area = ?";
            $result = $db->query($query, [$id]);
            
            if ($result) {
                Response::success(['mensaje' => 'Área eliminada exitosamente']);
            }
            
            Response::error('Error al eliminar área', 500);
        } catch (Exception $e) {
            Response::error('Error al eliminar área: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/diagnosticos/areas/{id}/preguntas
     * Listar preguntas de un área
     */
    public function getPreguntas($areaId) {
        $user = $this->authMiddleware->requireAuth();
        
        try {
            $preguntas = $this->tipoDiagnosticoModel->getPreguntasByArea($areaId);
            Response::success($preguntas);
        } catch (Exception $e) {
            Response::error('Error al listar preguntas: ' . $e->getMessage(), 500);
        }
    }
    
    // =========================================================================
    // ADMIN: GESTIÓN DE PREGUNTAS
    // =========================================================================
    
    /**
     * POST /api/v1/diagnosticos/preguntas
     * Crear nueva pregunta (Solo admin)
     */
    public function createPregunta() {
        $user = $this->authMiddleware->requireAuth();
        
        if ($user['tipo_usuario'] !== 'administrador') {
            Response::error('No tienes permisos para realizar esta acción', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $rules = [
            'id_area' => 'required|integer',
            'pregunta' => 'required|string|min:10',
            'tipo_pregunta' => 'required'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $preguntaId = $this->tipoDiagnosticoModel->createPregunta($data);
            
            if ($preguntaId) {
                $pregunta = $this->tipoDiagnosticoModel->getPreguntaById($preguntaId);
                
                Response::success([
                    'pregunta' => $pregunta
                ], 'Pregunta creada exitosamente', 201);
            }
            
            Response::error('Error al crear pregunta', 500);
        } catch (Exception $e) {
            Response::error('Error al crear pregunta: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /api/v1/diagnosticos/preguntas/{id}
     * Actualizar pregunta (Solo admin)
     */
    public function updatePregunta($id) {
        $user = $this->authMiddleware->requireAuth();
        
        if ($user['tipo_usuario'] !== 'administrador') {
            Response::error('No tienes permisos para realizar esta acción', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $db = Database::getInstance();
            
            // Verificar que existe
            $pregunta = $this->tipoDiagnosticoModel->getPreguntaById($id);
            
            if (!$pregunta) {
                Response::error('Pregunta no encontrada', 404);
            }
            
            // Actualizar
            $updates = [];
            $params = [];
            $allowedFields = [
                'pregunta', 'descripcion_ayuda', 'tipo_pregunta', 'opciones',
                'escala_minima', 'escala_maxima', 'etiqueta_minima', 'etiqueta_maxima',
                'ponderacion', 'es_obligatoria', 'orden'
            ];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = ?";
                    
                    // Convertir opciones a JSON si es array
                    if ($field === 'opciones' && is_array($data[$field])) {
                        $params[] = json_encode($data[$field]);
                    } else {
                        $params[] = $data[$field];
                    }
                }
            }
            
            if (empty($updates)) {
                Response::error('No hay campos para actualizar', 400);
            }
            
            $params[] = $id;
            $query = "UPDATE preguntas_diagnostico SET " . implode(', ', $updates) . " WHERE id_pregunta = ?";
            
            $result = $db->query($query, $params);
            
            if ($result) {
                $preguntaActualizada = $this->tipoDiagnosticoModel->getPreguntaById($id);
                Response::success([
                    'pregunta' => $preguntaActualizada
                ], 'Pregunta actualizada exitosamente');
            }
            
            Response::error('Error al actualizar pregunta', 500);
        } catch (Exception $e) {
            Response::error('Error al actualizar pregunta: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/v1/diagnosticos/preguntas/{id}
     * Eliminar pregunta (Solo admin)
     */
    public function deletePregunta($id) {
        $user = $this->authMiddleware->requireAuth();
        
        if ($user['tipo_usuario'] !== 'administrador') {
            Response::error('No tienes permisos para realizar esta acción', 403);
        }
        
        try {
            $db = Database::getInstance();
            
            // Verificar que existe
            $pregunta = $this->tipoDiagnosticoModel->getPreguntaById($id);
            
            if (!$pregunta) {
                Response::error('Pregunta no encontrada', 404);
            }
            
            // Eliminar
            $query = "DELETE FROM preguntas_diagnostico WHERE id_pregunta = ?";
            $result = $db->query($query, [$id]);
            
            if ($result) {
                Response::success(['mensaje' => 'Pregunta eliminada exitosamente']);
            }
            
            Response::error('Error al eliminar pregunta', 500);
        } catch (Exception $e) {
            Response::error('Error al eliminar pregunta: ' . $e->getMessage(), 500);
        }
    }
}
