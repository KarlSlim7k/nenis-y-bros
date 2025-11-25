<?php
/**
 * ============================================================================
 * MODELO: DisponibilidadInstructor
 * ============================================================================
 * Gestiona la disponibilidad horaria de los instructores
 * 
 * @author Nenis y Bros
 * @version 1.0
 * @date 2025-11-18
 * ============================================================================
 */

class DisponibilidadInstructor {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear nuevo bloque de disponibilidad
     * 
     * @param array $data Datos de disponibilidad
     * @return int ID de disponibilidad creado
     */
    public function crear($data) {
        // Validar que no exista solapamiento
        if ($this->existeSolapamiento($data['id_instructor'], $data['dia_semana'], 
                                        $data['hora_inicio'], $data['hora_fin'])) {
            throw new Exception('Ya existe un bloque de disponibilidad en ese horario');
        }
        
        $query = "
            INSERT INTO disponibilidad_instructores (
                id_instructor, dia_semana, hora_inicio, hora_fin, activo
            ) VALUES (?, ?, ?, ?, ?)
        ";
        
        $params = [
            $data['id_instructor'],
            $data['dia_semana'],
            $data['hora_inicio'],
            $data['hora_fin'],
            $data['activo'] ?? true
        ];
        
        return $this->db->insert($query, $params);
    }
    
