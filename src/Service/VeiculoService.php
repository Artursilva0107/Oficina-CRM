<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ClienteRepository;
use App\Repository\VeiculoRepository;

/**
 * Regra crítica do negócio: a PLACA é o único identificador confiável de um
 * veículo. Modelo/cor nunca devem ser usados para localizar um carro com
 * certeza — apenas para exibir candidatos quando a placa não foi informada.
 */
final class VeiculoService
{
    public function __construct(
        private VeiculoRepository $veiculos,
        private ClienteRepository $clientes
    ) {
    }

    public function cadastrar(array $dados): array
    {
        $placaNormalizada = VeiculoRepository::normalizarPlaca((string) $dados['placa']);

        $existente = $this->veiculos->buscarPorPlaca($placaNormalizada);
        if ($existente !== null) {
            throw new \DomainException("Já existe um veículo cadastrado com a placa {$placaNormalizada}.");
        }

        $clienteId = $this->clientes->localizarOuCriar(
            (string) $dados['cliente_nome'],
            $dados['cliente_telefone'] ?? null
        );

        $veiculoId = $this->veiculos->criar(
            $clienteId,
            $placaNormalizada,
            (string) $dados['modelo'],
            $dados['cor'] ?? null,
            isset($dados['ano']) ? (int) $dados['ano'] : null,
            $dados['marca'] ?? null,
            $dados['versao'] ?? null,
            isset($dados['quilometragem']) ? (int) $dados['quilometragem'] : null,
            $dados['combustivel'] ?? null,
            $dados['observacoes'] ?? null
        );

        return $this->veiculos->buscarPorId($veiculoId);
    }

    public function buscarPorPlacaObrigatoria(string $placa): array
    {
        $veiculo = $this->veiculos->buscarPorPlaca($placa);
        if ($veiculo === null) {
            throw new \DomainException("Nenhum veículo encontrado com a placa {$placa}.");
        }
        return $veiculo;
    }

    public function buscarPorPlaca(string $placa): ?array
    {
        return $this->veiculos->buscarPorPlaca($placa);
    }

    public function atualizarQuilometragem(int $veiculoId, int $quilometragem): void
    {
        $this->veiculos->atualizarQuilometragem($veiculoId, $quilometragem);
    }

    /** Usado apenas para sugerir candidatos ao usuário quando a placa não foi informada. */
    public function candidatosPorModeloECor(string $modelo, ?string $cor): array
    {
        return $this->veiculos->buscarPorModeloECor($modelo, $cor);
    }

    public function buscar(string $termo): array
    {
        return $this->veiculos->buscar($termo);
    }

    public function listarTodos(): array
    {
        return $this->veiculos->listarTodos();
    }
}
