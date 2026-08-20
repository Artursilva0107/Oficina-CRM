<?php

declare(strict_types=1);

namespace App\Chat;

use App\Nlp\NlpInterface;
use App\Repository\ConversaContextoRepository;
use App\Repository\InteracaoChatRepository;
use App\Repository\VeiculoRepository;
use App\Service\DiagnosticoService;
use App\Service\GarantiaService;
use App\Service\OrdemServicoService;
use App\Service\VeiculoService;

/**
 * Recebe o texto já pronto (mensagem digitada ou áudio já transcrito),
 * decide o que fazer e devolve o texto de resposta para o chat.
 *
 * Regra de ouro: em caso de ambiguidade (placa não identificada, mais de
 * uma OS aberta para o veículo, veículo não encontrado), NUNCA adivinha —
 * sempre pergunta de volta e guarda o estado pendente da conversa.
 */
final class ChatOrchestrator
{
    public function __construct(
        private NlpInterface $nlp,
        private VeiculoService $veiculoService,
        private OrdemServicoService $osService,
        private DiagnosticoService $diagnosticoService,
        private GarantiaService $garantiaService,
        private ConversaContextoRepository $contexto,
        private InteracaoChatRepository $log
    ) {
    }

    /** @param string $tipoOrigem 'texto' ou 'audio' (áudio já transcrito) — apenas para fins de log. */
    public function processarTexto(string $chatId, ?int $usuarioId, string $texto, string $tipoOrigem = 'texto'): string
    {
        $pendente = $this->contexto->obter($chatId);

        if ($pendente !== null && $pendente['intencao_pendente'] !== null) {
            return $this->continuarComEsclarecimento($chatId, $usuarioId, $texto, $pendente);
        }

        $interpretacao = $this->nlp->interpretarMensagem($texto, []);
        return $this->executar($chatId, $usuarioId, $texto, $interpretacao, $tipoOrigem);
    }

    /** Anexa um arquivo (foto/áudio) já baixado localmente à OS certa. */
    public function processarAnexo(string $chatId, ?int $usuarioId, string $tipo, string $caminhoLocal, ?string $placaInformada, ?string $transcricao = null): string
    {
        $placa = $placaInformada !== null ? self::extrairPlaca($placaInformada) : null;

        if ($placa === null) {
            $osAlvo = $this->unicaOsAbertaGlobalmente();
            if ($osAlvo === null) {
                $this->log->registrar($usuarioId, $tipo, null, $transcricao, 'anexo_ambiguo', null, false);
                return '⚠️ Não sei a qual veículo esse anexo pertence. Me diga a placa (ex.: "placa ABC1D23") junto com a foto/áudio.';
            }
        } else {
            $osAlvo = $this->osService->osAlvoParaPlaca($placa);
            if ($osAlvo === null) {
                $this->log->registrar($usuarioId, $tipo, null, $transcricao, 'anexo_sem_os', null, false);
                return "⚠️ Não encontrei uma única OS em aberto para a placa {$placa}. Verifique se o veículo está cadastrado e com uma OS aberta.";
            }
        }

        $this->osService->anexarArquivo((int) $osAlvo['id'], $tipo, $caminhoLocal, $transcricao);
        $this->log->registrar($usuarioId, $tipo, null, $transcricao, 'anexo_registrado', ['ordem_servico_id' => $osAlvo['id']], true);

        return "📎 Anexo salvo na OS #{$osAlvo['id']}.";
    }

    private function continuarComEsclarecimento(string $chatId, ?int $usuarioId, string $texto, array $pendente): string
    {
        $intencao = $pendente['intencao_pendente'];
        $dados = $pendente['dados_pendentes'];

        $placaExtraida = self::extrairPlaca($texto);
        if ($placaExtraida !== null) {
            $dados['placa'] = $placaExtraida;
        } elseif (empty($dados['reclamacao']) && $intencao === Intencao::ABRIR_OS) {
            $dados['reclamacao'] = $texto;
        } elseif (empty($dados['descricao']) && in_array($intencao, [Intencao::REGISTRAR_DIAGNOSTICO, Intencao::REGISTRAR_SERVICO, Intencao::REGISTRAR_PECA], true)) {
            $dados['descricao'] = $texto;
        } else {
            // Não conseguimos extrair o dado esperado — repete a pergunta original.
            return "Ainda não entendi. " . ($dados['_pergunta_esclarecimento'] ?? 'Pode confirmar a placa do veículo?');
        }

        unset($dados['_pergunta_esclarecimento']);
        $this->contexto->limpar($chatId);

        return $this->executarAcao($chatId, $usuarioId, $texto, $intencao, $dados);
    }

