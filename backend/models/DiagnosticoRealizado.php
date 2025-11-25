<?php
/**
 * ============================================================================
 * MODELO: DIAGNOSTICO REALIZADO
 * ============================================================================
 * Gestiona la ejecución de diagnósticos, respuestas y cálculo de resultados
 * Fase 3 - Perfiles Empresariales y Diagnósticos
 * ============================================================================
 */

class DiagnosticoRealizado {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Iniciar nuevo diagnóstico
     */
    public function create($data) {
        $query = "INSERT INTO diagnosticos_realizados (
            id_usuario, id_perfil_empresarial, id_tipo_diagnostico, estado
        ) VALUES (?, ?, ?, 'en_progreso')";
        
        $params = [
            $data['id_usuario'],
            $data['id_perfil_empresarial'] ?? null,
            $data['id_tipo_diagnostico']
        ];
        
        try {
            $diagnosticoId = $this->db->insert($query, $params);
            if ($diagnosticoId) {
                Logger::activity($data['id_usuario'], "Diagnóstico iniciado", ['id_diagnostico' => $diagnosticoId]);
                return $diagnosticoId;
            }
            return false;
        } catch (Exception $e) {
            Logger::error("Error al iniciar diagnóstico: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener diagnóstico por ID
     */
    public function findById($id) {
        $query = "SELECT 
            dr.*,
            td.nombre as tipo_diagnostico,
            td.descripcion as tipo_descripcion,
            u.nombre as usuario_nombre,
            u.email as usuario_email,
            pe.nombre_empresa
        FROM diagnosticos_realizados dr
        INNER JOIN tipos_diagnostico td ON dr.id_tipo_diagnostico = td.id_tipo_diagnostico
        INNER JOIN usuarios u ON dr.id_usuario = u.id_usuario
        LEFT JOIN perfiles_empresariales pe ON dr.id_perfil_empresarial = pe.id_perfil
        WHERE dr.id_diagnostico_realizado = ?";
        
        $diagnostico = $this->db->fetchOne($query, [$id]);
        
        if ($diagnostico) {
            // Decodificar JSONs
            if (isset($diagnostico['resultados_areas']) && $diagnostico['resultados_areas']) {
                $diagnostico['resultados_areas'] = json_decode($diagnostico['resultados_areas'], true);
            }
            if (isset($diagnostico['recomendaciones_generadas']) && $diagnostico['recomendaciones_generadas']) {
                $diagnostico['recomendaciones_generadas'] = json_decode($diagnostico['recomendaciones_generadas'], true);
            }
            if (isset($diagnostico['areas_fuertes']) && $diagnostico['areas_fuertes']) {
                $diagnostico['areas_fuertes'] = json_decode($diagnostico['areas_fuertes'], true);
            }
            if (isset($diagnostico['areas_mejora']) && $diagnostico['areas_mejora']) {
                $diagnostico['areas_mejora'] = json_decode($diagnostico['areas_mejora'], true);
            }
            
            // Obtener respuestas
            $diagnostico['respuestas'] = $this->getRespuestas($id);
            $diagnostico['progreso'] = $this->getProgreso($id);
        }
        
        return $diagnostico;
    }
    
    /**
     * Obtener diagnósticos de un usuario
     */
    public function findByUser($userId, $filters = []) {
        $where = ["dr.id_usuario = ?"];
        $params = [$userId];
        
        if (isset($filters['estado'])) {
            $where[] = "dr.estado = ?";
            $params[] = $filters['estado'];
        }
        
        if (isset($filters['tipo_diagnostico'])) {
            $where[] = "dr.id_tipo_diagnostico = ?";
            $params[] = $filters['tipo_diagnostico'];
        }
        
        $whereClause = implode(" AND ", $where);
        
        $query = "SELECT 
            dr.*,
            td.nombre as tipo_diagnostico,
            pe.nombre_empresa
        FROM diagnosticos_realizados dr
        INNER JOIN tipos_diagnostico td ON dr.id_tipo_diagnostico = td.id_tipo_diagnostico
        LEFT JOIN perfiles_empresariales pe ON dr.id_perfil_empresarial = pe.id_perfil
        WHERE $whereClause
        ORDER BY dr.fecha_inicio DESC";
        
        $diagnosticos = $this->db->fetchAll($query, $params);
        
        // Decodificar JSONs
        foreach ($diagnosticos as &$diag) {
            if (isset($diag['resultados_areas']) && $diag['resultados_areas']) {
                $diag['resultados_areas'] = json_decode($diag['resultados_areas'], true);
            }
            if (isset($diag['recomendaciones_generadas']) && $diag['recomendaciones_generadas']) {
                $diag['recomendaciones_generadas'] = json_decode($diag['recomendaciones_generadas'], true);
            }
            if (isset($diag['areas_fuertes']) && $diag['areas_fuertes']) {
                $diag['areas_fuertes'] = json_decode($diag['areas_fuertes'], true);
            }
            if (isset($diag['areas_mejora']) && $diag['areas_mejora']) {
                $diag['areas_mejora'] = json_decode($diag['areas_mejora'], true);
            }
        }
        
        return $diagnosticos;
    }
    
    /**
     * Guardar respuesta a una pregunta
     */
    public function saveRespuesta($diagnosticoId, $preguntaId, $valor, $valorTexto = null) {
        // Verificar si ya existe respuesta para esta pregunta
        $existingQuery = "SELECT id_respuesta FROM respuestas_diagnostico 
                         WHERE id_diagnostico_realizado = ? AND id_pregunta = ?";
        $existing = $this->db->fetchOne($existingQuery, [$diagnosticoId, $preguntaId]);
        
        if ($existing) {
            // Actualizar respuesta existente
            $query = "UPDATE respuestas_diagnostico 
                     SET valor_numerico = ?, valor_texto = ?, fecha_respuesta = CURRENT_TIMESTAMP
                     WHERE id_respuesta = ?";
            $params = [$valor, $valorTexto, $existing['id_respuesta']];
        } else {
            // Insertar nueva respuesta
            $query = "INSERT INTO respuestas_diagnostico (
                id_diagnostico_realizado, id_pregunta, valor_numerico, valor_texto
            ) VALUES (?, ?, ?, ?)";
            $params = [$diagnosticoId, $preguntaId, $valor, $valorTexto];
        }
        
        try {
            return $this->db->query($query, $params);
        } catch (Exception $e) {
            Logger::error("Error al guardar respuesta: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener respuestas de un diagnóstico
     */
    public function getRespuestas($diagnosticoId) {
        $query = "SELECT 
            rd.*,
            pd.pregunta,
            pd.tipo_pregunta,
            ae.nombre as area_nombre,
            ae.id_area
        FROM respuestas_diagnostico rd
        INNER JOIN preguntas_diagnostico pd ON rd.id_pregunta = pd.id_pregunta
        INNER JOIN areas_evaluacion ae ON pd.id_area = ae.id_area
        WHERE rd.id_diagnostico_realizado = ?
        ORDER BY ae.orden, pd.orden";
        
        return $this->db->fetchAll($query, [$diagnosticoId]);
    }
    
    /**
     * Calcular progreso del diagnóstico
     */
    public function getProgreso($diagnosticoId) {
        $query = "SELECT 
            (SELECT COUNT(*) FROM respuestas_diagnostico WHERE id_diagnostico_realizado = ?) as respondidas,
            (SELECT COUNT(*) FROM preguntas_diagnostico pd
             INNER JOIN areas_evaluacion ae ON pd.id_area = ae.id_area
             INNER JOIN diagnosticos_realizados dr ON ae.id_tipo_diagnostico = dr.id_tipo_diagnostico
             WHERE dr.id_diagnostico_realizado = ?) as total";
        
        $result = $this->db->fetchOne($query, [$diagnosticoId, $diagnosticoId]);
        
        $respondidas = (int)$result['respondidas'];
        $total = (int)$result['total'];
        $porcentaje = $total > 0 ? round(($respondidas / $total) * 100, 2) : 0;
        
        return [
            'respondidas' => $respondidas,
            'total' => $total,
            'porcentaje' => $porcentaje,
            'completo' => $respondidas === $total
        ];
    }
    
    /**
     * Calcular resultados y finalizar diagnóstico
     */
    public function finalizarYCalcular($diagnosticoId) {
        // Obtener tipo de diagnóstico
        $diagQuery = "SELECT id_tipo_diagnostico FROM diagnosticos_realizados WHERE id_diagnostico_realizado = ?";
        $diag = $this->db->fetchOne($diagQuery, [$diagnosticoId]);
        
        if (!$diag) {
            throw new Exception("Diagnóstico no encontrado");
        }
        
        // Obtener áreas con ponderación
        $areasQuery = "SELECT * FROM areas_evaluacion WHERE id_tipo_diagnostico = ?";
        $areas = $this->db->fetchAll($areasQuery, [$diag['id_tipo_diagnostico']]);
        
        $resultadosAreas = [];
        $puntajeTotal = 0;
        $ponderacionTotal = 0;
        
        foreach ($areas as $area) {
            $resultado = $this->calcularPuntajeArea($diagnosticoId, $area['id_area']);
            $resultadosAreas[] = [
                'id_area' => $area['id_area'],
                'nombre' => $area['nombre'],
                'puntaje' => $resultado['puntaje'],
                'puntaje_maximo' => $resultado['puntaje_maximo'],
                'porcentaje' => $resultado['porcentaje'],
                'nivel' => $this->determinarNivel($resultado['porcentaje'])
            ];
            
            // Puntaje ponderado
            $puntajeTotal += $resultado['porcentaje'] * ($area['ponderacion'] / 100);
            $ponderacionTotal += $area['ponderacion'];
        }
        
        // Normalizar si las ponderaciones no suman 100
        if ($ponderacionTotal != 100) {
            $puntajeTotal = ($puntajeTotal / $ponderacionTotal) * 100;
        }
        
        $nivelMadurez = $this->determinarNivel($puntajeTotal);
        
        // Actualizar diagnóstico con resultados
        $updateQuery = "UPDATE diagnosticos_realizados SET
            puntaje_total = ?,
            nivel_madurez = ?,
            resultados_areas = ?,
            estado = 'completado',
            fecha_finalizacion = CURRENT_TIMESTAMP
        WHERE id_diagnostico_realizado = ?";
        
        $params = [
            round($puntajeTotal, 2),
            $nivelMadurez,
            json_encode($resultadosAreas),
            $diagnosticoId
        ];
        
        try {
            $this->db->query($updateQuery, $params);
            
            // Obtener userId para log
            $diagData = $this->db->fetchOne("SELECT id_usuario FROM diagnosticos_realizados WHERE id_diagnostico_realizado = ?", [$diagnosticoId]);
            if ($diagData) {
                Logger::activity($diagData['id_usuario'], "Diagnóstico completado", [
                    'id_diagnostico' => $diagnosticoId,
                    'puntaje_total' => $puntajeTotal,
                    'nivel_madurez' => $nivelMadurez
                ]);
            }
            
            return [
                'puntaje_total' => round($puntajeTotal, 2),
                'nivel_madurez' => $nivelMadurez,
                'resultados_areas' => $resultadosAreas
            ];
        } catch (Exception $e) {
            Logger::error("Error al finalizar diagnóstico: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calcular puntaje de un área específica
     */
    private function calcularPuntajeArea($diagnosticoId, $areaId) {
        $query = "SELECT 
            SUM(rd.valor_numerico * pd.ponderacion) as puntaje_obtenido,
            SUM(pd.escala_maxima * pd.ponderacion) as puntaje_maximo
        FROM respuestas_diagnostico rd
        INNER JOIN preguntas_diagnostico pd ON rd.id_pregunta = pd.id_pregunta
        WHERE rd.id_diagnostico_realizado = ? AND pd.id_area = ?";
        
        $resultado = $this->db->fetchOne($query, [$diagnosticoId, $areaId]);
        
        $puntaje = (float)$resultado['puntaje_obtenido'];
        $maximo = (float)$resultado['puntaje_maximo'];
        $porcentaje = $maximo > 0 ? ($puntaje / $maximo) * 100 : 0;
        
        return [
            'puntaje' => round($puntaje, 2),
            'puntaje_maximo' => round($maximo, 2),
            'porcentaje' => round($porcentaje, 2)
        ];
    }
    
    /**
     * Determinar nivel según porcentaje
     */
    private function determinarNivel($porcentaje) {
        if ($porcentaje >= 80) return 'avanzado';
        if ($porcentaje >= 60) return 'intermedio';
        if ($porcentaje >= 40) return 'basico';
        return 'inicial';
    }
    
    /**
     * Comparar diagnósticos históricos
     */
    public function compararDiagnosticos($diagnosticoActualId, $diagnosticoAnteriorId) {
        $actual = $this->findById($diagnosticoActualId);
        $anterior = $this->findById($diagnosticoAnteriorId);
        
        if (!$actual || !$anterior) {
            return null;
        }
        
        $comparacion = [
            'puntaje_actual' => $actual['puntaje_total'],
            'puntaje_anterior' => $anterior['puntaje_total'],
            'diferencia' => round($actual['puntaje_total'] - $anterior['puntaje_total'], 2),
            'mejora_porcentual' => $anterior['puntaje_total'] > 0 
                ? round((($actual['puntaje_total'] - $anterior['puntaje_total']) / $anterior['puntaje_total']) * 100, 2)
                : 0,
            'areas' => []
        ];
        
        // Comparar por áreas
        foreach ($actual['resultados_areas'] as $areaActual) {
            $areaAnterior = array_filter($anterior['resultados_areas'], function($a) use ($areaActual) {
                return $a['id_area'] === $areaActual['id_area'];
            });
            
            if (!empty($areaAnterior)) {
                $areaAnterior = reset($areaAnterior);
                $comparacion['areas'][] = [
                    'nombre' => $areaActual['nombre'],
                    'actual' => $areaActual['porcentaje'],
                    'anterior' => $areaAnterior['porcentaje'],
                    'diferencia' => round($areaActual['porcentaje'] - $areaAnterior['porcentaje'], 2)
                ];
            }
        }
        
        return $comparacion;
    }
    
    /**
     * Verificar si el diagnóstico pertenece al usuario
     */
    public function belongsToUser($diagnosticoId, $userId) {
        $query = "SELECT COUNT(*) as count FROM diagnosticos_realizados 
                  WHERE id_diagnostico_realizado = ? AND id_usuario = ?";
        $result = $this->db->fetchOne($query, [$diagnosticoId, $userId]);
        return $result['count'] > 0;
    }
    
    /**
     * Eliminar diagnóstico (soft delete - cambiar estado)
     */
    public function delete($diagnosticoId) {
        $query = "UPDATE diagnosticos_realizados SET estado = 'cancelado' WHERE id_diagnostico_realizado = ?";
        return $this->db->query($query, [$diagnosticoId]);
    }
}
