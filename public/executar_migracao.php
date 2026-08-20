<?php
require dirname(__DIR__) . '/bootstrap.php';

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
    $sql = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    $pdo->exec($sql);
    echo "✅ Tabelas criadas!";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}