    private function executar(string $chatId, ?int $usuarioId, string $textoOriginal, array $interpretacao, string $tipoOrigem = 'texto'): string
    {
        $intencao = $interpretacao['intencao'] ?? Intencao::NAO_RECONHECIDA;
        $dados = $interpretacao['dados'] ?? [];
        $perguntaIA = $interpretacao['pergunta_esclarecimento'] ?? null;

        if ($perguntaIA !== null) {
            $dadosParaSalvar = $dados;
            $dadosParaSalvar['_pergunta_esclarecimento'] = $perguntaIA;
            $this->contexto->salvar($chatId, $intencao, $dadosParaSalvar);
            $this->log->registrar($usuarioId, $tipoOrigem, $textoOriginal, json_encode($interpretacao, JSON_UNESCAPED_UNICODE), 'pediu_esclarecimento', $dados, true);
            return "❓ {$perguntaIA}";
        }

        return $this->executarAcao($chatId, $usuarioId, $textoOriginal, $intencao, $dados, $tipoOrigem);
    }

    private function executarAcao(string $chatId, ?int $usuarioId, string $textoOriginal, string $intencao, array $dados, string $tipoOrigem = 'texto'): string
    {
        try {
            $resposta = match ($intencao) {
                Intencao::CADASTRAR_VEICULO     => $this->acaoCadastrarVeiculo($dados),
                Intencao::ABRIR_OS              => $this->acaoAbrirOs($usuarioId, $dados, $chatId),
                Intencao::CONSULTAR_HISTORICO   => $this->acaoConsultarHistorico($dados),
                Intencao::REGISTRAR_DIAGNOSTICO => $this->acaoRegistrarDiagnostico($dados, $chatId),
                Intencao::CONFIRMAR_DIAGNOSTICO => $this->acaoDefinirDiagnostico($dados, 'confirmado', $chatId),
                Intencao::DESCARTAR_DIAGNOSTICO => $this->acaoDefinirDiagnostico($dados, 'descartado', $chatId),
                Intencao::REGISTRAR_SERVICO     => $this->acaoRegistrarServico($dados, $chatId),
                Intencao::REGISTRAR_PECA        => $this->acaoRegistrarPeca($dados, $chatId),
                Intencao::REGISTRAR_GARANTIA    => $this->acaoRegistrarGarantia($dados, $chatId),
                Intencao::MARCAR_ENTREGUE       => $this->acaoMarcarEntregue($dados, $chatId),
                Intencao::AJUDA                 => $this->acaoAjuda(),
                default                          => "🤔 Não entendi o que você quer fazer. Digite \"ajuda\" para ver os comandos que eu entendo.",
            };

            $this->log->registrar($usuarioId, $tipoOrigem, $textoOriginal, $intencao, $intencao, $dados, true);
            return $resposta;
        } catch (\DomainException $e) {
            $this->log->registrar($usuarioId, $tipoOrigem, $textoOriginal, $intencao, $intencao, $dados, false);
            return "⚠️ {$e->getMessage()}";
        }
    }

    // ---------------------------------------------------------------
    // Ações individuais
    // ---------------------------------------------------------------

    private function acaoCadastrarVeiculo(array $dados): string
    {
        $this->exigir($dados, ['placa', 'modelo', 'cliente_nome']);

        $veiculo = $this->veiculoService->cadastrar([
            'placa'            => (string) $dados['placa'],
            'modelo'           => (string) $dados['modelo'],
            'marca'            => $dados['marca'] ?? null,
            'versao'           => $dados['versao'] ?? null,
            'cor'              => $dados['cor'] ?? null,
            'ano'              => isset($dados['ano']) ? (int) $dados['ano'] : null,
            'quilometragem'    => isset($dados['quilometragem']) ? (int) $dados['quilometragem'] : null,
            'combustivel'      => $dados['combustivel'] ?? null,
            'cliente_nome'     => (string) $dados['cliente_nome'],
            'cliente_telefone' => $dados['cliente_telefone'] ?? null,
        ]);

        return "✅ Veículo cadastrado: {$veiculo['modelo']} placa {$veiculo['placa']}, cliente {$dados['cliente_nome']}.";
    }

