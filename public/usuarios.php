<?php

declare(strict_types=1);

require __DIR__ . '/_painel_bootstrap.php';

use App\Config\Container;

$usuarioLogado = $auth->exigirPapel(['admin']);
$usuarios = Container::usuarioRepository();

$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $papel = (string) ($_POST['papel'] ?? 'mecanico');
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    if ($nome === '' || !in_array($papel, ['admin', 'mecanico', 'atendente'], true)) {
        $erro = 'Preencha nome e papel corretamente.';
    } else {
        $senhaHash = ($email !== '' && $senha !== '') ? password_hash($senha, PASSWORD_BCRYPT) : null;
        $usuarios->criar($nome, $papel, $email !== '' ? $email : null, $senhaHash, null);
        $sucesso = "Usuário {$nome} criado. Se for mecânico, ele pode começar a usar o Telegram — o vínculo é feito no primeiro contato com o bot.";
    }
}

$lista = $usuarios->listarTodos();

$paginaAtiva = 'usuarios';
$titulo = 'Usuários — Oficina';

require dirname(__DIR__) . '/resources/views/partials/header.php';
?>

<h1>Usuários</h1>

<?php if ($erro !== null): ?><div class="aviso aviso-erro"><?= e($erro) ?></div><?php endif; ?>
<?php if ($sucesso !== null): ?><div class="aviso aviso-ok"><?= e($sucesso) ?></div><?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <h2>Equipe</h2>
        <table>
            <thead><tr><th>Nome</th><th>Papel</th><th>Telegram vinculado</th><th>E-mail</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $u): ?>
                <tr>
                    <td><?= e($u['nome']) ?></td>
                    <td><?= e(ucfirst($u['papel'])) ?></td>
                    <td><?= $u['telegram_id'] !== null ? '✅' : '—' ?></td>
                    <td><?= e($u['email'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Adicionar usuário</h2>
        <form method="post" action="/usuarios.php">
            <input type="hidden" name="acao" value="criar">

            <label for="nome">Nome</label>
            <input type="text" id="nome" name="nome" required>

            <label for="papel">Papel</label>
            <select id="papel" name="papel">
                <option value="mecanico">Mecânico (usa só o chat)</option>
                <option value="atendente">Atendente (painel web)</option>
                <option value="admin">Admin (painel web)</option>
            </select>

            <label for="email">E-mail (necessário para login no painel)</label>
            <input type="email" id="email" name="email">

            <label for="senha">Senha (necessária para login no painel)</label>
            <input type="password" id="senha" name="senha">

            <button type="submit">Criar usuário</button>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/resources/views/partials/footer.php'; ?>
