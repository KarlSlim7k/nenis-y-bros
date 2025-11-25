<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();
$users = $db->fetchAll("SELECT id_usuario, email, tipo_usuario, estado FROM usuarios LIMIT 10");

foreach ($users as $user) {
    echo "ID: {$user['id_usuario']}, Email: {$user['email']}, Type: {$user['tipo_usuario']}, Status: {$user['estado']}\n";
}
