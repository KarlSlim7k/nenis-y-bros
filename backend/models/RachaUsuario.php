<?php
/**
 * Modelo: RachaUsuario
 * Gestiona las rachas de actividad diaria
 */

class RachaUsuario {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener racha de un usuario
     */
    public function getRacha($idUsuario) {
        $query = "SELECT * FROM rachas_usuario WHERE id_usuario = ?";
        $racha = $this->db->fetchOne($query, [$idUsuario]);
        
        if (!$racha) {
            $this->inicializarRacha($idUsuario);
            return $this->getRacha($idUsuario);
        }
        
        // Verificar si la racha está activa
        if ($racha['ultima_actividad']) {
            $ultimaActividad = strtotime($racha['ultima_actividad']);
            $ahora = time();
            $diferenciaHoras = ($ahora - $ultimaActividad) / 3600;
            
            $racha['racha_activa'] = $diferenciaHoras < 48; // 2 días de margen
            $racha['puede_continuar'] = $diferenciaHoras < 24;
        } else {
            // Usuario nuevo sin actividad
            $racha['racha_activa'] = false;
            $racha['puede_continuar'] = true;
        }
        
        return $racha;
    }
    
    /**
     * Inicializar racha para nuevo usuario
     */
    private function inicializarRacha($idUsuario) {
        $query = "INSERT INTO rachas_usuario (
            id_usuario, racha_actual, racha_maxima, 
            congelaciones_disponibles
        ) VALUES (?, 0, 0, 3)";
        
