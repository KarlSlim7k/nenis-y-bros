<?php
/**
 * Modelo: Notificacion
 * Gestiona el sistema de notificaciones
 */

class Notificacion {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear notificación
     */
    public function crear($idUsuario, $tipo, $titulo, $mensaje, $icono = null, $datosJson = null) {
        // Verificar preferencias del usuario
        if (!$this->usuarioAceptaNotificacion($idUsuario, $tipo)) {
            return null;
        }
        
        $query = "INSERT INTO notificaciones (
            id_usuario, tipo, titulo, mensaje, icono, datos_json
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        try {
            $idNotificacion = $this->db->insert($query, [
                $idUsuario,
                $tipo,
                $titulo,
                $mensaje,
                $icono,
                $datosJson ? json_encode($datosJson) : null
            ]);
            
            Logger::activity($idUsuario, "Notificación creada", [
                'tipo' => $tipo,
                'titulo' => $titulo
            ]);
            
            return $idNotificacion;
        } catch (Exception $e) {
            Logger::error("Error al crear notificación: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener notificaciones de un usuario
     */
    public function getByUsuario($idUsuario, $limite = 50, $offset = 0, $soloNoLeidas = false) {
        $query = "SELECT * FROM notificaciones 
                 WHERE id_usuario = ?";
        $params = [$idUsuario];
        
        if ($soloNoLeidas) {
            $query .= " AND leida = FALSE";
        }
        
        $query .= " ORDER BY fecha_creacion DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;
        
        $notificaciones = $this->db->fetchAll($query, $params);
        
        // Decodificar datos JSON
        foreach ($notificaciones as &$notif) {
            if ($notif['datos_json']) {
                $notif['datos'] = json_decode($notif['datos_json'], true);
            }
        }
        
        return $notificaciones;
    }
    
    /**
     * Obtener notificación por ID
     */
    public function getById($idNotificacion) {
        $query = "SELECT * FROM notificaciones WHERE id_notificacion = ?";
        $notif = $this->db->fetchOne($query, [$idNotificacion]);
        
        if ($notif && $notif['datos_json']) {
            $notif['datos'] = json_decode($notif['datos_json'], true);
        }
        
        return $notif;
    }
    
    /**
     * Marcar como leída
     */
    public function marcarLeida($idNotificacion, $idUsuario) {
        $query = "UPDATE notificaciones 
                 SET leida = TRUE, fecha_lectura = NOW() 
                 WHERE id_notificacion = ? AND id_usuario = ?";
        
        return $this->db->query($query, [$idNotificacion, $idUsuario]);
    }
    
    /**
     * Marcar todas como leídas
     */
    public function marcarTodasLeidas($idUsuario) {
        $query = "UPDATE notificaciones 
                 SET leida = TRUE, fecha_lectura = NOW() 
                 WHERE id_usuario = ? AND leida = FALSE";
        
        $resultado = $this->db->query($query, [$idUsuario]);
        
        Logger::activity($idUsuario, "Todas las notificaciones marcadas como leídas");
        
        return $resultado;
    }
    
    /**
     * Eliminar notificación
     */
    public function eliminar($idNotificacion, $idUsuario) {
        $query = "DELETE FROM notificaciones 
                 WHERE id_notificacion = ? AND id_usuario = ?";
        
        return $this->db->query($query, [$idNotificacion, $idUsuario]);
    }
    
    /**
     * Eliminar todas las leídas
     */
    public function eliminarLeidas($idUsuario) {
        $query = "DELETE FROM notificaciones 
                 WHERE id_usuario = ? AND leida = TRUE";
        
        return $this->db->query($query, [$idUsuario]);
    }
    
    /**
     * Contar no leídas
     */
    public function contarNoLeidas($idUsuario) {
        $query = "SELECT COUNT(*) as total FROM notificaciones 
                 WHERE id_usuario = ? AND leida = FALSE";
        
        $resultado = $this->db->fetchOne($query, [$idUsuario]);
        return (int)$resultado['total'];
    }
    
    /**
     * Obtener estadísticas de notificaciones
     */
    public function getEstadisticas($idUsuario) {
        $query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN leida = FALSE THEN 1 ELSE 0 END) as no_leidas,
            SUM(CASE WHEN leida = TRUE THEN 1 ELSE 0 END) as leidas,
            COUNT(DISTINCT tipo) as tipos_diferentes,
            MAX(fecha_creacion) as ultima_notificacion
        FROM notificaciones
        WHERE id_usuario = ?";
        
        $stats = $this->db->fetchOne($query, [$idUsuario]);
        
        // Contar por tipo
        $queryTipos = "SELECT tipo, COUNT(*) as cantidad 
                      FROM notificaciones 
                      WHERE id_usuario = ? 
                      GROUP BY tipo 
                      ORDER BY cantidad DESC";
        $porTipo = $this->db->fetchAll($queryTipos, [$idUsuario]);
        
        return [
            'total' => (int)$stats['total'],
            'no_leidas' => (int)$stats['no_leidas'],
            'leidas' => (int)$stats['leidas'],
            'tipos_diferentes' => (int)$stats['tipos_diferentes'],
            'ultima_notificacion' => $stats['ultima_notificacion'],
            'por_tipo' => $porTipo
        ];
    }
    
    /**
     * Verificar si usuario acepta notificación de tipo
     */
    private function usuarioAceptaNotificacion($idUsuario, $tipo) {
        $query = "SELECT notificaciones_app FROM preferencias_notificacion 
                 WHERE id_usuario = ? AND tipo_notificacion = ?";
        
        $pref = $this->db->fetchOne($query, [$idUsuario, $tipo]);
        
        // Si no hay preferencia, aceptar por defecto
        return $pref ? (bool)$pref['notificaciones_app'] : true;
    }
    
    /**
     * Obtener preferencias de notificación
     */
    public function getPreferencias($idUsuario) {
        $query = "SELECT * FROM preferencias_notificacion WHERE id_usuario = ?";
        $preferencias = $this->db->fetchAll($query, [$idUsuario]);
        
        // Si no hay preferencias, crear con valores por defecto
        if (empty($preferencias)) {
            $this->inicializarPreferencias($idUsuario);
            return $this->getPreferencias($idUsuario);
        }
        
        return $preferencias;
    }
    
    /**
     * Actualizar preferencias
     */
    public function actualizarPreferencias($idUsuario, $tipo, $app = null, $email = null, $push = null) {
        $campos = [];
        $valores = [];
        
        if ($app !== null) {
            $campos[] = "notificaciones_app = ?";
            $valores[] = $app;
        }
        
        if ($email !== null) {
            $campos[] = "notificaciones_email = ?";
            $valores[] = $email;
        }
        
        if ($push !== null) {
            $campos[] = "notificaciones_push = ?";
            $valores[] = $push;
        }
        
        if (empty($campos)) {
            return false;
        }
        
        $valores[] = $idUsuario;
        $valores[] = $tipo;
        
        $query = "UPDATE preferencias_notificacion 
                 SET " . implode(', ', $campos) . "
                 WHERE id_usuario = ? AND tipo_notificacion = ?";
        
        $resultado = $this->db->query($query, $valores);
        
        Logger::activity($idUsuario, "Preferencias de notificación actualizadas", [
            'tipo' => $tipo
        ]);
        
        return $resultado;
    }
    
    /**
     * Inicializar preferencias por defecto
     */
    private function inicializarPreferencias($idUsuario) {
        $tipos = ['logro', 'curso', 'evaluacion', 'certificado', 'mentoria', 'sistema', 'racha', 'puntos'];
        
        foreach ($tipos as $tipo) {
            $query = "INSERT INTO preferencias_notificacion (
                id_usuario, tipo_notificacion, 
                notificaciones_app, notificaciones_email, notificaciones_push
            ) VALUES (?, ?, TRUE, TRUE, FALSE)";
            
            $this->db->insert($query, [$idUsuario, $tipo]);
        }
    }
    
    /**
     * Limpiar notificaciones antiguas (ejecutar periódicamente)
     */
    public function limpiarAntiguas($diasAntiguedad = 90) {
        $query = "DELETE FROM notificaciones 
                 WHERE leida = TRUE 
                 AND fecha_creacion < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $resultado = $this->db->query($query, [$diasAntiguedad]);
        
        Logger::activity(0, "Limpieza de notificaciones antiguas ejecutada", [
            'dias' => $diasAntiguedad
        ]);
        
        return $resultado;
    }
    
    /**
     * Notificaciones masivas (para admins)
     */
    public function crearMasiva($idsUsuarios, $tipo, $titulo, $mensaje, $icono = null) {
        $creadas = 0;
        
        foreach ($idsUsuarios as $idUsuario) {
            if ($this->crear($idUsuario, $tipo, $titulo, $mensaje, $icono)) {
                $creadas++;
            }
        }
        
        Logger::activity(0, "Notificación masiva enviada", [
            'usuarios' => count($idsUsuarios),
            'creadas' => $creadas,
            'tipo' => $tipo
        ]);
        
        return $creadas;
    }
    
    /**
     * Helpers para crear notificaciones específicas
     */
    
    public function notificarNuevoCurso($idUsuario, $nombreCurso, $idCurso) {
        return $this->crear(
            $idUsuario,
            'curso',
            '¡Nuevo Curso Disponible!',
            "El curso \"$nombreCurso\" ya está disponible para ti",
            '📚',
            ['id_curso' => $idCurso, 'nombre' => $nombreCurso]
        );
    }
    
    public function notificarEvaluacionDisponible($idUsuario, $nombreEvaluacion, $idEvaluacion) {
        return $this->crear(
            $idUsuario,
            'evaluacion',
            'Evaluación Lista',
            "La evaluación \"$nombreEvaluacion\" está lista para realizarse",
            '📝',
            ['id_evaluacion' => $idEvaluacion, 'nombre' => $nombreEvaluacion]
        );
    }
    
    public function notificarCertificado($idUsuario, $nombreCurso, $idCertificado) {
        return $this->crear(
            $idUsuario,
            'certificado',
            '¡Certificado Obtenido!',
            "Has obtenido el certificado del curso \"$nombreCurso\"",
            '🎓',
            ['id_certificado' => $idCertificado, 'curso' => $nombreCurso]
        );
    }
    
    public function notificarRachaPeligro($idUsuario, $diasRacha) {
        return $this->crear(
            $idUsuario,
            'racha',
            '⚠️ Racha en Peligro',
            "Tu racha de $diasRacha días está a punto de romperse. ¡Entra hoy!",
            '⚠️',
            ['racha' => $diasRacha]
        );
    }
}