    private function acaoAbrirOs(?int $usuarioId, array $dados, string $chatId): string
    {
        $this->exigir($dados, ['placa']);
        $placa = self::extrairPlaca((string) $dados['placa']) ?? (string) $dados['placa'];
        $veiculo = $this->veiculoService->buscarPorPlacaObrigatoria($placa);

        $os = $this->osService->abrir((int) $veiculo['id'], [
            'reclamacao'            => $dados['reclamacao'] ?? null,
            'motivo'                => $dados['motivo'] ?? null,
            'quilometragem_entrada' => $dados['quilometragem'] ?? null,
        ], $usuarioId);

        return "✅ OS #{$os['id']} aberta para o {$veiculo['modelo']} placa {$veiculo['placa']}."
            . (!empty($dados['reclamacao']) ? " Reclamação: {$dados['reclamacao']}." : '');
    }

    private function acaoConsultarHistorico(array $dados): string
    {
        $this->exigir($dados, ['placa']);
        $placa = self::extrairPlaca((string) $dados['placa']) ?? (string) $dados['placa'];
        $veiculo = $this->veiculoService->buscarPorPlacaObrigatoria($placa);
        $historico = $this->osService->historicoCompleto((int) $veiculo['id']);

        if (empty($historico)) {
            return "📋 O veículo {$veiculo['placa']} ainda não tem nenhuma OS registrada.";
        }

        $linhas = ["📋 Histórico de {$veiculo['modelo']} placa {$veiculo['placa']}:"];
        foreach ($historico as $os) {
            $linhas[] = "\n#OS {$os['id']} — status: {$os['status']} — entrada: {$os['data_entrada']}";
            if (!empty($os['reclamacao_cliente'])) {
                $linhas[] = "  Reclamação: {$os['reclamacao_cliente']}";
            }
            foreach ($os['servicos'] as $s) {
                $linhas[] = "  ✔ Serviço: {$s['descricao']}";
            }
        }

        return implode("\n", $linhas);
    }

    private function acaoRegistrarDiagnostico(array $dados, string $chatId): string
    {
        $this->exigir($dados, ['descricao']);
        $os = $this->resolverOsAlvo($dados, $chatId, Intencao::REGISTRAR_DIAGNOSTICO);
        if ($os === null) {
            return $this->pedirEsclarecimentoOs($chatId, Intencao::REGISTRAR_DIAGNOSTICO, $dados);
        }

        $diag = $this->diagnosticoService->registrarManual((int) $os['id'], (string) $dados['descricao']);
        return "✅ Diagnóstico registrado na OS #{$os['id']}: {$diag['descricao']}.";
    }

    private function acaoDefinirDiagnostico(array $dados, string $novoStatus, string $chatId): string
    {
        $this->exigir($dados, ['indice_diagnostico']);
        $os = $this->resolverOsAlvo($dados, $chatId, $novoStatus === 'confirmado' ? Intencao::CONFIRMAR_DIAGNOSTICO : Intencao::DESCARTAR_DIAGNOSTICO);
        if ($os === null) {
            return $this->pedirEsclarecimentoOs($chatId, Intencao::CONFIRMAR_DIAGNOSTICO, $dados);
        }

        $diag = $this->diagnosticoService->definirStatusPorIndice((int) $os['id'], (int) $dados['indice_diagnostico'], $novoStatus);
        if ($diag === null) {
            return '⚠️ Não encontrei um diagnóstico sugerido nessa posição. Peça a lista novamente.';
        }

        $emoji = $novoStatus === 'confirmado' ? '✅' : '❌';
        return "{$emoji} Diagnóstico \"{$diag['descricao']}\" marcado como {$novoStatus}.";
    }

    private function acaoRegistrarServico(array $dados, string $chatId): string
    {
        $this->exigir($dados, ['descricao']);
        $os = $this->resolverOsAlvo($dados, $chatId, Intencao::REGISTRAR_SERVICO);
        if ($os === null) {
            return $this->pedirEsclarecimentoOs($chatId, Intencao::REGISTRAR_SERVICO, $dados);
        }

        $this->osService->registrarServico((int) $os['id'], (string) $dados['descricao']);
        return "✅ Serviço registrado na OS #{$os['id']}: {$dados['descricao']}.";
    }