        return $this->db->insert($query, [$idUsuario]);
    }
    
    /**
     * Registrar actividad del usuario
     */
    public function registrarActividad($idUsuario) {
        $racha = $this->getRacha($idUsuario);
        
        // Verificar si ya registró actividad hoy
        $ultimaActividad = $racha['ultima_actividad'] ? strtotime($racha['ultima_actividad']) : 0;
        $hoy = strtotime('today');
        
        if ($ultimaActividad && $ultimaActividad >= $hoy) {
            // Ya registró actividad hoy
            return [
                'actualizada' => false,
                'racha_actual' => $racha['racha_actual'],
                'mensaje' => 'Ya registraste actividad hoy'
            ];
        }
        
        $ayer = strtotime('yesterday');
        $nuevaRacha = $racha['racha_actual'];
        $notificaciones = [];
        
        if ($ultimaActividad >= $ayer) {
            // Continuó la racha
            $nuevaRacha++;
        } else {
            // Verificar si puede usar congelación
            $diasSinActividad = floor(($hoy - $ultimaActividad) / 86400);
            
            if ($diasSinActividad <= 3 && $racha['congelaciones_disponibles'] > 0) {
                // Usar congelación automática
                $this->usarCongelacion($idUsuario);
                $nuevaRacha++;
                $notificaciones[] = [
                    'tipo' => 'congelacion_usada',
                    'mensaje' => "Se usó una congelación. Te quedan " . ($racha['congelaciones_disponibles'] - 1)
                ];
            } else {
                // Se rompió la racha
                if ($racha['racha_actual'] > 0) {
                    $notificaciones[] = [
                        'tipo' => 'racha_rota',
                        'mensaje' => "Tu racha de {$racha['racha_actual']} días se ha roto"
                    ];
                }
                $nuevaRacha = 1; // Iniciar nueva racha
            }
        }
        
        // Actualizar racha
        $nuevaRachaMaxima = max($racha['racha_maxima'], $nuevaRacha);
        
        $query = "UPDATE rachas_usuario 
                 SET racha_actual = ?, 
                     racha_maxima = ?,
                     ultima_actividad = NOW()
                 WHERE id_usuario = ?";
        
        $this->db->query($query, [$nuevaRacha, $nuevaRachaMaxima, $idUsuario]);
        
        // Verificar hitos de racha
        $this->verificarHitosRacha($idUsuario, $nuevaRacha);
        
        // Otorgar puntos por mantener racha
        if ($nuevaRacha > 0 && $nuevaRacha % 7 === 0) {
            require_once __DIR__ . '/PuntosUsuario.php';
            $puntosModel = new PuntosUsuario();
            $concepto = $nuevaRacha >= 30 ? 'racha_mensual' : 'racha_semanal';
            $puntosModel->otorgarPuntos($idUsuario, $concepto, 'racha', $nuevaRacha);
        }
        
        Logger::activity($idUsuario, "Actividad registrada", [
            'racha' => $nuevaRacha,
            'racha_maxima' => $nuevaRachaMaxima
        ]);
        
        return [
            'actualizada' => true,
            'racha_anterior' => $racha['racha_actual'],
            'racha_actual' => $nuevaRacha,
            'racha_maxima' => $nuevaRachaMaxima,
            'es_record' => $nuevaRacha === $nuevaRachaMaxima && $nuevaRacha > $racha['racha_maxima'],
            'notificaciones' => $notificaciones
        ];
    }
    
    /**
     * Usar congelación de racha
     */
    public function usarCongelacion($idUsuario) {
        $query = "UPDATE rachas_usuario 
                 SET congelaciones_disponibles = congelaciones_disponibles - 1
                 WHERE id_usuario = ? 
                 AND congelaciones_disponibles > 0";
        
        $resultado = $this->db->query($query, [$idUsuario]);
        
        if ($resultado) {
            Logger::activity($idUsuario, "Congelación de racha usada");
        }
        
        return $resultado;
    }
    
    /**
     * Otorgar congelación
     */
    public function otorgarCongelacion($idUsuario, $cantidad = 1) {
        $query = "UPDATE rachas_usuario 
                 SET congelaciones_disponibles = congelaciones_disponibles + ?
                 WHERE id_usuario = ?";
        
        return $this->db->query($query, [$cantidad, $idUsuario]);
    }
    
    /**
     * Verificar hitos de racha para logros
     */
    private function verificarHitosRacha($idUsuario, $racha) {
        require_once __DIR__ . '/Logro.php';
        $logroModel = new Logro();
        
        // Verificar logros de racha
        $hitos = [7, 30, 100, 365];
        if (in_array($racha, $hitos)) {
            $logroModel->verificarCondicion($idUsuario, 'racha_dias', $racha);
        }
    }
    
    /**
     * Obtener estadísticas de racha
     */
    public function getEstadisticas($idUsuario) {
        $racha = $this->getRacha($idUsuario);
        
        // Calcular días hasta próximo hito
        $proximosHitos = [7, 30, 100, 365];
        $proximoHito = null;
        
        foreach ($proximosHitos as $hito) {
            if ($racha['racha_actual'] < $hito) {
                $proximoHito = $hito;
                break;
            }
        }
        
        // Obtener ranking de rachas
        $query = "SELECT COUNT(*) + 1 as posicion 
                 FROM rachas_usuario 
                 WHERE racha_actual > ?";
        $ranking = $this->db->fetchOne($query, [$racha['racha_actual']]);
        
        return [
            'racha_actual' => (int)$racha['racha_actual'],
            'racha_maxima' => (int)$racha['racha_maxima'],
            'racha_activa' => $racha['racha_activa'],
            'puede_continuar' => $racha['puede_continuar'],
            'congelaciones_disponibles' => (int)$racha['congelaciones_disponibles'],
            'ultima_actividad' => $racha['ultima_actividad'],
            'proximo_hito' => $proximoHito,
            'dias_hasta_hito' => $proximoHito ? $proximoHito - $racha['racha_actual'] : null,
            'posicion_ranking' => (int)$ranking['posicion']
        ];
    }
    
    /**
     * Obtener ranking de rachas
     */
    public function getRankingRachas($limite = 50, $offset = 0) {
        $query = "SELECT 
            ru.id_usuario,
            u.nombre,
            u.apellido,
            u.foto_perfil,
            ru.racha_actual,
            ru.racha_maxima,
            ru.ultima_actividad,
            RANK() OVER (ORDER BY ru.racha_actual DESC, ru.racha_maxima DESC) as posicion
        FROM rachas_usuario ru
        INNER JOIN usuarios u ON ru.id_usuario = u.id_usuario
        WHERE ru.racha_actual > 0
        ORDER BY ru.racha_actual DESC, ru.racha_maxima DESC
        LIMIT ? OFFSET ?";
        
        return $this->db->fetchAll($query, [$limite, $offset]);
    }
    
    /**
     * Validar rachas (ejecutar diariamente vía cron)
     */
    public function validarRachas() {
        // Obtener usuarios con rachas que pueden romperse
        $query = "SELECT id_usuario, racha_actual, ultima_actividad, congelaciones_disponibles
                 FROM rachas_usuario
                 WHERE racha_actual > 0 
                 AND ultima_actividad < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $rachasEnRiesgo = $this->db->fetchAll($query);
        $rachasRotas = 0;
        $congelacionesUsadas = 0;
        
        foreach ($rachasEnRiesgo as $racha) {
            $diasSinActividad = floor((time() - strtotime($racha['ultima_actividad'])) / 86400);
            
            if ($diasSinActividad >= 2) {
                // Verificar si puede usar congelación
                if ($racha['congelaciones_disponibles'] > 0 && $diasSinActividad <= 3) {
                    $this->usarCongelacion($racha['id_usuario']);
                    $this->notificarCongelacionUsada($racha['id_usuario']);
                    $congelacionesUsadas++;
                } else {
                    // Romper racha
                    $queryUpdate = "UPDATE rachas_usuario 
                                   SET racha_actual = 0 
                                   WHERE id_usuario = ?";
                    $this->db->query($queryUpdate, [$racha['id_usuario']]);
                    $this->notificarRachaRota($racha['id_usuario'], $racha['racha_actual']);
                    $rachasRotas++;
                }
            }
        }
        
        Logger::activity(0, "Validación de rachas ejecutada", [
            'rachas_verificadas' => count($rachasEnRiesgo),
            'rachas_rotas' => $rachasRotas,
            'congelaciones_usadas' => $congelacionesUsadas
        ]);
        
        return [
            'verificadas' => count($rachasEnRiesgo),
            'rotas' => $rachasRotas,
            'congelaciones' => $congelacionesUsadas
        ];
    }
    
    /**
     * Notificar congelación usada
     */
    private function notificarCongelacionUsada($idUsuario) {
        $query = "INSERT INTO notificaciones (
            id_usuario, tipo, titulo, mensaje, icono
        ) VALUES (?, 'racha', ?, ?, '❄️')";
        
        $this->db->insert($query, [
            $idUsuario,
            "Congelación Usada",
            "Se usó una congelación para mantener tu racha activa"
        ]);
    }
    
    /**
     * Notificar racha rota
     */
    private function notificarRachaRota($idUsuario, $rachaAnterior) {
        $query = "INSERT INTO notificaciones (
            id_usuario, tipo, titulo, mensaje, icono
        ) VALUES (?, 'racha', ?, ?, '💔')";
        
        $this->db->insert($query, [
            $idUsuario,
            "Racha Perdida",
            "Tu racha de $rachaAnterior días se ha roto. ¡Empieza una nueva!"
        ]);
    }
}
