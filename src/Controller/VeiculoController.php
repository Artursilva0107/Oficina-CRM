<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\GarantiaService;
use App\Service\OrdemServicoService;
use App\Service\VeiculoService;

final class VeiculoController
{
    public function __construct(
        private VeiculoService $veiculos,
        private OrdemServicoService $ordens,
        private GarantiaService $garantias
    ) {
    }

    public function listar(?string $termo): array
    {
        return $termo !== null && $termo !== ''
            ? $this->veiculos->buscar($termo)
            : $this->veiculos->listarTodos();
    }

    public function ficha(string $placa): ?array
    {
        $veiculo = $this->veiculos->buscarPorPlaca($placa);
        if ($veiculo === null) {
            return null;
        }

        return [
            'veiculo'         => $veiculo,
            'historico'       => $this->ordens->historicoCompleto((int) $veiculo['id']),
            'garantia_ativa'  => $this->garantias->veiculoEmGarantia((int) $veiculo['id']),
        ];
    }

    public function cadastrar(array $dadosFormulario): array
    {
        return $this->veiculos->cadastrar([
            'placa'            => (string) $dadosFormulario['placa'],
            'modelo'           => (string) $dadosFormulario['modelo'],
            'marca'            => ($dadosFormulario['marca'] ?? '') !== '' ? $dadosFormulario['marca'] : null,
            'versao'           => ($dadosFormulario['versao'] ?? '') !== '' ? $dadosFormulario['versao'] : null,
            'cor'              => $dadosFormulario['cor'] !== '' ? $dadosFormulario['cor'] : null,
            'ano'              => $dadosFormulario['ano'] !== '' ? (int) $dadosFormulario['ano'] : null,
            'quilometragem'    => ($dadosFormulario['quilometragem'] ?? '') !== '' ? (int) $dadosFormulario['quilometragem'] : null,
            'combustivel'      => ($dadosFormulario['combustivel'] ?? '') !== '' ? $dadosFormulario['combustivel'] : null,
            'observacoes'      => ($dadosFormulario['observacoes'] ?? '') !== '' ? $dadosFormulario['observacoes'] : null,
            'cliente_nome'     => (string) $dadosFormulario['cliente_nome'],
            'cliente_telefone' => $dadosFormulario['cliente_telefone'] !== '' ? $dadosFormulario['cliente_telefone'] : null,
        ]);
    }
}
