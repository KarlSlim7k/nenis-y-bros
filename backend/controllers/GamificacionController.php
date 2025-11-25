<?php
/**
 * Controlador: GamificacionController
 * Gestiona endpoints de gamificación (puntos, logros, rachas, notificaciones)
 */

require_once __DIR__ . '/../models/PuntosUsuario.php';
require_once __DIR__ . '/../models/Logro.php';
require_once __DIR__ . '/../models/RachaUsuario.php';
require_once __DIR__ . '/../models/Notificacion.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

class GamificacionController {
    private $puntosModel;
    private $logroModel;
    private $rachaModel;
    private $notificacionModel;
    
    public function __construct() {
        $this->puntosModel = new PuntosUsuario();
        $this->logroModel = new Logro();
        $this->rachaModel = new RachaUsuario();
        $this->notificacionModel = new Notificacion();
    }
    
    /**
     * ============================================
     * PUNTOS
     * ============================================
     */
    
    /**
     * GET /gamificacion/puntos
     * Obtener puntos del usuario autenticado
     */
    public function misPuntos() {
        $usuario = AuthMiddleware::verify();
        
        try {
            $estadisticas = $this->puntosModel->getEstadisticas($usuario['id_usuario']);
            
            Response::success($estadisticas, "Estadísticas de puntos obtenidas");
        } catch (Exception $e) {
            Response::error("Error al obtener puntos: " . $e->getMessage());
        }
    }
    
    /**
     * GET /gamificacion/puntos/transacciones
     * Obtener historial de transacciones de puntos
     */
    public function misTransacciones() {
        $usuario = AuthMiddleware::verify();
        
        $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        try {
            $transacciones = $this->puntosModel->getHistorial($usuario['id_usuario'], $limite, $offset);
            
            Response::success([
                'transacciones' => $transacciones,
                'limite' => $limite,
                'offset' => $offset,
                'total' => count($transacciones)
            ], "Historial de transacciones obtenido");
        } catch (Exception $e) {
            Response::error("Error al obtener transacciones: " . $e->getMessage());
        }
    }
    
    /**
     * GET /gamificacion/ranking
     * Obtener ranking global de puntos
     */
    public function ranking() {
        $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        try {
            $ranking = $this->puntosModel->getRanking($limite, $offset);
            
            // Obtener posición del usuario autenticado si está logueado
            $miPosicion = null;
            try {
                $usuario = AuthMiddleware::verify();
                $miPosicion = $this->puntosModel->getPosicionRanking($usuario['id_usuario']);
            } catch (Exception $e) {
                // Usuario no autenticado, continuar sin posición
            }
            
            Response::success([
                'ranking' => $ranking,
                'mi_posicion' => $miPosicion,
                'limite' => $limite,
                'offset' => $offset
            ], "Ranking obtenido");
        } catch (Exception $e) {
            Response::error("Error al obtener ranking: " . $e->getMessage());
        }
    }
    
    /**
     * ============================================
     * LOGROS
     * ============================================
     */
    
    /**
     * GET /gamificacion/logros
     * Obtener catálogo completo de logros
     */
    public function catalogoLogros() {
        $categoria = isset($_GET['categoria']) ? $_GET['categoria'] : null;
        
        try {
            $logros = $this->logroModel->getAll($categoria);
            
            // Si hay usuario autenticado, incluir progreso
            try {
                $usuario = AuthMiddleware::verify();
                $misLogros = $this->logroModel->getByUsuario($usuario['id_usuario']);
                $stats = $this->logroModel->getEstadisticas($usuario['id_usuario']);
                
                Response::success([
                    'logros' => $logros,
                    'mis_logros' => $misLogros,
                    'estadisticas' => $stats
                ], "Catálogo de logros obtenido");
            } catch (Exception $e) {
                // Usuario no autenticado, solo catálogo
                Response::success(['logros' => $logros], "Catálogo de logros obtenido");
            }
        } catch (Exception $e) {
            Response::error("Error al obtener logros: " . $e->getMessage());
        }
    }
    
    /**
     * GET /gamificacion/logros/mis-logros
     * Obtener logros del usuario autenticado
     */
    public function misLogros() {
        $usuario = AuthMiddleware::verify();
        
        $soloDesbloqueados = isset($_GET['desbloqueados']) ? (bool)$_GET['desbloqueados'] : false;
        
        try {
            $logros = $this->logroModel->getByUsuario($usuario['id_usuario'], $soloDesbloqueados);
            $stats = $this->logroModel->getEstadisticas($usuario['id_usuario']);
            
            Response::success([
                'logros' => $logros,
                'estadisticas' => $stats
            ], "Logros obtenidos");
        } catch (Exception $e) {
            Response::error("Error al obtener logros: " . $e->getMessage());
        }
    }
    
