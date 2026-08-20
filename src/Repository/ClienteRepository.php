<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ClienteRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function criar(string $nome, ?string $telefone, ?string $cpfCnpj = null, ?string $email = null, ?string $observacoes = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO clientes (nome, telefone, cpf_cnpj, email, observacoes)
             VALUES (:nome, :telefone, :cpf_cnpj, :email, :observacoes)'
        );
        $stmt->execute([
            'nome'        => $nome,
            'telefone'    => $telefone,
            'cpf_cnpj'    => $cpfCnpj,
            'email'       => $email,
            'observacoes' => $observacoes,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function atualizar(int $id, string $nome, ?string $telefone, ?string $cpfCnpj, ?string $email, ?string $observacoes): void
    {
        $stmt = $this->db->prepare(
            'UPDATE clientes SET nome = :nome, telefone = :telefone, cpf_cnpj = :cpf_cnpj,
                email = :email, observacoes = :observacoes WHERE id = :id'
        );
        $stmt->execute([
            'nome' => $nome, 'telefone' => $telefone, 'cpf_cnpj' => $cpfCnpj,
            'email' => $email, 'observacoes' => $observacoes, 'id' => $id,
        ]);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM clientes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function buscarPorTelefone(string $telefone): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM clientes WHERE telefone = :telefone LIMIT 1');
        $stmt->execute(['telefone' => $telefone]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Encontra por nome (aproximado) ou cria um novo cliente. */
    public function localizarOuCriar(string $nome, ?string $telefone = null): int
    {
        if ($telefone !== null) {
            $existente = $this->buscarPorTelefone($telefone);
            if ($existente !== null) {
                return (int) $existente['id'];
            }
        }

        $stmt = $this->db->prepare('SELECT id FROM clientes WHERE nome = :nome LIMIT 1');
        $stmt->execute(['nome' => $nome]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return (int) $row['id'];
        }

        return $this->criar($nome, $telefone);
    }

    public function buscar(string $termo, int $limite = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM clientes WHERE nome LIKE :termo OR telefone LIKE :termo OR cpf_cnpj LIKE :termo ORDER BY nome LIMIT :limite'
        );
        $stmt->bindValue('termo', "%{$termo}%", PDO::PARAM_STR);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listarTodos(): array
    {
        return $this->db->query('SELECT * FROM clientes ORDER BY nome')->fetchAll();
    }
}
