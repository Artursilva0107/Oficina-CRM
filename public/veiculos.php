<?php

declare(strict_types=1);

require __DIR__ . '/_painel_bootstrap.php';

use App\Config\Container;

$controller = Container::veiculoController();
$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar') {
    try {
        $veiculo = $controller->cadastrar([
            'placa'            => (string) ($_POST['placa'] ?? ''),
            'modelo'           => (string) ($_POST['modelo'] ?? ''),
            'marca'            => trim((string) ($_POST['marca'] ?? '')),
            'versao'           => trim((string) ($_POST['versao'] ?? '')),
            'cor'              => trim((string) ($_POST['cor'] ?? '')),
            'ano'              => trim((string) ($_POST['ano'] ?? '')),
            'quilometragem'    => trim((string) ($_POST['quilometragem'] ?? '')),
            'combustivel'      => trim((string) ($_POST['combustivel'] ?? '')),
            'cliente_nome'     => (string) ($_POST['cliente_nome'] ?? ''),
            'cliente_telefone' => trim((string) ($_POST['cliente_telefone'] ?? '')),
        ]);
        $sucesso = "Veículo {$veiculo['placa']} cadastrado com sucesso.";
    } catch (\Throwable $e) {
        $erro = $e->getMessage();
    }
}

$termo = trim((string) ($_GET['busca'] ?? ''));
$veiculos = $controller->listar($termo);

$paginaAtiva = 'veiculos';
$titulo = 'Veículos — Oficina';

require dirname(__DIR__) . '/resources/views/partials/header.php';
?>

<h1>Veículos</h1>

<?php if ($erro !== null): ?><div class="aviso aviso-erro"><?= e($erro) ?></div><?php endif; ?>
<?php if ($sucesso !== null): ?><div class="aviso aviso-ok"><?= e($sucesso) ?></div><?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <h2>Buscar</h2>
        <form class="busca" method="get" action="/veiculos.php">
            <input type="text" name="busca" placeholder="Placa, modelo ou cliente" value="<?= e($termo) ?>">
            <button type="submit">Buscar</button>
        </form>

        <?php if (empty($veiculos)): ?>
            <p class="vazio">Nenhum veículo encontrado.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Placa</th><th>Modelo</th><th>Cliente</th></tr></thead>
            <tbody>
            <?php foreach ($veiculos as $v): ?>
                <tr>
                    <td><a href="/veiculo.php?placa=<?= urlencode($v['placa']) ?>"><?= placa_badge($v['placa']) ?></a></td>
                    <td><?= e($v['modelo']) ?><?php if (!empty($v['cor'])): ?> — <?= e($v['cor']) ?><?php endif; ?></td>
                    <td><?= e($v['cliente_nome'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Cadastrar veículo</h2>
        <p style="color:var(--texto-fraco); font-size:0.85rem;">
            Cadastro manual — em geral isso é feito pelo mecânico direto no chat.
            A placa é o identificador único e não pode se repetir.
        </p>
        <form method="post" action="/veiculos.php">
            <input type="hidden" name="acao" value="cadastrar">

            <label for="placa">Placa</label>
            <input type="text" id="placa" name="placa" required maxlength="8" placeholder="ABC1D23">

            <div class="grid grid-2" style="gap:12px;">
                <div>
                    <label for="marca">Marca</label>
                    <input type="text" id="marca" name="marca" placeholder="Renault">
                </div>
                <div>
                    <label for="modelo">Modelo</label>
                    <input type="text" id="modelo" name="modelo" required placeholder="Sandero">
                </div>
            </div>

            <label for="versao">Versão</label>
            <input type="text" id="versao" name="versao" placeholder="Zen 1.0 12V">

            <div class="grid grid-2" style="gap:12px;">
                <div>
                    <label for="cor">Cor</label>
                    <input type="text" id="cor" name="cor" placeholder="Preto">
                </div>
                <div>
                    <label for="ano">Ano</label>
                    <input type="number" id="ano" name="ano" min="1950" max="2100" placeholder="2020">
                </div>
            </div>

            <div class="grid grid-2" style="gap:12px;">
                <div>
                    <label for="quilometragem">Quilometragem</label>
                    <input type="number" id="quilometragem" name="quilometragem" placeholder="85000">
                </div>
                <div>
                    <label for="combustivel">Combustível</label>
                    <select id="combustivel" name="combustivel">
                        <option value="">—</option>
                        <option value="flex">Flex</option>
                        <option value="gasolina">Gasolina</option>
                        <option value="etanol">Etanol</option>
                        <option value="diesel">Diesel</option>
                        <option value="gnv">GNV</option>
                        <option value="eletrico">Elétrico</option>
                        <option value="hibrido">Híbrido</option>
                    </select>
                </div>
            </div>

            <label for="cliente_nome">Nome do cliente</label>
            <input type="text" id="cliente_nome" name="cliente_nome" required>

            <label for="cliente_telefone">Telefone do cliente</label>
            <input type="text" id="cliente_telefone" name="cliente_telefone" placeholder="(11) 99999-0000">

            <button type="submit">Cadastrar veículo</button>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/resources/views/partials/footer.php'; ?>
