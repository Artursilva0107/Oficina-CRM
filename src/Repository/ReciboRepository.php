<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ReciboRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function salvar(int $ordemServicoId, string $caminhoPdf): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO recibos (ordem_servico_id, pdf_caminho) VALUES (:os_id, :caminho)
             ON DUPLICATE KEY UPDATE pdf_caminho = VALUES(pdf_caminho), criado_em = NOW()'
        );
        $stmt->execute(['os_id' => $ordemServicoId, 'caminho' => $caminhoPdf]);

        $stmt2 = $this->db->prepare('SELECT id FROM recibos WHERE ordem_servico_id = :os_id');
        $stmt2->execute(['os_id' => $ordemServicoId]);
        return (int) $stmt2->fetchColumn();
    }

    public function buscarPorOs(int $ordemServicoId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM recibos WHERE ordem_servico_id = :os_id');
        $stmt->execute(['os_id' => $ordemServicoId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
