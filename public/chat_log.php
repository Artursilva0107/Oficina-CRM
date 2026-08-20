<?php

declare(strict_types=1);

require __DIR__ . '/_painel_bootstrap.php';

use App\Config\Container;

$interacoes = Container::interacaoChatRepository()->listarRecentes(150);

$paginaAtiva = 'chat';
$titulo = 'Log do chat — Oficina';

require dirname(__DIR__) . '/resources/views/partials/header.php';
?>

<h1>Log de interações do chat</h1>
<p style="color:var(--texto-fraco);">Histórico bruto de tudo que chega pelo Telegram, para auditoria e para melhorar a interpretação da IA.</p>

<div class="card">
    <?php if (empty($interacoes)): ?>
        <p class="vazio">Nenhuma interação registrada ainda.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Quando</th><th>Mecânico</th><th>Tipo</th><th>Mensagem</th><th>Ação</th><th>Sucesso</th></tr></thead>
        <tbody>
        <?php foreach ($interacoes as $i): ?>
            <tr>
                <td><?= data_br($i['criado_em']) ?></td>
                <td><?= e($i['usuario_nome'] ?? '—') ?></td>
                <td><?= e($i['tipo']) ?></td>
                <td style="max-width:320px; white-space:normal;"><?= e(mb_strimwidth((string) $i['mensagem_original'], 0, 140, '…')) ?></td>
                <td><?= e($i['acao_executada'] ?? '—') ?></td>
                <td><?= ((int) $i['sucesso']) === 1 ? '✅' : '⚠️' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/resources/views/partials/footer.php'; ?>