    /**
     * GET /gamificacion/logros/no-vistos
     * Obtener logros desbloqueados no vistos
     */
    public function logrosNoVistos() {
        $usuario = AuthMiddleware::verify();
        
        try {
            $logros = $this->logroModel->getNoVistos($usuario['id_usuario']);
            
            Response::success(['logros' => $logros], "Logros no vistos obtenidos");
        } catch (Exception $e) {
            Response::error("Error al obtener logros no vistos: " . $e->getMessage());
        }
    }
    
    /**
     * PUT /gamificacion/logros/{id}/marcar-visto
     * Marcar logro como visto
     */
    public function marcarLogroVisto($idLogro) {
        $usuario = AuthMiddleware::verify();
        
        try {
            $this->logroModel->marcarVisto($usuario['id_usuario'], $idLogro);
            
            Response::success(null, "Logro marcado como visto");
        } catch (Exception $e) {
            Response::error("Error al marcar logro: " . $e->getMessage());
        }
    }
    
    /**
     * ============================================
     * RACHAS
     * ============================================
     */
    
    /**
     * GET /gamificacion/racha
     * Obtener racha del usuario autenticado
     */
    public function miRacha() {
        $usuario = AuthMiddleware::verify();
        
        try {
            $estadisticas = $this->rachaModel->getEstadisticas($usuario['id_usuario']);
            
            Response::success($estadisticas, "Estadísticas de racha obtenidas");
        } catch (Exception $e) {
            Response::error("Error al obtener racha: " . $e->getMessage());
        }
    }
    
    /**
     * POST /gamificacion/racha/registrar
     * Registrar actividad diaria
     */
    public function registrarActividad() {
        $usuario = AuthMiddleware::verify();
        
        try {
            $resultado = $this->rachaModel->registrarActividad($usuario['id_usuario']);
            
            Response::success($resultado, $resultado['mensaje'] ?? "Actividad registrada");
        } catch (Exception $e) {
            Response::error("Error al registrar actividad: " . $e->getMessage());
        }
    }
    
    /**
     * GET /gamificacion/racha/ranking
     * Obtener ranking de rachas
     */
    public function rankingRachas() {
        $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        try {
            $ranking = $this->rachaModel->getRankingRachas($limite, $offset);
            
            // Obtener posición del usuario autenticado
            $miPosicion = null;
            try {
                $usuario = AuthMiddleware::verify();
                $stats = $this->rachaModel->getEstadisticas($usuario['id_usuario']);
                $miPosicion = $stats['posicion_ranking'];
            } catch (Exception $e) {
                // Usuario no autenticado
            }
            
            Response::success([
                'ranking' => $ranking,
                'mi_posicion' => $miPosicion,
                'limite' => $limite,
                'offset' => $offset
            ], "Ranking de rachas obtenido");
        } catch (Exception $e) {
            Response::error("Error al obtener ranking: " . $e->getMessage());
        }
    }
    
    /**
     * ============================================
     * NOTIFICACIONES
     * ============================================
     */
    
    /**
     * GET /gamificacion/notificaciones
     * Obtener notificaciones del usuario
     */
    public function misNotificaciones() {
        $usuario = AuthMiddleware::verify();
        
        $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $soloNoLeidas = isset($_GET['no_leidas']) ? (bool)$_GET['no_leidas'] : false;
        
        try {
            $notificaciones = $this->notificacionModel->getByUsuario($usuario['id_usuario'], $limite, $offset, $soloNoLeidas);
            $noLeidas = $this->notificacionModel->contarNoLeidas($usuario['id_usuario']);
            $stats = $this->notificacionModel->getEstadisticas($usuario['id_usuario']);
            
            Response::success([
                'notificaciones' => $notificaciones,
                'no_leidas' => $noLeidas,
                'estadisticas' => $stats,
                'limite' => $limite,
                'offset' => $offset
            ], "Notificaciones obtenidas");
        } catch (Exception $e) {
            Response::error("Error al obtener notificaciones: " . $e->getMessage());
        }
    }
    
    /**
     * GET /gamificacion/notificaciones/contador
     * Obtener contador de notificaciones no leídas
     */
    public function contadorNotificaciones() {
        $usuario = AuthMiddleware::verify();
        
        try {
            $noLeidas = $this->notificacionModel->contarNoLeidas($usuario['id_usuario']);
            
            Response::success(['no_leidas' => $noLeidas], "Contador obtenido");
        } catch (Exception $e) {
            Response::error("Error al obtener contador: " . $e->getMessage());
        }
    }
    
