<?php

declare(strict_types=1);

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Gera os PDFs de orçamento e recibo a partir de um HTML simples.
 * Depende do pacote "dompdf/dompdf" (ver composer.json) — rode
 * `composer install` antes de usar esta funcionalidade.
 */
final class PdfService
{
    public function __construct(
        private string $diretorioSaida,
        private string $nomeOficina = 'Oficina',
        private ?string $logoCaminho = null
    ) {
    }

    /**
     * @param array $orcamento  Inclui 'itens' (ver OrcamentoService::buscarPorId)
     */
    public function gerarOrcamentoPdf(array $orcamento, array $os, array $veiculo, array $cliente): string
    {
        $html = $this->renderizarOrcamento($orcamento, $os, $veiculo, $cliente);
        $caminho = $this->caminhoArquivo('orcamento', (int) $orcamento['id']);
        $this->renderizarPdf($html, $caminho);
        return $caminho;
    }

    public function gerarReciboPdf(array $os, array $veiculo, array $cliente, array $servicos): string
    {
        $html = $this->renderizarRecibo($os, $veiculo, $cliente, $servicos);
        $caminho = $this->caminhoArquivo('recibo', (int) $os['id']);
        $this->renderizarPdf($html, $caminho);
        return $caminho;
    }

    private function renderizarPdf(string $html, string $caminhoDestino): void
    {
        if (!class_exists(Dompdf::class)) {
            throw new \RuntimeException(
                'A biblioteca dompdf/dompdf não está instalada. Rode "composer install" no servidor.'
            );
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $diretorio = dirname($caminhoDestino);
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0775, true);
        }

