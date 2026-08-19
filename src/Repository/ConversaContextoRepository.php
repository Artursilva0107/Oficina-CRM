<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Guarda o estado pendente de uma conversa (ex.: aguardando a placa que
 * o mecânico ainda não informou), para que o próximo texto/áudio dele
 * seja interpretado no contexto certo em vez de como uma ação nova.
 */
final class ConversaContextoRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function obter(string $chatId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM conversa_contexto WHERE chat_id = :chat_id');
        $stmt->execute(['chat_id' => $chatId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $row['dados_pendentes'] = $row['dados_pendentes'] !== null
            ? (json_decode($row['dados_pendentes'], true) ?? [])
            : [];

        return $row;
    }

    public function salvar(string $chatId, ?string $intencaoPendente, array $dadosPendentes): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO conversa_contexto (chat_id, intencao_pendente, dados_pendentes)
             VALUES (:chat_id, :intencao, :dados)
             ON DUPLICATE KEY UPDATE intencao_pendente = VALUES(intencao_pendente),
                                     dados_pendentes = VALUES(dados_pendentes)'
        );
        $stmt->execute([
            'chat_id'  => $chatId,
            'intencao' => $intencaoPendente,
            'dados'    => json_encode($dadosPendentes, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function limpar(string $chatId): void
    {
        $stmt = $this->db->prepare('DELETE FROM conversa_contexto WHERE chat_id = :chat_id');
        $stmt->execute(['chat_id' => $chatId]);
    }
}
