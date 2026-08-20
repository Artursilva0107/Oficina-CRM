<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
session_name(\App\Config\Env::get('SESSION_NAME', 'oficina_sess'));
session_start();

use App\Config\Container;

$auth = Container::authService();

if ($auth->usuarioLogado() !== null) {
    header('Location: /index.php');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    try {
        $usuario = $auth->autenticar($email, $senha);
        if ($usuario === null) {
            $erro = 'E-mail ou senha inválidos.';
        } else {
            $auth->iniciarSessao($usuario);
            header('Location: /index.php');
            exit;
        }
    } catch (\Throwable $e) {
        error_log('Erro no login: ' . $e->getMessage());
        $erro = (\App\Config\Env::get('APP_ENV', 'production') === 'local')
            ? 'Erro técnico: ' . $e->getMessage()
            : 'Não foi possível conectar ao sistema agora. Tente novamente em instantes.';
    }
}

$titulo = 'Entrar — Painel da Oficina';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="tela-login">
    <div class="card caixa-login">
        <h1>// Oficina</h1>
        <p class="subtitulo">Acesso do gestor/administrativo</p>

        <?php if ($erro !== null): ?>
            <div class="aviso aviso-erro"><?= e($erro) ?></div>
        <?php endif; ?>

        <form method="post" action="/login.php">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required autofocus>

            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" required>

            <button type="submit" style="width:100%">Entrar</button>
        </form>
    </div>
</div>
</body>
</html>
