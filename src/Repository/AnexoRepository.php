<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class AnexoRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function criar(int $ordemServicoId, string $tipo, string $caminhoArquivo, ?string $transcricao = null, string $categoria = 'outro'): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO anexos (ordem_servico_id, tipo, categoria, caminho_arquivo, transcricao)
             VALUES (:os_id, :tipo, :categoria, :caminho, :transcricao)'
        );
        $stmt->execute([
            'os_id'       => $ordemServicoId,
            'tipo'        => $tipo,
            'categoria'   => $categoria,
            'caminho'     => $caminhoArquivo,
            'transcricao' => $transcricao,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listarPorOs(int $ordemServicoId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM anexos WHERE ordem_servico_id = :os_id ORDER BY criado_em ASC');
        $stmt->execute(['os_id' => $ordemServicoId]);
        return $stmt->fetchAll();
    }
}
