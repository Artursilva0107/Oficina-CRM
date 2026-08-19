<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ClienteRepository;
use App\Repository\VeiculoRepository;
use App\Service\OrcamentoService;
use App\Service\OrdemServicoService;
use App\Service\PdfService;

final class OrcamentoController
{
    public function __construct(
        private OrcamentoService $orcamentos,
        private OrdemServicoService $ordens,
        private VeiculoRepository $veiculos,
        private ClienteRepository $clientes,
        private PdfService $pdf
    ) {
    }

    public function criar(int $ordemServicoId, int $validadeDias, ?int $usuarioId, ?string $observacoes): array
    {
        return $this->orcamentos->criar($ordemServicoId, $validadeDias, $usuarioId, $observacoes);
    }

    public function adicionarItem(int $orcamentoId, string $tipo, string $descricao, float $quantidade, float $valorUnitario): void
    {
        $this->orcamentos->adicionarItem($orcamentoId, $tipo, $descricao, $quantidade, $valorUnitario);
    }

    public function removerItem(int $orcamentoId, int $itemId): void
    {
        $this->orcamentos->removerItem($orcamentoId, $itemId);
    }

    public function definirDesconto(int $orcamentoId, float $desconto): void
    {
        $this->orcamentos->definirDesconto($orcamentoId, $desconto);
    }

    public function aprovarTudo(int $orcamentoId): void
    {
        $this->orcamentos->aprovarTudo($orcamentoId);
    }

    public function aprovarParcial(int $orcamentoId, array $idsAprovados): void
    {
        $this->orcamentos->aprovarParcial($orcamentoId, $idsAprovados);
    }

    public function recusar(int $orcamentoId): void
    {
        $this->orcamentos->recusar($orcamentoId);
    }

    public function ficha(int $orcamentoId): ?array
    {
        return $this->orcamentos->buscarPorId($orcamentoId);
    }

    /** Gera o PDF do orçamento e devolve o caminho do arquivo salvo. */
    public function gerarPdf(int $orcamentoId): string
    {
        $orcamento = $this->orcamentos->buscarPorId($orcamentoId);
        if ($orcamento === null) {
            throw new \DomainException('Orçamento não encontrado.');
        }

        $os = $this->ordens->buscarPorId((int) $orcamento['ordem_servico_id']);
        $veiculo = $this->veiculos->buscarPorId((int) $os['veiculo_id']);
        $cliente = $this->clientes->buscarPorId((int) $veiculo['cliente_id']);

        $caminho = $this->pdf->gerarOrcamentoPdf($orcamento, $os, $veiculo, $cliente);
        $this->orcamentos->salvarCaminhoPdf($orcamentoId, $caminho);

        return $caminho;
    }
}
