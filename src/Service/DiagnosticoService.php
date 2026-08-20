<?php

declare(strict_types=1);

namespace App\Service;

use App\Nlp\NlpInterface;
use App\Repository\DiagnosticoRepository;

final class DiagnosticoService
{
    public function __construct(
        private DiagnosticoRepository $diagnosticos,
        private NlpInterface $nlp
    ) {
    }

    public function registrarManual(int $ordemServicoId, string $descricao): array
    {
        $id = $this->diagnosticos->criar($ordemServicoId, $descricao, 'informado_pelo_mecanico', 'confirmado');
        return $this->diagnosticos->buscarPorId($id);
    }

    /** Consulta a IA e grava cada sugestão com status "sugerido", para o mecânico ir confirmando/descartando. */
    public function sugerirEGravar(int $ordemServicoId, string $reclamacao, array $veiculo): array
    {
        $sugestoes = $this->nlp->sugerirDiagnosticos($reclamacao, $veiculo);
        $criados = [];
        foreach ($sugestoes as $descricao) {
            $id = $this->diagnosticos->criar($ordemServicoId, $descricao, 'sugerido_por_ia', 'sugerido');
            $criados[] = $this->diagnosticos->buscarPorId($id);
        }
        return $criados;
    }

    public function listarPorOs(int $ordemServicoId): array
    {
        return $this->diagnosticos->listarPorOs($ordemServicoId);
    }

    /** Confirma/descarta o N-ésimo diagnóstico ainda "sugerido" (1-based), na ordem em que foi criado. */
    public function definirStatusPorIndice(int $ordemServicoId, int $indice1Based, string $novoStatus): ?array
    {
        $sugeridos = $this->diagnosticos->listarSugeridosPorOs($ordemServicoId);
        $posicao = $indice1Based - 1;

        if (!isset($sugeridos[$posicao])) {
            return null;
        }

        $diagnostico = $sugeridos[$posicao];
        $this->diagnosticos->atualizarStatus((int) $diagnostico['id'], $novoStatus);
        return $this->diagnosticos->buscarPorId((int) $diagnostico['id']);
    }
}
