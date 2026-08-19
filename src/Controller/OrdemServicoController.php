<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ClienteRepository;
use App\Repository\VeiculoRepository;
use App\Service\DiagnosticoService;
use App\Service\GarantiaService;
use App\Service\OrdemServicoService;
use App\Service\PdfService;
use App\Repository\ReciboRepository;
use App\Repository\ServicoRepository;

final class OrdemServicoController
{
    public function __construct(
        private OrdemServicoService $ordens,
        private DiagnosticoService $diagnosticos,
        private GarantiaService $garantias,
        private VeiculoRepository $veiculos,
        private ClienteRepository $clientes,
        private ServicoRepository $servicos,
        private PdfService $pdf,
        private ReciboRepository $recibos
    ) {
    }

    public function ficha(int $id): ?array
    {
        $os = $this->ordens->buscarPorId($id);
        if ($os === null) {
            return null;
        }

        return [
            'os'            => $os,
            'diagnosticos'  => $this->diagnosticos->listarPorOs($id),
            'garantias'     => $this->garantias->listarPorOs($id),
        ];
    }

    public function marcarEntregue(int $id, array $dadosSaida = []): void
    {
        $this->ordens->marcarEntregue($id, $dadosSaida);
    }

    /** Gera (ou regenera) o recibo em PDF da OS e salva o registro no histórico. */
    public function gerarRecibo(int $id): string
    {
        $os = $this->ordens->buscarPorId($id);
        if ($os === null) {
            throw new \DomainException('OS não encontrada.');
        }

        $veiculo = $this->veiculos->buscarPorId((int) $os['veiculo_id']);
        $cliente = $this->clientes->buscarPorId((int) $veiculo['cliente_id']);
        $servicos = $this->servicos->listarPorOs($id);

        $caminho = $this->pdf->gerarReciboPdf($os, $veiculo, $cliente, $servicos);
        $this->recibos->salvar($id, $caminho);

        return $caminho;
    }
}
