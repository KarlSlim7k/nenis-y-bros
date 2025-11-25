<?php
/**
 * Modelo: PuntosUsuario
 * Gestiona el sistema de puntos y niveles
 */

class PuntosUsuario {
    private $db;
    
    // Configuración de puntos por actividad
    const PUNTOS_CONFIG = [
        'leccion_completada' => 10,
        'curso_completado' => 100,
        'diagnostico_realizado' => 50,
        'evaluacion_aprobada' => 30,
        'evaluacion_perfecta' => 50, // Bonus por 100%
        'certificado_obtenido' => 100,
        'racha_semanal' => 25,
        'racha_mensual' => 100,
        'primer_curso' => 20,
        'perfil_completado' => 15
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener puntos de un usuario
     */
    public function getPuntos($idUsuario) {
        $query = "SELECT * FROM puntos_usuario WHERE id_usuario = ?";
        $puntos = $this->db->fetchOne($query, [$idUsuario]);
        
        if (!$puntos) {
            // Crear registro si no existe
            $this->inicializarPuntos($idUsuario);
            return $this->getPuntos($idUsuario);
        }
        
        return $puntos;
    }
    
    /**
     * Inicializar puntos para nuevo usuario
     */
    private function inicializarPuntos($idUsuario) {
        $query = "INSERT INTO puntos_usuario (id_usuario, puntos_totales, puntos_disponibles, nivel)
                 VALUES (?, 0, 0, 1)";
        return $this->db->insert($query, [$idUsuario]);
    }
    
    /**
     * Otorgar puntos a usuario
     */
    public function otorgarPuntos($idUsuario, $concepto, $referenciaTipo = null, $referenciaId = null, $puntosCustom = null) {
        // Determinar cantidad de puntos
        $puntos = $puntosCustom ?? self::PUNTOS_CONFIG[$concepto] ?? 0;
        
        if ($puntos <= 0) {
            return false;
        }
        
        // Registrar transacción
        $query = "INSERT INTO transacciones_puntos (
            id_usuario, tipo_transaccion, puntos, concepto, 
            referencia_tipo, referencia_id
        ) VALUES (?, 'ganancia', ?, ?, ?, ?)";
        
        try {
            $idTransaccion = $this->db->insert($query, [
                $idUsuario,
                $puntos,
                $this->getConceptoTexto($concepto),
                $referenciaTipo,
                $referenciaId
            ]);
            
            Logger::activity($idUsuario, "Puntos ganados", [
                'puntos' => $puntos,
                'concepto' => $concepto,
                'id_transaccion' => $idTransaccion
            ]);
            
            // Verificar si subió de nivel
            $nivelAnterior = $this->getPuntos($idUsuario)['nivel'];
            $nivelActual = $this->calcularNivel($idUsuario);
            
            if ($nivelActual > $nivelAnterior) {
                $this->notificarSubidaNivel($idUsuario, $nivelActual);
            }
            
            return $idTransaccion;
        } catch (Exception $e) {
            Logger::error("Error al otorgar puntos: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gastar puntos
     */
    public function gastarPuntos($idUsuario, $cantidad, $concepto, $referenciaTipo = null, $referenciaId = null) {
        $puntosActuales = $this->getPuntos($idUsuario);
        
        if ($puntosActuales['puntos_disponibles'] < $cantidad) {
            throw new Exception("Puntos insuficientes");
        }
        
        $query = "INSERT INTO transacciones_puntos (
            id_usuario, tipo_transaccion, puntos, concepto,
            referencia_tipo, referencia_id
        ) VALUES (?, 'gasto', ?, ?, ?, ?)";
        
        try {
            $idTransaccion = $this->db->insert($query, [
                $idUsuario,
                $cantidad,
                $concepto,
                $referenciaTipo,
                $referenciaId
            ]);
            
            Logger::activity($idUsuario, "Puntos gastados", [
                'puntos' => $cantidad,
                'concepto' => $concepto
            ]);
            
            return $idTransaccion;
        } catch (Exception $e) {
            Logger::error("Error al gastar puntos: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener historial de transacciones
     */
    public function getHistorial($idUsuario, $limite = 50, $offset = 0) {
        $query = "SELECT * FROM transacciones_puntos 
                 WHERE id_usuario = ? 
                 ORDER BY fecha_transaccion DESC 
                 LIMIT ? OFFSET ?";
        
        return $this->db->fetchAll($query, [$idUsuario, $limite, $offset]);
    }
    
    /**
     * Calcular nivel basado en experiencia
     */
    private function calcularNivel($idUsuario) {
        $puntos = $this->getPuntos($idUsuario);
        // Fórmula: nivel = floor(sqrt(experiencia / 100)) + 1
        return floor(sqrt($puntos['experiencia'] / 100)) + 1;
    }
    
    /**
     * Obtener estadísticas de puntos
     */
    public function getEstadisticas($idUsuario) {
        $puntos = $this->getPuntos($idUsuario);
        
        // Calcular puntos para siguiente nivel
        $nivelActual = $puntos['nivel'];
        $puntosParaSiguienteNivel = pow(($nivelActual), 2) * 100;
        $progresoNivel = ($puntos['experiencia'] % ($puntosParaSiguienteNivel)) / $puntosParaSiguienteNivel * 100;
        
        // Obtener transacciones recientes
        $query = "SELECT 
            SUM(CASE WHEN tipo_transaccion = 'ganancia' THEN puntos ELSE 0 END) as puntos_ganados_hoy,
            SUM(CASE WHEN tipo_transaccion = 'gasto' THEN puntos ELSE 0 END) as puntos_gastados_hoy
        FROM transacciones_puntos
        WHERE id_usuario = ? 
        AND DATE(fecha_transaccion) = CURDATE()";
        
        $hoy = $this->db->fetchOne($query, [$idUsuario]);
        
        return [
            'puntos_totales' => $puntos['puntos_totales'],
            'puntos_disponibles' => $puntos['puntos_disponibles'],
            'puntos_gastados' => $puntos['puntos_gastados'],
            'nivel' => $puntos['nivel'],
            'experiencia' => $puntos['experiencia'],
            'puntos_siguiente_nivel' => $puntosParaSiguienteNivel,
            'progreso_nivel' => round($progresoNivel, 2),
            'puntos_ganados_hoy' => $hoy['puntos_ganados_hoy'] ?? 0,
            'puntos_gastados_hoy' => $hoy['puntos_gastados_hoy'] ?? 0
        ];
    }
    
    /**
     * Obtener ranking de usuarios
     */
    public function getRanking($limite = 100, $offset = 0) {
        $query = "SELECT * FROM ranking_usuarios 
                 LIMIT ? OFFSET ?";
        
        return $this->db->fetchAll($query, [$limite, $offset]);
    }
    
    /**
     * Obtener posición de usuario en ranking
     */
    public function getPosicionRanking($idUsuario) {
        $query = "SELECT posicion_global FROM ranking_usuarios 
                 WHERE id_usuario = ?";
        
        $resultado = $this->db->fetchOne($query, [$idUsuario]);
        return $resultado['posicion_global'] ?? null;
    }
    
    /**
     * Notificar subida de nivel
     */
    private function notificarSubidaNivel($idUsuario, $nuevoNivel) {
        // Crear notificación (se implementará en el modelo de Notificaciones)
        $query = "INSERT INTO notificaciones (
            id_usuario, tipo, titulo, mensaje, icono
        ) VALUES (?, 'puntos', ?, ?, '🎉')";
        
        $this->db->insert($query, [
            $idUsuario,
            "¡Nivel $nuevoNivel Alcanzado!",
            "Has subido al nivel $nuevoNivel. ¡Sigue así!"
        ]);
    }
    
    /**
     * Obtener texto descriptivo del concepto
     */
    private function getConceptoTexto($concepto) {
        $textos = [
            'leccion_completada' => 'Lección completada',
            'curso_completado' => 'Curso completado',
            'diagnostico_realizado' => 'Diagnóstico realizado',
            'evaluacion_aprobada' => 'Evaluación aprobada',
            'evaluacion_perfecta' => 'Evaluación con puntuación perfecta',
            'certificado_obtenido' => 'Certificado obtenido',
            'racha_semanal' => 'Racha de 7 días',
            'racha_mensual' => 'Racha de 30 días',
            'primer_curso' => 'Primer curso inscrito',
            'perfil_completado' => 'Perfil completado'
        ];
        
        return $textos[$concepto] ?? $concepto;
    }
}
