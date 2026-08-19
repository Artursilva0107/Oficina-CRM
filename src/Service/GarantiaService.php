<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\GarantiaRepository;
use App\Repository\OrdemServicoRepository;

final class GarantiaService
{
    public function __construct(
        private GarantiaRepository $garantias,
        private OrdemServicoRepository $ordens
    ) {
    }

    /**
     * Registra a garantia de uma OS. data_inicio é a data de saída da OS, se já
     * entregue, ou a data atual caso contrário — data_fim é sempre calculada.
     */
    public function registrar(int $ordemServicoId, int $prazoDias, ?string $observacoes): array
    {
        $os = $this->ordens->buscarPorId($ordemServicoId);
        if ($os === null) {
            throw new \DomainException('Ordem de serviço não encontrada.');
        }

        $dataInicio = $os['data_saida'] !== null
            ? new \DateTimeImmutable($os['data_saida'])
            : new \DateTimeImmutable('now');

        $id = $this->garantias->criar($ordemServicoId, $prazoDias, $dataInicio, $observacoes);
        return $this->garantias->listarPorOs($ordemServicoId)[0] ?? ['id' => $id];
    }

    public function proximasDoVencimento(int $diasJanela = 15): array
    {
        return $this->garantias->proximasDoVencimento($diasJanela);
    }

    public function veiculoEmGarantia(int $veiculoId): array
    {
        return $this->garantias->veiculoEmGarantia($veiculoId);
    }

    public function listarPorOs(int $ordemServicoId): array
    {
        return $this->garantias->listarPorOs($ordemServicoId);
    }
}
