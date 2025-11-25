<?php
/**
 * ============================================================================
 * CONFIGURACIÓN PRINCIPAL DEL SISTEMA
 * ============================================================================
 * Carga las variables de entorno y define constantes globales
 * ============================================================================
 */

// Cargar variables de entorno
if (file_exists(__DIR__ . '/../../.env')) {
    $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Función helper para obtener variables de entorno
function env($key, $default = null) {
    $value = getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // Convertir valores booleanos
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'null':
        case '(null)':
            return null;
    }
    
    return $value;
}

// Configuración de la aplicación
define('APP_NAME', env('APP_NAME', 'Nenis y Bros'));
define('APP_ENV', env('APP_ENV', 'development'));
define('APP_DEBUG', env('APP_DEBUG', true));
define('APP_URL', env('APP_URL', 'http://localhost/nenis_y_bros'));

// Configuración de base de datos
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_DATABASE', env('DB_DATABASE', 'formacion_empresarial'));
define('DB_USERNAME', env('DB_USERNAME', 'root'));
define('DB_PASSWORD', env('DB_PASSWORD', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
define('DB_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci'));

// Seguridad
define('JWT_SECRET', env('JWT_SECRET', 'change_this_secret_key'));
define('SESSION_LIFETIME', env('SESSION_LIFETIME', 120));
define('ENCRYPTION_KEY', env('ENCRYPTION_KEY', 'change_this_encryption_key'));

// Rutas
define('BASE_PATH', dirname(dirname(__DIR__)));
define('BACKEND_PATH', BASE_PATH . '/backend');
define('UPLOAD_PATH', BASE_PATH . '/' . env('UPLOAD_PATH', 'uploads/'));
define('LOG_PATH', BACKEND_PATH . '/logs/');

// Configuración de archivos
define('UPLOAD_MAX_SIZE', env('UPLOAD_MAX_SIZE', 5242880)); // 5MB por defecto
define('UPLOAD_ALLOWED_EXTENSIONS', explode(',', env('UPLOAD_ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,pdf')));

// Timezone
date_default_timezone_set(env('TIMEZONE', 'America/Mexico_City'));

// Configuración de errores
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// API
define('API_PREFIX', env('API_PREFIX', 'api'));
define('API_VERSION', env('API_VERSION', 'v1'));

// Cache (Redis)
define('CACHE_ENABLED', env('CACHE_ENABLED', false));
define('REDIS_HOST', env('REDIS_HOST', '127.0.0.1'));
define('REDIS_PORT', env('REDIS_PORT', 6379));
define('REDIS_PASSWORD', env('REDIS_PASSWORD', ''));
define('REDIS_DB', env('REDIS_DB', 0));
define('CACHE_PREFIX', env('CACHE_PREFIX', 'nyd'));

// Headers de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// CORS - Permitir peticiones desde el frontend
$allowedOrigins = [
    'https://nenis-y-bros.vercel.app',
    'https://nenis-y-bros-karlslim7ks-projects.vercel.app',
    'http://localhost',
    'http://127.0.0.1'
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// En desarrollo permitir todo, en producción solo orígenes permitidos
if (APP_ENV === 'development') {
    header('Access-Control-Allow-Origin: *');
} elseif (in_array($origin, $allowedOrigins) || strpos($origin, 'vercel.app') !== false) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://nenis-y-bros.vercel.app');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Manejar preflight requests (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 200 OK');
    exit();
}

return [
    'app' => [
        'name' => APP_NAME,
        'env' => APP_ENV,
        'debug' => APP_DEBUG,
        'url' => APP_URL
    ],
    'database' => [
        'host' => DB_HOST,
        'port' => DB_PORT,
        'database' => DB_DATABASE,
        'username' => DB_USERNAME,
        'password' => DB_PASSWORD,
        'charset' => DB_CHARSET,
        'collation' => DB_COLLATION
    ],
    'security' => [
        'jwt_secret' => JWT_SECRET,
        'session_lifetime' => SESSION_LIFETIME,
        'encryption_key' => ENCRYPTION_KEY
    ],
    'paths' => [
        'base' => BASE_PATH,
        'backend' => BACKEND_PATH,
        'upload' => UPLOAD_PATH,
        'log' => LOG_PATH
    ]
];
