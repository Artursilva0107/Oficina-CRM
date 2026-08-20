<?php

declare(strict_types=1);

require __DIR__ . '/_painel_bootstrap.php';

use App\Config\Container;

$controller = Container::filaController();
$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $osId = (int) ($_POST['ordem_servico_id'] ?? 0);
    $acao = (string) ($_POST['acao'] ?? '');

    try {
        if ($acao === 'prioridade' && $osId > 0) {
            $motivo = trim((string) ($_POST['motivo'] ?? ''));
            if ($motivo === '') {
                throw new \DomainException('Informe o motivo da alteração de prioridade — isso fica registrado no histórico.');
            }
            $prioridade = trim((string) ($_POST['prioridade'] ?? '')) !== '' ? (int) $_POST['prioridade'] : null;
            $controller->definirPrioridade($osId, $prioridade, $motivo, (int) $usuarioLogado['id']);
            $sucesso = "Prioridade da OS #{$osId} atualizada.";
        } elseif ($acao === 'pausar' && $osId > 0) {
            $status = (string) ($_POST['status_pausa'] ?? 'aguardando_peca');
            $motivo = trim((string) ($_POST['motivo_pausa'] ?? '')) ?: null;
            $controller->pausar($osId, $status, $motivo, (int) $usuarioLogado['id']);
            $sucesso = "OS #{$osId} pausada.";
        } elseif ($acao === 'retomar' && $osId > 0) {
            $controller->retomar($osId, 'em_servico', (int) $usuarioLogado['id']);
            $sucesso = "OS #{$osId} retomada na fila.";
        } elseif ($acao === 'avancar_status' && $osId > 0) {
            $novoStatus = (string) ($_POST['novo_status'] ?? '');
            $controller->atualizarStatus($osId, $novoStatus);
            $sucesso = "Status da OS #{$osId} atualizado.";
        }
    } catch (\Throwable $e) {
        $erro = $e->getMessage();
    }
}

$fila = $controller->fila();

$paginaAtiva = 'fila';
$titulo = 'Fila da Oficina';

require dirname(__DIR__) . '/resources/views/partials/header.php';
?>

<h1>Fila da Oficina</h1>
<p style="color:var(--texto-fraco);">
    Regra padrão: quem entrou primeiro é atendido primeiro. Uma prioridade manual é sempre uma
    <strong>exceção registrada</strong> — é preciso informar o motivo. OS pausadas (aguardando peça/aprovação)
    não ocupam a vez das próximas, mas guardam a data real de entrada.
</p>

<?php if ($erro !== null): ?><div class="aviso aviso-erro"><?= e($erro) ?></div><?php endif; ?>
<?php if ($sucesso !== null): ?><div class="aviso aviso-ok"><?= e($sucesso) ?></div><?php endif; ?>

<div class="card">
    <?php if (empty($fila)): ?>
        <p class="vazio">Nenhum veículo na fila no momento.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th><th>Placa</th><th>Veículo</th><th>Cliente</th><th>Entrada</th><th>Tempo na oficina</th>
                <th>Status</th><th>Responsável</th><th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($fila as $posicao => $os): ?>
            <tr style="<?= ((int) $os['pausada']) === 1 ? 'opacity:0.6;' : '' ?>">
                <td><?= $posicao + 1 ?><?php if ($os['prioridade_manual'] !== null): ?> ⚡<?php endif; ?></td>
                <td><a href="/os.php?id=<?= (int) $os['id'] ?>"><?= placa_badge($os['placa']) ?></a></td>
                <td><?= e(trim(($os['marca'] ?? '') . ' ' . $os['modelo'])) ?></td>
                <td><?= e($os['cliente_nome']) ?></td>
                <td><?= data_br($os['data_entrada']) ?></td>
                <td><?= tempo_na_oficina((int) $os['minutos_na_oficina']) ?></td>
                <td><?= status_badge($os['status']) ?><?php if (((int) $os['pausada']) === 1): ?><br><span class="badge badge-pausada">Pausada</span><?php endif; ?></td>
                <td><?= e($os['responsavel_nome'] ?? '—') ?></td>
                <td>
                    <details>
                        <summary style="cursor:pointer; color:var(--amber-forte);">Gerenciar</summary>
                        <div style="margin-top:10px; min-width:260px;">

                            <?php if (((int) $os['pausada']) === 1): ?>
                            <form method="post" style="margin-bottom:10px;">
                                <input type="hidden" name="acao" value="retomar">
                                <input type="hidden" name="ordem_servico_id" value="<?= (int) $os['id'] ?>">
                                <button type="submit">Retomar na fila</button>
                            </form>
                            <?php else: ?>
                            <form method="post" style="margin-bottom:10px;">
                                <input type="hidden" name="acao" value="pausar">
                                <input type="hidden" name="ordem_servico_id" value="<?= (int) $os['id'] ?>">
                                <label>Pausar como</label>
                                <select name="status_pausa">
                                    <option value="aguardando_peca">Aguardando peça</option>
                                    <option value="aguardando_aprovacao">Aguardando aprovação</option>
                                </select>
                                <label>Motivo (opcional)</label>
                                <input type="text" name="motivo_pausa">
                                <button type="submit">Pausar</button>
                            </form>
                            <?php endif; ?>

                            <form method="post" style="margin-bottom:10px;">
                                <input type="hidden" name="acao" value="avancar_status">
                                <input type="hidden" name="ordem_servico_id" value="<?= (int) $os['id'] ?>">
                                <label>Mudar status</label>
                                <select name="novo_status">
                                    <?php foreach (['recebido','em_diagnostico','em_servico','pronto'] as $st): ?>
                                        <option value="<?= $st ?>" <?= $os['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_',' ', $st))) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit">Atualizar status</button>
                            </form>

                            <form method="post">
                                <input type="hidden" name="acao" value="prioridade">
                                <input type="hidden" name="ordem_servico_id" value="<?= (int) $os['id'] ?>">
                                <label>Prioridade manual (menor = mais prioritário; vazio = automática)</label>
                                <input type="number" name="prioridade" value="<?= e((string) ($os['prioridade_manual'] ?? '')) ?>">
                                <label>Motivo (obrigatório para alterar)</label>
                                <input type="text" name="motivo" placeholder="Ex.: cliente aguardando no local">
                                <button type="submit">Salvar prioridade</button>
                            </form>
                        </div>
                    </details>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/resources/views/partials/footer.php'; ?>
