<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class InteracaoChatRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function registrar(
        ?int $usuarioId,
        string $tipo,
        ?string $mensagemOriginal,
        ?string $transcricaoOuInterpretacao,
        ?string $acaoExecutada,
        ?array $payload,
        bool $sucesso
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO interacoes_chat
                (usuario_id, mensagem_original, tipo, transcricao_ou_interpretacao, acao_executada, payload_json, sucesso)
             VALUES (:usuario_id, :msg, :tipo, :interp, :acao, :payload, :sucesso)'
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'msg'        => $mensagemOriginal,
            'tipo'       => $tipo,
            'interp'     => $transcricaoOuInterpretacao,
            'acao'       => $acaoExecutada,
            'payload'    => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'sucesso'    => $sucesso ? 1 : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listarRecentes(int $limite = 100): array
    {
        $sql = 'SELECT ic.*, u.nome AS usuario_nome
                FROM interacoes_chat ic
                LEFT JOIN usuarios u ON u.id = ic.usuario_id
                ORDER BY ic.criado_em DESC
                LIMIT :limite';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
