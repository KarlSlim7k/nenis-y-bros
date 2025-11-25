<?php
// Test password hash
$password = 'password';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: $password\n";
echo "Hash: $hash\n";
echo "\nVerify test: " . (password_verify($password, $hash) ? 'SUCCESS' : 'FAILED') . "\n";

// Test with existing hash from DB
$db_hash = '$2y$10$mmOINyBXgD56Q.ZQHVl1BuQ17ciNy9d7gLc2GWc5Z5.qDl9Zb39Ui';
echo "\nTest with generated hash: " . (password_verify($password, $db_hash) ? 'SUCCESS' : 'FAILED') . "\n";
