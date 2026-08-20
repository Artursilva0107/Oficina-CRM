<?php

declare(strict_types=1);

namespace App\Auth;

use App\Repository\UsuarioRepository;

final class AuthService
{
    public function __construct(private UsuarioRepository $usuarios)
    {
    }

    public function autenticar(string $email, string $senha): ?array
    {
        $usuario = $this->usuarios->buscarPorEmail($email);

        if ($usuario === null || $usuario['senha_hash'] === null) {
            return null;
        }

        if (!password_verify($senha, $usuario['senha_hash'])) {
            return null;
        }

        return $usuario;
    }

    public function iniciarSessao(array $usuario): void
    {
        $_SESSION['usuario_id']    = $usuario['id'];
        $_SESSION['usuario_nome']  = $usuario['nome'];
        $_SESSION['usuario_papel'] = $usuario['papel'];
    }

    public function encerrarSessao(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function usuarioLogado(): ?array
    {
        if (!isset($_SESSION['usuario_id'])) {
            return null;
        }
        return [
            'id'    => $_SESSION['usuario_id'],
            'nome'  => $_SESSION['usuario_nome'],
            'papel' => $_SESSION['usuario_papel'],
        ];
    }

    public function exigirLogin(): array
    {
        $usuario = $this->usuarioLogado();
        if ($usuario === null) {
            header('Location: /login.php');
            exit;
        }
        return $usuario;
    }

    public function exigirPapel(array $papeisPermitidos): array
    {
        $usuario = $this->exigirLogin();
        if (!in_array($usuario['papel'], $papeisPermitidos, true)) {
            http_response_code(403);
            echo 'Acesso negado para o seu perfil de usuário.';
            exit;
        }
        return $usuario;
    }
}
