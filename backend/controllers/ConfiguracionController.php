<?php

/**
 * ConfiguracionController
 * 
 * Controlador para gestionar la configuración del sistema
 */
class ConfiguracionController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * GET /api/v1/configuracion
     * Obtener todas las configuraciones del sistema
     */
    public function listar() {
        try {
            error_log("DEBUG ConfiguracionController: listar() iniciado");
            $usuario = AuthMiddleware::authenticate();
            error_log("DEBUG ConfiguracionController: usuario autenticado - id: " . ($usuario['id_usuario'] ?? 'null'));
            
            if ($usuario['tipo_usuario'] !== 'administrador') {
                Response::error('No tienes permisos para ver la configuración', 403);
            }
            
            error_log("DEBUG ConfiguracionController: ejecutando query");
            // Obtener todas las configuraciones
            $stmt = $this->db->query("SELECT * FROM configuracion_sistema ORDER BY categoria, clave");
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("DEBUG ConfiguracionController: configs obtenidas - " . count($configs));
            
            // Convertir a formato estructurado por categoría
            $resultado = [];
            foreach ($configs as $config) {
                $categoria = $config['categoria'] ?? 'general';
                if (!isset($resultado[$categoria])) {
                    $resultado[$categoria] = [];
                }
                
                // Parsear valor según tipo
                $valor = $config['valor'];
                switch ($config['tipo_dato']) {
                    case 'boolean':
                        $valor = $valor === '1' || $valor === 'true';
                        break;
                    case 'number':
                        $valor = is_numeric($valor) ? (float)$valor : 0;
                        break;
                    case 'json':
                        $valor = json_decode($valor, true) ?? [];
                        break;
                }
                
                $resultado[$categoria][$config['clave']] = [
                    'id' => $config['id_config'],
                    'valor' => $valor,
                    'tipo_dato' => $config['tipo_dato'],
                    'descripcion' => $config['descripcion'],
                    'fecha_actualizacion' => $config['fecha_actualizacion']
                ];
            }
            
            Response::success([
                'configuraciones' => $resultado,
                'total' => count($configs)
            ]);
            
        } catch (Exception $e) {
            error_log("DEBUG ConfiguracionController ERROR: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
            Logger::error('Error al listar configuración: ' . $e->getMessage());
            Response::error('Error al obtener configuración', 500);
        }
    }
    
    /**
     * PUT /api/v1/configuracion/{clave}
     * Actualizar una configuración específica
     */
    public function actualizar($clave) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['tipo_usuario'] !== 'administrador') {
                Response::error('No tienes permisos para modificar la configuración', 403);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['valor'])) {
                Response::validationError(['valor' => 'El valor es requerido']);
            }
            
            // Verificar que existe la clave
            $stmt = $this->db->prepare("SELECT * FROM configuracion_sistema WHERE clave = ?");
            $stmt->execute([$clave]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$config) {
                Response::notFound('Configuración no encontrada');
            }
            
            // Convertir valor según tipo
            $valor = $data['valor'];
            switch ($config['tipo_dato']) {
                case 'boolean':
                    $valor = $valor ? '1' : '0';
                    break;
                case 'json':
                    $valor = is_string($valor) ? $valor : json_encode($valor);
                    break;
                default:
                    $valor = (string)$valor;
            }
            
            // Actualizar
            $stmt = $this->db->prepare("UPDATE configuracion_sistema SET valor = ? WHERE clave = ?");
            $stmt->execute([$valor, $clave]);
            
            // Log de auditoría
            Logger::activity("Configuración actualizada: {$clave}", $usuario['id_usuario']);
            
            Response::success([
                'mensaje' => 'Configuración actualizada correctamente',
                'clave' => $clave,
                'valor' => $data['valor']
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al actualizar configuración: ' . $e->getMessage());
            Response::error('Error al actualizar configuración', 500);
        }
    }
    
    /**
     * POST /api/v1/configuracion
     * Crear o actualizar múltiples configuraciones
     */
    public function guardarMultiples() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['tipo_usuario'] !== 'administrador') {
                Response::error('No tienes permisos para modificar la configuración', 403);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['configuraciones']) || !is_array($data['configuraciones'])) {
                Response::validationError(['configuraciones' => 'Se requiere un array de configuraciones']);
            }
            
            $actualizadas = 0;
            $creadas = 0;
            
            foreach ($data['configuraciones'] as $item) {
                if (!isset($item['clave']) || !isset($item['valor'])) {
                    continue;
                }
                
                $clave = $item['clave'];
                $valor = $item['valor'];
                $tipo_dato = $item['tipo_dato'] ?? 'string';
                $descripcion = $item['descripcion'] ?? null;
                $categoria = $item['categoria'] ?? 'general';
                
                // Convertir valor según tipo
                switch ($tipo_dato) {
                    case 'boolean':
                        $valorDb = $valor ? '1' : '0';
                        break;
                    case 'json':
                        $valorDb = is_string($valor) ? $valor : json_encode($valor);
                        break;
                    default:
                        $valorDb = (string)$valor;
                }
                
                // Verificar si existe
                $stmt = $this->db->prepare("SELECT id_config FROM configuracion_sistema WHERE clave = ?");
                $stmt->execute([$clave]);
                $existe = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existe) {
                    // Actualizar
                    $stmt = $this->db->prepare("UPDATE configuracion_sistema SET valor = ? WHERE clave = ?");
                    $stmt->execute([$valorDb, $clave]);
                    $actualizadas++;
                } else {
                    // Crear
                    $stmt = $this->db->prepare("
                        INSERT INTO configuracion_sistema (clave, valor, tipo_dato, descripcion, categoria)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$clave, $valorDb, $tipo_dato, $descripcion, $categoria]);
                    $creadas++;
                }
            }
            
            Logger::activity("Configuraciones guardadas: {$actualizadas} actualizadas, {$creadas} creadas", $usuario['id_usuario']);
            
            Response::success([
                'mensaje' => 'Configuraciones guardadas correctamente',
                'actualizadas' => $actualizadas,
                'creadas' => $creadas
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al guardar configuraciones: ' . $e->getMessage());
            Response::error('Error al guardar configuraciones', 500);
        }
    }
    
    /**
     * GET /api/v1/configuracion/sistema
     * Obtener información del sistema (versiones, estado, etc.)
     */
    public function infoSistema() {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['tipo_usuario'] !== 'administrador') {
                Response::error('No tienes permisos para ver información del sistema', 403);
            }
            
            // Versión PHP
            $phpVersion = phpversion();
            
            // Versión MySQL
            $stmt = $this->db->query("SELECT VERSION() as version");
            $mysqlVersion = $stmt->fetch(PDO::FETCH_ASSOC)['version'];
            
            // Estadísticas de la base de datos
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM usuarios");
            $totalUsuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM usuarios WHERE estado = 'activo'");
            $usuariosActivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Espacio en disco (si es posible)
            $diskFree = function_exists('disk_free_space') ? disk_free_space('/') : null;
            $diskTotal = function_exists('disk_total_space') ? disk_total_space('/') : null;
            
            // Memoria
            $memoryLimit = ini_get('memory_limit');
            $memoryUsage = memory_get_usage(true);
            
            // Uptime del servidor (si está disponible)
            $serverUptime = null;
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $serverLoad = $load[0] ?? null;
            } else {
                $serverLoad = null;
            }
            
            Response::success([
                'version_sistema' => '1.0.0',
                'version_php' => $phpVersion,
                'version_mysql' => $mysqlVersion,
                'estado_servidor' => 'online',
                'estadisticas' => [
                    'total_usuarios' => (int)$totalUsuarios,
                    'usuarios_activos' => (int)$usuariosActivos
                ],
                'recursos' => [
                    'memoria_limite' => $memoryLimit,
                    'memoria_uso' => round($memoryUsage / 1024 / 1024, 2) . ' MB',
                    'disco_libre' => $diskFree ? round($diskFree / 1024 / 1024 / 1024, 2) . ' GB' : 'N/A',
                    'disco_total' => $diskTotal ? round($diskTotal / 1024 / 1024 / 1024, 2) . ' GB' : 'N/A',
                    'carga_servidor' => $serverLoad
                ],
                'fecha_servidor' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener info del sistema: ' . $e->getMessage());
            Response::error('Error al obtener información del sistema', 500);
        }
    }
    
    /**
     * GET /api/v1/configuracion/{clave}
     * Obtener una configuración específica
     */
    public function obtener($clave) {
        try {
            $usuario = AuthMiddleware::authenticate();
            
            if ($usuario['tipo_usuario'] !== 'administrador') {
                Response::error('No tienes permisos para ver la configuración', 403);
            }
            
            $stmt = $this->db->prepare("SELECT * FROM configuracion_sistema WHERE clave = ?");
            $stmt->execute([$clave]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$config) {
                Response::notFound('Configuración no encontrada');
            }
            
            // Parsear valor según tipo
            $valor = $config['valor'];
            switch ($config['tipo_dato']) {
                case 'boolean':
                    $valor = $valor === '1' || $valor === 'true';
                    break;
                case 'number':
                    $valor = is_numeric($valor) ? (float)$valor : 0;
                    break;
                case 'json':
                    $valor = json_decode($valor, true) ?? [];
                    break;
            }
            
            Response::success([
                'id' => $config['id_config'],
                'clave' => $config['clave'],
                'valor' => $valor,
                'tipo_dato' => $config['tipo_dato'],
                'descripcion' => $config['descripcion'],
                'categoria' => $config['categoria'],
                'fecha_actualizacion' => $config['fecha_actualizacion']
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener configuración: ' . $e->getMessage());
            Response::error('Error al obtener configuración', 500);
        }
    }
}
