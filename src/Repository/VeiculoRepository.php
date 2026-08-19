<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class VeiculoRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** Normaliza a placa para o formato interno: maiúsculas, sem espaços/traços. */
    public static function normalizarPlaca(string $placa): string
    {
        $placa = strtoupper(trim($placa));
        return preg_replace('/[^A-Z0-9]/', '', $placa) ?? $placa;
    }

    public function criar(
        int $clienteId,
        string $placa,
        string $modelo,
        ?string $cor,
        ?int $ano,
        ?string $marca = null,
        ?string $versao = null,
        ?int $quilometragem = null,
        ?string $combustivel = null,
        ?string $observacoes = null
    ): int {
        $placa = self::normalizarPlaca($placa);

        $stmt = $this->db->prepare(
            'INSERT INTO veiculos (cliente_id, placa, marca, modelo, versao, cor, ano, quilometragem, combustivel, observacoes)
             VALUES (:cliente_id, :placa, :marca, :modelo, :versao, :cor, :ano, :km, :combustivel, :observacoes)'
        );
        $stmt->execute([
            'cliente_id'    => $clienteId,
            'placa'         => $placa,
            'marca'         => $marca,
            'modelo'        => $modelo,
            'versao'        => $versao,
            'cor'           => $cor,
            'ano'           => $ano,
            'km'            => $quilometragem,
            'combustivel'   => $combustivel,
            'observacoes'   => $observacoes,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function atualizarQuilometragem(int $veiculoId, int $quilometragem): void
    {
        // Só atualiza se a nova km for maior que a última conhecida (evita retrocesso por engano).
        $stmt = $this->db->prepare(
            'UPDATE veiculos SET quilometragem = :km WHERE id = :id AND (quilometragem IS NULL OR quilometragem < :km2)'
        );
        $stmt->execute(['km' => $quilometragem, 'km2' => $quilometragem, 'id' => $veiculoId]);
    }

    public function buscarPorPlaca(string $placa): ?array
    {
        $placa = self::normalizarPlaca($placa);
        $stmt = $this->db->prepare('SELECT * FROM veiculos WHERE placa = :placa');
        $stmt->execute(['placa' => $placa]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM veiculos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Busca veículos por modelo/cor — usada apenas para EXIBIR candidatos ao
     * mecânico quando ele não informa a placa. Nunca deve ser usada para
     * identificar automaticamente um veículo com certeza (ver regra de negócio:
     * placa é o único identificador confiável).
     */
    public function buscarPorModeloECor(string $modelo, ?string $cor = null): array
    {
        if ($cor !== null) {
            $stmt = $this->db->prepare(
                'SELECT * FROM veiculos WHERE modelo LIKE :modelo AND cor LIKE :cor'
            );
            $stmt->execute(['modelo' => "%{$modelo}%", 'cor' => "%{$cor}%"]);
        } else {
            $stmt = $this->db->prepare('SELECT * FROM veiculos WHERE modelo LIKE :modelo');
            $stmt->execute(['modelo' => "%{$modelo}%"]);
        }
        return $stmt->fetchAll();
    }

    public function buscar(string $termo, int $limite = 20): array
    {
        $sql = 'SELECT v.*, c.nome AS cliente_nome
                FROM veiculos v
                JOIN clientes c ON c.id = v.cliente_id
                WHERE v.placa LIKE :termo OR v.modelo LIKE :termo OR v.marca LIKE :termo OR c.nome LIKE :termo
                ORDER BY v.criado_em DESC
                LIMIT :limite';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('termo', "%{$termo}%", PDO::PARAM_STR);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listarTodos(): array
    {
        $sql = 'SELECT v.*, c.nome AS cliente_nome
                FROM veiculos v JOIN clientes c ON c.id = v.cliente_id
                ORDER BY v.criado_em DESC';
        return $this->db->query($sql)->fetchAll();
    }
}
