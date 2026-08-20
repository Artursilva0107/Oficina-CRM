<?php

declare(strict_types=1);

require __DIR__ . '/_painel_bootstrap.php';

use App\Config\Container;

$placa = trim((string) ($_GET['placa'] ?? ''));
$ficha = $placa !== '' ? Container::veiculoController()->ficha($placa) : null;

$paginaAtiva = 'veiculos';
$titulo = 'Ficha do veículo — Oficina';

require dirname(__DIR__) . '/resources/views/partials/header.php';

if ($ficha === null): ?>
    <h1>Veículo não encontrado</h1>
    <p><a href="/veiculos.php">&larr; Voltar para a lista de veículos</a></p>
<?php else:
    $v = $ficha['veiculo'];
?>

<p><a href="/veiculos.php">&larr; Voltar para a lista de veículos</a></p>

<div class="card" style="display:flex; align-items:center; gap:20px;">
    <?= placa_badge($v['placa']) ?>
    <div>
        <h1 style="margin-bottom:2px;"><?= e($v['modelo']) ?></h1>
        <div style="color:var(--texto-fraco);">
            <?= e($v['cor'] ?? '') ?><?php if (!empty($v['ano'])): ?> · <?= (int) $v['ano'] ?><?php endif; ?>
            · Cliente: <?= e($v['cliente_nome'] ?? '') ?>
        </div>
    </div>
</div>

<?php if (!empty($ficha['garantia_ativa'])): ?>
<div class="card">
    <h2>Garantia vigente</h2>
    <?php foreach ($ficha['garantia_ativa'] as $g): ?>
        <p>Válida até <strong><?= data_br($g['data_fim'], false) ?></strong> (<?= (int) $g['prazo_dias'] ?> dias)<?php if (!empty($g['observacoes'])): ?> — <?= e($g['observacoes']) ?><?php endif; ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <h2>Histórico de ordens de serviço</h2>
    <?php if (empty($ficha['historico'])): ?>
        <p class="vazio">Este veículo ainda não tem nenhuma OS registrada.</p>
    <?php else: foreach ($ficha['historico'] as $os): ?>
        <div class="card" style="background:var(--grafite-900);">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;"><a href="/os.php?id=<?= (int) $os['id'] ?>">OS #<?= (int) $os['id'] ?></a></h3>
                <?= status_badge($os['status']) ?>
            </div>
            <p style="color:var(--texto-fraco); font-size:0.85rem; margin:6px 0;">
                Entrada: <?= data_br($os['data_entrada']) ?>
                <?php if (!empty($os['data_saida'])): ?> · Saída: <?= data_br($os['data_saida']) ?><?php endif; ?>
            </p>
            <?php if (!empty($os['reclamacao_cliente'])): ?>
                <p><strong>Reclamação:</strong> <?= e($os['reclamacao_cliente']) ?></p>
            <?php endif; ?>
            <?php if (!empty($os['servicos'])): ?>
                <p><strong>Serviços realizados:</strong></p>
                <ul>
                <?php foreach ($os['servicos'] as $s): ?>
                    <li><?= e($s['descricao']) ?> — <?= data_br($s['data']) ?></li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($os['pecas'])): ?>
                <p><strong>Peças:</strong></p>
                <ul>
                <?php foreach ($os['pecas'] as $p): ?>
                    <li><?= e($p['descricao']) ?> (comprada por: <?= e($p['comprada_por']) ?>)<?php if ($p['valor'] !== null): ?> — R$ <?= number_format((float) $p['valor'], 2, ',', '.') ?><?php endif; ?></li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($os['anexos'])): ?>
                <p><strong>Anexos:</strong> <?= count($os['anexos']) ?> arquivo(s)</p>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php endif;
require dirname(__DIR__) . '/resources/views/partials/footer.php';
