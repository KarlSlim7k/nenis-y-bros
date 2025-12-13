<?php

/**
 * AuditoriaController
 * 
 * Controlador para gestionar logs de auditoría del sistema
 */
class AuditoriaController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * GET /api/v1/auditoria
     * Listar logs de auditoría con filtros
     */
    public function listarLogs() {
        try {
            error_log("DEBUG AuditoriaController: listarLogs() iniciado");
            $usuario = AuthMiddleware::authenticate();
            error_log("DEBUG AuditoriaController: usuario autenticado - " . json_encode($usuario['id_usuario'] ?? 'null'));
            
            if ($usuario['tipo_usuario'] !== 'administrador') {
                Response::error('No tienes permisos para ver logs de auditoría', 403);
            }
            
            // Filtros
            $filtros = [
                'tipo_evento' => $_GET['tipo_evento'] ?? null,
                'modulo' => $_GET['modulo'] ?? null,
                'id_usuario' => $_GET['id_usuario'] ?? null,
                'fecha_desde' => $_GET['fecha_desde'] ?? null,
                'fecha_hasta' => $_GET['fecha_hasta'] ?? null,
                'buscar' => $_GET['buscar'] ?? null
            ];
            
            $page = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
            $limit = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
            $offset = ($page - 1) * $limit;
            
            // Construir query
            $where = ['1=1'];
            $params = [];
            
            if ($filtros['tipo_evento']) {
                $where[] = 'a.tipo_evento = ?';
                $params[] = $filtros['tipo_evento'];
            }
            
            if ($filtros['modulo']) {
                $where[] = 'a.modulo = ?';
                $params[] = $filtros['modulo'];
            }
            
            if ($filtros['id_usuario']) {
                $where[] = 'a.id_usuario = ?';
                $params[] = $filtros['id_usuario'];
            }
            
            if ($filtros['fecha_desde']) {
                $where[] = 'a.fecha_creacion >= ?';
                $params[] = $filtros['fecha_desde'] . ' 00:00:00';
            }
            
            if ($filtros['fecha_hasta']) {
                $where[] = 'a.fecha_creacion <= ?';
                $params[] = $filtros['fecha_hasta'] . ' 23:59:59';
            }
            
            if ($filtros['buscar']) {
                $where[] = '(a.accion LIKE ? OR a.descripcion LIKE ? OR a.ip_address LIKE ?)';
                $searchTerm = '%' . $filtros['buscar'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $where);
            
            // Query principal con JOIN para obtener nombre de usuario
            $query = "
                SELECT 
                    a.*,
                    u.nombre,
                    u.apellido,
                    u.email
                FROM auditoria_logs a
                LEFT JOIN usuarios u ON a.id_usuario = u.id_usuario
                {$whereClause}
                ORDER BY a.fecha_creacion DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($query);
            error_log("DEBUG AuditoriaController: Query preparado, ejecutando con params: " . count($params));
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("DEBUG AuditoriaController: Logs obtenidos: " . count($logs));
            
            // Contar total
            $countQuery = "SELECT COUNT(*) as total FROM auditoria_logs a {$whereClause}";
            $countParams = array_slice($params, 0, -2);
            $stmt = $this->db->prepare($countQuery);
            $stmt->execute($countParams);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            Response::success([
                'logs' => $logs,
                'total' => $total,
                'pagina_actual' => $page,
                'total_paginas' => ceil($total / $limit),
                'por_pagina' => $limit
            ]);
            
        } catch (Exception $e) {
            error_log("DEBUG AuditoriaController ERROR: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
            Logger::error('Error al listar logs: ' . $e->getMessage());
            Response::error('Error al obtener logs', 500);
        }
    }
    
    /**
     * GET /api/v1/auditoria/estadisticas
     * Obtener estadísticas de auditoría
     */
    public function estadisticas() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['tipo_usuario'] !== 'administrador') {
                Response::error('No tienes permisos para ver estadísticas', 403);
            }
            
            // Estadísticas generales
            $query = "
                SELECT 
                    COUNT(*) as total_eventos,
                    COUNT(DISTINCT id_usuario) as usuarios_activos,
                    COUNT(CASE WHEN tipo_evento = 'login' THEN 1 END) as total_logins,
                    COUNT(CASE WHEN tipo_evento = 'error' THEN 1 END) as total_errores,
                    COUNT(CASE WHEN tipo_evento = 'seguridad' THEN 1 END) as eventos_seguridad,
                    COUNT(CASE WHEN DATE(fecha_creacion) = CURDATE() THEN 1 END) as eventos_hoy
                FROM auditoria_logs
                WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Eventos por tipo
            $queryTipos = "
                SELECT 
                    tipo_evento,
                    COUNT(*) as total
                FROM auditoria_logs
                WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY tipo_evento
                ORDER BY total DESC
            ";
            
            $stmt = $this->db->prepare($queryTipos);
            $stmt->execute();
            $eventosPorTipo = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Módulos más activos
            $queryModulos = "
                SELECT 
                    modulo,
                    COUNT(*) as total
                FROM auditoria_logs
                WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY modulo
                ORDER BY total DESC
                LIMIT 10
            ";
            
            $stmt = $this->db->prepare($queryModulos);
            $stmt->execute();
            $modulosActivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success([
                'generales' => $stats,
                'eventos_por_tipo' => $eventosPorTipo,
                'modulos_activos' => $modulosActivos
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener estadísticas: ' . $e->getMessage());
            Response::error('Error al obtener estadísticas', 500);
        }
    }
    
    /**
     * GET /api/v1/auditoria/{id}
     * Obtener detalle de un log específico
     */
    public function obtenerLog($id) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['tipo_usuario'] !== 'administrador') {
                Response::error('No tienes permisos', 403);
            }
            
            $query = "
                SELECT 
                    a.*,
                    u.nombre,
                    u.apellido,
                    u.email
                FROM auditoria_logs a
                LEFT JOIN usuarios u ON a.id_usuario = u.id_usuario
                WHERE a.id_log = ?
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$log) {
                Response::error('Log no encontrado', 404);
            }
            
            // Decodificar JSON si existen
            if ($log['datos_anteriores']) {
                $log['datos_anteriores'] = json_decode($log['datos_anteriores'], true);
            }
            if ($log['datos_nuevos']) {
                $log['datos_nuevos'] = json_decode($log['datos_nuevos'], true);
            }
            
            Response::success($log);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener log: ' . $e->getMessage());
            Response::error('Error al obtener log', 500);
        }
    }
}
