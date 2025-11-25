<?php
// Simulate environment
define('APP_DEBUG', true);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/v1/auth/login';
$_SERVER['SCRIPT_NAME'] = '/nenis_y_bros/backend/index.php';

// Mock input
$input = json_encode([
    'email' => 'test@example.com',
    'password' => 'password123'
]);

// We can't easily mock php://input for file_get_contents, 
// so we'll modify index.php slightly or just test AuthController directly if possible.
// But AuthController depends on global $_RAW_BODY.

// Let's set the global variable
$_RAW_BODY = $input;

// Load necessary files (mimic index.php)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Logger.php';
require_once __DIR__ . '/utils/Validator.php';
require_once __DIR__ . '/utils/Security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Usuario.php';
require_once __DIR__ . '/controllers/AuthController.php';

// Mock Response class to avoid exiting
class MockResponse {
    public static function success($data, $message = '') {
        echo "SUCCESS: " . json_encode($data) . "\nMessage: $message\n";
    }
    public static function error($message, $code = 400) {
        echo "ERROR ($code): $message\n";
    }
    public static function unauthorized($message) {
        echo "UNAUTHORIZED: $message\n";
    }
    public static function validationError($errors) {
        echo "VALIDATION ERROR: " . json_encode($errors) . "\n";
    }
    public static function serverError($message) {
        echo "SERVER ERROR: $message\n";
    }
    public static function forbidden($message) {
        echo "FORBIDDEN: $message\n";
    }
    public static function created($data, $message = '') {
        echo "CREATED: " . json_encode($data) . "\nMessage: $message\n";
    }
}

// Override Response class using class_alias if possible, but Response is already included.
// We can't redefine class Response.
// So we have to rely on the actual Response class which might exit.
// Let's check Response.php to see if it exits.
