<?php

declare(strict_types=1);

namespace App\Chat;

/**
 * Lista fechada de intenções que o módulo de NLP pode identificar.
 * Mantida como classe de constantes (em vez de enum nativo) para
 * compatibilidade ampla e fácil serialização em logs/JSON.
 */
final class Intencao
{
    public const CADASTRAR_VEICULO      = 'cadastrar_veiculo';
    public const ABRIR_OS               = 'abrir_os';
    public const CONSULTAR_HISTORICO    = 'consultar_historico';
    public const REGISTRAR_DIAGNOSTICO  = 'registrar_diagnostico';
    public const CONFIRMAR_DIAGNOSTICO  = 'confirmar_diagnostico';
    public const DESCARTAR_DIAGNOSTICO  = 'descartar_diagnostico';
    public const REGISTRAR_SERVICO      = 'registrar_servico';
    public const REGISTRAR_PECA         = 'registrar_peca';
    public const REGISTRAR_GARANTIA     = 'registrar_garantia';
    public const MARCAR_ENTREGUE        = 'marcar_entregue';
    public const AJUDA                  = 'ajuda';
    public const NAO_RECONHECIDA        = 'nao_reconhecida';

    public static function todas(): array
    {
        return [
            self::CADASTRAR_VEICULO,
            self::ABRIR_OS,
            self::CONSULTAR_HISTORICO,
            self::REGISTRAR_DIAGNOSTICO,
            self::CONFIRMAR_DIAGNOSTICO,
            self::DESCARTAR_DIAGNOSTICO,
            self::REGISTRAR_SERVICO,
            self::REGISTRAR_PECA,
            self::REGISTRAR_GARANTIA,
            self::MARCAR_ENTREGUE,
            self::AJUDA,
            self::NAO_RECONHECIDA,
        ];
    }
}