    /**
     * Obtener disponibilidad de un instructor
     * 
     * @param int $idInstructor
     * @param bool $soloActivos
     * @return array
     */
    public function getPorInstructor($idInstructor, $soloActivos = true) {
        $query = "
            SELECT *
            FROM disponibilidad_instructores
            WHERE id_instructor = ?
        ";
        
        $params = [$idInstructor];
        
        if ($soloActivos) {
            $query .= " AND activo = TRUE";
        }
        
        $query .= " ORDER BY dia_semana, hora_inicio";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Actualizar bloque de disponibilidad
     * 
     * @param int $idDisponibilidad
     * @param array $data
     * @return bool
     */
    public function actualizar($idDisponibilidad, $data) {
        // Validar solapamiento (excluyendo el mismo registro)
        $disponibilidad = $this->getById($idDisponibilidad);
        if (!$disponibilidad) {
            return false;
        }
        
        if (isset($data['dia_semana']) || isset($data['hora_inicio']) || isset($data['hora_fin'])) {
            $dia = $data['dia_semana'] ?? $disponibilidad['dia_semana'];
            $inicio = $data['hora_inicio'] ?? $disponibilidad['hora_inicio'];
            $fin = $data['hora_fin'] ?? $disponibilidad['hora_fin'];
            
            if ($this->existeSolapamiento($disponibilidad['id_instructor'], $dia, $inicio, $fin, $idDisponibilidad)) {
                throw new Exception('El horario se solapa con otro bloque de disponibilidad');
            }
        }
        
        $campos = [];
        $params = [];
        
        if (isset($data['dia_semana'])) {
            $campos[] = "dia_semana = ?";
            $params[] = $data['dia_semana'];
        }
        if (isset($data['hora_inicio'])) {
            $campos[] = "hora_inicio = ?";
            $params[] = $data['hora_inicio'];
        }
        if (isset($data['hora_fin'])) {
            $campos[] = "hora_fin = ?";
            $params[] = $data['hora_fin'];
        }
        if (isset($data['activo'])) {
            $campos[] = "activo = ?";
            $params[] = $data['activo'];
        }
        
        if (empty($campos)) {
            return false;
        }
        
        $params[] = $idDisponibilidad;
        
        $query = "
            UPDATE disponibilidad_instructores
            SET " . implode(', ', $campos) . "
            WHERE id_disponibilidad = ?
        ";
        
        return $this->db->execute($query, $params);
    }
    
    /**
     * Eliminar bloque de disponibilidad
     * 
     * @param int $idDisponibilidad
     * @return bool
     */
    public function eliminar($idDisponibilidad) {
        $query = "
            DELETE FROM disponibilidad_instructores
            WHERE id_disponibilidad = ?
        ";
        
        return $this->db->execute($query, [$idDisponibilidad]);
    }
    
    /**
     * Obtener por ID
     * 
     * @param int $idDisponibilidad
     * @return array|null
     */
    public function getById($idDisponibilidad) {
        $query = "
            SELECT * FROM disponibilidad_instructores
            WHERE id_disponibilidad = ?
        ";
        
        return $this->db->fetchOne($query, [$idDisponibilidad]);
    }
    
    /**
     * Verificar si instructor está disponible en este momento
     * 
     * @param int $idInstructor
     * @return bool
     */
    public function estaDisponibleAhora($idInstructor) {
        // Día de la semana (0 = Domingo, 6 = Sábado)
        $diaSemana = date('w');
        $horaActual = date('H:i:s');
        
        $query = "
            SELECT COUNT(*) as count
            FROM disponibilidad_instructores
            WHERE id_instructor = ?
              AND dia_semana = ?
              AND hora_inicio <= ?
              AND hora_fin >= ?
              AND activo = TRUE
        ";
        
        $result = $this->db->fetchOne($query, [$idInstructor, $diaSemana, $horaActual, $horaActual]);
        return $result['count'] > 0;
    }
    
    /**
     * Obtener próxima disponibilidad del instructor
     * 
     * @param int $idInstructor
     * @return array|null
     */
    public function getProximaDisponibilidad($idInstructor) {
        $diaActual = date('w');
        $horaActual = date('H:i:s');
        
        // Buscar disponibilidad en los próximos 7 días
        $query = "
            SELECT 
                dia_semana,
                hora_inicio,
                hora_fin,
                CASE 
                    WHEN dia_semana >= ? THEN dia_semana - ?
                    ELSE (7 - ? + dia_semana)
                END as dias_hasta
            FROM disponibilidad_instructores
            WHERE id_instructor = ?
              AND activo = TRUE
              AND (
                  (dia_semana > ?) OR 
                  (dia_semana = ? AND hora_inicio > ?)
              )
            ORDER BY dias_hasta, hora_inicio
            LIMIT 1
        ";
        
        $params = [
            $diaActual, $diaActual, $diaActual,
            $idInstructor,
            $diaActual, $diaActual, $horaActual
        ];
        
        $disponibilidad = $this->db->fetchOne($query, $params);
        
        if (!$disponibilidad) {
            // Si no hay en el futuro, buscar el primer bloque de la semana
            $query = "
                SELECT 
                    dia_semana,
                    hora_inicio,
                    hora_fin,
                    (7 - ? + dia_semana) as dias_hasta
                FROM disponibilidad_instructores
                WHERE id_instructor = ?
                  AND activo = TRUE
                ORDER BY dia_semana, hora_inicio
                LIMIT 1
            ";
            
            $disponibilidad = $this->db->fetchOne($query, [$diaActual, $idInstructor]);
        }
        
        if ($disponibilidad) {
            // Agregar nombres de días
            $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            $disponibilidad['nombre_dia'] = $diasSemana[$disponibilidad['dia_semana']];
        }
        
        return $disponibilidad;
    }
    
    /**
     * Verificar si existe solapamiento de horarios
     * 
     * @param int $idInstructor
     * @param int $diaSemana
     * @param string $horaInicio
     * @param string $horaFin
     * @param int|null $excluirId ID de disponibilidad a excluir de la validación
     * @return bool
     */
    private function existeSolapamiento($idInstructor, $diaSemana, $horaInicio, $horaFin, $excluirId = null) {
        $query = "
            SELECT COUNT(*) as count
            FROM disponibilidad_instructores
            WHERE id_instructor = ?
              AND dia_semana = ?
              AND activo = TRUE
              AND (
                  (hora_inicio < ? AND hora_fin > ?) OR
                  (hora_inicio < ? AND hora_fin > ?) OR
                  (hora_inicio >= ? AND hora_fin <= ?)
              )
        ";
        
        $params = [
            $idInstructor,
            $diaSemana,
            $horaFin, $horaInicio,
            $horaFin, $horaFin,
            $horaInicio, $horaFin
        ];
        
        if ($excluirId) {
            $query .= " AND id_disponibilidad != ?";
            $params[] = $excluirId;
        }
        
        $result = $this->db->fetchOne($query, $params);
        return $result['count'] > 0;
    }
    
    /**
     * Configurar disponibilidad de la semana (reemplaza la existente)
     * 
     * @param int $idInstructor
     * @param array $bloques Array de bloques: [['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '12:00'], ...]
     * @return bool
     */
    public function configurarSemana($idInstructor, $bloques) {
        // Validar bloques
        foreach ($bloques as $bloque) {
            if (!isset($bloque['dia_semana']) || !isset($bloque['hora_inicio']) || !isset($bloque['hora_fin'])) {
                throw new Exception('Cada bloque debe tener dia_semana, hora_inicio y hora_fin');
            }
            
            if ($bloque['dia_semana'] < 0 || $bloque['dia_semana'] > 6) {
                throw new Exception('dia_semana debe estar entre 0 (domingo) y 6 (sábado)');
            }
            
            if ($bloque['hora_inicio'] >= $bloque['hora_fin']) {
                throw new Exception('hora_inicio debe ser menor que hora_fin');
            }
        }
        
        // Desactivar bloques existentes
        $query = "
            UPDATE disponibilidad_instructores
            SET activo = FALSE
            WHERE id_instructor = ?
        ";
        
        $this->db->execute($query, [$idInstructor]);
        
        // Crear nuevos bloques
        foreach ($bloques as $bloque) {
            $this->crear([
                'id_instructor' => $idInstructor,
                'dia_semana' => $bloque['dia_semana'],
                'hora_inicio' => $bloque['hora_inicio'],
                'hora_fin' => $bloque['hora_fin'],
                'activo' => true
            ]);
        }
        
        return true;
    }
}
