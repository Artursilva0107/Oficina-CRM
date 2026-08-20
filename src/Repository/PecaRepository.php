<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class PecaRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function criar(int $ordemServicoId, string $descricao, string $compradaPor, ?float $valor, float $quantidade = 1.0): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pecas (ordem_servico_id, descricao, quantidade, comprada_por, valor)
             VALUES (:os_id, :descricao, :quantidade, :comprada_por, :valor)'
        );
        $stmt->execute([
            'os_id'        => $ordemServicoId,
            'descricao'    => $descricao,
            'quantidade'   => $quantidade,
            'comprada_por' => $compradaPor,
            'valor'        => $valor,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listarPorOs(int $ordemServicoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM pecas WHERE ordem_servico_id = :os_id ORDER BY id ASC'
        );
        $stmt->execute(['os_id' => $ordemServicoId]);
        return $stmt->fetchAll();
    }
}
