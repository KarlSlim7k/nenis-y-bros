<?php
/**
 * Controlador: EvaluacionController
 * Gestiona las evaluaciones, intentos y certificados
 */

class EvaluacionController {
    private $evaluacion;
    private $pregunta;
    private $intento;
    private $certificado;
    
    public function __construct() {
        $this->evaluacion = new Evaluacion();
        $this->pregunta = new PreguntaEvaluacion();
        $this->intento = new IntentoEvaluacion();
        $this->certificado = new Certificado();
    }
    
    /**
     * Crear nueva evaluación (Admin/Instructor)
     * POST /evaluaciones
     */
    public function crear() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos
        $validator = new Validator($data, [
            'id_leccion' => 'required|integer',
            'titulo' => 'required|min:3|max:200',
            'tipo_evaluacion' => 'required'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $idEvaluacion = $this->evaluacion->create($data);
            
            // Si incluye preguntas, crearlas
            if (isset($data['preguntas']) && is_array($data['preguntas'])) {
                foreach ($data['preguntas'] as $preguntaData) {
                    $preguntaData['id_evaluacion'] = $idEvaluacion;
                    
                    if (isset($preguntaData['opciones'])) {
                        $opciones = $preguntaData['opciones'];
                        unset($preguntaData['opciones']);
                        $this->pregunta->createConOpciones($preguntaData, $opciones);
                    } else {
                        $this->pregunta->create($preguntaData);
                    }
                }
            }
            
            Response::success([
                'id_evaluacion' => $idEvaluacion,
                'mensaje' => 'Evaluación creada exitosamente'
            ], 201);
        } catch (Exception $e) {
            Response::error('Error al crear evaluación: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtener evaluación completa
     * GET /evaluaciones/:id
     */
    public function obtener($id) {
        try {
            $evaluacion = $this->evaluacion->getEvaluacionCompleta($id);
            
            if (!$evaluacion) {
                Response::error('Evaluación no encontrada', 404);
            }
            
            Response::success($evaluacion);
        } catch (Exception $e) {
            Response::error('Error al obtener evaluación: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtener evaluaciones de una lección
     * GET /lecciones/:id/evaluaciones
     */
    public function listarPorLeccion($idLeccion) {
        try {
            $evaluaciones = $this->evaluacion->findByLeccion($idLeccion);
            Response::success($evaluaciones);
        } catch (Exception $e) {
            Response::error('Error al listar evaluaciones: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtener evaluaciones de un curso
     * GET /cursos/:id/evaluaciones
     */
    public function listarPorCurso($idCurso) {
        try {
            $evaluaciones = $this->evaluacion->findByCurso($idCurso);
            Response::success($evaluaciones);
        } catch (Exception $e) {
            Response::error('Error al listar evaluaciones: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Iniciar intento de evaluación
     * POST /evaluaciones/:id/iniciar
     */
    public function iniciarIntento($id) {
        $usuario = AuthMiddleware::verify();
        
        try {
            // Verificar si puede iniciar
            $puede = $this->evaluacion->puedeIniciarEvaluacion($id, $usuario['id_usuario']);
            if (!$puede['puede']) {
                Response::error($puede['razon'], 403);
            }
            
            // Verificar si ya tiene intento en progreso
            $intentoEnProgreso = $this->intento->tieneIntentoEnProgreso($id, $usuario['id_usuario']);
            if ($intentoEnProgreso) {
                Response::success([
                    'id_intento' => $intentoEnProgreso,
                    'mensaje' => 'Ya tiene un intento en progreso'
                ]);
            }
            
            // Iniciar nuevo intento
            $idIntento = $this->intento->iniciarIntento($id, $usuario['id_usuario']);
            
            // Obtener evaluación con preguntas (sin respuestas correctas)
            $evaluacion = $this->evaluacion->getEvaluacionCompleta($id, false);
            
            Response::success([
                'id_intento' => $idIntento,
                'evaluacion' => $evaluacion,
                'mensaje' => 'Intento iniciado exitosamente'
            ], 201);
        } catch (Exception $e) {
            Response::error('Error al iniciar intento: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Guardar respuesta durante intento
     * POST /evaluaciones/intentos/:id/responder
     */
    public function responder($idIntento) {
        $usuario = AuthMiddleware::verify();
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar
        $validator = new Validator($data, [
            'id_pregunta' => 'required'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            // Verificar que el intento pertenece al usuario
            $intento = $this->intento->findById($idIntento);
            if (!$intento || $intento['id_usuario'] != $usuario['id_usuario']) {
                Response::error('Intento no encontrado', 404);
            }
            
            if ($intento['estado'] !== 'en_progreso') {
                Response::error('El intento ya fue completado', 400);
            }
            
            $this->intento->guardarRespuesta(
                $idIntento,
                $data['id_pregunta'],
                $data
            );
            
            Response::success(['mensaje' => 'Respuesta guardada']);
        } catch (Exception $e) {
            Response::error('Error al guardar respuesta: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Finalizar y calificar intento
     * POST /evaluaciones/intentos/:id/finalizar
     */
    public function finalizarIntento($idIntento) {
        $usuario = AuthMiddleware::verify();
        
        try {
            // Verificar que el intento pertenece al usuario
            $intento = $this->intento->findById($idIntento);
            if (!$intento || $intento['id_usuario'] != $usuario['id_usuario']) {
                Response::error('Intento no encontrado', 404);
            }
            
            if ($intento['estado'] !== 'en_progreso') {
                Response::error('El intento ya fue completado', 400);
            }
            
            // Finalizar y calificar
            $resultado = $this->intento->finalizarIntento($idIntento);
            
            // Si aprobó, verificar si puede obtener certificado del curso
            if ($resultado['aprobado']) {
                $evaluacion = $this->evaluacion->findById($intento['id_evaluacion']);
                
                // Obtener curso de la lección
                $query = "SELECT id_curso FROM lecciones WHERE id_leccion = ?";
                $db = Database::getInstance();
                $leccion = $db->fetchOne($query, [$evaluacion['id_leccion']]);
                
                if ($leccion) {
                    $puedeObtener = $this->certificado->puedeObtenerCertificado(
                        $usuario['id_usuario'], 
                        $leccion['id_curso']
                    );
                    
                    if ($puedeObtener['puede']) {
                        $idCertificado = $this->certificado->generar(
                            $usuario['id_usuario'],
                            $leccion['id_curso']
                        );
                        $resultado['certificado_generado'] = true;
                        $resultado['id_certificado'] = $idCertificado;
                    }
                }
            }
            
            Response::success($resultado);
        } catch (Exception $e) {
            Response::error('Error al finalizar intento: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtener resultados de un intento
     * GET /evaluaciones/intentos/:id/resultados
     */
    public function obtenerResultados($idIntento) {
        $usuario = AuthMiddleware::verify();
        
        try {
            $intento = $this->intento->findById($idIntento);
            if (!$intento || $intento['id_usuario'] != $usuario['id_usuario']) {
                Response::error('Intento no encontrado', 404);
            }
            
            $respuestas = $this->intento->getRespuestas($idIntento);
            
            Response::success([
                'intento' => $intento,
                'respuestas' => $respuestas
            ]);
        } catch (Exception $e) {
            Response::error('Error al obtener resultados: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtener historial de intentos del usuario para una evaluación
     * GET /evaluaciones/:id/mis-intentos
     */
    public function misIntentos($idEvaluacion) {
        $usuario = AuthMiddleware::verify();
        
        try {
            $intentos = $this->intento->getIntentosByUsuario($idEvaluacion, $usuario['id_usuario']);
            Response::success($intentos);
        } catch (Exception $e) {
            Response::error('Error al obtener intentos: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtener estadísticas de una evaluación (Admin/Instructor)
     * GET /evaluaciones/:id/estadisticas
     */
    public function obtenerEstadisticas($id) {
        try {
            $estadisticas = $this->evaluacion->getEstadisticas($id);
            Response::success($estadisticas);
        } catch (Exception $e) {
            Response::error('Error al obtener estadísticas: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtener certificados del usuario
     * GET /mis-certificados
     */
    public function misCertificados() {
        $usuario = AuthMiddleware::verify();
        
        try {
            $certificados = $this->certificado->getCertificadosByUsuario($usuario['id_usuario']);
            Response::success($certificados);
        } catch (Exception $e) {
            Response::error('Error al obtener certificados: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtener certificado específico
     * GET /certificados/:id
     */
    public function obtenerCertificado($id) {
        $usuario = AuthMiddleware::verify();
        
        try {
            $certificado = $this->certificado->findById($id);
            
            if (!$certificado || $certificado['id_usuario'] != $usuario['id_usuario']) {
                Response::error('Certificado no encontrado', 404);
            }
            
            Response::success($certificado);
        } catch (Exception $e) {
            Response::error('Error al obtener certificado: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Verificar certificado público
     * GET /certificados/verificar/:codigo
     */
    public function verificarCertificado($codigo) {
        try {
            $certificado = $this->certificado->verificar($codigo);
            
            if (!$certificado) {
                Response::error('Certificado no válido', 404);
            }
            
            Response::success($certificado);
        } catch (Exception $e) {
            Response::error('Error al verificar certificado: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Actualizar evaluación (Admin/Instructor)
     * PUT /evaluaciones/:id
     */
    public function actualizar($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $this->evaluacion->update($id, $data);
            Response::success(['mensaje' => 'Evaluación actualizada']);
        } catch (Exception $e) {
            Response::error('Error al actualizar evaluación: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Eliminar evaluación (Admin)
     * DELETE /evaluaciones/:id
     */
    public function eliminar($id) {
        try {
            $this->evaluacion->delete($id);
            Response::success(['mensaje' => 'Evaluación eliminada']);
        } catch (Exception $e) {
            Response::error('Error al eliminar evaluación: ' . $e->getMessage(), 500);
        }
    }
}
