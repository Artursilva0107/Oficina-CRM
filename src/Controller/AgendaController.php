<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AgendaService;
use App\Service\OrdemServicoService;
use App\Service\VeiculoService;

final class AgendaController
{
    public function __construct(
        private AgendaService $agenda,
        private OrdemServicoService $ordens,
        private VeiculoService $veiculos
    ) {
    }

    public function visao(string $visao, \DateTimeImmutable $referencia): array
    {
        return $this->agenda->listarPorVisao($visao, $referencia);
    }

    public function agendar(array $dados, ?int $usuarioId): array
    {
        return $this->agenda->agendar($dados, $usuarioId);
    }

    public function confirmar(int $id): void
    {
        $this->agenda->confirmar($id);
    }

    public function cancelar(int $id): void
    {
        $this->agenda->cancelar($id);
    }

    public function marcarNaoCompareceu(int $id): void
    {
        $this->agenda->marcarNaoCompareceu($id);
    }

    /** Converte um agendamento em entrada do veículo (OS), sem recadastrar nada. */
    public function transformarEmOs(int $agendamentoId, array $dadosEntrada, ?int $usuarioId): array
    {
        $agendamento = $this->agenda->buscarPorId($agendamentoId);
        if ($agendamento === null) {
            throw new \DomainException('Agendamento não encontrado.');
        }
        if ($agendamento['veiculo_id'] === null) {
            throw new \DomainException('Este agendamento ainda não tem um veículo vinculado — cadastre o veículo antes de dar entrada.');
        }

        $os = $this->ordens->abrir((int) $agendamento['veiculo_id'], array_merge($dadosEntrada, [
            'agendamento_id' => $agendamentoId,
        ]), $usuarioId);

        $this->agenda->vincularOrdemServico($agendamentoId, (int) $os['id']);

        return $os;
    }
}
