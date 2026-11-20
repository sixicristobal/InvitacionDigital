<?php
declare(strict_types=1);

$pdo = require dirname(__DIR__) . '/config/database.php';
$migrationsDirectory = dirname(__DIR__) . '/sql/migrations';
$migrationFiles = glob($migrationsDirectory . '/*.sql');

if ($migrationFiles === false) {
    throw new RuntimeException('No se pudieron leer las migraciones.');
}

sort($migrationFiles, SORT_STRING);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

foreach ($migrationFiles as $migrationFile) {
    $version = basename($migrationFile);
    $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
    $check->execute([$version]);

    if ($check->fetchColumn() !== false) {
        printf("Ya aplicada: %s\n", $version);
        continue;
    }

    $sql = file_get_contents($migrationFile);
    if ($sql === false) {
        throw new RuntimeException("No se pudo leer la migración: {$version}");
    }

    $pdo->exec($sql);
    $record = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
    $record->execute([$version]);

    printf("Aplicada: %s\n", $version);
}
