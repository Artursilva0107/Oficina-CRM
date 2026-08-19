<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class DiagnosticoRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function criar(int $ordemServicoId, string $descricao, string $origem, string $status = 'sugerido'): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO diagnosticos (ordem_servico_id, descricao, origem, status)
             VALUES (:os_id, :descricao, :origem, :status)'
        );
        $stmt->execute([
            'os_id'     => $ordemServicoId,
            'descricao' => $descricao,
            'origem'    => $origem,
            'status'    => $status,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listarPorOs(int $ordemServicoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM diagnosticos WHERE ordem_servico_id = :os_id ORDER BY id ASC'
        );
        $stmt->execute(['os_id' => $ordemServicoId]);
        return $stmt->fetchAll();
    }

    /** Lista apenas os diagnósticos ainda em aberto (status = sugerido), em ordem — usado para "confirma o segundo". */
    public function listarSugeridosPorOs(int $ordemServicoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM diagnosticos WHERE ordem_servico_id = :os_id AND status = 'sugerido' ORDER BY id ASC"
        );
        $stmt->execute(['os_id' => $ordemServicoId]);
        return $stmt->fetchAll();
    }

    public function atualizarStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE diagnosticos SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM diagnosticos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
