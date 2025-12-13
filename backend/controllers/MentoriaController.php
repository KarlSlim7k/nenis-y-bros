<?php
/**
 * ============================================================================
 * CONTROLADOR: MentorIA
 * ============================================================================
 * Gestiona el sistema de chat con instructores y asistente virtual
 * 
 * @author Nenis y Bros
 * @version 1.0
 * @date 2025-11-18
 * ============================================================================
 */

class MentoriaController {
    private $conversacionModel;
    private $mensajeModel;
    private $disponibilidadModel;
    private $estadoPresenciaModel;
    private $puntosModel;
    private $notificacionModel;
    private $mentoriaService;
    
    public function __construct() {
        $this->conversacionModel = new Conversacion();
        $this->mensajeModel = new Mensaje();
        $this->disponibilidadModel = new DisponibilidadInstructor();
        $this->estadoPresenciaModel = new EstadoPresencia();
        $this->puntosModel = new PuntosUsuario();
        $this->notificacionModel = new Notificacion();
        $this->mentoriaService = new MentoriaService();
    }
    
    /**
     * ========================================================================
     * ENDPOINTS - CONVERSACIONES
     * ========================================================================
     */
    
    /**
     * POST /api/v1/chat/conversaciones
     * Crear o recuperar conversación con instructor
     */
    public function crearConversacion() {
        $usuario = AuthMiddleware::requireAuth();
        
        // Mapear tipo_usuario a rol si es necesario
        $rol = $usuario['tipo_usuario'] ?? $usuario['rol'] ?? '';
        
        // Solo alumnos pueden iniciar conversaciones (emprendedor o empresario)
        if (!in_array($rol, ['emprendedor', 'empresario'])) {
            Response::error('Solo los alumnos pueden iniciar conversaciones', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar entrada
        $rules = [
            'id_curso' => 'required|integer'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        $idCurso = $data['id_curso'];
        $idAlumno = $usuario['id_usuario'];
        
        try {
            // Verificar que el alumno está inscrito en el curso
            if (!$this->conversacionModel->verificarInscripcion($idAlumno, $idCurso)) {
                Response::error('No estás inscrito en este curso', 403);
            }
            
            // Obtener instructor del curso
            $idInstructor = $this->conversacionModel->getInstructorCurso($idCurso);
            if (!$idInstructor) {
                Response::error('El curso no tiene un instructor asignado', 404);
            }
            
            // Obtener o crear conversación
            $conversacion = $this->conversacionModel->getOrCreate(
                $idCurso,
                $idAlumno,
                $idInstructor,
                'instructor'
            );
            
            // Verificar disponibilidad del instructor
            $estadoInstructor = $this->estadoPresenciaModel->get($idInstructor);
            $disponible = $this->disponibilidadModel->estaDisponibleAhora($idInstructor);
            
            // Agregar información de disponibilidad
            $conversacion['instructor_disponible'] = $disponible;
            $conversacion['instructor_estado'] = $estadoInstructor['estado'] ?? 'desconectado';
            
            // Si es primera vez, otorgar puntos
            $mensajes = $this->mensajeModel->getPorConversacion($conversacion['id_conversacion'], 1, 1);
            if (empty($mensajes)) {
                $this->puntosModel->otorgarPuntos(
                    $idAlumno,
                    'primera_pregunta_instructor',
                    'conversacion',
                    $conversacion['id_conversacion']
                );
            }
            
            Response::success($conversacion, 'Conversación creada/recuperada exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al crear conversación', [
                'usuario' => $idAlumno,
                'curso' => $idCurso,
                'error' => $e->getMessage()
            ]);
            Response::error('Error al crear conversación: ' . $e->getMessage());
        }
    }
    
    /**
     * GET /api/v1/chat/conversaciones
     * Listar conversaciones del usuario
     */
    public function getConversaciones() {
        $usuario = AuthMiddleware::requireAuth();
        
        $estado = $_GET['estado'] ?? 'activa';
        $tipoUsuario = $usuario['tipo_usuario'] ?? $usuario['rol'] ?? '';
        $rol = ($tipoUsuario === 'mentor' || $tipoUsuario === 'administrador') ? 'instructor' : 'alumno';
        
        try {
            $conversaciones = $this->conversacionModel->listarPorUsuario(
                $usuario['id_usuario'],
                $rol,
                $estado
            );
            
            // Agregar conteo de mensajes no leídos
            foreach ($conversaciones as &$conv) {
                $conv['no_leidos'] = $this->mensajeModel->contarNoLeidos(
                    $conv['id_conversacion'],
                    $rol
                );
            }
            
            Response::success([
                'conversaciones' => $conversaciones,
                'total' => count($conversaciones)
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al listar conversaciones', [
                'usuario' => $usuario['id_usuario'],
                'error' => $e->getMessage()
            ]);
            Response::error('Error al obtener conversaciones');
        }
    }
    
    /**
     * GET /api/v1/chat/conversaciones/{id}
     * Obtener detalles de una conversación
     */
    public function getConversacion($idConversacion) {
        $usuario = AuthMiddleware::requireAuth();
        
        try {
            // Verificar que el usuario pertenece a la conversación
            if (!$this->conversacionModel->perteneceAlUsuario($idConversacion, $usuario['id_usuario'])) {
                Response::error('No tienes acceso a esta conversación', 403);
            }
            
            $conversacion = $this->conversacionModel->getById($idConversacion);
            if (!$conversacion) {
                Response::error('Conversación no encontrada', 404);
            }
            
            // Obtener mensajes recientes
            $mensajes = $this->mensajeModel->getRecientes($idConversacion, 50);
            
            // Marcar mensajes como leídos
            $rol = ($usuario['id_usuario'] == $conversacion['id_alumno']) ? 'alumno' : 'instructor';
            $this->mensajeModel->marcarTodosLeidos($idConversacion, $usuario['id_usuario'], $rol);
            
            Response::success([
                'conversacion' => $conversacion,
                'mensajes' => $mensajes
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener conversación', [
                'usuario' => $usuario['id_usuario'],
                'conversacion' => $idConversacion,
                'error' => $e->getMessage()
            ]);
            Response::error('Error al obtener conversación');
        }
    }
    
    /**
     * POST /api/v1/chat/conversaciones/{id}/archivar
     * Archivar conversación
     */
    public function archivarConversacion($idConversacion) {
        $usuario = AuthMiddleware::requireAuth();
        
        try {
            if (!$this->conversacionModel->archivar($idConversacion, $usuario['id_usuario'])) {
                Response::error('No se pudo archivar la conversación', 403);
            }
            
            Response::success(null, 'Conversación archivada exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al archivar conversación', [
                'usuario' => $usuario['id_usuario'],
                'conversacion' => $idConversacion,
                'error' => $e->getMessage()
            ]);
            Response::error('Error al archivar conversación');
        }
    }
    
    /**
     * ========================================================================
     * ENDPOINTS - MENSAJES
     * ========================================================================
     */
    
    /**
     * POST /api/v1/chat/mensajes
     * Enviar mensaje
     */
    public function enviarMensaje() {
        $usuario = AuthMiddleware::requireAuth();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar entrada
        $rules = [
            'id_conversacion' => 'required|integer',
            'contenido' => 'required|string|min:1|max:5000'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        $idConversacion = $data['id_conversacion'];
        
        try {
            // Verificar acceso
            if (!$this->conversacionModel->perteneceAlUsuario($idConversacion, $usuario['id_usuario'])) {
                Response::error('No tienes acceso a esta conversación', 403);
            }
            
            // Obtener conversación
            $conversacion = $this->conversacionModel->getById($idConversacion);
            
            // Determinar tipo de remitente
            $remitenteType = ($usuario['id_usuario'] == $conversacion['id_alumno']) ? 'alumno' : 'instructor';
            
            // Crear mensaje
            $idMensaje = $this->mensajeModel->crear([
                'id_conversacion' => $idConversacion,
                'id_remitente' => $usuario['id_usuario'],
                'remitente_tipo' => $remitenteType,
                'contenido' => $data['contenido'],
                'tipo_mensaje' => $data['tipo_mensaje'] ?? 'texto',
                'metadata' => $data['metadata'] ?? null
            ]);
            
            // Si es instructor respondiendo, otorgar puntos
            if ($remitenteType === 'instructor') {
                $this->puntosModel->otorgarPuntos(
                    $conversacion['id_alumno'],
                    'instructor_responde',
                    'mensaje',
                    $idMensaje
                );
                
                // TODO: Notificar al alumno (deshabilitado temporalmente)
                /*
                $this->notificacionModel->crear(
                    $conversacion['id_alumno'],
                    'chat_mensaje',
                    'Nuevo mensaje del instructor',
                    $usuario['nombre'] . ' te ha enviado un mensaje',
                    'chat',
                    json_encode([
                        'id_conversacion' => $idConversacion,
                        'id_mensaje' => $idMensaje
                    ])
                );
                */
            } else {
                // TODO: Notificar al instructor (deshabilitado temporalmente)
                /*
                $this->notificacionModel->crear(
                    $conversacion['id_instructor'],
                    'chat_mensaje',
                    'Nuevo mensaje de alumno',
                    $usuario['nombre'] . ' te ha enviado un mensaje',
                    'chat',
                    json_encode([
                        'id_conversacion' => $idConversacion,
                        'id_mensaje' => $idMensaje
                    ])
                );
                */
            }
            
            // Actualizar actividad del usuario
            $this->estadoPresenciaModel->actualizarActividad($usuario['id_usuario']);
            
            // Obtener el mensaje creado con detalles
            $query = "
                SELECT 
                    m.*,
                    u.nombre as remitente_nombre,
                    u.foto_perfil as remitente_foto
                FROM mensajes m
                LEFT JOIN usuarios u ON m.id_remitente = u.id_usuario
                WHERE m.id_mensaje = ?
            ";
            $db = Database::getInstance();
            $mensaje = $db->fetchOne($query, [$idMensaje]);
            
            Response::success($mensaje, 'Mensaje enviado exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al enviar mensaje', [
                'usuario' => $usuario['id_usuario'],
                'conversacion' => $idConversacion,
                'error' => $e->getMessage()
            ]);
            Response::error('Error al enviar mensaje: ' . $e->getMessage());
        }
    }
    
    /**
     * GET /api/v1/chat/mensajes/{id_conversacion}/nuevos
     * Obtener mensajes nuevos (long-polling)
     */
    public function getMensajesNuevos($idConversacion) {
        $usuario = AuthMiddleware::requireAuth();
        
        // Verificar acceso
        if (!$this->conversacionModel->perteneceAlUsuario($idConversacion, $usuario['id_usuario'])) {
            Response::error('No tienes acceso a esta conversación', 403);
        }
        
        $desdeFecha = $_GET['desde'] ?? date('Y-m-d H:i:s');
        
        try {
            // Long-polling: esperar hasta 30 segundos por nuevos mensajes
            $timeout = 30;
            $inicio = time();
            
            do {
                $mensajes = $this->mensajeModel->getDesde($idConversacion, $desdeFecha);
                
                if (!empty($mensajes)) {
                    // Marcar como leídos
                    $conversacion = $this->conversacionModel->getById($idConversacion);
                    $rol = ($usuario['id_usuario'] == $conversacion['id_alumno']) ? 'alumno' : 'instructor';
                    $this->mensajeModel->marcarTodosLeidos($idConversacion, $usuario['id_usuario'], $rol);
                    
                    Response::success($mensajes);
                }
                
                // Esperar 1 segundo antes de volver a consultar
                sleep(1);
                
            } while ((time() - $inicio) < $timeout);
            
            // No hay mensajes nuevos
            Response::success([]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener mensajes nuevos', [
                'usuario' => $usuario['id_usuario'],
                'conversacion' => $idConversacion,
                'error' => $e->getMessage()
            ]);
            Response::error('Error al obtener mensajes');
        }
    }
    
    /**
     * PUT /api/v1/chat/mensajes/{id}/leer
     * Marcar mensaje como leído
     */
    public function marcarLeido($idMensaje) {
        $usuario = AuthMiddleware::requireAuth();
        
        try {
            $this->mensajeModel->marcarLeido($idMensaje);
            Response::success(null, 'Mensaje marcado como leído');
            
        } catch (Exception $e) {
            Logger::error('Error al marcar mensaje leído', [
                'usuario' => $usuario['id_usuario'],
                'mensaje' => $idMensaje,
                'error' => $e->getMessage()
            ]);
            Response::error('Error al marcar mensaje');
        }
    }
    
    /**
     * ========================================================================
     * ENDPOINTS - DISPONIBILIDAD
     * ========================================================================
     */
    
    /**
     * GET /api/v1/chat/disponibilidad/{id_instructor}
     * Obtener disponibilidad de un instructor
     */
    public function getDisponibilidad($idInstructor) {
        $usuario = AuthMiddleware::requireAuth();
        
        try {
            $disponibilidad = $this->disponibilidadModel->getPorInstructor($idInstructor);
            $proximaDisponibilidad = $this->disponibilidadModel->getProximaDisponibilidad($idInstructor);
            $estaDisponible = $this->disponibilidadModel->estaDisponibleAhora($idInstructor);
            $estadoPresencia = $this->estadoPresenciaModel->get($idInstructor);
            
            Response::success([
                'disponibilidad' => $disponibilidad,
                'proxima_disponibilidad' => $proximaDisponibilidad,
                'disponible_ahora' => $estaDisponible,
                'estado_presencia' => $estadoPresencia
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener disponibilidad', [
                'instructor' => $idInstructor,
                'error' => $e->getMessage()
            ]);
            Response::error('Error al obtener disponibilidad');
        }
    }
    
    /**
     * POST /api/v1/chat/disponibilidad
     * Configurar disponibilidad del instructor
     */
    public function configurarDisponibilidad() {
        $usuario = AuthMiddleware::requireAuth();
        
        // Solo instructores
        $tipoUsuario = $usuario['tipo_usuario'] ?? $usuario['rol'] ?? '';
        if ($tipoUsuario !== 'mentor') {
            Response::error('Solo los instructores pueden configurar disponibilidad', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar entrada
        $rules = [
            'bloques' => 'required|array'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $this->disponibilidadModel->configurarSemana($usuario['id_usuario'], $data['bloques']);
            
            Response::success(null, 'Disponibilidad configurada exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al configurar disponibilidad', [
                'usuario' => $usuario['id_usuario'],
                'error' => $e->getMessage()
            ]);
            Response::error('Error al configurar disponibilidad: ' . $e->getMessage());
        }
    }
    
    /**
     * PUT /api/v1/chat/estado
     * Cambiar estado de presencia
     */
    public function cambiarEstado() {
        $usuario = AuthMiddleware::requireAuth();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar entrada
        $rules = [
            'estado' => 'required|in:en_linea,ausente,ocupado,desconectado'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $this->estadoPresenciaModel->actualizar(
                $usuario['id_usuario'],
                $data['estado'],
                $data['mensaje'] ?? null
            );
            
            Response::success(null, 'Estado actualizado exitosamente');
            
        } catch (Exception $e) {
            Logger::error('Error al cambiar estado', [
                'usuario' => $usuario['id_usuario'],
                'error' => $e->getMessage()
            ]);
            Response::error('Error al cambiar estado: ' . $e->getMessage());
        }
    }
    
    /**
     * ========================================================================
     * ENDPOINTS - ESTADÍSTICAS
     * ========================================================================
     */
    
    /**
     * GET /api/v1/chat/estadisticas/instructor
     * Estadísticas de conversaciones del instructor
     */
    public function getEstadisticasInstructor() {
        $usuario = AuthMiddleware::requireAuth();
        
        // Solo instructores y administradores
        $tipoUsuario = $usuario['tipo_usuario'] ?? $usuario['rol'] ?? '';
        if ($tipoUsuario !== 'mentor' && $tipoUsuario !== 'administrador') {
            Response::error('Solo los instructores pueden ver estas estadísticas', 403);
        }
        
        try {
            $estadisticas = $this->conversacionModel->getEstadisticasInstructor($usuario['id_usuario']);
            
            Response::success($estadisticas);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener estadísticas instructor', [
                'usuario' => $usuario['id_usuario'],
                'error' => $e->getMessage()
            ]);
            Response::error('Error al obtener estadísticas');
        }
    }
    
    /**
     * ========================================================================
     * ENDPOINTS - MENTORIA IA (Placeholder para Fase 5B.2)
     * ========================================================================
     */
    
    /**
     * POST /api/v1/mentoria/iniciar
     * Iniciar conversación con MentorIA
     */
    public function iniciarMentoria() {
        $usuario = AuthMiddleware::requireAuth();
        
        try {
            // Obtener perfil empresarial para contexto
            $perfilModel = new PerfilEmpresarial();
            $perfil = $perfilModel->findByUser($usuario['id_usuario']);
            
            $contextoEmpresarial = null;
            if ($perfil) {
                $contextoEmpresarial = "Tipo de negocio: {$perfil['tipo_negocio']}\n" .
                                     "Sector: {$perfil['sector_industria']}\n" .
                                     "Tamaño: {$perfil['tamano_empresa']} empleados\n" .
                                     "Años de experiencia: {$perfil['anos_experiencia']}";
            }
            
            // Generar mensaje de bienvenida con IA
            $mensajeInicial = [
                ['role' => 'user', 'content' => '¡Hola! Soy un emprendedor que necesita ayuda con mi negocio.']
            ];
            
            $respuestaIA = $this->mentoriaService->obtenerRespuesta($mensajeInicial, $contextoEmpresarial);
            
            if (!$respuestaIA['success']) {
                Response::error('No se pudo conectar con MentorIA: ' . $respuestaIA['error']);
            }
            
            // Generar sugerencias de temas
            $sugerencias = [];
            if ($contextoEmpresarial) {
                $respuestaSugerencias = $this->mentoriaService->generarSugerencias($contextoEmpresarial);
                if ($respuestaSugerencias['success']) {
                    $sugerencias = $respuestaSugerencias['sugerencias'];
                }
            }
            
            Logger::activity($usuario['id_usuario'], 'Sesión de MentorIA iniciada', [
                'tokens_usados' => $respuestaIA['tokens_used'] ?? 0
            ]);
            
            Response::success([
                'mensaje_bienvenida' => $respuestaIA['response'],
                'sugerencias_temas' => $sugerencias,
                'contexto_cargado' => !is_null($contextoEmpresarial)
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al iniciar MentorIA', [
                'usuario' => $usuario['id_usuario'],
                'error' => $e->getMessage()
            ]);
            Response::error('Error al iniciar sesión de mentoría: ' . $e->getMessage());
        }
    }
    
    /**
     * POST /api/v1/mentoria/preguntar
     * Enviar pregunta a MentorIA
     */
    public function preguntarMentoria() {
        $usuario = AuthMiddleware::requireAuth();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar entrada
        $rules = [
            'pregunta' => 'required|string|min:3',
            'historial' => 'array'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            // Obtener perfil empresarial para contexto
            $perfilModel = new PerfilEmpresarial();
            $perfil = $perfilModel->findByUser($usuario['id_usuario']);
            
            $contextoEmpresarial = null;
            if ($perfil) {
                $contextoEmpresarial = "Tipo de negocio: {$perfil['tipo_negocio']}\n" .
                                     "Sector: {$perfil['sector_industria']}\n" .
                                     "Tamaño: {$perfil['tamano_empresa']} empleados\n" .
                                     "Años de experiencia: {$perfil['anos_experiencia']}";
            }
            
            // Construir historial de mensajes
            $mensajes = $data['historial'] ?? [];
            $mensajes[] = [
                'role' => 'user',
                'content' => $data['pregunta']
            ];
            
            // Obtener respuesta de la IA
            $respuestaIA = $this->mentoriaService->obtenerRespuesta($mensajes, $contextoEmpresarial);
            
            if (!$respuestaIA['success']) {
                Response::error('Error al obtener respuesta de MentorIA: ' . $respuestaIA['error']);
            }
            
            Logger::activity($usuario['id_usuario'], 'Consulta a MentorIA procesada', [
                'pregunta_length' => strlen($data['pregunta']),
                'tokens_usados' => $respuestaIA['tokens_used'] ?? 0
            ]);
            
            Response::success([
                'respuesta' => $respuestaIA['response'],
                'tokens_usados' => $respuestaIA['tokens_used'] ?? 0,
                'finish_reason' => $respuestaIA['finish_reason'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al preguntar a MentorIA', [
                'usuario' => $usuario['id_usuario'],
                'error' => $e->getMessage()
            ]);
            Response::error('Error al procesar pregunta: ' . $e->getMessage());
        }
    }
    
    /**
     * POST /api/v1/mentoria/feedback
     * Enviar feedback sobre respuesta de MentorIA
     */
    public function feedbackMentoria() {
        $usuario = AuthMiddleware::requireAuth();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar entrada
        $rules = [
            'interaccion_id' => 'required|string',
            'calificacion' => 'required|in:positivo,negativo,neutral',
            'comentario' => 'string'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            // Registrar feedback en logs para análisis posterior
            Logger::activity($usuario['id_usuario'], 'Feedback de MentorIA recibido', [
                'interaccion_id' => $data['interaccion_id'],
                'calificacion' => $data['calificacion'],
                'comentario' => $data['comentario'] ?? null
            ]);
            
            // TODO: En el futuro, almacenar en tabla dedicada para análisis
            // y mejora del modelo con fine-tuning
            
            Response::success(null, 'Gracias por tu feedback. Nos ayuda a mejorar MentorIA.');
            
        } catch (Exception $e) {
            Logger::error('Error al procesar feedback de MentorIA', [
                'usuario' => $usuario['id_usuario'],
                'error' => $e->getMessage()
            ]);
            Response::error('Error al procesar feedback');
        }
    }
    
    /**
     * GET /api/v1/mentoria/estadisticas
     * Estadísticas de uso de MentorIA
     */
    public function getEstadisticasMentoria() {
        $usuario = AuthMiddleware::requireAuth();
        
        try {
            // Health check del servicio
            $healthCheck = $this->mentoriaService->healthCheck();
            
            // TODO: En el futuro, obtener estadísticas reales de uso desde la BD
            // Por ahora, devolver información básica del servicio
            
            $estadisticas = [
                'servicio_activo' => $healthCheck['success'],
                'modelo_actual' => $healthCheck['model'] ?? 'N/A',
                'mensaje' => $healthCheck['message'],
                // Placeholder para estadísticas futuras
                'consultas_realizadas' => 0,
                'tokens_totales' => 0,
                'promedio_calificacion' => null
            ];
            
            Response::success($estadisticas);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener estadísticas de MentorIA', [
                'usuario' => $usuario['id_usuario'],
                'error' => $e->getMessage()
            ]);
            Response::error('Error al obtener estadísticas');
        }
    }
}
