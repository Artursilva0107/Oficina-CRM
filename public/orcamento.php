<?php

declare(strict_types=1);

require __DIR__ . '/_painel_bootstrap.php';

use App\Config\Container;

$controller = Container::orcamentoController();
$ordemServicoId = (int) ($_GET['os_id'] ?? $_POST['os_id'] ?? 0);
$orcamentoId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    try {
        if ($acao === 'criar' && $ordemServicoId > 0) {
            $orcamento = $controller->criar($ordemServicoId, (int) ($_POST['validade_dias'] ?? 7), (int) $usuarioLogado['id'], trim((string) ($_POST['observacoes'] ?? '')) ?: null);
            header("Location: /orcamento.php?id={$orcamento['id']}");
            exit;
        } elseif ($acao === 'adicionar_item' && $orcamentoId > 0) {
            $controller->adicionarItem(
                $orcamentoId,
                (string) $_POST['tipo'],
                (string) $_POST['descricao'],
                (float) str_replace(',', '.', (string) $_POST['quantidade']),
                (float) str_replace(',', '.', (string) $_POST['valor_unitario'])
            );
            $sucesso = 'Item adicionado.';
        } elseif ($acao === 'remover_item' && $orcamentoId > 0) {
            $controller->removerItem($orcamentoId, (int) $_POST['item_id']);
            $sucesso = 'Item removido.';
        } elseif ($acao === 'desconto' && $orcamentoId > 0) {
            $controller->definirDesconto($orcamentoId, (float) str_replace(',', '.', (string) $_POST['desconto']));
            $sucesso = 'Desconto atualizado.';
        } elseif ($acao === 'aprovar_tudo' && $orcamentoId > 0) {
            $controller->aprovarTudo($orcamentoId);
            $sucesso = 'Orçamento aprovado integralmente.';
        } elseif ($acao === 'aprovar_parcial' && $orcamentoId > 0) {
            $ids = array_map('intval', $_POST['itens_aprovados'] ?? []);
            $controller->aprovarParcial($orcamentoId, $ids);
            $sucesso = 'Orçamento aprovado parcialmente.';
        } elseif ($acao === 'recusar' && $orcamentoId > 0) {
            $controller->recusar($orcamentoId);
            $sucesso = 'Orçamento marcado como recusado.';
        } elseif ($acao === 'gerar_pdf' && $orcamentoId > 0) {
            $controller->gerarPdf($orcamentoId);
            $sucesso = 'PDF gerado com sucesso.';
        }
    } catch (\Throwable $e) {
        $erro = $e->getMessage();
    }
}

$orcamento = $orcamentoId > 0 ? $controller->ficha($orcamentoId) : null;
if ($orcamento !== null) {
    $ordemServicoId = (int) $orcamento['ordem_servico_id'];
}

$paginaAtiva = 'veiculos';
$titulo = 'Orçamento — Oficina';

require dirname(__DIR__) . '/resources/views/partials/header.php';
?>

<?php if ($erro !== null): ?><div class="aviso aviso-erro"><?= e($erro) ?></div><?php endif; ?>
<?php if ($sucesso !== null): ?><div class="aviso aviso-ok"><?= e($sucesso) ?></div><?php endif; ?>

<?php if ($orcamento === null): ?>

    <h1>Novo orçamento</h1>
    <?php if ($ordemServicoId === 0): ?>
        <p class="vazio">Informe a OS pela URL (?os_id=) — normalmente você chega aqui a partir da ficha da OS.</p>
    <?php else: ?>
    <div class="card">
        <p><a href="/os.php?id=<?= $ordemServicoId ?>">&larr; Voltar para a OS #<?= $ordemServicoId ?></a></p>
        <form method="post">
            <input type="hidden" name="acao" value="criar">
            <input type="hidden" name="os_id" value="<?= $ordemServicoId ?>">
            <label>Validade (dias)</label>
            <input type="number" name="validade_dias" value="7" required>
            <label>Observações</label>
            <textarea name="observacoes" rows="2"></textarea>
            <button type="submit">Criar orçamento</button>
        </form>
    </div>
    <?php endif; ?>

