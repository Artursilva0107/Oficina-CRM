<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(?string $valor): string
    {
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('placa_badge')) {
    function placa_badge(string $placa): string
    {
        return '<span class="placa"><span class="faixa">BRASIL</span><span class="numero">' . e($placa) . '</span></span>';
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        $rotulos = [
            'recebido'              => 'Recebido',
            'em_diagnostico'        => 'Em diagnóstico',
            'aguardando_aprovacao'  => 'Aguardando aprovação',
            'aguardando_peca'       => 'Aguardando peça',
            'em_servico'            => 'Em serviço',
            'pronto'                => 'Pronto',
            'entregue'              => 'Entregue',
            'agendado'              => 'Agendado',
            'confirmado'            => 'Confirmado',
            'chegou'                => 'Chegou',
            'cancelado'             => 'Cancelado',
            'nao_compareceu'        => 'Não compareceu',
            'pendente'              => 'Pendente',
            'aprovado'              => 'Aprovado',
            'parcialmente_aprovado' => 'Parcialmente aprovado',
            'recusado'              => 'Recusado',
        ];
        $rotulo = $rotulos[$status] ?? $status;
        return '<span class="badge badge-' . e($status) . '">' . e($rotulo) . '</span>';
    }
}

if (!function_exists('moeda_br')) {
    function moeda_br(?float $valor): string
    {
        if ($valor === null) {
            return '—';
        }
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
}

if (!function_exists('tempo_na_oficina')) {
    function tempo_na_oficina(int $minutos): string
    {
        if ($minutos < 60) {
            return "{$minutos} min";
        }
        $horas = intdiv($minutos, 60);
        if ($horas < 24) {
            return "{$horas}h";
        }
        $dias = intdiv($horas, 24);
        $horasRestantes = $horas % 24;
        return $horasRestantes > 0 ? "{$dias}d {$horasRestantes}h" : "{$dias}d";
    }
}

if (!function_exists('data_br')) {
    function data_br(?string $dataIso, bool $comHora = true): string
    {
        if ($dataIso === null || $dataIso === '') {
            return '—';
        }
        $formato = $comHora ? 'd/m/Y H:i' : 'd/m/Y';
        return (new DateTimeImmutable($dataIso))->format($formato);
    }
}
