<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class AgendamentoRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function criar(
        int $clienteId,
        ?int $veiculoId,
        \DateTimeImmutable $dataHora,
        ?string $servicoMotivo,
        ?string $observacoes,
        ?int $previsaoDuracaoMinutos,
        ?int $criadoPor
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO agendamentos (cliente_id, veiculo_id, data_hora, servico_motivo, observacoes, previsao_duracao_minutos, criado_por)
             VALUES (:cliente_id, :veiculo_id, :data_hora, :motivo, :observacoes, :duracao, :criado_por)'
        );
        $stmt->execute([
            'cliente_id' => $clienteId,
            'veiculo_id' => $veiculoId,
            'data_hora'  => $dataHora->format('Y-m-d H:i:s'),
            'motivo'     => $servicoMotivo,
            'observacoes' => $observacoes,
            'duracao'    => $previsaoDuracaoMinutos,
            'criado_por' => $criadoPor,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM agendamentos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function atualizarStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE agendamentos SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function vincularOrdemServico(int $id, int $ordemServicoId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE agendamentos SET ordem_servico_id = :os_id, status = 'chegou' WHERE id = :id"
        );
        $stmt->execute(['os_id' => $ordemServicoId, 'id' => $id]);
    }

    /** Lista os agendamentos de um intervalo (dia/semana/mês), do mais cedo para o mais tarde. */
    public function listarPorPeriodo(\DateTimeImmutable $inicio, \DateTimeImmutable $fim): array
    {
        $sql = "SELECT a.*, c.nome AS cliente_nome, v.placa, v.modelo
                FROM agendamentos a
                JOIN clientes c ON c.id = a.cliente_id
                LEFT JOIN veiculos v ON v.id = a.veiculo_id
                WHERE a.data_hora BETWEEN :inicio AND :fim
                ORDER BY a.data_hora ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'inicio' => $inicio->format('Y-m-d H:i:s'),
            'fim'    => $fim->format('Y-m-d H:i:s'),
        ]);
        return $stmt->fetchAll();
    }

    /** Próximos agendamentos ainda não atendidos (agendado/confirmado), para o dashboard. */
    public function proximos(int $limite = 10): array
    {
        $sql = "SELECT a.*, c.nome AS cliente_nome, v.placa, v.modelo
                FROM agendamentos a
                JOIN clientes c ON c.id = a.cliente_id
                LEFT JOIN veiculos v ON v.id = a.veiculo_id
                WHERE a.status IN ('agendado','confirmado') AND a.data_hora >= NOW()
                ORDER BY a.data_hora ASC
                LIMIT :limite";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
