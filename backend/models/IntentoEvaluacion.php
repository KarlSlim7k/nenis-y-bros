<?php
/**
 * Modelo: IntentoEvaluacion
 * Gestiona los intentos de evaluación de los usuarios
 */

class IntentoEvaluacion {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Iniciar un nuevo intento
     */
    public function iniciarIntento($idEvaluacion, $idUsuario) {
        // Obtener número de intento
        $query = "SELECT COALESCE(MAX(numero_intento), 0) + 1 as siguiente_intento 
                 FROM intentos_evaluacion 
                 WHERE id_evaluacion = ? AND id_usuario = ?";
        $result = $this->db->fetchOne($query, [$idEvaluacion, $idUsuario]);
        $numeroIntento = $result['siguiente_intento'];
        
        // Crear intento
        $query = "INSERT INTO intentos_evaluacion (
            id_evaluacion, id_usuario, numero_intento, estado
        ) VALUES (?, ?, ?, 'en_progreso')";
        
        try {
            $idIntento = $this->db->insert($query, [$idEvaluacion, $idUsuario, $numeroIntento]);
            Logger::activity($idUsuario, "Intento de evaluación iniciado", [
                'id_intento' => $idIntento,
                'id_evaluacion' => $idEvaluacion,
                'numero_intento' => $numeroIntento
            ]);
            return $idIntento;
        } catch (Exception $e) {
            Logger::error("Error al iniciar intento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Guardar respuesta de usuario
     */
    public function guardarRespuesta($idIntento, $idPregunta, $respuestaData) {
        // Verificar si ya existe respuesta
        $query = "SELECT id_respuesta_evaluacion FROM respuestas_evaluacion 
                 WHERE id_intento = ? AND id_pregunta_evaluacion = ?";
        $existente = $this->db->fetchOne($query, [$idIntento, $idPregunta]);
        
        if ($existente) {
            // Actualizar
            $query = "UPDATE respuestas_evaluacion SET 
                     id_opcion_seleccionada = ?, 
                     respuesta_texto = ?,
                     fecha_respuesta = CURRENT_TIMESTAMP
                     WHERE id_respuesta_evaluacion = ?";
            return $this->db->query($query, [
                $respuestaData['id_opcion'] ?? null,
                $respuestaData['respuesta_texto'] ?? null,
                $existente['id_respuesta_evaluacion']
            ]);
        } else {
            // Insertar
            $query = "INSERT INTO respuestas_evaluacion (
                id_intento, id_pregunta_evaluacion, id_opcion_seleccionada, respuesta_texto
            ) VALUES (?, ?, ?, ?)";
            return $this->db->insert($query, [
                $idIntento,
                $idPregunta,
                $respuestaData['id_opcion'] ?? null,
                $respuestaData['respuesta_texto'] ?? null
            ]);
        }
    }
    
    /**
     * Finalizar y calificar intento
     */
    public function finalizarIntento($idIntento) {
        // Obtener intento
        $intento = $this->findById($idIntento);
        if (!$intento) throw new Exception("Intento no encontrado");
        
        // Obtener evaluación
        $queryEval = "SELECT * FROM evaluaciones WHERE id_evaluacion = ?";
        $evaluacion = $this->db->fetchOne($queryEval, [$intento['id_evaluacion']]);
        
        // Obtener preguntas y respuestas
        $queryPreguntas = "SELECT 
            pe.*, 
            re.id_opcion_seleccionada,
            re.respuesta_texto,
            op.es_correcta
        FROM preguntas_evaluacion pe
        LEFT JOIN respuestas_evaluacion re ON pe.id_pregunta_evaluacion = re.id_pregunta_evaluacion 
            AND re.id_intento = ?
        LEFT JOIN opciones_pregunta op ON re.id_opcion_seleccionada = op.id_opcion
        WHERE pe.id_evaluacion = ?";
        
        $preguntas = $this->db->fetchAll($queryPreguntas, [$idIntento, $intento['id_evaluacion']]);
        
        $puntajeTotal = 0;
        $puntajeObtenido = 0;
        
        // Calificar cada pregunta
        foreach ($preguntas as $pregunta) {
            $puntajeTotal += $pregunta['puntos'];
            $esCorrecta = false;
            $puntosObtenidos = 0;
            
            if ($pregunta['tipo_pregunta'] === 'multiple_choice' || $pregunta['tipo_pregunta'] === 'verdadero_falso') {
                if ($pregunta['es_correcta']) {
                    $esCorrecta = true;
                    $puntosObtenidos = $pregunta['puntos'];
                    $puntajeObtenido += $puntosObtenidos;
                }
            }
            // Para respuestas de texto, por ahora no calificamos automáticamente
            
            // Actualizar respuesta con calificación
            if ($pregunta['id_opcion_seleccionada'] || $pregunta['respuesta_texto']) {
                $queryUpdate = "UPDATE respuestas_evaluacion SET 
                               es_correcta = ?, puntos_obtenidos = ? 
                               WHERE id_intento = ? AND id_pregunta_evaluacion = ?";
                $this->db->query($queryUpdate, [
                    $esCorrecta, 
                    $puntosObtenidos, 
                    $idIntento, 
                    $pregunta['id_pregunta_evaluacion']
                ]);
            }
        }
        
        // Calcular porcentaje
        $porcentaje = $puntajeTotal > 0 ? ($puntajeObtenido / $puntajeTotal) * 100 : 0;
        $aprobado = $porcentaje >= $evaluacion['puntaje_minimo_aprobacion'];
        
        // Calcular tiempo transcurrido
        $tiempoTranscurrido = "TIMESTAMPDIFF(SECOND, fecha_inicio, CURRENT_TIMESTAMP)";
        
        // Actualizar intento
        $queryFinalizar = "UPDATE intentos_evaluacion SET 
                          puntaje_obtenido = ?,
                          puntaje_maximo = ?,
                          porcentaje = ?,
                          aprobado = ?,
                          estado = 'completado',
                          fecha_finalizacion = CURRENT_TIMESTAMP,
                          tiempo_transcurrido = $tiempoTranscurrido
                          WHERE id_intento = ?";
        
        $this->db->query($queryFinalizar, [
            $puntajeObtenido,
            $puntajeTotal,
            $porcentaje,
            $aprobado,
            $idIntento
        ]);
        
        Logger::activity($intento['id_usuario'], "Intento de evaluación finalizado", [
            'id_intento' => $idIntento,
            'porcentaje' => $porcentaje,
            'aprobado' => $aprobado
        ]);
        
        return [
            'puntaje_obtenido' => $puntajeObtenido,
            'puntaje_maximo' => $puntajeTotal,
            'porcentaje' => round($porcentaje, 2),
            'aprobado' => $aprobado
        ];
    }
    
    /**
     * Obtener intento por ID
     */
    public function findById($id) {
        $query = "SELECT * FROM intentos_evaluacion WHERE id_intento = ?";
        return $this->db->fetchOne($query, [$id]);
    }
    
    /**
     * Obtener intentos de un usuario para una evaluación
     */
    public function getIntentosByUsuario($idEvaluacion, $idUsuario) {
        $query = "SELECT * FROM intentos_evaluacion 
                 WHERE id_evaluacion = ? AND id_usuario = ?
                 ORDER BY numero_intento DESC";
        return $this->db->fetchAll($query, [$idEvaluacion, $idUsuario]);
    }
    
    /**
     * Obtener respuestas de un intento
     */
    public function getRespuestas($idIntento) {
        $query = "SELECT 
            re.*,
            pe.pregunta_texto,
            pe.tipo_pregunta,
            pe.explicacion,
            op.texto_opcion,
            op.es_correcta
        FROM respuestas_evaluacion re
        INNER JOIN preguntas_evaluacion pe ON re.id_pregunta_evaluacion = pe.id_pregunta_evaluacion
        LEFT JOIN opciones_pregunta op ON re.id_opcion_seleccionada = op.id_opcion
        WHERE re.id_intento = ?
        ORDER BY pe.orden";
        
        return $this->db->fetchAll($query, [$idIntento]);
    }
    
    /**
     * Verificar si usuario tiene intento en progreso
     */
    public function tieneIntentoEnProgreso($idEvaluacion, $idUsuario) {
        $query = "SELECT id_intento FROM intentos_evaluacion 
                 WHERE id_evaluacion = ? AND id_usuario = ? AND estado = 'en_progreso'";
        $result = $this->db->fetchOne($query, [$idEvaluacion, $idUsuario]);
        return $result ? $result['id_intento'] : null;
    }
}