    private function acaoRegistrarPeca(array $dados, string $chatId): string
    {
        $this->exigir($dados, ['descricao']);
        $os = $this->resolverOsAlvo($dados, $chatId, Intencao::REGISTRAR_PECA);
        if ($os === null) {
            return $this->pedirEsclarecimentoOs($chatId, Intencao::REGISTRAR_PECA, $dados);
        }

        $compradaPor = in_array($dados['comprada_por'] ?? null, ['oficina', 'cliente'], true) ? $dados['comprada_por'] : 'oficina';
        $valor = isset($dados['valor']) ? (float) $dados['valor'] : null;

        $this->osService->registrarPeca((int) $os['id'], (string) $dados['descricao'], $compradaPor, $valor);
        return "✅ Peça registrada na OS #{$os['id']}: {$dados['descricao']} (comprada por: {$compradaPor}).";
    }

    private function acaoRegistrarGarantia(array $dados, string $chatId): string
    {
        $os = $this->resolverOsAlvo($dados, $chatId, Intencao::REGISTRAR_GARANTIA);
        if ($os === null) {
            return $this->pedirEsclarecimentoOs($chatId, Intencao::REGISTRAR_GARANTIA, $dados);
        }

        $prazo = isset($dados['prazo_dias']) ? (int) $dados['prazo_dias'] : 90;
        $garantia = $this->garantiaService->registrar((int) $os['id'], $prazo, $dados['observacoes'] ?? null);

        return "✅ Garantia de {$prazo} dias registrada na OS #{$os['id']}, válida até {$garantia['data_fim']}.";
    }

    private function acaoMarcarEntregue(array $dados, string $chatId): string
    {
        $os = $this->resolverOsAlvo($dados, $chatId, Intencao::MARCAR_ENTREGUE);
        if ($os === null) {
            return $this->pedirEsclarecimentoOs($chatId, Intencao::MARCAR_ENTREGUE, $dados);
        }

        $this->osService->marcarEntregue((int) $os['id']);
        return "✅ OS #{$os['id']} marcada como entregue.";
    }

    private function acaoAjuda(): string
    {
        return "🛠️ Posso te ajudar com:\n"
            . "• Cadastrar veículo (placa, modelo, cliente)\n"
            . "• Abrir OS com a reclamação do cliente\n"
            . "• Consultar histórico de um veículo pela placa\n"
            . "• Registrar/confirmar/descartar diagnóstico\n"
            . "• Registrar serviço realizado ou peça usada\n"
            . "• Registrar garantia\n"
            . "• Marcar veículo como entregue\n\n"
            . "Fale naturalmente, em texto ou áudio, e sempre inclua a placa quando puder.";
    }

    // ---------------------------------------------------------------
    // Auxiliares
    // ---------------------------------------------------------------

    /** Resolve a OS alvo a partir da placa informada (ou já conhecida pelo contexto). Retorna null se ambíguo/indefinido. */
    private function resolverOsAlvo(array $dados, string $chatId, string $intencao): ?array
    {
        if (empty($dados['placa'])) {
            return null;
        }

        $placa = self::extrairPlaca((string) $dados['placa']) ?? (string) $dados['placa'];
        $veiculo = $this->veiculoService->buscarPorPlaca($placa);
        if ($veiculo === null) {
            return null;
        }

        return $this->osService->osAlvoParaPlaca($placa);
    }

    private function pedirEsclarecimentoOs(string $chatId, string $intencao, array $dados): string
    {
        $this->contexto->salvar($chatId, $intencao, $dados);
        return '❓ Qual a placa do veículo? (preciso confirmar para não misturar com outro carro em aberto)';
    }

    /** OS aberta única em toda a oficina — só usada como fallback quando nenhuma placa foi citada em um anexo. */
    private function unicaOsAbertaGlobalmente(): ?array
    {
        $abertas = $this->osService->listarAbertas();
        return count($abertas) === 1 ? $abertas[0] : null;
    }

    private function exigir(array $dados, array $campos): void
    {
        foreach ($campos as $campo) {
            if (empty($dados[$campo])) {
                throw new \DomainException("Faltou informar \"{$campo}\" — pode me passar esse dado?");
            }
        }
    }

    /** Extrai uma placa (padrão antigo ABC1234 ou Mercosul ABC1D23) de um texto livre. */
    public static function extrairPlaca(string $texto): ?string
    {
        $normalizado = strtoupper($texto);
        if (preg_match('/\b([A-Z]{3}[0-9][A-Z0-9][0-9]{2})\b/', str_replace([' ', '-'], '', $normalizado), $m)) {
            return $m[1];
        }
        return null;
    }
}
