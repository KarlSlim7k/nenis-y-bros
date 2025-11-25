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
        
        $diagnostico = $this->tipoDiagnosticoModel->findById($id, true);
        
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
}
