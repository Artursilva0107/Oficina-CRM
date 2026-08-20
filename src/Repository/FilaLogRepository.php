<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class FilaLogRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function registrar(int $ordemServicoId, string $acao, ?string $motivo, ?int $usuarioId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO fila_log (ordem_servico_id, acao, motivo, usuario_id)
             VALUES (:os_id, :acao, :motivo, :usuario_id)'
        );
        $stmt->execute([
            'os_id'      => $ordemServicoId,
            'acao'       => $acao,
            'motivo'     => $motivo,
            'usuario_id' => $usuarioId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listarPorOs(int $ordemServicoId): array
    {
        $sql = 'SELECT fl.*, u.nome AS usuario_nome
                FROM fila_log fl LEFT JOIN usuarios u ON u.id = fl.usuario_id
                WHERE fl.ordem_servico_id = :os_id ORDER BY fl.criado_em ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['os_id' => $ordemServicoId]);
        return $stmt->fetchAll();
    }
}
