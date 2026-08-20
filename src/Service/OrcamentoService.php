<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\OrcamentoItemRepository;
use App\Repository\OrcamentoRepository;

/**
 * Suporta tanto o preenchimento manual (painel) quanto o "orçamento rápido"
 * digitado em texto livre (ex.: "2 bieletas 180, alinhamento 100, mão de obra 150"),
 * que chega já quebrado em itens por App\Nlp\NlpInterface / quem chamar este serviço.
 */
final class OrcamentoService
{
    public function __construct(
        private OrcamentoRepository $orcamentos,
        private OrcamentoItemRepository $itens
    ) {
    }

    public function criar(int $ordemServicoId, int $validadeDias, ?int $criadoPor, ?string $observacoes = null): array
    {
        $id = $this->orcamentos->criar($ordemServicoId, $validadeDias, $criadoPor, $observacoes);
        return $this->orcamentos->buscarPorId($id);
    }

    /**
     * Adiciona um item e recalcula o total automaticamente.
     * @param string $tipo 'peca' | 'servico' | 'mao_de_obra'
     */
    public function adicionarItem(int $orcamentoId, string $tipo, string $descricao, float $quantidade, float $valorUnitario): void
    {
        $this->itens->criar($orcamentoId, $tipo, $descricao, $quantidade, $valorUnitario);
        $this->orcamentos->recalcularTotal($orcamentoId);
    }

    public function removerItem(int $orcamentoId, int $itemId): void
    {
        $this->itens->remover($itemId);
        $this->orcamentos->recalcularTotal($orcamentoId);
    }

    public function definirDesconto(int $orcamentoId, float $desconto): void
    {
        $this->orcamentos->atualizarDesconto($orcamentoId, $desconto);
        $this->orcamentos->recalcularTotal($orcamentoId);
    }

    public function aprovarTudo(int $orcamentoId): void
    {
        foreach ($this->itens->listarPorOrcamento($orcamentoId) as $item) {
            $this->itens->definirAprovado((int) $item['id'], true);
        }
        $this->orcamentos->atualizarStatus($orcamentoId, 'aprovado', true);
    }

    public function recusar(int $orcamentoId): void
    {
        $this->orcamentos->atualizarStatus($orcamentoId, 'recusado');
    }

    /** Aprovação parcial: recebe os IDs dos itens aprovados; os demais ficam marcados como não aprovados. */
    public function aprovarParcial(int $orcamentoId, array $idsItensAprovados): void
    {
        foreach ($this->itens->listarPorOrcamento($orcamentoId) as $item) {
            $this->itens->definirAprovado((int) $item['id'], in_array((int) $item['id'], $idsItensAprovados, true));
        }
        $this->orcamentos->atualizarStatus($orcamentoId, 'parcialmente_aprovado', true);
    }

    public function buscarPorId(int $id): ?array
    {
        $orcamento = $this->orcamentos->buscarPorId($id);
        if ($orcamento === null) {
            return null;
        }
        $orcamento['itens'] = $this->itens->listarPorOrcamento($id);
        return $orcamento;
    }

    public function listarPorOs(int $ordemServicoId): array
    {
        return $this->orcamentos->listarPorOs($ordemServicoId);
    }

    public function salvarCaminhoPdf(int $orcamentoId, string $caminho): void
    {
        $this->orcamentos->salvarCaminhoPdf($orcamentoId, $caminho);
    }
}