<?php else: ?>

    <p><a href="/os.php?id=<?= $ordemServicoId ?>">&larr; Voltar para a OS #<?= $ordemServicoId ?></a></p>

    <div class="card" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1 style="margin-bottom:4px;">Orçamento #<?= (int) $orcamento['id'] ?></h1>
            <div style="color:var(--texto-fraco); font-size:0.88rem;">
                Validade: <?= data_br($orcamento['data_validade'], false) ?>
            </div>
        </div>
        <?= status_badge($orcamento['status']) ?>
    </div>

    <div class="card">
        <h2>Itens</h2>
        <?php if (empty($orcamento['itens'])): ?>
            <p class="vazio">Nenhum item ainda.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Tipo</th><th>Descrição</th><th>Qtd.</th><th>Valor unit.</th><th>Total</th><th>Aprovado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orcamento['itens'] as $item): ?>
                <tr>
                    <td><?= e(['peca' => 'Peça', 'servico' => 'Serviço', 'mao_de_obra' => 'Mão de obra'][$item['tipo']] ?? $item['tipo']) ?></td>
                    <td><?= e($item['descricao']) ?></td>
                    <td><?= rtrim(rtrim(number_format((float) $item['quantidade'], 2, ',', '.'), '0'), ',') ?></td>
                    <td><?= moeda_br((float) $item['valor_unitario']) ?></td>
                    <td><?= moeda_br((float) $item['valor_total']) ?></td>
                    <td><?= ((int) $item['aprovado']) === 1 ? '✅' : '—' ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Remover este item?');">
                            <input type="hidden" name="acao" value="remover_item">
                            <input type="hidden" name="id" value="<?= (int) $orcamento['id'] ?>">
                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="botao-secundario" style="margin:0; padding:4px 10px;">×</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <form method="post" style="margin-top:16px;">
            <input type="hidden" name="acao" value="adicionar_item">
                            <input type="hidden" name="id" value="<?= (int) $orcamento['id'] ?>">
            <div class="grid grid-3" style="gap:10px; align-items:end;">
                <div>
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="peca">Peça</option>
                        <option value="servico">Serviço</option>
                        <option value="mao_de_obra">Mão de obra</option>
                    </select>
                </div>
                <div><label>Descrição</label><input type="text" name="descricao" required></div>
                <div><label>Quantidade</label><input type="text" name="quantidade" value="1" required></div>
            </div>
            <label>Valor unitário (R$)</label>
            <input type="text" name="valor_unitario" required placeholder="150,00">
            <button type="submit">Adicionar item</button>
        </form>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <h2>Desconto e total</h2>
            <form method="post">
                <input type="hidden" name="acao" value="desconto">
                            <input type="hidden" name="id" value="<?= (int) $orcamento['id'] ?>">
                <label>Desconto (R$)</label>
                <input type="text" name="desconto" value="<?= number_format((float) $orcamento['desconto'], 2, ',', '.') ?>">
                <button type="submit">Atualizar desconto</button>
            </form>
            <p style="margin-top:14px; font-size:1.2rem;"><strong>Total: <?= moeda_br((float) $orcamento['total']) ?></strong></p>
        </div>

        <div class="card">
            <h2>Aprovação</h2>
            <?php if ($orcamento['status'] === 'pendente' && !empty($orcamento['itens'])): ?>
            <form method="post" style="margin-bottom:14px;">
                <input type="hidden" name="acao" value="aprovar_tudo">
                            <input type="hidden" name="id" value="<?= (int) $orcamento['id'] ?>">
                <button type="submit">Aprovar tudo</button>
            </form>

            <form method="post" style="margin-bottom:14px;">
                <input type="hidden" name="acao" value="aprovar_parcial">
                            <input type="hidden" name="id" value="<?= (int) $orcamento['id'] ?>">
                <label>Aprovação parcial — marque os itens aprovados:</label>
                <?php foreach ($orcamento['itens'] as $item): ?>
                    <label style="display:flex; align-items:center; gap:6px; font-weight:normal; color:var(--texto);">
                        <input type="checkbox" name="itens_aprovados[]" value="<?= (int) $item['id'] ?>" style="width:auto;">
                        <?= e($item['descricao']) ?> — <?= moeda_br((float) $item['valor_total']) ?>
                    </label>
                <?php endforeach; ?>
                <button type="submit" class="botao-secundario">Aprovar selecionados</button>
            </form>

            <form method="post">
                <input type="hidden" name="acao" value="recusar">
                            <input type="hidden" name="id" value="<?= (int) $orcamento['id'] ?>">
                <button type="submit" class="botao-secundario">Recusar orçamento</button>
            </form>
            <?php else: ?>
                <p>Aprovado em: <?= data_br($orcamento['aprovado_em']) ?></p>
            <?php endif; ?>

            <form method="post" style="margin-top:14px;">
                <input type="hidden" name="acao" value="gerar_pdf">
                            <input type="hidden" name="id" value="<?= (int) $orcamento['id'] ?>">
                <button type="submit" class="botao-secundario">Gerar PDF do orçamento</button>
            </form>
            <?php if (!empty($orcamento['pdf_caminho'])): ?>
                <p style="color:var(--texto-fraco); font-size:0.82rem; margin-top:8px;">
                    Último PDF gerado em: <?= data_br($orcamento['atualizado_em']) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php require dirname(__DIR__) . '/resources/views/partials/footer.php'; ?>
