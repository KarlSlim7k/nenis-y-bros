<?php
/**
 * ============================================================================
 * CONTROLADOR: ONBOARDING
 * ============================================================================
 * Gestiona el flujo de onboarding para nuevos usuarios
 * ============================================================================
 */

class OnboardingController {
    
    private $cuestionarioModel;
    
    public function __construct() {
        $this->cuestionarioModel = new CuestionarioInicial();
    }
    
    /**
     * GET /api/v1/onboarding/preguntas
     * Obtener preguntas del cuestionario inicial
     */
    public function getPreguntas() {
        try {
            $preguntas = $this->cuestionarioModel->getPreguntas();
            Response::success(['preguntas' => $preguntas]);
        } catch (Exception $e) {
            Logger::error("Error al obtener preguntas onboarding: " . $e->getMessage());
            Response::serverError("Error al cargar el cuestionario");
        }
    }
    
    /**
     * POST /api/v1/onboarding/guardar-respuestas
     * Guardar respuestas y calcular nivel
     */
    public function guardarRespuestas() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['respuestas']) || !is_array($data['respuestas'])) {
            Response::badRequest("Formato de respuestas inválido");
            return;
        }
        
        // Generar o usar token temporal
        $token = $data['token'] ?? bin2hex(random_bytes(32));
        
        try {
            $resultado = $this->cuestionarioModel->saveRespuestas($token, $data['respuestas']);
            
            // Obtener cursos recomendados
            $cursos = $this->cuestionarioModel->getCursosRecomendados($resultado['nivel']);
            
            Response::success([
                'nivel' => $resultado['nivel'],
                'puntaje' => $resultado['puntaje'],
                'token' => $resultado['token'],
                'cursos_recomendados' => $cursos
            ], "Cuestionario completado exitosamente");
            
        } catch (Exception $e) {
            Logger::error("Error al guardar respuestas onboarding: " . $e->getMessage());
            Response::serverError("Error al procesar las respuestas");
        }
    }
    
    /**
     * GET /api/v1/onboarding/cursos-recomendados/{nivel}
     * Obtener cursos recomendados por nivel
     */
    public function cursosRecomendados($nivel) {
        $nivelesValidos = ['principiante', 'intermedio', 'avanzado'];
        
        if (!in_array($nivel, $nivelesValidos)) {
            Response::badRequest("Nivel inválido");
            return;
        }
        
        try {
            $cursos = $this->cuestionarioModel->getCursosRecomendados($nivel);
            Response::success(['cursos' => $cursos]);
        } catch (Exception $e) {
            Logger::error("Error al obtener cursos recomendados: " . $e->getMessage());
            Response::serverError("Error al cargar recomendaciones");
        }
    }
}
