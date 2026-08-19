<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OrdemServicoService;

final class FilaController
{
    public function __construct(private OrdemServicoService $ordens)
    {
    }

    public function fila(): array
    {
        return $this->ordens->filaDaOficina();
    }

    public function definirPrioridade(int $ordemServicoId, ?int $prioridade, string $motivo, int $usuarioId): array
    {
        return $this->ordens->definirPrioridadeManual($ordemServicoId, $prioridade, $motivo, $usuarioId);
    }

    public function pausar(int $ordemServicoId, string $status, ?string $motivo, int $usuarioId): array
    {
        return $this->ordens->pausar($ordemServicoId, $status, $motivo, $usuarioId);
    }

    public function retomar(int $ordemServicoId, string $novoStatus, int $usuarioId): array
    {
        return $this->ordens->retomar($ordemServicoId, $novoStatus, $usuarioId);
    }

    public function atualizarStatus(int $ordemServicoId, string $status): array
    {
        return $this->ordens->atualizarStatus($ordemServicoId, $status);
    }

    public function historicoDaFila(int $ordemServicoId): array
    {
        return $this->ordens->historicoDaFila($ordemServicoId);
    }
}
