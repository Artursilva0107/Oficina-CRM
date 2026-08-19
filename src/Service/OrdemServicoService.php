<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AnexoRepository;
use App\Repository\FilaLogRepository;
use App\Repository\OrdemServicoRepository;
use App\Repository\PecaRepository;
use App\Repository\ServicoRepository;
use App\Repository\VeiculoRepository;

final class OrdemServicoService
{
    public function __construct(
        private OrdemServicoRepository $ordens,
        private VeiculoRepository $veiculos,
        private ServicoRepository $servicos,
        private PecaRepository $pecas,
        private AnexoRepository $anexos,
        private FilaLogRepository $filaLog
    ) {
    }

    public function abrir(int $veiculoId, array $dados, ?int $criadoPor): array
    {
        $id = $this->ordens->criar(
            $veiculoId,
            $dados['reclamacao'] ?? null,
            $criadoPor,
            isset($dados['quilometragem_entrada']) ? (int) $dados['quilometragem_entrada'] : null,
            $dados['motivo'] ?? null,
            isset($dados['previsao_entrega']) ? new \DateTimeImmutable($dados['previsao_entrega']) : null,
            isset($dados['responsavel_atendimento_id']) ? (int) $dados['responsavel_atendimento_id'] : null,
            isset($dados['agendamento_id']) ? (int) $dados['agendamento_id'] : null
        );

        if (isset($dados['quilometragem_entrada'])) {
            $this->veiculos->atualizarQuilometragem($veiculoId, (int) $dados['quilometragem_entrada']);
        }

        $this->filaLog->registrar($id, 'entrada_fila', null, $criadoPor);

        return $this->ordens->buscarPorId($id);
    }

    public function atualizarStatus(int $ordemServicoId, string $status): array
    {
        $this->ordens->atualizarStatus($ordemServicoId, $status);
        return $this->ordens->buscarPorId($ordemServicoId);
    }

    /** Pausa a OS na fila (aguardando peça ou aguardando aprovação), mantendo o histórico e o motivo. */
    public function pausar(int $ordemServicoId, string $status, ?string $motivo, ?int $usuarioId): array
    {
        $this->ordens->pausar($ordemServicoId, $status);
        $this->filaLog->registrar($ordemServicoId, 'pausada', $motivo, $usuarioId);
        return $this->ordens->buscarPorId($ordemServicoId);
    }

    public function retomar(int $ordemServicoId, string $novoStatus, ?int $usuarioId): array
    {
        $this->ordens->retomar($ordemServicoId, $novoStatus);
        $this->filaLog->registrar($ordemServicoId, 'retomada', null, $usuarioId);
        return $this->ordens->buscarPorId($ordemServicoId);
    }

    /**
     * Define uma prioridade manual na fila (exceção à ordem natural por data de
     * entrada). Sempre exige o motivo e registra quem alterou, para que a regra
     * de "primeiro que entra, primeiro que sai" não se perca silenciosamente.
     */
    public function definirPrioridadeManual(int $ordemServicoId, ?int $prioridade, string $motivo, int $usuarioId): array
    {
        $this->ordens->definirPrioridadeManual($ordemServicoId, $prioridade, $motivo, $usuarioId);
        $this->filaLog->registrar($ordemServicoId, 'prioridade_alterada', $motivo, $usuarioId);
        return $this->ordens->buscarPorId($ordemServicoId);
    }

