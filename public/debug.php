<?php

declare(strict_types=1);

// ⚠️ ARQUIVO TEMPORÁRIO DE DIAGNÓSTICO — APAGUE DEPOIS DE USAR.
// Ele expõe informações técnicas do servidor; não deixe em produção
// depois de resolver o problema.

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== PHP ===\n";
echo 'Versão: ' . PHP_VERSION . "\n";
echo 'pdo_mysql carregado: ' . (extension_loaded('pdo_mysql') ? 'sim' : 'NÃO — falta essa extensão no container') . "\n";
echo 'openssl carregado: ' . (extension_loaded('openssl') ? 'sim' : 'não') . "\n";

echo "\n=== Autoload ===\n";
echo 'vendor/autoload.php existe: ' . (is_file(dirname(__DIR__) . '/vendor/autoload.php') ? 'sim (composer)' : 'não (usando autoload de fallback)') . "\n";
echo 'Container encontrado: ' . (class_exists(\App\Config\Container::class) ? 'sim' : 'NÃO') . "\n";
echo 'AuthService encontrado: ' . (class_exists(\App\Auth\AuthService::class) ? 'sim' : 'NÃO') . "\n";

echo "\n=== Variáveis de ambiente (sem mostrar senha) ===\n";
foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_SSL_MODE'] as $k) {
    echo "{$k}: " . (\App\Config\Env::get($k) ?? '(não definida)') . "\n";
}
echo 'DB_PASSWORD definida: ' . (\App\Config\Env::get('DB_PASSWORD') !== null ? 'sim' : 'NÃO') . "\n";

echo "\n=== Conexão com o banco ===\n";
try {
    $pdo = \App\Config\Database::connection();
    echo "Conexão OK.\n";

    echo "\n=== Tabela 'usuarios' ===\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    if ($stmt->rowCount() === 0) {
        echo "A TABELA 'usuarios' NÃO EXISTE — rode o schema.sql neste banco.\n";
    } else {
        echo "Tabela existe.\n";
        $count = $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        echo "Total de usuários cadastrados: {$count}\n";
    }
} catch (\Throwable $e) {
    echo "FALHA NA CONEXÃO: " . $e->getMessage() . "\n";
}
