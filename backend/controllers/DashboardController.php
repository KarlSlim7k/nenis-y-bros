<?php
/**
 * ============================================================================
 * CONTROLADOR: DASHBOARD
 * ============================================================================
 * Maneja las estadísticas y datos del dashboard del emprendedor/empresario
 * ============================================================================
 */

class DashboardController {
    
    private $inscripcionModel;
    private $diagnosticoModel;
    private $logroModel;
    private $productoModel;
    private $progresoModel;
    
    public function __construct() {
        $this->inscripcionModel = new Inscripcion();
        $this->diagnosticoModel = new DiagnosticoRealizado();
        $this->logroModel = new Logro();
        $this->productoModel = new Producto();
        $this->progresoModel = new ProgresoLeccion();
    }
    
    /**
     * Obtener estadísticas generales del dashboard
     * GET /dashboard/stats
     * Requiere autenticación
     */
    public function getStats() {
        try {
            $user = AuthMiddleware::requireAuth();
            $userId = $user['id_usuario'];
            $userType = $user['tipo_usuario'];
            
            // Estadísticas generales
            $stats = [
                'cursos_activos' => $this->getCursosActivos($userId),
                'diagnosticos_completados' => $this->getDiagnosticosCompletados($userId),
                'logros_desbloqueados' => $this->getLogrosDesbloqueados($userId),
            ];
            
            // Solo para empresarios: productos
            if ($userType === 'empresario') {
                $stats['productos'] = $this->getProductosCount($userId);
            }
            
            Response::success('Estadísticas obtenidas exitosamente', $stats);
        } catch (Exception $e) {
            Logger::error('Error al obtener estadísticas: ' . $e->getMessage());
            Response::serverError('Error al obtener estadísticas');
        }
    }
    
    /**
     * Obtener cursos en progreso del usuario
     * GET /dashboard/cursos-progreso
     * Requiere autenticación
     */
    public function getCursosEnProgreso() {
        try {
            $user = AuthMiddleware::requireAuth();
            $userId = $user['id_usuario'];
            
            $db = Database::getInstance();
            
            // Obtener cursos inscritos con su progreso
            $query = "
                SELECT 
                    c.id_curso,
                    c.titulo,
                    c.descripcion,
                    c.imagen_portada,
                    c.duracion_estimada,
                    i.fecha_inscripcion,
                    i.fecha_ultima_actividad,
                    i.progreso,
                    i.completado,
                    i.fecha_completado,
                    cat.nombre as categoria,
                    CONCAT(u.nombre, ' ', u.apellido) as instructor
                FROM inscripciones i
                INNER JOIN cursos c ON i.id_curso = c.id_curso
                LEFT JOIN categorias cat ON c.id_categoria = cat.id_categoria
                LEFT JOIN usuarios u ON c.id_instructor = u.id_usuario
                WHERE i.id_usuario = ?
                    AND i.completado = 0
                    AND c.estado = 'publicado'
                ORDER BY i.fecha_ultima_actividad DESC
                LIMIT 6
            ";
            
            $cursos = $db->fetchAll($query, [$userId]);
            
            // Calcular tiempo restante estimado para cada curso
            foreach ($cursos as &$curso) {
                $tiempoTotal = $curso['duracion_estimada'] ?? 0;
                $progresoActual = $curso['progreso'] ?? 0;
                $tiempoRestante = $tiempoTotal * (1 - ($progresoActual / 100));
                $curso['tiempo_restante'] = round($tiempoRestante);
            }
            
            Response::success('Cursos en progreso obtenidos', $cursos);
        } catch (Exception $e) {
            Logger::error('Error al obtener cursos en progreso: ' . $e->getMessage());
            Response::serverError('Error al obtener cursos en progreso');
        }
    }
    
    /**
     * Obtener diagnósticos recientes del usuario
     * GET /dashboard/diagnosticos-recientes
     * Requiere autenticación
     */
    public function getDiagnosticosRecientes() {
        try {
            $user = AuthMiddleware::requireAuth();
            $userId = $user['id_usuario'];
            
            $db = Database::getInstance();
            
            $query = "
                SELECT 
                    dr.id_diagnostico_realizado,
                    dr.id_tipo_diagnostico,
                    dr.puntaje_total,
                    dr.estado,
                    dr.fecha_inicio,
                    dr.fecha_completado,
                    dt.titulo as nombre_diagnostico,
                    dt.descripcion
                FROM diagnosticos_realizados dr
                INNER JOIN diagnosticos_tipos dt ON dr.id_tipo_diagnostico = dt.id_tipo_diagnostico
                WHERE dr.id_usuario = ?
                    AND dr.estado = 'completado'
                ORDER BY dr.fecha_completado DESC
                LIMIT 5
            ";
            
            $diagnosticos = $db->fetchAll($query, [$userId]);
            
            Response::success('Diagnósticos recientes obtenidos', $diagnosticos);
        } catch (Exception $e) {
            Logger::error('Error al obtener diagnósticos recientes: ' . $e->getMessage());
            Response::serverError('Error al obtener diagnósticos recientes');
        }
    }
    
