<?php

declare(strict_types=1);

namespace App\Config;

use App\Auth\AuthService;
use App\Chat\ChatOrchestrator;
use App\Controller\AgendaController;
use App\Controller\DashboardController;
use App\Controller\FilaController;
use App\Controller\OrcamentoController;
use App\Controller\OrdemServicoController;
use App\Controller\VeiculoController;
use App\Nlp\ClaudeNlpAdapter;
use App\Nlp\NlpInterface;
use App\Repository\AgendamentoRepository;
use App\Repository\AnexoRepository;
use App\Repository\ClienteRepository;
use App\Repository\ConversaContextoRepository;
use App\Repository\DiagnosticoRepository;
use App\Repository\FilaLogRepository;
use App\Repository\GarantiaRepository;
use App\Repository\InteracaoChatRepository;
use App\Repository\OrcamentoItemRepository;
use App\Repository\OrcamentoRepository;
use App\Repository\OrdemServicoRepository;
use App\Repository\PecaRepository;
use App\Repository\ReciboRepository;
use App\Repository\ServicoRepository;
use App\Repository\UsuarioRepository;
use App\Repository\VeiculoRepository;
use App\Service\AgendaService;
use App\Service\ClienteService;
use App\Service\DiagnosticoService;
use App\Service\GarantiaService;
use App\Service\OrcamentoService;
use App\Service\OrdemServicoService;
use App\Service\PdfService;
use App\Service\TranscricaoService;
use App\Service\VeiculoService;
use App\Telegram\TelegramClient;
use PDO;

/**
 * Container bem simples (sem "mágica"): monta e devolve os objetos já
 * conectados entre si. Suficiente para o tamanho deste projeto — evita
 * trazer uma dependência de DI container externo.
 */
final class Container
{
    private static array $instancias = [];

    public static function db(): PDO
    {
        return Database::connection();
    }

    // ---- Repositórios --------------------------------------------------

    public static function clienteRepository(): ClienteRepository
    {
        return self::$instancias[ClienteRepository::class] ??= new ClienteRepository(self::db());
    }

    public static function veiculoRepository(): VeiculoRepository
    {
        return self::$instancias[VeiculoRepository::class] ??= new VeiculoRepository(self::db());
    }

    public static function ordemServicoRepository(): OrdemServicoRepository
    {
        return self::$instancias[OrdemServicoRepository::class] ??= new OrdemServicoRepository(self::db());
    }

    public static function diagnosticoRepository(): DiagnosticoRepository
    {
        return self::$instancias[DiagnosticoRepository::class] ??= new DiagnosticoRepository(self::db());
    }

    public static function servicoRepository(): ServicoRepository
    {
        return self::$instancias[ServicoRepository::class] ??= new ServicoRepository(self::db());
    }

    public static function pecaRepository(): PecaRepository
    {
        return self::$instancias[PecaRepository::class] ??= new PecaRepository(self::db());
    }

    public static function garantiaRepository(): GarantiaRepository
    {
        return self::$instancias[GarantiaRepository::class] ??= new GarantiaRepository(self::db());
    }

    public static function anexoRepository(): AnexoRepository
    {
        return self::$instancias[AnexoRepository::class] ??= new AnexoRepository(self::db());
    }

    public static function usuarioRepository(): UsuarioRepository
    {
        return self::$instancias[UsuarioRepository::class] ??= new UsuarioRepository(self::db());
    }

    public static function interacaoChatRepository(): InteracaoChatRepository
    {
        return self::$instancias[InteracaoChatRepository::class] ??= new InteracaoChatRepository(self::db());
    }

    public static function conversaContextoRepository(): ConversaContextoRepository
    {
        return self::$instancias[ConversaContextoRepository::class] ??= new ConversaContextoRepository(self::db());
    }

    public static function filaLogRepository(): FilaLogRepository
    {
        return self::$instancias[FilaLogRepository::class] ??= new FilaLogRepository(self::db());
    }

    public static function agendamentoRepository(): AgendamentoRepository
    {
        return self::$instancias[AgendamentoRepository::class] ??= new AgendamentoRepository(self::db());
    }

    public static function orcamentoRepository(): OrcamentoRepository
    {
        return self::$instancias[OrcamentoRepository::class] ??= new OrcamentoRepository(self::db());
    }

    public static function orcamentoItemRepository(): OrcamentoItemRepository
    {
        return self::$instancias[OrcamentoItemRepository::class] ??= new OrcamentoItemRepository(self::db());
    }

    public static function reciboRepository(): ReciboRepository
    {
        return self::$instancias[ReciboRepository::class] ??= new ReciboRepository(self::db());
    }

    // ---- Integrações externas ------------------------------------------

