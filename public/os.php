<?php

declare(strict_types=1);

require __DIR__ . '/_painel_bootstrap.php';

use App\Config\Container;

$id = (int) ($_GET['id'] ?? 0);
$controller = Container::ordemServicoController();
$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    $acao = (string) ($_POST['acao'] ?? '');
    try {
        if ($acao === 'marcar_entregue') {
            $controller->marcarEntregue($id, [
                'quilometragem_saida'      => trim((string) ($_POST['quilometragem_saida'] ?? '')) !== '' ? (int) $_POST['quilometragem_saida'] : null,
                'valor_final'              => trim((string) ($_POST['valor_final'] ?? '')) !== '' ? (float) str_replace(',', '.', (string) $_POST['valor_final']) : null,
                'forma_pagamento'          => trim((string) ($_POST['forma_pagamento'] ?? '')) ?: null,
                'data_limite_retorno'      => trim((string) ($_POST['data_limite_retorno'] ?? '')) ?: null,
                'observacoes_finais'       => trim((string) ($_POST['observacoes_finais'] ?? '')) ?: null,
            ]);
            header("Location: /os.php?id={$id}");
            exit;
        } elseif ($acao === 'gerar_recibo') {
            $controller->gerarRecibo($id);
            $sucesso = 'Recibo gerado com sucesso.';
        }
    } catch (\Throwable $e) {
        $erro = $e->getMessage();
    }
}

$ficha = $id > 0 ? $controller->ficha($id) : null;

$paginaAtiva = 'veiculos';
$titulo = "OS #{$id} — Oficina";

require dirname(__DIR__) . '/resources/views/partials/header.php';

if ($ficha === null): ?>
    <h1>Ordem de serviço não encontrada</h1>
    <p><a href="/veiculos.php">&larr; Voltar</a></p>
<?php else:
    $os = $ficha['os'];
?>

<p><a href="/veiculos.php">&larr; Voltar para veículos</a></p>

<?php if ($erro !== null): ?><div class="aviso aviso-erro"><?= e($erro) ?></div><?php endif; ?>
<?php if ($sucesso !== null): ?><div class="aviso aviso-ok"><?= e($sucesso) ?></div><?php endif; ?>

<div class="card" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
        <h1 style="margin-bottom:4px;">OS #<?= (int) $os['id'] ?></h1>
        <div style="color:var(--texto-fraco); font-size:0.88rem;">
            Entrada: <?= data_br($os['data_entrada']) ?>
            <?php if (!empty($os['quilometragem_entrada'])): ?> · KM entrada: <?= (int) $os['quilometragem_entrada'] ?><?php endif; ?>
            <?php if (!empty($os['data_saida'])): ?> · Saída: <?= data_br($os['data_saida']) ?><?php endif; ?>
        </div>
        <?php if (!empty($os['motivo'])): ?><p style="margin:6px 0;"><strong>Motivo da entrada:</strong> <?= e($os['motivo']) ?></p><?php endif; ?>
    </div>
    <div style="text-align:right;">
        <?= status_badge($os['status']) ?>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Orçamento</h2>
        <p><a href="/orcamento.php?os_id=<?= (int) $os['id'] ?>" class="botao">Novo/gerenciar orçamento</a></p>
    </div>

    <div class="card">
        <h2><?= $os['status'] === 'entregue' ? 'Recibo' : 'Saída do veículo' ?></h2>
        <?php if ($os['status'] === 'entregue'): ?>
            <p>Saída registrada em <?= data_br($os['data_saida']) ?>.</p>
            <?php if (!empty($os['valor_final'])): ?><p>Valor: <?= moeda_br((float) $os['valor_final']) ?> — <?= e($os['forma_pagamento'] ?? '') ?></p><?php endif; ?>
            <?php if (!empty($os['data_limite_retorno'])): ?><p>Prazo de retorno gratuito: <?= data_br($os['data_limite_retorno'], false) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="acao" value="gerar_recibo">
                <button type="submit">Gerar recibo em PDF</button>
            </form>
        <?php else: ?>
        <form method="post">
            <input type="hidden" name="acao" value="marcar_entregue">
            <label>Quilometragem de saída</label>
            <input type="number" name="quilometragem_saida">
            <label>Valor final (R$)</label>
            <input type="text" name="valor_final" placeholder="450,00">
            <label>Forma de pagamento</label>
            <input type="text" name="forma_pagamento" placeholder="Pix, cartão, dinheiro...">
            <label>Prazo para retorno gratuito</label>
            <input type="date" name="data_limite_retorno">
            <label>Observações finais</label>
            <textarea name="observacoes_finais" rows="2"></textarea>
            <button type="submit">Marcar como entregue</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($os['reclamacao_cliente'])): ?>
<div class="card">
    <h2>Reclamação do cliente</h2>
    <p><?= nl2br(e($os['reclamacao_cliente'])) ?></p>
</div>
<?php endif; ?>

<div class="card">
    <h2>Diagnósticos</h2>
    <?php if (empty($ficha['diagnosticos'])): ?>
        <p class="vazio">Nenhum diagnóstico registrado ainda.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>#</th><th>Descrição</th><th>Origem</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($ficha['diagnosticos'] as $i => $d): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($d['descricao']) ?></td>
                <td><?= $d['origem'] === 'sugerido_por_ia' ? 'Sugerido por IA' : 'Informado pelo mecânico' ?></td>
                <td><?= e(ucfirst($d['status'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Garantia</h2>
    <?php if (empty($ficha['garantias'])): ?>
        <p class="vazio">Nenhuma garantia registrada para esta OS.</p>
    <?php else: foreach ($ficha['garantias'] as $g): ?>
        <p><?= (int) $g['prazo_dias'] ?> dias — de <?= data_br($g['data_inicio'], false) ?> até <strong><?= data_br($g['data_fim'], false) ?></strong><?php if (!empty($g['observacoes'])): ?> — <?= e($g['observacoes']) ?><?php endif; ?></p>
    <?php endforeach; endif; ?>
</div>

<?php endif;
require dirname(__DIR__) . '/resources/views/partials/footer.php';
