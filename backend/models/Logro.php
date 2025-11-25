<?php
/**
 * Modelo: Logro
 * Gestiona el catálogo de logros/achievements
 */

class Logro {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener todos los logros
     */
    public function getAll($categoria = null) {
        $query = "SELECT l.*, 
                 (SELECT COUNT(*) FROM logros_usuarios WHERE id_logro = l.id_logro) as usuarios_desbloqueados
                 FROM logros l";
        $params = [];
        
        if ($categoria) {
            $query .= " WHERE l.id_categoria_logro = ?";
            $params[] = $categoria;
        }
        
        $query .= " ORDER BY l.puntos_recompensa DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Obtener logro por ID
     */
    public function getById($idLogro) {
        $query = "SELECT l.*,
                 (SELECT COUNT(*) FROM logros_usuarios WHERE id_logro = l.id_logro) as usuarios_desbloqueados
                 FROM logros l
                 WHERE l.id_logro = ?";
        
        return $this->db->fetchOne($query, [$idLogro]);
    }
    
    /**
     * Obtener logro por tipo
     */
    public function getByTipo($tipo) {
        $query = "SELECT * FROM logros WHERE tipo_logro = ?";
        return $this->db->fetchOne($query, [$tipo]);
    }
    
    /**
     * Obtener logros de un usuario
     */
    public function getByUsuario($idUsuario, $soloDesbloqueados = false) {
        $query = "SELECT l.*, 
                 lu.fecha_obtencion, 
                 lu.visto,
                 CASE WHEN lu.id_logro_usuario IS NOT NULL THEN 1 ELSE 0 END as desbloqueado
                 FROM logros l
                 LEFT JOIN logros_usuarios lu ON l.id_logro = lu.id_logro AND lu.id_usuario = ?";
        
        if ($soloDesbloqueados) {
            $query .= " WHERE lu.id_logro_usuario IS NOT NULL";
        }
        
        $query .= " ORDER BY 
                    CASE WHEN lu.id_logro_usuario IS NOT NULL THEN 0 ELSE 1 END,
                    lu.fecha_obtencion DESC,
                    l.id_logro DESC";
        
        return $this->db->fetchAll($query, [$idUsuario]);
    }
    
    /**
     * Desbloquear logro para usuario
     */
    public function desbloquear($idUsuario, $idLogro) {
        // Verificar si ya está desbloqueado
        $query = "SELECT id_logro_usuario FROM logros_usuarios 
                 WHERE id_usuario = ? AND id_logro = ?";
        $existe = $this->db->fetchOne($query, [$idUsuario, $idLogro]);
        
        if ($existe) {
            return ['ya_desbloqueado' => true, 'id' => $existe['id_logro_usuario']];
        }
        
        // Obtener datos del logro
        $logro = $this->getById($idLogro);
        if (!$logro) {
            throw new Exception("Logro no encontrado");
        }
        
        try {
            // Desbloquear
            $query = "INSERT INTO logros_usuarios (id_usuario, id_logro) 
                     VALUES (?, ?)";
            $idLogroUsuario = $this->db->insert($query, [$idUsuario, $idLogro]);
            
            // Otorgar puntos
            require_once __DIR__ . '/PuntosUsuario.php';
            $puntosModel = new PuntosUsuario();
            $puntosModel->otorgarPuntos(
                $idUsuario, 
                'logro_desbloqueado', 
                'logro', 
                $idLogro,
                $logro['puntos_recompensa']
            );
            
            // Crear notificación
            $this->notificarLogro($idUsuario, $logro);
            
            Logger::activity($idUsuario, "Logro desbloqueado", [
                'logro' => $logro['nombre'],
                'puntos' => $logro['puntos_recompensa']
            ]);
            
            return [
                'desbloqueado' => true,
                'id' => $idLogroUsuario,
                'logro' => $logro
            ];
        } catch (Exception $e) {
            Logger::error("Error al desbloquear logro: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Marcar logro como visto
     */
    public function marcarVisto($idUsuario, $idLogro) {
        $query = "UPDATE logros_usuarios 
                 SET visto = TRUE 
                 WHERE id_usuario = ? AND id_logro = ?";
        
        return $this->db->query($query, [$idUsuario, $idLogro]);
    }
    
    /**
     * Obtener logros no vistos
     */
    public function getNoVistos($idUsuario) {
        $query = "SELECT l.*, lu.fecha_obtencion
                 FROM logros l
                 INNER JOIN logros_usuarios lu ON l.id_logro = lu.id_logro
                 WHERE lu.id_usuario = ? AND lu.visto = FALSE
                 ORDER BY lu.fecha_obtencion DESC";
        
        return $this->db->fetchAll($query, [$idUsuario]);
    }
    
    /**
     * Verificar condición de logro
     */
    public function verificarCondicion($idUsuario, $tipo, $valor = null) {
        $logrosAVerificar = $this->getLogrosPorTipo($tipo);
        $desbloqueados = [];
        
        foreach ($logrosAVerificar as $logro) {
            $condicion = json_decode($logro['condicion'], true);
            
            if ($this->evaluarCondicion($idUsuario, $condicion, $valor)) {
                $resultado = $this->desbloquear($idUsuario, $logro['id_logro']);
                if ($resultado['desbloqueado'] && !isset($resultado['ya_desbloqueado'])) {
                    $desbloqueados[] = $logro;
                }
            }
        }
        
        return $desbloqueados;
    }
    
    /**
     * Obtener logros por tipo de evento
     */
    private function getLogrosPorTipo($tipo) {
        $query = "SELECT * FROM logros 
                 WHERE JSON_EXTRACT(condicion, '$.tipo') = ? 
                 AND activo = TRUE";
        
        return $this->db->fetchAll($query, [$tipo]);
    }
    
    /**
     * Evaluar si se cumple una condición
     */
    private function evaluarCondicion($idUsuario, $condicion, $valor = null) {
        $tipo = $condicion['tipo'];
        
        switch ($tipo) {
            case 'cursos_completados':
                $query = "SELECT COUNT(*) as total FROM inscripciones 
                         WHERE id_usuario = ? AND estado = 'completado'";
                $resultado = $this->db->fetchOne($query, [$idUsuario]);
                return $resultado['total'] >= $condicion['cantidad'];
            
            case 'evaluaciones_aprobadas':
                $query = "SELECT COUNT(DISTINCT ie.id_evaluacion) as total 
                         FROM intentos_evaluacion ie
                         WHERE ie.id_usuario = ? AND ie.aprobado = TRUE";
                $resultado = $this->db->fetchOne($query, [$idUsuario]);
                return $resultado['total'] >= $condicion['cantidad'];
            
            case 'certificados_obtenidos':
                $query = "SELECT COUNT(*) as total FROM certificados 
                         WHERE id_usuario = ?";
                $resultado = $this->db->fetchOne($query, [$idUsuario]);
                return $resultado['total'] >= $condicion['cantidad'];
            
            case 'racha_dias':
                $query = "SELECT racha_actual FROM rachas_usuario 
                         WHERE id_usuario = ?";
                $resultado = $this->db->fetchOne($query, [$idUsuario]);
                return ($resultado['racha_actual'] ?? 0) >= $condicion['dias'];
            
            case 'diagnosticos_realizados':
                $query = "SELECT COUNT(*) as total FROM diagnosticos_realizados 
                         WHERE id_usuario = ?";
                $resultado = $this->db->fetchOne($query, [$idUsuario]);
                return $resultado['total'] >= $condicion['cantidad'];
            
            case 'nivel_alcanzado':
                require_once __DIR__ . '/PuntosUsuario.php';
                $puntosModel = new PuntosUsuario();
                $puntos = $puntosModel->getPuntos($idUsuario);
                return $puntos['nivel'] >= $condicion['nivel'];
            
            case 'primera_vez':
                // Para logros de "primer X", el valor indica el ID de la actividad
                return $valor !== null;
            
            case 'hora_actividad':
                // Para logros de madrugador/nocturno
                $horaActual = (int)date('H');
                if ($condicion['periodo'] === 'madrugada') {
                    return $horaActual >= 5 && $horaActual < 8;
                } elseif ($condicion['periodo'] === 'noche') {
                    return $horaActual >= 22 || $horaActual < 5;
                }
                return false;
            
            default:
                return false;
        }
    }
    
    /**
     * Obtener estadísticas de logros de usuario
     */
    public function getEstadisticas($idUsuario) {
        $query = "SELECT 
            COUNT(*) as total_logros,
            SUM(CASE WHEN lu.id_logro_usuario IS NOT NULL THEN 1 ELSE 0 END) as desbloqueados,
            SUM(CASE WHEN lu.id_logro_usuario IS NOT NULL THEN l.puntos_recompensa ELSE 0 END) as puntos_logros
        FROM logros l
        LEFT JOIN logros_usuarios lu ON l.id_logro = lu.id_logro AND lu.id_usuario = ?";
        
        $stats = $this->db->fetchOne($query, [$idUsuario]);
        
        $porcentaje = $stats['total_logros'] > 0 
            ? round(($stats['desbloqueados'] / $stats['total_logros']) * 100, 2) 
            : 0;
        
        return [
            'total' => (int)$stats['total_logros'],
            'desbloqueados' => (int)$stats['desbloqueados'],
            'pendientes' => (int)($stats['total_logros'] - $stats['desbloqueados']),
            'porcentaje' => $porcentaje,
            'puntos_totales' => (int)$stats['puntos_logros']
        ];
    }
    
    /**
     * Crear notificación de logro desbloqueado
     */
    private function notificarLogro($idUsuario, $logro) {
        $query = "INSERT INTO notificaciones (
            id_usuario, tipo, titulo, mensaje, icono, datos_json
        ) VALUES (?, 'logro', ?, ?, ?, ?)";
        
        $datos = json_encode([
            'id_logro' => $logro['id_logro'],
            'tipo_logro' => $logro['tipo_logro'],
            'puntos' => $logro['puntos_recompensa']
        ]);
        
        $this->db->insert($query, [
            $idUsuario,
            "¡Logro Desbloqueado!",
            "{$logro['icono']} {$logro['nombre']} - +{$logro['puntos_recompensa']} puntos",
            $logro['icono'],
            $datos
        ]);
    }
    
    /**
     * Crear un nuevo logro (Admin)
     */
    public function crear($datos) {
        $query = "INSERT INTO logros (
            codigo, nombre, descripcion, icono, categoria, 
            puntos, condicion, orden, activo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        return $this->db->insert($query, [
            $datos['codigo'],
            $datos['nombre'],
            $datos['descripcion'],
            $datos['icono'] ?? '🏆',
            $datos['categoria'],
            $datos['puntos'],
            json_encode($datos['condicion']),
            $datos['orden'] ?? 0,
            $datos['activo'] ?? true
        ]);
    }
    
    /**
     * Actualizar logro
     */
    public function actualizar($idLogro, $datos) {
        $campos = [];
        $valores = [];
        
        $permitidos = ['nombre', 'descripcion', 'icono', 'categoria', 'puntos', 'orden', 'activo'];
        
        foreach ($permitidos as $campo) {
            if (isset($datos[$campo])) {
                $campos[] = "$campo = ?";
                $valores[] = $datos[$campo];
            }
        }
        
        if (isset($datos['condicion'])) {
            $campos[] = "condicion = ?";
            $valores[] = json_encode($datos['condicion']);
        }
        
        if (empty($campos)) {
            return false;
        }
        
        $valores[] = $idLogro;
        $query = "UPDATE logros SET " . implode(', ', $campos) . " WHERE id_logro = ?";
        
        return $this->db->query($query, $valores);
    }
}
