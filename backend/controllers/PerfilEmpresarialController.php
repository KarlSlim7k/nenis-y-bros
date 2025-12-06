<?php
/**
 * ============================================================================
 * CONTROLADOR: PERFIL EMPRESARIAL
 * ============================================================================
 * Gestiona perfiles empresariales de usuarios
 * Fase 3 - Perfiles Empresariales y Diagnósticos
 * ============================================================================
 */

class PerfilEmpresarialController {
    
    private $model;
    private $authMiddleware;
    
    public function __construct() {
        $this->model = new PerfilEmpresarial();
        $this->authMiddleware = new AuthMiddleware();
    }
    
    /**
     * GET /api/v1/perfiles
     * Listar perfiles (admin) o propio (usuario)
     */
    public function index() {
        $user = $this->authMiddleware->requireAuth();
        
        $filtros = [
            'sector' => $_GET['sector'] ?? null,
            'tipo_negocio' => $_GET['tipo_negocio'] ?? null,
            'etapa_negocio' => $_GET['etapa_negocio'] ?? null,
            'ciudad' => $_GET['ciudad'] ?? null,
            'pais' => $_GET['pais'] ?? null,
            'buscar' => $_GET['buscar'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 20
        ];
        
        // Solo admins pueden ver todos los perfiles
        if ($user['rol'] !== 'admin') {
            Response::error('Acceso denegado', 403);
        }
        
        $perfiles = $this->model->findAll($filtros);
        Response::success($perfiles);
    }
    
    /**
     * GET /api/v1/perfiles/mi-perfil
     * Obtener perfil propio del usuario autenticado
     */
    public function miPerfil() {
        $user = $this->authMiddleware->requireAuth();
        
        $perfil = $this->model->findByUser($user['id_usuario']);
        
        if (!$perfil) {
            Response::success(['perfil' => null, 'existe' => false]);
        }
        
        Response::success(['perfil' => $perfil, 'existe' => true]);
    }
    
    /**
     * GET /api/v1/perfiles/{id}
     * Obtener perfil específico
     */
    public function show($id) {
        $user = $this->authMiddleware->requireAuth();
        
        $perfil = $this->model->findById($id);
        
        if (!$perfil) {
            Response::error('Perfil no encontrado', 404);
        }
        
        // Verificar permisos: admin o propietario
        if ($user['rol'] !== 'admin' && $perfil['id_usuario'] != $user['id_usuario']) {
            Response::error('Acceso denegado', 403);
        }
        
        Response::success($perfil);
    }
    
    /**
     * POST /api/v1/perfiles
     * Crear nuevo perfil empresarial
     */
    public function store() {
        $user = $this->authMiddleware->requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar que el usuario no tenga ya un perfil
        if ($this->model->findByUser($user['id_usuario'])) {
            Response::error('Ya tienes un perfil empresarial creado. Usa PUT para actualizarlo.', 400);
        }
        
        // Reglas de validación
        $rules = [
            'nombre_empresa' => 'required|string|min:2|max:200',
            'sector' => 'required|string',
            'tipo_negocio' => 'required|string',
            'etapa_negocio' => 'required|string',
            'numero_empleados' => 'integer|min:0',
            'facturacion_anual' => 'numeric|min:0',
            'descripcion' => 'string|max:1000',
            'sitio_web' => 'url|max:255',
            'telefono' => 'string|max:20',
            'ciudad' => 'string|max:100',
            'pais' => 'string|max:100',
            'direccion' => 'string|max:255'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        // Agregar ID de usuario
        $data['id_usuario'] = $user['id_usuario'];
        
        try {
            $perfilId = $this->model->create($data);
            
            if ($perfilId) {
                // INTENTO DE ASCENSO AUTOMÁTICO
                $upgradeService = new UserUpgradeService();
                $upgraded = false;
                $newToken = null;
                
                // Verificar requisitos (aunque por ahora siempre es true)
                $check = $upgradeService->checkRequirements($user['id_usuario']);
                
                if ($check['eligible']) {
                    // Realizar ascenso
                    if ($upgradeService->upgradeToEmpresario($user['id_usuario'])) {
                        $upgraded = true;
                        
                        // Generar nuevo token con el rol actualizado
                        // Nota: Asumimos que Security::generateJWT existe y funciona como en AuthController
                        // Obtenemos los datos frescos del usuario para el token
                        $updatedUser = (new Usuario())->findById($user['id_usuario']);
                        
                        if (class_exists('Security')) {
                            $newToken = Security::generateJWT([
                                'user_id' => $updatedUser['id_usuario'],
                                'email' => $updatedUser['email'],
                                'tipo_usuario' => $updatedUser['tipo_usuario']
                            ]);
                        }
                    }
                }
                
                $perfil = $this->model->findById($perfilId);
                
                Response::success([
                    'mensaje' => 'Perfil empresarial creado exitosamente' . ($upgraded ? '. ¡Ascendido a Empresario!' : ''),
                    'perfil' => $perfil,
                    'role_upgraded' => $upgraded,
                    'new_token' => $newToken // El frontend debe actualizar su token si este campo viene
                ], 201);
            }
            
            Response::error('Error al crear perfil empresarial', 500);
        } catch (Exception $e) {
            Response::error('Error al crear perfil: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /api/v1/perfiles/{id}
     * Actualizar perfil empresarial
     */
    public function update($id) {
        $user = $this->authMiddleware->requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Verificar que el perfil existe
        $perfilExistente = $this->model->findById($id);
        if (!$perfilExistente) {
            Response::error('Perfil no encontrado', 404);
        }
        
        // Verificar permisos
        if ($user['rol'] !== 'admin' && $perfilExistente['id_usuario'] != $user['id_usuario']) {
            Response::error('No tienes permiso para modificar este perfil', 403);
        }
        
        // Reglas de validación (opcionales para actualización)
        $rules = [
            'nombre_empresa' => 'string|min:2|max:200',
            'sector' => 'string',
            'tipo_negocio' => 'string',
            'etapa_negocio' => 'string',
            'numero_empleados' => 'integer|min:0',
            'facturacion_anual' => 'numeric|min:0',
            'descripcion' => 'string|max:1000',
            'sitio_web' => 'url|max:255',
            'telefono' => 'string|max:20',
            'ciudad' => 'string|max:100',
            'pais' => 'string|max:100',
            'direccion' => 'string|max:255'
        ];
        
        $validator = new Validator($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $resultado = $this->model->update($id, $data);
            
            if ($resultado) {
                $perfil = $this->model->findById($id);
                Response::success([
                    'mensaje' => 'Perfil actualizado exitosamente',
                    'perfil' => $perfil
                ]);
            }
            
            Response::error('Error al actualizar perfil', 500);
        } catch (Exception $e) {
            Response::error('Error al actualizar perfil: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/v1/perfiles/{id}
     * Eliminar perfil empresarial
     */
    public function delete($id) {
        $user = $this->authMiddleware->requireAuth();
        
        $perfil = $this->model->findById($id);
        if (!$perfil) {
            Response::error('Perfil no encontrado', 404);
        }
        
        // Verificar permisos
        if ($user['rol'] !== 'admin' && $perfil['id_usuario'] != $user['id_usuario']) {
            Response::error('No tienes permiso para eliminar este perfil', 403);
        }
        
        try {
            $resultado = $this->model->delete($id);
            
            if ($resultado) {
                Response::success(['mensaje' => 'Perfil eliminado exitosamente']);
            }
            
            Response::error('Error al eliminar perfil', 500);
        } catch (Exception $e) {
            Response::error('Error al eliminar perfil: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/perfiles/stats
     * Estadísticas de perfiles (admin)
     */
    public function stats() {
        $user = $this->authMiddleware->requireAuth();
        
        if ($user['rol'] !== 'admin') {
            Response::error('Acceso denegado', 403);
        }
        
        $stats = $this->model->getStats();
        Response::success($stats);
    }
    
    /**
     * GET /api/v1/perfiles/sectores
     * Listar sectores disponibles con conteo
     */
    public function sectores() {
        $this->authMiddleware->requireAuth();
        
        $sectores = $this->model->getSectores();
        Response::success($sectores);
    }
}
