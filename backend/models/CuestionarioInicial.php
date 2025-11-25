<?php
/**
 * ============================================================================
 * MODELO: CUESTIONARIO INICIAL
 * ============================================================================
 * Gestiona el cuestionario de onboarding para nuevos usuarios
 * ============================================================================
 */

class CuestionarioInicial {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener preguntas activas del cuestionario
     */
    public function getPreguntas() {
        $query = "SELECT * FROM preguntas_cuestionario_inicial 
                  WHERE activo = TRUE 
                  ORDER BY orden ASC";
        
        $preguntas = $this->db->fetchAll($query);
        
        // Decodificar opciones JSON
        foreach ($preguntas as &$pregunta) {
            if ($pregunta['opciones']) {
                $pregunta['opciones'] = json_decode($pregunta['opciones'], true);
            }
        }
        
        return $preguntas;
    }
    
    /**
     * Crear nuevo cuestionario temporal
     */
    public function create($token) {
        $query = "INSERT INTO cuestionario_inicial (token_temporal) VALUES (?)";
        try {
            $id = $this->db->insert($query, [$token]);
            return $id;
        } catch (Exception $e) {
            Logger::error("Error al crear cuestionario inicial: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Guardar respuestas y calcular resultados
     */
    public function saveRespuestas($token, $respuestas) {
        // Obtener ID del cuestionario o crear uno nuevo
        $cuestionario = $this->findByToken($token);
        
        if (!$cuestionario) {
            $idCuestionario = $this->create($token);
        } else {
            $idCuestionario = $cuestionario['id_cuestionario'];
            // Limpiar respuestas anteriores si existen
            $this->db->query("DELETE FROM respuestas_cuestionario_inicial WHERE id_cuestionario = ?", [$idCuestionario]);
        }
        
        $puntajeTotal = 0;
        $maxPuntajePosible = 0;
        
        // Guardar cada respuesta
        foreach ($respuestas as $respuesta) {
            $query = "INSERT INTO respuestas_cuestionario_inicial (
                id_cuestionario, id_pregunta, valor_numerico, valor_texto
            ) VALUES (?, ?, ?, ?)";
            
            $this->db->insert($query, [
                $idCuestionario,
                $respuesta['id_pregunta'],
                $respuesta['valor_numerico'],
                $respuesta['valor_texto'] ?? null
            ]);
            
            // Calcular puntaje
            // Obtener ponderación de la pregunta
            $pregunta = $this->getPreguntaById($respuesta['id_pregunta']);
            if ($pregunta) {
                $puntajeTotal += $respuesta['valor_numerico'] * $pregunta['ponderacion'];
                // Asumimos valor máximo 5 por pregunta
                $maxPuntajePosible += 5 * $pregunta['ponderacion'];
            }
        }
        
        // Calcular porcentaje y nivel
        $porcentaje = $maxPuntajePosible > 0 ? ($puntajeTotal / $maxPuntajePosible) * 100 : 0;
        $nivel = $this->determinarNivel($porcentaje);
        
        // Actualizar cuestionario
        $updateQuery = "UPDATE cuestionario_inicial SET 
            puntaje_total = ?, 
            nivel_determinado = ?, 
            completado = TRUE 
            WHERE id_cuestionario = ?";
            
        $this->db->query($updateQuery, [$porcentaje, $nivel, $idCuestionario]);
        
        return [
            'puntaje' => round($porcentaje, 2),
            'nivel' => $nivel,
            'token' => $token
        ];
    }
    
    /**
     * Obtener cuestionario por token
     */
    public function findByToken($token) {
        $query = "SELECT * FROM cuestionario_inicial WHERE token_temporal = ?";
        return $this->db->fetchOne($query, [$token]);
    }
    
    /**
     * Obtener pregunta por ID
     */
    private function getPreguntaById($id) {
        $query = "SELECT * FROM preguntas_cuestionario_inicial WHERE id_pregunta = ?";
        return $this->db->fetchOne($query, [$id]);
    }
    
    /**
     * Determinar nivel basado en puntaje
     */
    private function determinarNivel($puntaje) {
        if ($puntaje >= 71) return 'avanzado';
        if ($puntaje >= 41) return 'intermedio';
        return 'principiante';
    }
    
    /**
     * Obtener cursos recomendados por nivel
     */
    public function getCursosRecomendados($nivel) {
        $query = "SELECT * FROM cursos 
                  WHERE nivel_curso = ? 
                  AND recomendado_onboarding = TRUE 
                  AND estado = 'publicado' 
                  LIMIT 3";
                  
        return $this->db->fetchAll($query, [$nivel]);
    }
    
    /**
     * Asociar cuestionario a usuario registrado
     */
    public function asociarUsuario($token, $userId) {
        $query = "UPDATE cuestionario_inicial SET 
            id_usuario = ?, 
            token_temporal = NULL, 
            fecha_registro = CURRENT_TIMESTAMP 
            WHERE token_temporal = ?";
            
        return $this->db->query($query, [$userId, $token]);
    }
}
