<?php

declare(strict_types=1);

namespace App\Nlp;

use App\Chat\Intencao;

/**
 * Implementação do NlpInterface usando a API de mensagens da Anthropic (Claude).
 * Toda comunicação HTTP é isolada aqui — trocar de provedor significa apenas
 * escrever uma nova classe que implemente NlpInterface.
 */
final class ClaudeNlpAdapter implements NlpInterface
{
    public function __construct(
        private string $apiKey,
        private string $apiUrl = 'https://api.anthropic.com/v1/messages',
        private string $model = 'claude-sonnet-4-6'
    ) {
    }

    public function interpretarMensagem(string $texto, array $contexto = []): array
    {
        $intencoes = implode(', ', Intencao::todas());

        $system = <<<PROMPT
Você é o módulo de interpretação de linguagem natural de um CRM de oficina mecânica.
Sua única tarefa é ler a mensagem de um mecânico e devolver APENAS um JSON válido,
sem nenhum texto antes ou depois, no formato:

{
  "intencao": "uma das opções: {$intencoes}",
  "dados": {
    "placa": "string ou null",
    "marca": "string ou null",
    "modelo": "string ou null",
    "versao": "string ou null",
    "cor": "string ou null",
    "ano": "número ou null",
    "quilometragem": "número ou null",
    "combustivel": "gasolina, etanol, flex, diesel, gnv, eletrico, hibrido ou null",
    "cliente_nome": "string ou null",
    "cliente_telefone": "string ou null",
    "motivo": "string curto ou null (motivo da entrada)",
    "reclamacao": "string ou null",
    "descricao": "string ou null",
    "comprada_por": "oficina, cliente ou null",
    "valor": "número ou null",
    "prazo_dias": "número ou null",
    "indice_diagnostico": "número (posição 1-based citada, ex: 'o segundo' = 2) ou null"
  },
  "confianca": 0.0 a 1.0,
  "pergunta_esclarecimento": "string ou null — preencha SOMENTE se faltar um dado essencial (ex: placa não identificada) ou houver ambiguidade real"
}

Regras importantes:
- Nunca invente placa, nome de cliente ou valores que não estejam na mensagem.
- Se a mensagem citar dois carros do mesmo modelo/cor sem placa, marque a intenção
  correspondente mas deixe "placa" nulo e preencha "pergunta_esclarecimento" pedindo a placa.
- Retorne somente o JSON, nada mais.
PROMPT;

        $mensagemUsuario = $texto;
        if (!empty($contexto)) {
            $mensagemUsuario .= "\n\n[Contexto da conversa: " . json_encode($contexto, JSON_UNESCAPED_UNICODE) . ']';
        }

        $resposta = $this->chamarClaude($system, $mensagemUsuario, 800);
        $json = $this->extrairJson($resposta);

        if ($json === null) {
            return [
                'intencao'   => Intencao::NAO_RECONHECIDA,
                'dados'      => [],
                'confianca'  => 0.0,
                'pergunta_esclarecimento' => 'Não consegui entender a mensagem. Pode reformular?',
            ];
        }

        return $json;
    }

    public function sugerirDiagnosticos(string $reclamacao, array $veiculo): array
    {
        $system = <<<PROMPT
Você é um mecânico automotivo experiente. Com base na reclamação do cliente e nos
dados do veículo, liste de 3 a 6 possíveis causas técnicas, da mais provável para
a menos provável. Responda APENAS com um JSON no formato:
{"diagnosticos": ["causa 1", "causa 2", ...]}
Nada de texto fora do JSON.
PROMPT;

        $mensagemUsuario = sprintf(
            "Reclamação do cliente: %s\nVeículo: %s %s (%s), ano %s",
            $reclamacao,
            $veiculo['modelo'] ?? '?',
            $veiculo['cor'] ?? '',
            $veiculo['placa'] ?? '?',
            $veiculo['ano'] ?? '?'
        );

        $resposta = $this->chamarClaude($system, $mensagemUsuario, 500);
        $json = $this->extrairJson($resposta);

        if ($json === null || !isset($json['diagnosticos']) || !is_array($json['diagnosticos'])) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $json['diagnosticos'])));
    }

    private function chamarClaude(string $system, string $mensagemUsuario, int $maxTokens): string
    {
        $body = [
            'model'      => $this->model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [
                ['role' => 'user', 'content' => $mensagemUsuario],
            ],
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT    => 30,
        ]);

        $respostaBruta = curl_exec($ch);
        $erro = curl_error($ch);
        curl_close($ch);

        if ($respostaBruta === false) {
            throw new \RuntimeException("Falha ao chamar a API de NLP: {$erro}");
        }

        $decodificado = json_decode($respostaBruta, true);
        $texto = $decodificado['content'][0]['text'] ?? '';

        return (string) $texto;
    }

    private function extrairJson(string $texto): ?array
    {
        // Remove possíveis blocos de código markdown (```json ... ```)
        $texto = trim($texto);
        $texto = preg_replace('/^```(json)?/i', '', $texto);
        $texto = preg_replace('/```$/', '', $texto);
        $texto = trim($texto);

        $decodificado = json_decode($texto, true);
        return is_array($decodificado) ? $decodificado : null;
    }
}
