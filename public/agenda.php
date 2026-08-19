<?php

declare(strict_types=1);

require __DIR__ . '/_painel_bootstrap.php';

use App\Config\Container;

$controller = Container::agendaController();
$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    try {
        if ($acao === 'agendar') {
            $controller->agendar([
                'cliente_nome'             => (string) $_POST['cliente_nome'],
                'cliente_telefone'         => trim((string) ($_POST['cliente_telefone'] ?? '')) ?: null,
                'data_hora'                => (string) $_POST['data'] . ' ' . (string) $_POST['hora'],
                'servico_motivo'           => trim((string) ($_POST['servico_motivo'] ?? '')) ?: null,
                'previsao_duracao_minutos' => trim((string) ($_POST['duracao'] ?? '')) !== '' ? (int) $_POST['duracao'] : null,
                'observacoes'              => trim((string) ($_POST['observacoes'] ?? '')) ?: null,
            ], (int) $usuarioLogado['id']);
            $sucesso = 'Agendamento criado.';
        } elseif ($acao === 'confirmar') {
            $controller->confirmar((int) $_POST['id']);
            $sucesso = 'Agendamento confirmado.';
        } elseif ($acao === 'cancelar') {
            $controller->cancelar((int) $_POST['id']);
            $sucesso = 'Agendamento cancelado.';
        } elseif ($acao === 'nao_compareceu') {
            $controller->marcarNaoCompareceu((int) $_POST['id']);
            $sucesso = 'Agendamento marcado como não compareceu.';
        } elseif ($acao === 'transformar_em_os') {
            $os = $controller->transformarEmOs((int) $_POST['id'], [
                'quilometragem_entrada' => trim((string) ($_POST['km'] ?? '')) !== '' ? (int) $_POST['km'] : null,
                'motivo'                => trim((string) ($_POST['motivo'] ?? '')) ?: null,
                'reclamacao'            => trim((string) ($_POST['reclamacao'] ?? '')) ?: null,
            ], (int) $usuarioLogado['id']);
            header("Location: /os.php?id={$os['id']}");
            exit;
        }
    } catch (\Throwable $e) {
        $erro = $e->getMessage();
    }
}

$visao = in_array($_GET['visao'] ?? '', ['dia', 'semana', 'mes'], true) ? $_GET['visao'] : 'semana';
$dataRef = !empty($_GET['data']) ? new DateTimeImmutable((string) $_GET['data']) : new DateTimeImmutable('today');
$agendamentos = $controller->visao($visao, $dataRef);

$paginaAtiva = 'agenda';
$titulo = 'Agenda — Oficina';

require dirname(__DIR__) . '/resources/views/partials/header.php';
?>

<h1>Agenda</h1>

<?php if ($erro !== null): ?><div class="aviso aviso-erro"><?= e($erro) ?></div><?php endif; ?>
<?php if ($sucesso !== null): ?><div class="aviso aviso-ok"><?= e($sucesso) ?></div><?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0;">Compromissos — <?= e(ucfirst($visao)) ?></h2>
            <form method="get" style="display:flex; gap:8px;">
                <select name="visao" onchange="this.form.submit()">
                    <option value="dia" <?= $visao === 'dia' ? 'selected' : '' ?>>Dia</option>
                    <option value="semana" <?= $visao === 'semana' ? 'selected' : '' ?>>Semana</option>
                    <option value="mes" <?= $visao === 'mes' ? 'selected' : '' ?>>Mês</option>
                </select>
                <input type="date" name="data" value="<?= $dataRef->format('Y-m-d') ?>" onchange="this.form.submit()">
            </form>
        </div>

        <?php if (empty($agendamentos)): ?>
            <p class="vazio">Nenhum agendamento neste período.</p>
        <?php else: foreach ($agendamentos as $a): ?>
            <div class="card" style="background:var(--grafite-900); margin-top:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong><?= data_br($a['data_hora']) ?></strong>
                    <?= status_badge($a['status']) ?>
                </div>
                <p style="margin:6px 0;">
                    <?= e($a['cliente_nome']) ?>
                    <?php if (!empty($a['placa'])): ?> — <?= placa_badge($a['placa']) ?> <?= e($a['modelo']) ?><?php endif; ?>
                </p>
                <?php if (!empty($a['servico_motivo'])): ?><p style="color:var(--texto-fraco);"><?= e($a['servico_motivo']) ?></p><?php endif; ?>

                <?php if (in_array($a['status'], ['agendado', 'confirmado'], true)): ?>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px;">
                    <?php if ($a['status'] === 'agendado'): ?>
                    <form method="post"><input type="hidden" name="acao" value="confirmar"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button type="submit" class="botao-secundario">Confirmar</button></form>
                    <?php endif; ?>
                    <form method="post"><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button type="submit" class="botao-secundario">Cancelar</button></form>
                    <form method="post"><input type="hidden" name="acao" value="nao_compareceu"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button type="submit" class="botao-secundario">Não compareceu</button></form>

                    <?php if (!empty($a['veiculo_id'])): ?>
                    <details>
                        <summary class="botao" style="display:inline-block; cursor:pointer;">Veículo chegou → abrir OS</summary>
                        <form method="post" style="margin-top:10px; min-width:240px;">
                            <input type="hidden" name="acao" value="transformar_em_os">
                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                            <label>Quilometragem</label>
                            <input type="number" name="km">
                            <label>Motivo da entrada</label>
                            <input type="text" name="motivo" value="<?= e($a['servico_motivo'] ?? '') ?>">
                            <label>Reclamação do cliente</label>
                            <textarea name="reclamacao" rows="2"></textarea>
                            <button type="submit">Gerar OS</button>
                        </form>
                    </details>
                    <?php else: ?>
                        <span style="color:var(--texto-fraco); font-size:0.82rem;">Cadastre o veículo para gerar a OS.</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="card">
        <h2>Novo agendamento</h2>
        <form method="post">
            <input type="hidden" name="acao" value="agendar">

            <label>Nome do cliente</label>
            <input type="text" name="cliente_nome" required>

            <label>Telefone</label>
            <input type="text" name="cliente_telefone">

            <div class="grid grid-2" style="gap:12px;">
                <div><label>Data</label><input type="date" name="data" required></div>
                <div><label>Horário</label><input type="time" name="hora" required></div>
            </div>

            <label>Serviço / motivo</label>
            <input type="text" name="servico_motivo" placeholder="Ex.: Revisão dos 30 mil km">

            <label>Previsão de duração (minutos)</label>
            <input type="number" name="duracao" placeholder="60">

            <label>Observações</label>
            <textarea name="observacoes" rows="2"></textarea>

            <button type="submit">Agendar</button>
        </form>
        <p style="color:var(--texto-fraco); font-size:0.82rem; margin-top:12px;">
            O veículo pode ser vinculado depois — na chegada, cadastre-o normalmente e associe pela placa.
        </p>
    </div>
</div>

<?php require dirname(__DIR__) . '/resources/views/partials/footer.php'; ?>
