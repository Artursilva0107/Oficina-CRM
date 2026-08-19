<?php

declare(strict_types=1);

// Script de linha de comando para registrar o webhook do bot no Telegram.
// Uso: php bin/configurar_webhook.php

require dirname(__DIR__) . '/bootstrap.php';

use App\Config\Container;
use App\Config\Env;

$urlBase = rtrim(Env::required('APP_URL'), '/');
$urlWebhook = "{$urlBase}/webhook.php";
$secret = Env::required('TELEGRAM_WEBHOOK_SECRET');

$telegram = Container::telegramClient();
$resultado = $telegram->configurarWebhook($urlWebhook, $secret);

echo "Webhook configurado para: {$urlWebhook}\n";
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
