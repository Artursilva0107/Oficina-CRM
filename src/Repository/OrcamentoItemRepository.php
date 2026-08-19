<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class OrcamentoItemRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function criar(int $orcamentoId, string $tipo, string $descricao, float $quantidade, float $valorUnitario): int
    {
        $valorTotal = round($quantidade * $valorUnitario, 2);

        $stmt = $this->db->prepare(
            'INSERT INTO orcamento_itens (orcamento_id, tipo, descricao, quantidade, valor_unitario, valor_total)
             VALUES (:orcamento_id, :tipo, :descricao, :quantidade, :valor_unitario, :valor_total)'
        );
        $stmt->execute([
            'orcamento_id'   => $orcamentoId,
            'tipo'           => $tipo,
            'descricao'      => $descricao,
            'quantidade'     => $quantidade,
            'valor_unitario' => $valorUnitario,
            'valor_total'    => $valorTotal,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listarPorOrcamento(int $orcamentoId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM orcamento_itens WHERE orcamento_id = :id ORDER BY id ASC');
        $stmt->execute(['id' => $orcamentoId]);
        return $stmt->fetchAll();
    }

    public function definirAprovado(int $itemId, bool $aprovado): void
    {
        $stmt = $this->db->prepare('UPDATE orcamento_itens SET aprovado = :aprovado WHERE id = :id');
        $stmt->execute(['aprovado' => $aprovado ? 1 : 0, 'id' => $itemId]);
    }

    public function remover(int $itemId): void
    {
        $stmt = $this->db->prepare('DELETE FROM orcamento_itens WHERE id = :id');
        $stmt->execute(['id' => $itemId]);
    }
}
