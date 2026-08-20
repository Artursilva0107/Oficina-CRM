<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AgendamentoRepository;
use App\Repository\ClienteRepository;

final class AgendaService
{
    public function __construct(
        private AgendamentoRepository $agendamentos,
        private ClienteRepository $clientes
    ) {
    }

    public function agendar(array $dados, ?int $criadoPor): array
    {
        $clienteId = $this->clientes->localizarOuCriar(
            (string) $dados['cliente_nome'],
            $dados['cliente_telefone'] ?? null
        );

        $id = $this->agendamentos->criar(
            $clienteId,
            isset($dados['veiculo_id']) ? (int) $dados['veiculo_id'] : null,
            new \DateTimeImmutable($dados['data_hora']),
            $dados['servico_motivo'] ?? null,
            $dados['observacoes'] ?? null,
            isset($dados['previsao_duracao_minutos']) ? (int) $dados['previsao_duracao_minutos'] : null,
            $criadoPor
        );

        return $this->agendamentos->buscarPorId($id);
    }

    public function confirmar(int $id): void
    {
        $this->agendamentos->atualizarStatus($id, 'confirmado');
    }

    public function cancelar(int $id): void
    {
        $this->agendamentos->atualizarStatus($id, 'cancelado');
    }

    public function marcarNaoCompareceu(int $id): void
    {
        $this->agendamentos->atualizarStatus($id, 'nao_compareceu');
    }

    public function vincularOrdemServico(int $agendamentoId, int $ordemServicoId): void
    {
        $this->agendamentos->vincularOrdemServico($agendamentoId, $ordemServicoId);
    }

    /** @param string $visao 'dia' | 'semana' | 'mes' */
    public function listarPorVisao(string $visao, \DateTimeImmutable $referencia): array
    {
        [$inicio, $fim] = match ($visao) {
            'semana' => [
                $referencia->modify('monday this week')->setTime(0, 0),
                $referencia->modify('sunday this week')->setTime(23, 59, 59),
            ],
            'mes' => [
                $referencia->modify('first day of this month')->setTime(0, 0),
                $referencia->modify('last day of this month')->setTime(23, 59, 59),
            ],
            default => [
                $referencia->setTime(0, 0),
                $referencia->setTime(23, 59, 59),
            ],
        };

        return $this->agendamentos->listarPorPeriodo($inicio, $fim);
    }

    public function proximos(int $limite = 10): array
    {
        return $this->agendamentos->proximos($limite);
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->agendamentos->buscarPorId($id);
    }
}
