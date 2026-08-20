<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Chat\ChatOrchestrator;
use App\Config\Container;
use App\Config\Env;
use App\Repository\UsuarioRepository;
use App\Telegram\TelegramClient;

header('Content-Type: application/json');

// Valida o segredo do webhook (definido no setWebhook e conferido a cada chamada).
$segredoEsperado = Env::get('TELEGRAM_WEBHOOK_SECRET');
$segredoRecebido = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;

if ($segredoEsperado !== null && $segredoRecebido !== $segredoEsperado) {
    http_response_code(401);
    echo json_encode(['erro' => 'Assinatura de webhook inválida.']);
    exit;
}

$corpoBruto = file_get_contents('php://input');
$update = json_decode($corpoBruto ?: '{}', true);

if (!is_array($update) || !isset($update['message'])) {
    // Outros tipos de update (callback_query, edited_message, etc.) são ignorados por ora.
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

$mensagem = $update['message'];
$chatId = (string) ($mensagem['chat']['id'] ?? '');
$telegramUserId = (string) ($mensagem['from']['id'] ?? '');
$nomeTelegram = trim(($mensagem['from']['first_name'] ?? '') . ' ' . ($mensagem['from']['last_name'] ?? ''));

if ($chatId === '' || $telegramUserId === '') {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

$usuarios = Container::usuarioRepository();
$usuario = $usuarios->buscarPorTelegramId($telegramUserId);

// Onboarding automático: primeiro contato de um mecânico vira usuário "mecanico".
// Para promover alguém a admin/atendente, o gestor ajusta o papel pelo painel web.
if ($usuario === null) {
    $novoId = $usuarios->criar($nomeTelegram !== '' ? $nomeTelegram : 'Mecânico', 'mecanico', null, null, $telegramUserId);
    $usuario = $usuarios->buscarPorId($novoId);
}

$usuarioId = (int) $usuario['id'];
$telegram = Container::telegramClient();
$orchestrator = Container::chatOrchestrator();

try {
    $resposta = processarMensagem($mensagem, $chatId, $usuarioId, $orchestrator, $telegram);
    $telegram->enviarMensagem($chatId, $resposta);
} catch (\Throwable $e) {
    error_log('Erro no webhook do Telegram: ' . $e->getMessage());
    $telegram->enviarMensagem($chatId, '⚠️ Ocorreu um erro inesperado ao processar sua mensagem. Tente novamente.');
}

http_response_code(200);
echo json_encode(['ok' => true]);

// -------------------------------------------------------------------

function processarMensagem(array $mensagem, string $chatId, int $usuarioId, ChatOrchestrator $orchestrator, TelegramClient $telegram): string
{
    // Texto simples
    if (isset($mensagem['text'])) {
        return $orchestrator->processarTexto($chatId, $usuarioId, (string) $mensagem['text']);
    }

    // Áudio / mensagem de voz -> transcreve e trata como texto
    if (isset($mensagem['voice']) || isset($mensagem['audio'])) {
        $fileId = $mensagem['voice']['file_id'] ?? $mensagem['audio']['file_id'];
        $caminhoLocal = $telegram->baixarArquivo($fileId, APP_BASE_PATH . '/storage/uploads/audio');

        $transcritor = Container::transcricaoService();
        $textoTranscrito = $transcritor->transcrever($caminhoLocal);

        return $orchestrator->processarTexto($chatId, $usuarioId, $textoTranscrito, 'audio');
    }

    // Foto -> anexa à OS (placa vem da legenda, se houver)
    if (isset($mensagem['photo'])) {
        $fotos = $mensagem['photo'];
        $maiorFoto = end($fotos); // Telegram envia várias resoluções; a última é a maior.
        $caminhoLocal = $telegram->baixarArquivo($maiorFoto['file_id'], APP_BASE_PATH . '/storage/uploads/fotos');
        $legenda = $mensagem['caption'] ?? null;

        return $orchestrator->processarAnexo($chatId, $usuarioId, 'foto', $caminhoLocal, $legenda);
    }

    return 'Recebi sua mensagem, mas ainda não sei processar esse tipo de conteúdo. Envie texto, áudio ou foto.';
}
