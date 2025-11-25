<?php
/**
 * ============================================================================
 * MODELO: TIPO DIAGNOSTICO
 * ============================================================================
 * Gestiona tipos de diagnósticos, áreas y preguntas
 * Fase 3 - Perfiles Empresariales y Diagnósticos
 * ============================================================================
 */

class TipoDiagnostico {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener todos los diagnósticos activos
     */
    public function findAll($includeInactive = false) {
        $where = $includeInactive ? "" : "WHERE activo = TRUE";
        
        $query = "SELECT 
            td.*,
            (SELECT COUNT(*) FROM areas_evaluacion WHERE id_tipo_diagnostico = td.id_tipo_diagnostico) as total_areas,
            (SELECT COUNT(*) FROM preguntas_diagnostico pd 
             INNER JOIN areas_evaluacion ae ON pd.id_area = ae.id_area 
             WHERE ae.id_tipo_diagnostico = td.id_tipo_diagnostico) as total_preguntas
        FROM tipos_diagnostico td
        $where
        ORDER BY td.nombre ASC";
        
        return $this->db->fetchAll($query);
    }
    
    /**
     * Obtener diagnóstico por ID
     */
    public function findById($id, $withDetails = false) {
        $query = "SELECT 
            td.*,
            (SELECT COUNT(*) FROM areas_evaluacion WHERE id_tipo_diagnostico = td.id_tipo_diagnostico) as total_areas,
            (SELECT COUNT(*) FROM preguntas_diagnostico pd 
             INNER JOIN areas_evaluacion ae ON pd.id_area = ae.id_area 
             WHERE ae.id_tipo_diagnostico = td.id_tipo_diagnostico) as total_preguntas
        FROM tipos_diagnostico td
        WHERE td.id_tipo_diagnostico = ?";
        
        $diagnostico = $this->db->fetchOne($query, [$id]);
        
        if ($diagnostico && $withDetails) {
            // Obtener áreas con preguntas
            $diagnostico['areas'] = $this->getAreasWithQuestions($id);
        }
        
        return $diagnostico;
    }
    
    /**
     * Obtener diagnóstico por slug
     */
    public function findBySlug($slug, $withDetails = false) {
        $query = "SELECT * FROM tipos_diagnostico WHERE slug = ?";
        $diagnostico = $this->db->fetchOne($query, [$slug]);
        
        if ($diagnostico && $withDetails) {
            $diagnostico['areas'] = $this->getAreasWithQuestions($diagnostico['id_tipo_diagnostico']);
        }
        
        return $diagnostico;
    }
    
    /**
     * Obtener áreas con sus preguntas
     */
    public function getAreasWithQuestions($tipoDiagnosticoId) {
        $query = "SELECT * FROM areas_evaluacion 
                  WHERE id_tipo_diagnostico = ? 
                  ORDER BY orden ASC";
        
        $areas = $this->db->fetchAll($query, [$tipoDiagnosticoId]);
        
        foreach ($areas as &$area) {
            $area['preguntas'] = $this->getPreguntasByArea($area['id_area']);
        }
        
        return $areas;
    }
    
    /**
     * Obtener preguntas de un área
     */
    public function getPreguntasByArea($areaId) {
        $query = "SELECT * FROM preguntas_diagnostico 
                  WHERE id_area = ? 
                  ORDER BY orden ASC";
        
        $preguntas = $this->db->fetchAll($query, [$areaId]);
        
        // Decodificar opciones JSON
        foreach ($preguntas as &$pregunta) {
            if ($pregunta['opciones']) {
                $pregunta['opciones'] = json_decode($pregunta['opciones'], true);
            }
        }
        
        return $preguntas;
    }
    
    /**
     * Obtener una pregunta específica
     */
    public function getPreguntaById($preguntaId) {
        $query = "SELECT p.*, a.nombre as area_nombre, a.id_tipo_diagnostico
                  FROM preguntas_diagnostico p
                  INNER JOIN areas_evaluacion a ON p.id_area = a.id_area
                  WHERE p.id_pregunta = ?";
        
        $pregunta = $this->db->fetchOne($query, [$preguntaId]);
        
        if ($pregunta && $pregunta['opciones']) {
            $pregunta['opciones'] = json_decode($pregunta['opciones'], true);
        }
        
        return $pregunta;
    }
    
    /**
     * Crear tipo de diagnóstico
     */
    public function create($data) {
        $query = "INSERT INTO tipos_diagnostico (
            nombre, descripcion, slug, duracion_estimada, nivel_detalle, formula_calculo, activo
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['slug'],
            $data['duracion_estimada'] ?? 30,
            $data['nivel_detalle'] ?? 'basico',
            isset($data['formula_calculo']) ? json_encode($data['formula_calculo']) : null,
            $data['activo'] ?? true
        ];
        
        try {
            $diagnosticoId = $this->db->insert($query, $params);
            if ($diagnosticoId) {
                Logger::info("Tipo de diagnóstico creado: {$data['nombre']} (ID: $diagnosticoId)");
                return $diagnosticoId;
            }
            return false;
        } catch (Exception $e) {
            Logger::error("Error al crear tipo de diagnóstico: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Crear área de evaluación
     */
    public function createArea($data) {
        $query = "INSERT INTO areas_evaluacion (
            id_tipo_diagnostico, nombre, descripcion, icono, color, ponderacion, orden
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['id_tipo_diagnostico'],
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['icono'] ?? null,
            $data['color'] ?? '#667eea',
            $data['ponderacion'] ?? 100.00,
            $data['orden'] ?? 0
        ];
        
        try {
            $areaId = $this->db->insert($query, $params);
            return $areaId ?: false;
        } catch (Exception $e) {
            Logger::error("Error al crear área: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Crear pregunta
     */
    public function createPregunta($data) {
        $query = "INSERT INTO preguntas_diagnostico (
            id_area, pregunta, descripcion_ayuda, tipo_pregunta, opciones,
            escala_minima, escala_maxima, etiqueta_minima, etiqueta_maxima,
            ponderacion, es_obligatoria, orden
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['id_area'],
            $data['pregunta'],
            $data['descripcion_ayuda'] ?? null,
            $data['tipo_pregunta'] ?? 'multiple_choice',
            isset($data['opciones']) ? json_encode($data['opciones']) : null,
            $data['escala_minima'] ?? 1,
            $data['escala_maxima'] ?? 5,
            $data['etiqueta_minima'] ?? null,
            $data['etiqueta_maxima'] ?? null,
            $data['ponderacion'] ?? 1.00,
            $data['es_obligatoria'] ?? true,
            $data['orden'] ?? 0
        ];
        
        try {
            $preguntaId = $this->db->insert($query, $params);
            return $preguntaId ?: false;
        } catch (Exception $e) {
            Logger::error("Error al crear pregunta: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generar slug único
     */
    public function generateUniqueSlug($titulo, $excludeId = null) {
        $baseSlug = $this->slugify($titulo);
        $slug = $baseSlug;
        $counter = 1;
        
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Verificar si un slug existe
     */
    private function slugExists($slug, $excludeId = null) {
        $query = "SELECT COUNT(*) as count FROM tipos_diagnostico WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $query .= " AND id_tipo_diagnostico != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->fetchOne($query, $params);
        return $result['count'] > 0;
    }
    
    /**
     * Convertir texto a slug
     */
    private function slugify($text) {
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'n'],
            $text
        );
        
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        $text = trim($text, '-');
        
        return $text;
    }
}