    /**
     * Obtener actividad reciente del usuario
     * GET /dashboard/actividad-reciente
     * Requiere autenticación
     */
    public function getActividadReciente() {
        try {
            $user = AuthMiddleware::requireAuth();
            $userId = $user['id_usuario'];
            
            $db = Database::getInstance();
            
            $actividades = [];
            
            // Logros recientes (últimos 7 días)
            $queryLogros = "
                SELECT 
                    'logro' as tipo,
                    l.titulo as descripcion,
                    l.icono,
                    ul.fecha_desbloqueo as fecha
                FROM usuarios_logros ul
                INNER JOIN logros l ON ul.id_logro = l.id_logro
                WHERE ul.id_usuario = ?
                    AND ul.fecha_desbloqueo >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY ul.fecha_desbloqueo DESC
                LIMIT 3
            ";
            
            $logros = $db->fetchAll($queryLogros, [$userId]);
            foreach ($logros as $logro) {
                $actividades[] = $logro;
            }
            
            // Diagnósticos completados (últimos 7 días)
            $queryDiagnosticos = "
                SELECT 
                    'diagnostico' as tipo,
                    CONCAT('Diagnóstico completado: ', dt.titulo) as descripcion,
                    '📊' as icono,
                    dr.fecha_completado as fecha
                FROM diagnosticos_realizados dr
                INNER JOIN diagnosticos_tipos dt ON dr.id_tipo_diagnostico = dt.id_tipo_diagnostico
                WHERE dr.id_usuario = ?
                    AND dr.estado = 'completado'
                    AND dr.fecha_completado >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY dr.fecha_completado DESC
                LIMIT 3
            ";
            
            $diagnosticos = $db->fetchAll($queryDiagnosticos, [$userId]);
            foreach ($diagnosticos as $diagnostico) {
                $actividades[] = $diagnostico;
            }
            
            // Inscripciones recientes (últimos 7 días)
            $queryInscripciones = "
                SELECT 
                    'inscripcion' as tipo,
                    CONCAT('Inscripción a curso: ', c.titulo) as descripcion,
                    '📚' as icono,
                    i.fecha_inscripcion as fecha
                FROM inscripciones i
                INNER JOIN cursos c ON i.id_curso = c.id_curso
                WHERE i.id_usuario = ?
                    AND i.fecha_inscripcion >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY i.fecha_inscripcion DESC
                LIMIT 3
            ";
            
            $inscripciones = $db->fetchAll($queryInscripciones, [$userId]);
            foreach ($inscripciones as $inscripcion) {
                $actividades[] = $inscripcion;
            }
            
            // Ordenar todas las actividades por fecha
            usort($actividades, function($a, $b) {
                return strtotime($b['fecha']) - strtotime($a['fecha']);
            });
            
            // Limitar a las 10 más recientes
            $actividades = array_slice($actividades, 0, 10);
            
            Response::success('Actividad reciente obtenida', $actividades);
        } catch (Exception $e) {
            Logger::error('Error al obtener actividad reciente: ' . $e->getMessage());
            Response::serverError('Error al obtener actividad reciente');
        }
    }
    
    // =========================================================================
    // Métodos privados auxiliares
    // =========================================================================
    
    private function getCursosActivos($userId) {
        $db = Database::getInstance();
        $query = "
            SELECT COUNT(*) as total
            FROM inscripciones
            WHERE id_usuario = ? AND completado = 0
        ";
        $result = $db->fetchOne($query, [$userId]);
        return (int)($result['total'] ?? 0);
    }
    
    private function getDiagnosticosCompletados($userId) {
        $db = Database::getInstance();
        $query = "
            SELECT COUNT(*) as total
            FROM diagnosticos_realizados
            WHERE id_usuario = ? AND estado = 'completado'
        ";
        $result = $db->fetchOne($query, [$userId]);
        return (int)($result['total'] ?? 0);
    }
    
    private function getLogrosDesbloqueados($userId) {
        $db = Database::getInstance();
        $query = "
            SELECT COUNT(*) as total
            FROM usuarios_logros
            WHERE id_usuario = ?
        ";
        $result = $db->fetchOne($query, [$userId]);
        return (int)($result['total'] ?? 0);
    }
    
    private function getProductosCount($userId) {
        $db = Database::getInstance();
        $query = "
            SELECT COUNT(*) as total
            FROM productos
            WHERE id_empresa = (
                SELECT id_empresa 
                FROM perfiles_empresariales 
                WHERE id_usuario = ?
            )
        ";
        $result = $db->fetchOne($query, [$userId]);
        return (int)($result['total'] ?? 0);
    }
}
