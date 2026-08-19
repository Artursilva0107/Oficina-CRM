<?php

declare(strict_types=1);

namespace App\Nlp;

/**
 * Contrato para o serviço de interpretação de linguagem natural.
 * Qualquer provedor (Claude, GPT, etc.) deve implementar esta interface,
 * para que o provedor possa ser trocado sem alterar o restante do sistema.
 */
interface NlpInterface
{
    /**
     * Interpreta uma mensagem livre do mecânico e retorna um array estruturado:
     * [
     *   'intencao' => string,   // uma das constantes App\Chat\Intencao::*
     *   'dados'    => array,    // campos extraídos (placa, modelo, cliente, descricao, etc.)
     *   'confianca'=> float,    // 0.0 a 1.0
     *   'pergunta_esclarecimento' => ?string // preenchido quando a IA não tem certeza
     * ]
     *
     * @param array $contexto contexto adicional (ex: veículo com OS aberta na conversa atual)
     */
    public function interpretarMensagem(string $texto, array $contexto = []): array;

    /**
     * Sugere uma lista de possíveis diagnósticos automotivos a partir da
     * reclamação do cliente e dados do veículo.
     *
     * @return string[] lista de descrições de possíveis causas
     */
    public function sugerirDiagnosticos(string $reclamacao, array $veiculo): array;
}
