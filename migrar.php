<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/bootstrap.php';

try {
    $pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE') . ';charset=utf8mb4',
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );

    $sql = file_get_contents(__DIR__ . '/database/schema.sql');
    $pdo->exec($sql);
    echo "<p style='color:green'>✅ Migração executada com sucesso! Tabelas criadas.</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erro: " . $e->getMessage() . "</p>";
}