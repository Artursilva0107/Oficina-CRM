<?php

declare(strict_types=1);

namespace App\Telegram;

/**
 * Cliente fino para a Bot API do Telegram: envio de mensagens e download
 * de arquivos (fotos/áudios) enviados pelo mecânico.
 */
final class TelegramClient
{
    private string $baseUrl;

    public function __construct(private string $token)
    {
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    public function enviarMensagem(int|string $chatId, string $texto): void
    {
        $this->chamar('sendMessage', [
            'chat_id'    => $chatId,
            'text'       => $texto,
            'parse_mode' => 'HTML',
        ]);
    }

    /** Baixa um arquivo enviado pelo Telegram (foto ou áudio) e salva localmente. Retorna o caminho local. */
    public function baixarArquivo(string $fileId, string $diretorioDestino): string
    {
        $info = $this->chamar('getFile', ['file_id' => $fileId]);
        $filePath = $info['result']['file_path'] ?? null;

        if ($filePath === null) {
            throw new \RuntimeException('Não foi possível obter o caminho do arquivo no Telegram.');
        }

        $url = "https://api.telegram.org/file/bot{$this->token}/{$filePath}";
        $conteudo = file_get_contents($url);

        if ($conteudo === false) {
            throw new \RuntimeException('Falha ao baixar arquivo do Telegram.');
        }

        if (!is_dir($diretorioDestino)) {
            mkdir($diretorioDestino, 0775, true);
        }

        $nomeArquivo = basename($filePath);
        $destino = rtrim($diretorioDestino, '/') . '/' . uniqid('', true) . '_' . $nomeArquivo;
        file_put_contents($destino, $conteudo);

        return $destino;
    }

    public function configurarWebhook(string $urlWebhook, string $secretToken): array
    {
        return $this->chamar('setWebhook', [
            'url'          => $urlWebhook,
            'secret_token' => $secretToken,
        ]);
    }

    private function chamar(string $metodo, array $parametros): array
    {
        $ch = curl_init("{$this->baseUrl}/{$metodo}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $parametros,
            CURLOPT_TIMEOUT        => 20,
        ]);

        $respostaBruta = curl_exec($ch);
        $erro = curl_error($ch);
        curl_close($ch);

        if ($respostaBruta === false) {
            throw new \RuntimeException("Falha na chamada ao Telegram ({$metodo}): {$erro}");
        }

        return json_decode($respostaBruta, true) ?? [];
    }
}
