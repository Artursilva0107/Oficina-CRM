<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ServicoRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function criar(
        int $ordemServicoId,
        string $descricao,
        ?int $mecanicoResponsavelId = null,
        ?\DateTimeImmutable $dataInicio = null,
        ?\DateTimeImmutable $dataConclusao = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO servicos_realizados (ordem_servico_id, descricao, mecanico_responsavel_id, data_inicio, data_conclusao, data)
             VALUES (:os_id, :descricao, :mecanico, :inicio, :conclusao, NOW())'
        );
        $stmt->execute([
            'os_id'     => $ordemServicoId,
            'descricao' => $descricao,
            'mecanico'  => $mecanicoResponsavelId,
            'inicio'    => $dataInicio?->format('Y-m-d H:i:s'),
            'conclusao' => $dataConclusao?->format('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function concluir(int $servicoId): void
    {
        $stmt = $this->db->prepare('UPDATE servicos_realizados SET data_conclusao = NOW() WHERE id = :id');
        $stmt->execute(['id' => $servicoId]);
    }

    public function listarPorOs(int $ordemServicoId): array
    {
        $sql = 'SELECT sr.*, u.nome AS mecanico_nome
                FROM servicos_realizados sr LEFT JOIN usuarios u ON u.id = sr.mecanico_responsavel_id
                WHERE sr.ordem_servico_id = :os_id ORDER BY sr.data ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['os_id' => $ordemServicoId]);
        return $stmt->fetchAll();
    }
}