    /**
     * PUT /gamificacion/notificaciones/{id}/leer
     * Marcar notificación como leída
     */
    public function marcarLeida($idNotificacion) {
        $usuario = AuthMiddleware::verify();
        
        try {
            $this->notificacionModel->marcarLeida($idNotificacion, $usuario['id_usuario']);
            
            Response::success(null, "Notificación marcada como leída");
        } catch (Exception $e) {
            Response::error("Error al marcar notificación: " . $e->getMessage());
        }
    }
    
    /**
     * PUT /gamificacion/notificaciones/leer-todas
     * Marcar todas las notificaciones como leídas
     */
    public function marcarTodasLeidas() {
        $usuario = AuthMiddleware::verify();
        
        try {
            $this->notificacionModel->marcarTodasLeidas($usuario['id_usuario']);
            
            Response::success(null, "Todas las notificaciones marcadas como leídas");
        } catch (Exception $e) {
            Response::error("Error al marcar notificaciones: " . $e->getMessage());
        }
    }
    
    /**
     * DELETE /gamificacion/notificaciones/{id}
     * Eliminar notificación
     */
    public function eliminarNotificacion($idNotificacion) {
        $usuario = AuthMiddleware::verify();
        
        try {
            $this->notificacionModel->eliminar($idNotificacion, $usuario['id_usuario']);
            
            Response::success(null, "Notificación eliminada");
        } catch (Exception $e) {
            Response::error("Error al eliminar notificación: " . $e->getMessage());
        }
    }
    
    /**
     * DELETE /gamificacion/notificaciones/limpiar-leidas
     * Eliminar todas las notificaciones leídas
     */
    public function limpiarLeidas() {
        $usuario = AuthMiddleware::verify();
        
        try {
            $this->notificacionModel->eliminarLeidas($usuario['id_usuario']);
            
            Response::success(null, "Notificaciones leídas eliminadas");
        } catch (Exception $e) {
            Response::error("Error al limpiar notificaciones: " . $e->getMessage());
        }
    }
    
    /**
     * GET /gamificacion/notificaciones/preferencias
     * Obtener preferencias de notificaciones
     */
    public function preferenciasNotificaciones() {
        $usuario = AuthMiddleware::verify();
        
        try {
            $preferencias = $this->notificacionModel->getPreferencias($usuario['id_usuario']);
            
            Response::success(['preferencias' => $preferencias], "Preferencias obtenidas");
        } catch (Exception $e) {
            Response::error("Error al obtener preferencias: " . $e->getMessage());
        }
    }
    
    /**
     * PUT /gamificacion/notificaciones/preferencias
     * Actualizar preferencias de notificaciones
     */
    public function actualizarPreferencias() {
        $usuario = AuthMiddleware::verify();
        $datos = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($datos['tipo_notificacion'])) {
            Response::validationError(['tipo_notificacion' => 'El tipo de notificación es requerido']);
        }
        
        try {
            $this->notificacionModel->actualizarPreferencias(
                $usuario['id_usuario'],
                $datos['tipo_notificacion'],
                $datos['notificaciones_app'] ?? null,
                $datos['notificaciones_email'] ?? null,
                $datos['notificaciones_push'] ?? null
            );
            
            Response::success(null, "Preferencias actualizadas");
        } catch (Exception $e) {
            Response::error("Error al actualizar preferencias: " . $e->getMessage());
        }
    }
    
    /**
     * ============================================
     * DASHBOARD GENERAL
     * ============================================
     */
    
    /**
     * GET /gamificacion/dashboard
     * Obtener resumen completo de gamificación
     */
    public function dashboard() {
        $usuario = AuthMiddleware::verify();
        
        try {
            $puntos = $this->puntosModel->getEstadisticas($usuario['id_usuario']);
            $racha = $this->rachaModel->getEstadisticas($usuario['id_usuario']);
            $notificaciones = $this->notificacionModel->contarNoLeidas($usuario['id_usuario']);
            $posicionRanking = $this->puntosModel->getPosicionRanking($usuario['id_usuario']);
            
            // Logros temporalmente simplificado
            $logros = ['total' => 6, 'desbloqueados' => 0, 'porcentaje' => 0];
            $logrosRecientes = [];
            
            Response::success([
                'puntos' => $puntos,
                'racha' => $racha,
                'logros' => $logros,
                'notificaciones_no_leidas' => $notificaciones,
                'posicion_ranking' => $posicionRanking,
                'logros_recientes' => $logrosRecientes
            ], "Dashboard de gamificación obtenido");
        } catch (Exception $e) {
            Response::error("Error al obtener dashboard: " . $e->getMessage());
        }
    }
}
