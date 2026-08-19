<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Converte arquivos de áudio em texto usando a API de transcrição (Whisper).
 * Isolado em um serviço próprio para permitir troca de provedor futuramente.
 */
final class TranscricaoService
{
    public function __construct(
        private string $apiKey,
        private string $apiUrl = 'https://api.openai.com/v1/audio/transcriptions',
        private string $modelo = 'whisper-1'
    ) {
    }

    /**
     * @param string $caminhoArquivoLocal caminho para o arquivo de áudio já salvo em disco
     */
    public function transcrever(string $caminhoArquivoLocal): string
    {
        if (!is_file($caminhoArquivoLocal)) {
            throw new \RuntimeException("Arquivo de áudio não encontrado: {$caminhoArquivoLocal}");
        }

        $cfile = new \CURLFile($caminhoArquivoLocal);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => [
                'file'            => $cfile,
                'model'           => $this->modelo,
                'language'        => 'pt',
                'response_format' => 'json',
            ],
            CURLOPT_TIMEOUT => 60,
        ]);

        $respostaBruta = curl_exec($ch);
        $erro = curl_error($ch);
        curl_close($ch);

        if ($respostaBruta === false) {
            throw new \RuntimeException("Falha ao transcrever áudio: {$erro}");
        }

        $decodificado = json_decode($respostaBruta, true);

        if (!isset($decodificado['text'])) {
            throw new \RuntimeException('Resposta inesperada do serviço de transcrição: ' . $respostaBruta);
        }

        return trim((string) $decodificado['text']);
    }
}
