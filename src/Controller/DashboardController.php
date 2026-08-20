<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\GarantiaService;
use App\Service\OrdemServicoService;

final class DashboardController
{
    public function __construct(
        private OrdemServicoService $ordens,
        private GarantiaService $garantias
    ) {
    }

    public function dados(): array
    {
        $porStatus = [];
        foreach ($this->ordens->contarPorStatus() as $linha) {
            $porStatus[$linha['status']] = (int) $linha['total'];
        }

        return [
            'os_abertas'           => $this->ordens->listarAbertas(),
            'contagem_por_status'  => $porStatus,
            'tempo_medio_horas'    => $this->ordens->tempoMedioAtendimentoHoras(),
            'garantias_vencendo'   => $this->garantias->proximasDoVencimento(15),
        ];
    }
}
