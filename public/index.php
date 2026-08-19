<?php

declare(strict_types=1);

require __DIR__ . '/_painel_bootstrap.php';

use App\Config\Container;

$dados = Container::dashboardController()->dados();
$paginaAtiva = 'dashboard';
$titulo = 'Painel — Oficina';

require dirname(__DIR__) . '/resources/views/partials/header.php';
?>

<h1>Painel</h1>

<div class="grid grid-3">
    <div class="card stat">
        <div class="valor"><?= count($dados['os_abertas']) ?></div>
        <div class="rotulo">OS em aberto</div>
    </div>
    <div class="card stat">
        <div class="valor"><?= $dados['tempo_medio_horas'] !== null ? round($dados['tempo_medio_horas']) . 'h' : '—' ?></div>
        <div class="rotulo">Tempo médio de atendimento</div>
    </div>
    <div class="card stat">
        <div class="valor"><?= count($dados['garantias_vencendo']) ?></div>
        <div class="rotulo">Garantias vencendo (15 dias)</div>
    </div>
</div>

<div class="card">
    <h2>Ordens de serviço em aberto</h2>
    <?php if (empty($dados['os_abertas'])): ?>
        <p class="vazio">Nenhuma OS em aberto no momento.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr><th>OS</th><th>Placa</th><th>Veículo</th><th>Cliente</th><th>Status</th><th>Entrada</th></tr>
        </thead>
        <tbody>
        <?php foreach ($dados['os_abertas'] as $os): ?>
            <tr>
                <td><a href="/os.php?id=<?= (int) $os['id'] ?>">#<?= (int) $os['id'] ?></a></td>
                <td><?= placa_badge($os['placa']) ?></td>
                <td><?= e($os['modelo']) ?></td>
                <td><?= e($os['cliente_nome']) ?></td>
                <td><?= status_badge($os['status']) ?></td>
                <td><?= data_br($os['data_entrada']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Garantias próximas do vencimento ou vencidas</h2>
    <?php if (empty($dados['garantias_vencendo'])): ?>
        <p class="vazio">Nenhuma garantia vencendo nos próximos 15 dias.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr><th>Placa</th><th>Veículo</th><th>Cliente</th><th>Vence em</th><th>Situação</th></tr>
        </thead>
        <tbody>
        <?php foreach ($dados['garantias_vencendo'] as $g): $vencida = strtotime($g['data_fim']) < strtotime('today'); ?>
            <tr>
                <td><?= placa_badge($g['placa']) ?></td>
                <td><?= e($g['modelo']) ?></td>
                <td><?= e($g['cliente_nome']) ?></td>
                <td><?= data_br($g['data_fim'], false) ?></td>
                <td><span class="badge <?= $vencida ? 'badge-vencida' : 'badge-aberta' ?>"><?= $vencida ? 'Vencida' : 'Vencendo' ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/resources/views/partials/footer.php'; ?>