    public function marcarEntregue(int $ordemServicoId, array $dadosSaida = []): array
    {
        $limiteRetorno = isset($dadosSaida['data_limite_retorno'])
            ? new \DateTimeImmutable($dadosSaida['data_limite_retorno'])
            : null;

        $this->ordens->marcarEntregue(
            $ordemServicoId,
            isset($dadosSaida['quilometragem_saida']) ? (int) $dadosSaida['quilometragem_saida'] : null,
            isset($dadosSaida['valor_final']) ? (float) $dadosSaida['valor_final'] : null,
            $dadosSaida['forma_pagamento'] ?? null,
            isset($dadosSaida['responsavel_entrega_id']) ? (int) $dadosSaida['responsavel_entrega_id'] : null,
            $limiteRetorno,
            $dadosSaida['observacoes_finais'] ?? null
        );

        if (isset($dadosSaida['quilometragem_saida'])) {
            $os = $this->ordens->buscarPorId($ordemServicoId);
            if ($os !== null) {
                $this->veiculos->atualizarQuilometragem((int) $os['veiculo_id'], (int) $dadosSaida['quilometragem_saida']);
            }
        }

        return $this->ordens->buscarPorId($ordemServicoId);
    }

    public function registrarServico(int $ordemServicoId, string $descricao, ?int $mecanicoId = null): array
    {
        $this->servicos->criar($ordemServicoId, $descricao, $mecanicoId, new \DateTimeImmutable('now'), null);
        $status = $this->ordens->buscarPorId($ordemServicoId)['status'] ?? null;
        if (in_array($status, ['recebido', 'em_diagnostico'], true)) {
            $this->ordens->atualizarStatus($ordemServicoId, 'em_servico');
        }
        return $this->ordens->buscarPorId($ordemServicoId);
    }

    public function registrarPeca(int $ordemServicoId, string $descricao, string $compradaPor, ?float $valor, float $quantidade = 1.0): void
    {
        $this->pecas->criar($ordemServicoId, $descricao, $compradaPor, $valor, $quantidade);
    }

    public function anexarArquivo(int $ordemServicoId, string $tipo, string $caminhoArquivo, ?string $transcricao = null, string $categoria = 'outro'): void
    {
        $this->anexos->criar($ordemServicoId, $tipo, $caminhoArquivo, $transcricao, $categoria);
    }

    /**
     * Determina a OS "alvo" para uma placa: se houver exatamente uma OS aberta,
     * usa-a; se houver mais de uma ou nenhuma, retorna null (chamador deve pedir
     * esclarecimento em vez de adivinhar).
     */
    public function osAlvoParaPlaca(string $placa): ?array
    {
        $veiculo = $this->veiculos->buscarPorPlaca($placa);
        if ($veiculo === null) {
            return null;
        }
        return $this->ordens->buscarUnicaAbertaPorVeiculo((int) $veiculo['id']);
    }

    public function osAbertasParaPlaca(string $placa): array
    {
        $veiculo = $this->veiculos->buscarPorPlaca($placa);
        if ($veiculo === null) {
            return [];
        }
        return $this->ordens->buscarAbertasPorVeiculo((int) $veiculo['id']);
    }

    public function historicoCompleto(int $veiculoId): array
    {
        $ordens = $this->ordens->listarPorVeiculo($veiculoId);
        foreach ($ordens as &$os) {
            $os['servicos'] = $this->servicos->listarPorOs((int) $os['id']);
            $os['pecas'] = $this->pecas->listarPorOs((int) $os['id']);
            $os['anexos'] = $this->anexos->listarPorOs((int) $os['id']);
        }
        return $ordens;
    }

    public function listarAbertas(): array
    {
        return $this->ordens->listarAbertas();
    }

    /** Fila da oficina — ordenada por prioridade manual (exceção) e, por padrão, por ordem real de entrada (FIFO). */
    public function filaDaOficina(): array
    {
        return $this->ordens->filaDaOficina();
    }

    public function historicoDaFila(int $ordemServicoId): array
    {
        return $this->filaLog->listarPorOs($ordemServicoId);
    }

    public function contarPorStatus(): array
    {
        return $this->ordens->contarPorStatus();
    }

    public function tempoMedioAtendimentoHoras(): ?float
    {
        return $this->ordens->tempoMedioAtendimentoHoras();
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->ordens->buscarPorId($id);
    }
}
