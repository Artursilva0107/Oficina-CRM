<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ClienteRepository;

final class ClienteService
{
    public function __construct(private ClienteRepository $clientes)
    {
    }

    public function localizarOuCriar(string $nome, ?string $telefone = null): array
    {
        $id = $this->clientes->localizarOuCriar($nome, $telefone);
        return $this->clientes->buscarPorId($id);
    }

    public function buscar(string $termo): array
    {
        return $this->clientes->buscar($termo);
    }

    public function listarTodos(): array
    {
        return $this->clientes->listarTodos();
    }
}
