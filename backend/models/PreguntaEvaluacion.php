<?php
/**
 * Modelo: PreguntaEvaluacion
 * Gestiona preguntas de evaluaciones
 */

class PreguntaEvaluacion {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear nueva pregunta
     */
    public function create($data) {
        $query = "INSERT INTO preguntas_evaluacion (
            id_evaluacion, pregunta_texto, tipo_pregunta, 
            puntos, orden, explicacion
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        try {
            $idPregunta = $this->db->insert($query, [
                $data['id_evaluacion'],
                $data['pregunta_texto'],
                $data['tipo_pregunta'],
                $data['puntos'] ?? 1,
                $data['orden'] ?? 0,
                $data['explicacion'] ?? null
            ]);
            
            Logger::activity(null, "Pregunta creada", ['id_pregunta' => $idPregunta]);
            return $idPregunta;
        } catch (Exception $e) {
            Logger::error("Error al crear pregunta: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Crear pregunta con opciones (para multiple choice)
     */
    public function createConOpciones($data, $opciones) {
        $idPregunta = $this->create($data);
        
        // Agregar opciones
        if ($opciones && is_array($opciones)) {
            $opcionModel = new OpcionPregunta();
            foreach ($opciones as $opcion) {
                $opcion['id_pregunta'] = $idPregunta;
                $opcionModel->create($opcion);
            }
        }
        
        return $idPregunta;
    }
    
    /**
     * Obtener pregunta por ID
     */
    public function findById($id) {
        $query = "SELECT * FROM preguntas_evaluacion WHERE id_pregunta_evaluacion = ?";
        return $this->db->fetchOne($query, [$id]);
    }
    
    /**
     * Obtener todas las preguntas de una evaluación
     */
    public function getPreguntasByEvaluacion($idEvaluacion) {
        $query = "SELECT * FROM preguntas_evaluacion 
                 WHERE id_evaluacion = ? 
                 ORDER BY orden ASC";
        return $this->db->fetchAll($query, [$idEvaluacion]);
    }
    
    /**
     * Obtener pregunta completa con opciones
     */
    public function getPreguntaCompleta($idPregunta) {
        $pregunta = $this->findById($idPregunta);
        if (!$pregunta) return null;
        
        if ($pregunta['tipo_pregunta'] === 'multiple_choice' || 
            $pregunta['tipo_pregunta'] === 'verdadero_falso') {
            $opcionModel = new OpcionPregunta();
            $pregunta['opciones'] = $opcionModel->getOpcionesByPregunta($idPregunta);
        }
        
        return $pregunta;
    }
    
    /**
     * Actualizar pregunta
     */
    public function update($id, $data) {
        $allowedFields = [
            'pregunta_texto', 'tipo_pregunta', 'puntos', 
            'orden', 'explicacion'
        ];
        
        $updates = [];
        $values = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($updates)) return false;
        
        $values[] = $id;
        $query = "UPDATE preguntas_evaluacion SET " . implode(', ', $updates) . " WHERE id_pregunta_evaluacion = ?";
        
        return $this->db->query($query, $values);
    }
    
    /**
     * Eliminar pregunta
     */
    public function delete($id) {
        // Las opciones se eliminan por CASCADE
        $query = "DELETE FROM preguntas_evaluacion WHERE id_pregunta_evaluacion = ?";
        return $this->db->query($query, [$id]);
    }
    
    /**
     * Reordenar preguntas
     */
    public function reordenar($idEvaluacion, $ordenArray) {
        // $ordenArray = [id_pregunta => nuevo_orden, ...]
        foreach ($ordenArray as $idPregunta => $orden) {
            $query = "UPDATE preguntas_evaluacion SET orden = ? WHERE id_pregunta_evaluacion = ?";
            $this->db->query($query, [$orden, $idPregunta]);
        }
        return true;
    }
}