    public static function nlp(): NlpInterface
    {
        return self::$instancias[NlpInterface::class] ??= new ClaudeNlpAdapter(
            Env::required('NLP_API_KEY'),
            Env::get('NLP_API_URL', 'https://api.anthropic.com/v1/messages'),
            Env::get('NLP_MODEL', 'claude-sonnet-4-6')
        );
    }

    public static function transcricaoService(): TranscricaoService
    {
        return self::$instancias[TranscricaoService::class] ??= new TranscricaoService(
            Env::required('WHISPER_API_KEY'),
            Env::get('WHISPER_API_URL', 'https://api.openai.com/v1/audio/transcriptions'),
            Env::get('WHISPER_MODEL', 'whisper-1')
        );
    }

    public static function telegramClient(): TelegramClient
    {
        return self::$instancias[TelegramClient::class] ??= new TelegramClient(Env::required('TELEGRAM_BOT_TOKEN'));
    }

    public static function pdfService(): PdfService
    {
        return self::$instancias[PdfService::class] ??= new PdfService(
            APP_BASE_PATH . '/storage/uploads/pdf',
            Env::get('APP_NOME_OFICINA', 'Oficina')
        );
    }

    // ---- Services --------------------------------------------------------

    public static function clienteService(): ClienteService
    {
        return self::$instancias[ClienteService::class] ??= new ClienteService(self::clienteRepository());
    }

    public static function veiculoService(): VeiculoService
    {
        return self::$instancias[VeiculoService::class] ??= new VeiculoService(
            self::veiculoRepository(),
            self::clienteRepository()
        );
    }

    public static function ordemServicoService(): OrdemServicoService
    {
        return self::$instancias[OrdemServicoService::class] ??= new OrdemServicoService(
            self::ordemServicoRepository(),
            self::veiculoRepository(),
            self::servicoRepository(),
            self::pecaRepository(),
            self::anexoRepository(),
            self::filaLogRepository()
        );
    }

    public static function diagnosticoService(): DiagnosticoService
    {
        return self::$instancias[DiagnosticoService::class] ??= new DiagnosticoService(
            self::diagnosticoRepository(),
            self::nlp()
        );
    }

    public static function garantiaService(): GarantiaService
    {
        return self::$instancias[GarantiaService::class] ??= new GarantiaService(
            self::garantiaRepository(),
            self::ordemServicoRepository()
        );
    }

    public static function agendaService(): AgendaService
    {
        return self::$instancias[AgendaService::class] ??= new AgendaService(
            self::agendamentoRepository(),
            self::clienteRepository()
        );
    }

    public static function orcamentoService(): OrcamentoService
    {
        return self::$instancias[OrcamentoService::class] ??= new OrcamentoService(
            self::orcamentoRepository(),
            self::orcamentoItemRepository()
        );
    }

    public static function chatOrchestrator(): ChatOrchestrator
    {
        return self::$instancias[ChatOrchestrator::class] ??= new ChatOrchestrator(
            self::nlp(),
            self::veiculoService(),
            self::ordemServicoService(),
            self::diagnosticoService(),
            self::garantiaService(),
            self::conversaContextoRepository(),
            self::interacaoChatRepository()
        );
    }

    public static function authService(): AuthService
    {
        return self::$instancias[AuthService::class] ??= new AuthService(self::usuarioRepository());
    }

    // ---- Controllers -------------------------------------------------------

    public static function dashboardController(): DashboardController
    {
        return self::$instancias[DashboardController::class] ??= new DashboardController(
            self::ordemServicoService(),
            self::garantiaService()
        );
    }

    public static function veiculoController(): VeiculoController
    {
        return self::$instancias[VeiculoController::class] ??= new VeiculoController(
            self::veiculoService(),
            self::ordemServicoService(),
            self::garantiaService()
        );
    }

    public static function ordemServicoController(): OrdemServicoController
    {
        return self::$instancias[OrdemServicoController::class] ??= new OrdemServicoController(
            self::ordemServicoService(),
            self::diagnosticoService(),
            self::garantiaService(),
            self::veiculoRepository(),
            self::clienteRepository(),
            self::servicoRepository(),
            self::pdfService(),
            self::reciboRepository()
        );
    }

    public static function agendaController(): AgendaController
    {
        return self::$instancias[AgendaController::class] ??= new AgendaController(
            self::agendaService(),
            self::ordemServicoService(),
            self::veiculoService()
        );
    }

    public static function filaController(): FilaController
    {
        return self::$instancias[FilaController::class] ??= new FilaController(self::ordemServicoService());
    }

    public static function orcamentoController(): OrcamentoController
    {
        return self::$instancias[OrcamentoController::class] ??= new OrcamentoController(
            self::orcamentoService(),
            self::ordemServicoService(),
            self::veiculoRepository(),
            self::clienteRepository(),
            self::pdfService()
        );
    }
}
