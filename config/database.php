<?php
// config/database.php
$host = getenv('DB_HOST') ?: 'db';
$dbname = getenv('DB_DATABASE') ?: 'boda_invitaciones';
$user = getenv('DB_USER') ?: 'sixinio';
$pass = getenv('DB_PASSWORD') ?: '123456';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}
return $pdo;