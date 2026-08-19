<?php
/** @var array $usuarioLogado */
/** @var string $paginaAtiva */
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo ?? 'Painel da Oficina') ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="marca"><span class="risco">//</span> Painel da Oficina</div>
    <nav>
        <a href="/index.php" class="<?= ($paginaAtiva ?? '') === 'dashboard' ? 'ativo' : '' ?>">Painel</a>
        <a href="/agenda.php" class="<?= ($paginaAtiva ?? '') === 'agenda' ? 'ativo' : '' ?>">Agenda</a>
        <a href="/fila.php" class="<?= ($paginaAtiva ?? '') === 'fila' ? 'ativo' : '' ?>">Fila da Oficina</a>
        <a href="/veiculos.php" class="<?= ($paginaAtiva ?? '') === 'veiculos' ? 'ativo' : '' ?>">Veículos</a>
        <a href="/chat_log.php" class="<?= ($paginaAtiva ?? '') === 'chat' ? 'ativo' : '' ?>">Log do chat</a>
        <?php if (($usuarioLogado['papel'] ?? '') === 'admin'): ?>
        <a href="/usuarios.php" class="<?= ($paginaAtiva ?? '') === 'usuarios' ? 'ativo' : '' ?>">Usuários</a>
        <?php endif; ?>
        <span class="usuario"><?= e($usuarioLogado['nome'] ?? '') ?></span>
        <a href="/logout.php">Sair</a>
    </nav>
</header>
<main class="container">
