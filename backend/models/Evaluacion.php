<?php
/**
 * Modelo: Evaluacion
 * Gestiona evaluaciones, quizzes y exámenes del sistema
 */

class Evaluacion {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear una nueva evaluación
     */
    public function create($data) {
        $query = "INSERT INTO evaluaciones (
            id_leccion, id_curso, titulo, descripcion, tipo_evaluacion,
            duracion_minutos, intentos_permitidos, puntaje_minimo_aprobacion,
            mostrar_resultados_inmediatos, permitir_revision, 
            barajar_preguntas, barajar_opciones, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['id_leccion'] ?? null,
            $data['id_curso'] ?? null,
            $data['titulo'],
            $data['descripcion'] ?? null,
            $data['tipo_evaluacion'] ?? 'quiz',
            $data['duracion_minutos'] ?? 0,
            $data['intentos_permitidos'] ?? 3,
            $data['puntaje_minimo_aprobacion'] ?? 70.00,
            $data['mostrar_resultados_inmediatos'] ?? true,
            $data['permitir_revision'] ?? true,
            $data['barajar_preguntas'] ?? false,
            $data['barajar_opciones'] ?? false,
            $data['estado'] ?? 'borrador'
        ];
        
        try {
            $id = $this->db->insert($query, $params);
            Logger::activity(0, "Evaluación creada", ['id_evaluacion' => $id]);
            return $id;
        } catch (Exception $e) {
            Logger::error("Error al crear evaluación: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener evaluación por ID
     */
    public function findById($id) {
        $query = "SELECT * FROM evaluaciones WHERE id_evaluacion = ?";
        return $this->db->fetchOne($query, [$id]);
    }
    
    /**
     * Obtener evaluaciones de una lección
     */
    public function findByLeccion($idLeccion) {
        $query = "SELECT * FROM evaluaciones 
                  WHERE id_leccion = ? AND estado = 'publicado'
                  ORDER BY fecha_creacion";
        return $this->db->fetchAll($query, [$idLeccion]);
    }
    
    /**
     * Obtener evaluaciones de un curso
     */
    public function findByCurso($idCurso) {
        $query = "SELECT * FROM evaluaciones 
                  WHERE id_curso = ? AND estado = 'publicado'
                  ORDER BY fecha_creacion";
        return $this->db->fetchAll($query, [$idCurso]);
    }
    
    /**
     * Actualizar evaluación
     */
    public function update($id, $data) {
        $campos = [];
        $valores = [];
        
        $camposPermitidos = [
            'titulo', 'descripcion', 'tipo_evaluacion', 'duracion_minutos',
            'intentos_permitidos', 'puntaje_minimo_aprobacion',
            'mostrar_resultados_inmediatos', 'permitir_revision',
            'barajar_preguntas', 'barajar_opciones', 'estado'
        ];
        
        foreach ($camposPermitidos as $campo) {
            if (isset($data[$campo])) {
                $campos[] = "$campo = ?";
                $valores[] = $data[$campo];
            }
        }
        
        if (empty($campos)) {
            return true;
        }
        
        $valores[] = $id;
        $query = "UPDATE evaluaciones SET " . implode(', ', $campos) . " WHERE id_evaluacion = ?";
        
        return $this->db->query($query, $valores);
    }
    
    /**
     * Eliminar evaluación
     */
    public function delete($id) {
        $query = "DELETE FROM evaluaciones WHERE id_evaluacion = ?";
        return $this->db->query($query, [$id]);
    }
    
    /**
     * Obtener evaluación completa con preguntas y opciones
     */
    public function getEvaluacionCompleta($id) {
        $evaluacion = $this->findById($id);
        if (!$evaluacion) return null;
        
        // Obtener preguntas
        $queryPreguntas = "SELECT * FROM preguntas_evaluacion 
                          WHERE id_evaluacion = ? 
                          ORDER BY orden, id_pregunta_evaluacion";
        $preguntas = $this->db->fetchAll($queryPreguntas, [$id]);
        
        // Para cada pregunta, obtener opciones
        foreach ($preguntas as &$pregunta) {
            if ($pregunta['tipo_pregunta'] === 'multiple_choice' || 
                $pregunta['tipo_pregunta'] === 'verdadero_falso') {
                $queryOpciones = "SELECT * FROM opciones_pregunta 
                                 WHERE id_pregunta_evaluacion = ? 
                                 ORDER BY orden, id_opcion";
                $pregunta['opciones'] = $this->db->fetchAll($queryOpciones, [$pregunta['id_pregunta_evaluacion']]);
            } else {
                $pregunta['opciones'] = [];
            }
        }
        
        $evaluacion['preguntas'] = $preguntas;
        return $evaluacion;
    }
    
    /**
     * Verificar si usuario puede iniciar evaluación
     */
    public function puedeIniciarEvaluacion($idEvaluacion, $idUsuario) {
        $evaluacion = $this->findById($idEvaluacion);
        if (!$evaluacion) return ['puede' => false, 'razon' => 'Evaluación no encontrada'];
        
        if ($evaluacion['estado'] !== 'publicado') {
            return ['puede' => false, 'razon' => 'Evaluación no disponible'];
        }
        
        // Verificar intentos
        if ($evaluacion['intentos_permitidos'] > 0) {
            $query = "SELECT COUNT(*) as total FROM intentos_evaluacion 
                     WHERE id_evaluacion = ? AND id_usuario = ?";
            $result = $this->db->fetchOne($query, [$idEvaluacion, $idUsuario]);
            
            if ($result['total'] >= $evaluacion['intentos_permitidos']) {
                return ['puede' => false, 'razon' => 'Límite de intentos alcanzado'];
            }
        }
        
        return ['puede' => true, 'intentos_restantes' => $evaluacion['intentos_permitidos'] - ($result['total'] ?? 0)];
    }
    
    /**
     * Obtener estadísticas de evaluación
     */
    public function getEstadisticas($idEvaluacion) {
        $query = "SELECT 
            COUNT(DISTINCT id_usuario) as total_usuarios,
            COUNT(*) as total_intentos,
            AVG(porcentaje) as promedio_porcentaje,
            MAX(porcentaje) as mejor_porcentaje,
            MIN(porcentaje) as peor_porcentaje,
            SUM(CASE WHEN aprobado = TRUE THEN 1 ELSE 0 END) as total_aprobados
        FROM intentos_evaluacion 
        WHERE id_evaluacion = ? AND estado = 'completado'";
        
        return $this->db->fetchOne($query, [$idEvaluacion]);
    }
}
