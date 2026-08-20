<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UsuarioRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function buscarPorTelegramId(string $telegramId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE telegram_id = :tid AND ativo = 1');
        $stmt->execute(['tid' => $telegramId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE email = :email AND ativo = 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function criar(string $nome, string $papel, ?string $email, ?string $senhaHash, ?string $telegramId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (nome, papel, email, senha_hash, telegram_id)
             VALUES (:nome, :papel, :email, :senha, :telegram_id)'
        );
        $stmt->execute([
            'nome'        => $nome,
            'papel'       => $papel,
            'email'       => $email,
            'senha'       => $senhaHash,
            'telegram_id' => $telegramId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listarTodos(): array
    {
        return $this->db->query('SELECT id, nome, telefone, telegram_id, papel, email, ativo, criado_em FROM usuarios ORDER BY nome')->fetchAll();
    }

    public function vincularTelegramId(int $usuarioId, string $telegramId): void
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET telegram_id = :tid WHERE id = :id');
        $stmt->execute(['tid' => $telegramId, 'id' => $usuarioId]);
    }
}