        file_put_contents($caminhoDestino, $dompdf->output());
    }

    private function caminhoArquivo(string $prefixo, int $id): string
    {
        return rtrim($this->diretorioSaida, '/') . "/{$prefixo}_{$id}_" . date('YmdHis') . '.pdf';
    }

    private function cabecalho(): string
    {
        $nome = htmlspecialchars($this->nomeOficina, ENT_QUOTES, 'UTF-8');
        return <<<HTML
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #1c1c1c; padding-bottom:10px; margin-bottom:16px;">
                <div style="font-size:20px; font-weight:bold;">{$nome}</div>
                <div style="font-size:11px; color:#555;">Documento gerado em {$this->dataHoraAtual()}</div>
            </div>
        HTML;
    }

    private function dataHoraAtual(): string
    {
        return (new \DateTimeImmutable('now'))->format('d/m/Y H:i');
    }

    private function renderizarOrcamento(array $orcamento, array $os, array $veiculo, array $cliente): string
    {
        $linhasItens = '';
        $rotulos = ['peca' => 'Peça', 'servico' => 'Serviço', 'mao_de_obra' => 'Mão de obra'];
        foreach ($orcamento['itens'] as $item) {
            $tipo = htmlspecialchars($rotulos[$item['tipo']] ?? $item['tipo'], ENT_QUOTES, 'UTF-8');
            $descricao = htmlspecialchars($item['descricao'], ENT_QUOTES, 'UTF-8');
            $linhasItens .= "<tr>
                <td>{$tipo}</td>
                <td>{$descricao}</td>
                <td style='text-align:center;'>{$item['quantidade']}</td>
                <td style='text-align:right;'>R$ " . number_format((float) $item['valor_unitario'], 2, ',', '.') . "</td>
                <td style='text-align:right;'>R$ " . number_format((float) $item['valor_total'], 2, ',', '.') . "</td>
            </tr>";
        }

        $cabecalho = $this->cabecalho();
        $clienteNome = htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8');
        $veiculoInfo = htmlspecialchars(trim(($veiculo['marca'] ?? '') . ' ' . $veiculo['modelo']), ENT_QUOTES, 'UTF-8');
        $placa = htmlspecialchars($veiculo['placa'], ENT_QUOTES, 'UTF-8');
        $validade = $orcamento['data_validade'] !== null
            ? (new \DateTimeImmutable($orcamento['data_validade']))->format('d/m/Y')
            : '—';

        return <<<HTML
        <html><head><meta charset="UTF-8"><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1c1c1c; }
            table { width: 100%; border-collapse: collapse; margin-top: 14px; }
            th, td { border-bottom: 1px solid #ddd; padding: 6px 8px; font-size: 12px; }
            th { text-align: left; background: #f2f2f2; }
            .totais td { border: none; font-weight: bold; }
            .info p { margin: 2px 0; }
        </style></head><body>
            {$cabecalho}
            <h2 style="margin-bottom:4px;">Orçamento #{$orcamento['id']} — OS #{$os['id']}</h2>
            <div class="info">
                <p><strong>Cliente:</strong> {$clienteNome}</p>
                <p><strong>Veículo:</strong> {$veiculoInfo} — Placa: {$placa}</p>
                <p><strong>Validade:</strong> {$validade}</p>
            </div>
            <table>
                <thead><tr><th>Tipo</th><th>Descrição</th><th>Qtd.</th><th>Valor unit.</th><th>Total</th></tr></thead>
                <tbody>{$linhasItens}</tbody>
                <tfoot>
                    <tr class="totais"><td colspan="4" style="text-align:right;">Desconto</td><td style="text-align:right;">R$ {$this->formatarMoeda($orcamento['desconto'])}</td></tr>
                    <tr class="totais"><td colspan="4" style="text-align:right;">Total</td><td style="text-align:right;">R$ {$this->formatarMoeda($orcamento['total'])}</td></tr>
                </tfoot>
            </table>
            <p style="margin-top:20px; font-size:11px; color:#555;">Este orçamento é uma estimativa e pode sofrer alterações após diagnóstico completo do veículo.</p>
        </body></html>
        HTML;
    }

    private function renderizarRecibo(array $os, array $veiculo, array $cliente, array $servicos): string
    {
        $cabecalho = $this->cabecalho();
        $clienteNome = htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8');
        $veiculoInfo = htmlspecialchars(trim(($veiculo['marca'] ?? '') . ' ' . $veiculo['modelo']), ENT_QUOTES, 'UTF-8');
        $placa = htmlspecialchars($veiculo['placa'], ENT_QUOTES, 'UTF-8');

        $linhasServicos = '';
        foreach ($servicos as $s) {
            $descricao = htmlspecialchars($s['descricao'], ENT_QUOTES, 'UTF-8');
            $linhasServicos .= "<li>{$descricao}</li>";
        }

        $dataEntrada = (new \DateTimeImmutable($os['data_entrada']))->format('d/m/Y H:i');
        $dataSaida = $os['data_saida'] !== null ? (new \DateTimeImmutable($os['data_saida']))->format('d/m/Y H:i') : '—';
        $limiteRetorno = $os['data_limite_retorno'] !== null ? (new \DateTimeImmutable($os['data_limite_retorno']))->format('d/m/Y') : '—';
        $valorFinal = $os['valor_final'] !== null ? 'R$ ' . $this->formatarMoeda((float) $os['valor_final']) : '—';
        $formaPagamento = htmlspecialchars($os['forma_pagamento'] ?? '—', ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <html><head><meta charset="UTF-8"><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1c1c1c; }
            .info p { margin: 3px 0; }
            ul { margin: 6px 0; padding-left: 18px; }
        </style></head><body>
            {$cabecalho}
            <h2 style="margin-bottom:4px;">Recibo — OS #{$os['id']}</h2>
            <div class="info">
                <p><strong>Cliente:</strong> {$clienteNome}</p>
                <p><strong>Veículo:</strong> {$veiculoInfo} — Placa: {$placa}</p>
                <p><strong>Entrada:</strong> {$dataEntrada} &nbsp; <strong>Saída:</strong> {$dataSaida}</p>
                <p><strong>Serviços realizados:</strong></p>
                <ul>{$linhasServicos}</ul>
                <p><strong>Valor:</strong> {$valorFinal} &nbsp; <strong>Forma de pagamento:</strong> {$formaPagamento}</p>
                <p><strong>Prazo para retorno gratuito:</strong> {$limiteRetorno}</p>
            </div>
            <p style="margin-top:24px; font-size:11px; color:#555;">Guarde este recibo — ele é necessário para acionar a garantia dentro do prazo indicado.</p>
        </body></html>
        HTML;
    }

    private function formatarMoeda(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }
}
