<?php
/**
 * Modelo: OpcionPregunta
 * Gestiona opciones de respuesta para preguntas multiple choice
 */

class OpcionPregunta {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear nueva opción
     */
    public function create($data) {
        $query = "INSERT INTO opciones_pregunta (
            id_pregunta, texto_opcion, es_correcta, orden
        ) VALUES (?, ?, ?, ?)";
        
        try {
            $idOpcion = $this->db->insert($query, [
                $data['id_pregunta'],
                $data['texto_opcion'],
                $data['es_correcta'] ?? 0,
                $data['orden'] ?? 0
            ]);
            return $idOpcion;
        } catch (Exception $e) {
            Logger::error("Error al crear opción: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener opciones de una pregunta
     */
    public function getOpcionesByPregunta($idPregunta, $incluirCorrectas = true) {
        $query = "SELECT 
            id_opcion, 
            texto_opcion, 
            orden" . 
            ($incluirCorrectas ? ", es_correcta" : "") . "
        FROM opciones_pregunta 
        WHERE id_pregunta_evaluacion = ? 
        ORDER BY orden ASC";
        
        return $this->db->fetchAll($query, [$idPregunta]);
    }
    
    /**
     * Actualizar opción
     */
    public function update($id, $data) {
        $allowedFields = ['texto_opcion', 'es_correcta', 'orden'];
        
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
        $query = "UPDATE opciones_pregunta SET " . implode(', ', $updates) . " WHERE id_opcion = ?";
        
        return $this->db->query($query, $values);
    }
    
    /**
     * Eliminar opción
     */
    public function delete($id) {
        $query = "DELETE FROM opciones_pregunta WHERE id_opcion = ?";
        return $this->db->query($query, [$id]);
    }
}
