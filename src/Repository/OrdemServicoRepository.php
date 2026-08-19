<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class OrdemServicoRepository
{
    /** Ordem "natural" do fluxo — usada para inferir o próximo status quando não informado. */
    public const FLUXO_STATUS = [
        'recebido', 'em_diagnostico', 'aguardando_aprovacao', 'aguardando_peca', 'em_servico', 'pronto', 'entregue',
    ];

    public function __construct(private PDO $db)
    {
    }

    public function criar(
        int $veiculoId,
        ?string $reclamacao,
        ?int $criadoPor,
        ?int $quilometragemEntrada = null,
        ?string $motivo = null,
        ?\DateTimeImmutable $previsaoEntrega = null,
        ?int $responsavelAtendimentoId = null,
        ?int $agendamentoId = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO ordens_servico
                (veiculo_id, agendamento_id, data_entrada, quilometragem_entrada, motivo,
                 reclamacao_cliente, previsao_entrega, responsavel_atendimento_id, status, criado_por)
             VALUES
                (:veiculo_id, :agendamento_id, NOW(), :km, :motivo,
                 :reclamacao, :previsao, :responsavel, :status, :criado_por)'
        );
        $stmt->execute([
            'veiculo_id'   => $veiculoId,
            'agendamento_id' => $agendamentoId,
            'km'           => $quilometragemEntrada,
            'motivo'       => $motivo,
            'reclamacao'   => $reclamacao,
            'previsao'     => $previsaoEntrega?->format('Y-m-d H:i:s'),
            'responsavel'  => $responsavelAtendimentoId,
            'status'       => 'recebido',
            'criado_por'   => $criadoPor,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ordens_servico WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Retorna as OS ainda não entregues de um veículo, mais recente primeiro. */
    public function buscarAbertasPorVeiculo(int $veiculoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM ordens_servico
             WHERE veiculo_id = :veiculo_id AND status <> 'entregue'
             ORDER BY data_entrada DESC"
        );
        $stmt->execute(['veiculo_id' => $veiculoId]);
        return $stmt->fetchAll();
    }

    /** A OS aberta "corrente" de um veículo, se houver exatamente uma. */
    public function buscarUnicaAbertaPorVeiculo(int $veiculoId): ?array
    {
        $abertas = $this->buscarAbertasPorVeiculo($veiculoId);
        return count($abertas) === 1 ? $abertas[0] : null;
    }

    public function listarPorVeiculo(int $veiculoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ordens_servico WHERE veiculo_id = :veiculo_id ORDER BY data_entrada DESC'
        );
        $stmt->execute(['veiculo_id' => $veiculoId]);
        return $stmt->fetchAll();
    }

    public function atualizarStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE ordens_servico SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** Marca a OS como "aguardando peça" ou "aguardando aprovação" e pausa a posição na fila (sem perder o histórico de entrada). */
    public function pausar(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE ordens_servico SET status = :status, pausada = 1 WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function retomar(int $id, string $novoStatus): void
    {
        $stmt = $this->db->prepare('UPDATE ordens_servico SET status = :status, pausada = 0 WHERE id = :id');
        $stmt->execute(['status' => $novoStatus, 'id' => $id]);
    }

    /** Define/limpa uma prioridade manual (exceção à ordem natural por data de entrada). */
    public function definirPrioridadeManual(int $id, ?int $prioridade, ?string $motivo, int $alteradoPor): void
    {
        $stmt = $this->db->prepare(
            'UPDATE ordens_servico
             SET prioridade_manual = :prioridade, prioridade_motivo = :motivo,
                 prioridade_alterada_por = :usuario, prioridade_alterada_em = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'prioridade' => $prioridade,
            'motivo'     => $motivo,
            'usuario'    => $alteradoPor,
            'id'         => $id,
        ]);
    }

    public function marcarEntregue(
        int $id,
        ?int $quilometragemSaida,
        ?float $valorFinal,
        ?string $formaPagamento,
        ?int $responsavelEntregaId,
        ?\DateTimeImmutable $dataLimiteRetorno,
        ?string $observacoesFinais
    ): void {
        $stmt = $this->db->prepare(
            "UPDATE ordens_servico SET
                status = 'entregue', data_saida = NOW(),
                quilometragem_saida = :km, valor_final = :valor, forma_pagamento = :pagamento,
                responsavel_entrega_id = :responsavel, data_limite_retorno = :limite,
                observacoes_finais = :observacoes
             WHERE id = :id"
        );
        $stmt->execute([
            'km'          => $quilometragemSaida,
            'valor'       => $valorFinal,
            'pagamento'   => $formaPagamento,
            'responsavel' => $responsavelEntregaId,
            'limite'      => $dataLimiteRetorno?->format('Y-m-d'),
            'observacoes' => $observacoesFinais,
            'id'          => $id,
        ]);
    }

    public function listarAbertas(): array
    {
        $sql = "SELECT os.*, v.placa, v.modelo, v.marca, c.nome AS cliente_nome
                FROM ordens_servico os
                JOIN veiculos v ON v.id = os.veiculo_id
                JOIN clientes c ON c.id = v.cliente_id
                WHERE os.status <> 'entregue'
                ORDER BY os.data_entrada ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Fila da oficina: ordena por prioridade manual (quando definida) e, dentro
     * de cada grupo, sempre pela data/hora real de entrada (FIFO). OS pausadas
     * (aguardando peça/aprovação) vão para o fim da lista, mas mantêm sua
     * data de entrada original registrada.
     */
    public function filaDaOficina(): array
    {
        $sql = "SELECT os.*, v.placa, v.modelo, v.marca, c.nome AS cliente_nome,
                       u.nome AS responsavel_nome,
                       TIMESTAMPDIFF(MINUTE, os.data_entrada, NOW()) AS minutos_na_oficina
                FROM ordens_servico os
                JOIN veiculos v ON v.id = os.veiculo_id
                JOIN clientes c ON c.id = v.cliente_id
                LEFT JOIN usuarios u ON u.id = os.responsavel_atendimento_id
                WHERE os.status <> 'entregue'
                ORDER BY os.pausada ASC,
                         (os.prioridade_manual IS NULL) ASC, os.prioridade_manual ASC,
                         os.data_entrada ASC";
        return $this->db->query($sql)->fetchAll();
    }

    public function contarPorStatus(): array
    {
        $sql = 'SELECT status, COUNT(*) AS total FROM ordens_servico GROUP BY status';
        return $this->db->query($sql)->fetchAll();
    }

    /** Tempo médio (em horas) entre entrada e saída das OS já entregues. */
    public function tempoMedioAtendimentoHoras(): ?float
    {
        $sql = "SELECT AVG(TIMESTAMPDIFF(HOUR, data_entrada, data_saida)) AS media
                FROM ordens_servico WHERE status = 'entregue' AND data_saida IS NOT NULL";
        $row = $this->db->query($sql)->fetch();
        return $row['media'] !== null ? (float) $row['media'] : null;
    }
}
