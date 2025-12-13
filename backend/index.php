<?php
/**
 * ============================================================================
 * PUNTO DE ENTRADA DE LA API
 * ============================================================================
 * Archivo principal que inicializa la aplicación y procesa las peticiones
 * ============================================================================
 */

// Iniciar sesión si es necesario
session_start();

// Cargar configuración
require_once __DIR__ . '/config/config.php';

// Cargar utilidades
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Logger.php';
require_once __DIR__ . '/utils/Validator.php';
require_once __DIR__ . '/utils/Security.php';
require_once __DIR__ . '/utils/Cache.php';
require_once __DIR__ . '/utils/FileOptimizer.php';

// Cargar configuración de base de datos
require_once __DIR__ . '/config/database.php';

// Cargar modelos
require_once __DIR__ . '/models/Usuario.php';
require_once __DIR__ . '/models/Categoria.php';
require_once __DIR__ . '/models/Curso.php';
require_once __DIR__ . '/models/Modulo.php';
require_once __DIR__ . '/models/Leccion.php';
require_once __DIR__ . '/models/Inscripcion.php';
require_once __DIR__ . '/models/ProgresoLeccion.php';
require_once __DIR__ . '/models/PerfilEmpresarial.php';
require_once __DIR__ . '/models/TipoDiagnostico.php';
require_once __DIR__ . '/models/DiagnosticoRealizado.php';
require_once __DIR__ . '/models/MotorRecomendaciones.php';
require_once __DIR__ . '/models/CuestionarioInicial.php';
// Modelos Fase 2B - Evaluaciones
require_once __DIR__ . '/models/Evaluacion.php';
require_once __DIR__ . '/models/PreguntaEvaluacion.php';
require_once __DIR__ . '/models/OpcionPregunta.php';
require_once __DIR__ . '/models/IntentoEvaluacion.php';
require_once __DIR__ . '/models/Certificado.php';
require_once __DIR__ . '/models/Prerrequisito.php';
// Modelos Fase 4 - Gamificación
require_once __DIR__ . '/models/PuntosUsuario.php';
require_once __DIR__ . '/models/Logro.php';
require_once __DIR__ . '/models/RachaUsuario.php';
require_once __DIR__ . '/models/Notificacion.php';
// Modelos Fase 5A - Productos
require_once __DIR__ . '/models/CategoriaProducto.php';
require_once __DIR__ . '/models/Producto.php';
// Modelos Fase 5B - MentorIA (Chat)
require_once __DIR__ . '/models/Conversacion.php';
require_once __DIR__ . '/models/Mensaje.php';
require_once __DIR__ . '/models/DisponibilidadInstructor.php';
require_once __DIR__ . '/models/EstadoPresencia.php';
// Modelos Fase 6 - Biblioteca de Recursos
require_once __DIR__ . '/models/CategoriaRecurso.php';
require_once __DIR__ . '/models/Recurso.php';
require_once __DIR__ . '/models/RecursoVersion.php';

// Servicios
require_once __DIR__ . '/services/MentoriaService.php';

// Cargar middleware
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// Cargar controladores
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/CategoriaController.php';
require_once __DIR__ . '/controllers/CursoController.php';
require_once __DIR__ . '/controllers/ModuloController.php';
require_once __DIR__ . '/controllers/LeccionController.php';
require_once __DIR__ . '/controllers/ProgresoController.php';
require_once __DIR__ . '/controllers/PerfilEmpresarialController.php';
require_once __DIR__ . '/controllers/DiagnosticoController.php';
require_once __DIR__ . '/controllers/EvaluacionController.php';
require_once __DIR__ . '/controllers/GamificacionController.php';
require_once __DIR__ . '/controllers/ProductoController.php';
require_once __DIR__ . '/controllers/MentoriaController.php';
require_once __DIR__ . '/controllers/RecursoController.php';
require_once __DIR__ . '/controllers/OnboardingController.php';
require_once __DIR__ . '/controllers/DashboardController.php';

// Cargar router
require_once __DIR__ . '/routes/Router.php';
require_once __DIR__ . '/routes/api.php';

// Manejo de errores global
set_exception_handler(function($exception) {
    Logger::error('Uncaught exception: ' . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    if (APP_DEBUG) {
        Response::serverError($exception->getMessage());
    } else {
        Response::serverError('Error interno del servidor');
    }
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    Logger::error("PHP Error: {$errstr}", [
        'file' => $errfile,
        'line' => $errline,
        'errno' => $errno
    ]);
    
    if (APP_DEBUG) {
        Response::serverError("Error: {$errstr} in {$errfile} on line {$errline}");
    } else {
        Response::serverError('Error interno del servidor');
    }
});

// Obtener método HTTP y URI
$method = $_SERVER['REQUEST_METHOD'];

// Guardar el body input para que pueda ser leído múltiples veces
// php://input solo se puede leer una vez, así que lo guardamos
$_RAW_BODY = file_get_contents('php://input');

// Soporte para métodos HTTP simulados
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

// También soportar el header X-HTTP-Method-Override
if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
}

// Obtener URI y limpiarla
// Primero intentar obtener de PATH_INFO si existe
if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
    $uri = $_SERVER['PATH_INFO'];
} else {
    $uri = $_SERVER['REQUEST_URI'];
    
    // Remover query string
    $uri = strtok($uri, '?');
    
    // Remover el path base del proyecto
    $scriptName = $_SERVER['SCRIPT_NAME']; // /nenis_y_bros/backend/index.php
    $basePath = dirname($scriptName); // /nenis_y_bros/backend
    
    // Si la URI empieza con el basePath, removerlo
    if (strpos($uri, $basePath) === 0) {
        $uri = substr($uri, strlen($basePath));
    }
    
    // Remover /index.php si existe
    if (strpos($uri, '/index.php') === 0) {
        $uri = substr($uri, strlen('/index.php'));
    }
}

// DEBUG: Log temporal
error_log("DEBUG - REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("DEBUG - PATH_INFO: " . (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : 'not set'));
error_log("DEBUG - URI antes de limpiar: " . $uri);

// Remover el prefijo de la API si existe (/api/v1)
$apiPrefix = '/' . API_PREFIX . '/' . API_VERSION;
if (strpos($uri, $apiPrefix) === 0) {
    $uri = substr($uri, strlen($apiPrefix));
}

error_log("DEBUG - URI después de remover apiPrefix: " . $uri);
error_log("DEBUG - URI final: " . $uri);

// Asegurar que empiece con /
if (!empty($uri) && $uri[0] !== '/') {
    $uri = '/' . $uri;
}

// Si está vacío, es la raíz
if (empty($uri)) {
    $uri = '/';
}

// Inicializar router
$router = new Router();

// Registrar rutas
registerRoutes($router);

// Ejecutar router
try {
    $router->dispatch($method, $uri);
} catch (Exception $e) {
    Logger::error('Router exception: ' . $e->getMessage());
    Response::serverError('Error al procesar la solicitud');
}
