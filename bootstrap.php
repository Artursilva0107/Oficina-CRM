<?php

declare(strict_types=1);

use App\Config\Env;

define('APP_BASE_PATH', __DIR__);

$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    // Fallback simples caso o `composer install` ainda não tenha sido rodado:
    // mapeia o namespace App\ diretamente para a pasta src/ (PSR-4 manual).
    spl_autoload_register(function (string $classe): void {
        if (!str_starts_with($classe, 'App\\')) {
            return;
        }
        $caminhoRelativo = str_replace('\\', '/', substr($classe, strlen('App\\')));
        $arquivo = __DIR__ . '/src/' . $caminhoRelativo . '.php';
        if (is_file($arquivo)) {
            require $arquivo;
        }
    });
}

require __DIR__ . '/src/Config/Env.php';
Env::load(APP_BASE_PATH);

date_default_timezone_set(Env::get('APP_TIMEZONE', 'America/Sao_Paulo'));

require_once __DIR__ . '/src/Support/helpers.php';

error_reporting(E_ALL);
ini_set('display_errors', Env::get('APP_ENV', 'production') === 'local' ? '1' : '0');
ini_set('log_errors', '1');
// Em produção (Render, containers em geral) o sistema de arquivos costuma ser
// efêmero e a pasta storage/logs pode nem existir no deploy — por isso os erros
// vão sempre para stderr, que é o que a maioria das plataformas (Render, Docker,
// etc.) captura e mostra na aba de Logs. Em ambiente local, tenta gravar em
// arquivo (mais prático pra debugar no seu próprio computador).
if (Env::get('APP_ENV', 'production') === 'local' && is_writable(APP_BASE_PATH . '/storage/logs')) {
    ini_set('error_log', APP_BASE_PATH . '/storage/logs/php-error.log');
} else {
    ini_set('error_log', 'php://stderr');
}
