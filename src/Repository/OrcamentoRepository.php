<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class OrcamentoRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function criar(int $ordemServicoId, int $validadeDias, ?int $criadoPor, ?string $observacoes = null): int
    {
        $dataValidade = (new \DateTimeImmutable('now'))->modify("+{$validadeDias} days")->format('Y-m-d');

        $stmt = $this->db->prepare(
            'INSERT INTO orcamentos (ordem_servico_id, validade_dias, data_validade, criado_por, observacoes)
             VALUES (:os_id, :validade, :data_validade, :criado_por, :observacoes)'
        );
        $stmt->execute([
            'os_id'         => $ordemServicoId,
            'validade'      => $validadeDias,
            'data_validade' => $dataValidade,
            'criado_por'    => $criadoPor,
            'observacoes'   => $observacoes,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orcamentos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listarPorOs(int $ordemServicoId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM orcamentos WHERE ordem_servico_id = :os_id ORDER BY id DESC');
        $stmt->execute(['os_id' => $ordemServicoId]);
        return $stmt->fetchAll();
    }

    public function atualizarDesconto(int $id, float $desconto): void
    {
        $stmt = $this->db->prepare('UPDATE orcamentos SET desconto = :desconto WHERE id = :id');
        $stmt->execute(['desconto' => $desconto, 'id' => $id]);
    }

    public function recalcularTotal(int $id): void
    {
        $sql = 'UPDATE orcamentos o
                SET total = GREATEST(0, (
                    SELECT COALESCE(SUM(valor_total), 0) FROM orcamento_itens WHERE orcamento_id = o.id
                ) - o.desconto)
                WHERE o.id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    public function atualizarStatus(int $id, string $status, bool $marcarAprovadoAgora = false): void
    {
        $sql = $marcarAprovadoAgora
            ? 'UPDATE orcamentos SET status = :status, aprovado_em = NOW() WHERE id = :id'
            : 'UPDATE orcamentos SET status = :status WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function salvarCaminhoPdf(int $id, string $caminho): void
    {
        $stmt = $this->db->prepare('UPDATE orcamentos SET pdf_caminho = :caminho WHERE id = :id');
        $stmt->execute(['caminho' => $caminho, 'id' => $id]);
    }
}
