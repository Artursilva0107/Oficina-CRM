<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class GarantiaRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Cria a garantia. data_fim é sempre calculada em aplicação a partir de
     * data_inicio + prazo_dias, nunca informada manualmente pelo usuário.
     */
    public function criar(int $ordemServicoId, int $prazoDias, \DateTimeImmutable $dataInicio, ?string $observacoes): int
    {
        $dataFim = $dataInicio->modify("+{$prazoDias} days");

        $stmt = $this->db->prepare(
            'INSERT INTO garantias (ordem_servico_id, prazo_dias, data_inicio, data_fim, observacoes)
             VALUES (:os_id, :prazo, :inicio, :fim, :obs)'
        );
        $stmt->execute([
            'os_id'  => $ordemServicoId,
            'prazo'  => $prazoDias,
            'inicio' => $dataInicio->format('Y-m-d'),
            'fim'    => $dataFim->format('Y-m-d'),
            'obs'    => $observacoes,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listarPorOs(int $ordemServicoId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM garantias WHERE ordem_servico_id = :os_id ORDER BY id DESC');
        $stmt->execute(['os_id' => $ordemServicoId]);
        return $stmt->fetchAll();
    }

    /** Garantias vigentes ou vencidas nos próximos N dias, para alerta no painel. */
    public function proximasDoVencimento(int $diasJanela = 15): array
    {
        $sql = "SELECT g.*, os.veiculo_id, v.placa, v.modelo, c.nome AS cliente_nome
                FROM garantias g
                JOIN ordens_servico os ON os.id = g.ordem_servico_id
                JOIN veiculos v ON v.id = os.veiculo_id
                JOIN clientes c ON c.id = v.cliente_id
                WHERE g.data_fim <= DATE_ADD(CURDATE(), INTERVAL :dias DAY)
                ORDER BY g.data_fim ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('dias', $diasJanela, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Verifica rapidamente se um veículo ainda está dentro do prazo de garantia. */
    public function veiculoEmGarantia(int $veiculoId): array
    {
        $sql = "SELECT g.* FROM garantias g
                JOIN ordens_servico os ON os.id = g.ordem_servico_id
                WHERE os.veiculo_id = :veiculo_id AND g.data_fim >= CURDATE()
                ORDER BY g.data_fim DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['veiculo_id' => $veiculoId]);
        return $stmt->fetchAll();
    }
}
