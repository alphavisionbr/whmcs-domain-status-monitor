<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Admin;

use Alphavision\DomainMonitor\Database\Schema;
use Alphavision\DomainMonitor\Dns\DnsResolver;
use Alphavision\DomainMonitor\Domain\DomainNormalizer;
use Alphavision\DomainMonitor\Repository\DomainStatusRepository;
use Alphavision\DomainMonitor\Repository\RunRepository;
use Alphavision\DomainMonitor\Repository\SettingsRepository;
use Alphavision\DomainMonitor\Repository\TargetRepository;
use Alphavision\DomainMonitor\Service\InventoryService;
use Alphavision\DomainMonitor\Service\InfrastructureTargetProvider;
use Alphavision\DomainMonitor\Service\RefreshService;
use Alphavision\DomainMonitor\Support\Csrf;
use WHMCS\Database\Capsule;

final class Controller
{
    private SettingsRepository $settings;
    private TargetRepository $targets;
    private DomainStatusRepository $statuses;
    private RunRepository $runs;
    private InventoryService $inventory;
    private InfrastructureTargetProvider $infrastructure;
    private RefreshService $refresh;

    public function __construct(private readonly array $vars)
    {
        Schema::install();
        $this->settings = new SettingsRepository();
        $this->targets = new TargetRepository();
        $this->statuses = new DomainStatusRepository();
        $this->runs = new RunRepository();
        $this->inventory = new InventoryService(new DomainNormalizer(), $this->runs);
        $this->infrastructure = new InfrastructureTargetProvider($this->settings, $this->targets, new \Alphavision\DomainMonitor\Domain\TargetNormalizer());
        $this->refresh = new RefreshService($this->statuses, $this->runs, $this->settings, $this->infrastructure, $this->inventory);
    }

    public function dispatch(): void
    {
        if (($_GET['ajax'] ?? '') === '1') {
            $this->ajax();
            return;
        }

        $settings = $this->settings->all();
        $view = new View((string) $this->vars['modulelink'], $settings, Csrf::token());

        if (($_GET['tab'] ?? 'domains') === 'diagnostic') {
            $diagnostic = $this->diagnostics();
            $view->diagnostic($diagnostic['checks'], $diagnostic['target_count']);
            return;
        }

        $filters = [
            'tracking' => trim((string) ($_GET['tracking'] ?? '')),
            'ns' => trim((string) ($_GET['ns'] ?? '')),
            'destination' => trim((string) ($_GET['destination'] ?? '')),
            'registrar' => trim((string) ($_GET['registrar'] ?? '')),
            'search' => trim((string) ($_GET['search'] ?? '')),
        ];
        $pageNumber = max(1, (int) ($_GET['p'] ?? 1));
        $result = $this->statuses->paginate($filters, $pageNumber);
        $view->domains(
            $result['rows'], $result['total'], $pageNumber, 50, $filters, $this->statuses->registrars(),
            $this->statuses->counts(), count($this->infrastructure->all()), $this->runs->running(), null
        );
    }

    private function diagnostics(): array
    {
        $settings = $this->settings->all();
        $resolverText = (string) ($settings['resolver_ips'] ?? '');
        $resolverValues = preg_split('/[\s,;]+/', $resolverText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $validResolvers = array_values(array_filter($resolverValues, static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false));
        $invalidResolvers = array_values(array_diff($resolverValues, $validResolvers));
        $resolver = new DnsResolver($validResolvers, (float) ($settings['dns_timeout'] ?? 3.0));
        $dnsTest = $resolver->query('example.com', 'A');
        $mode = $resolver->mode();

        $whmcsVersion = 'Não identificada';
        try {
            $detected = Capsule::table('tblconfiguration')->where('setting', 'Version')->value('value');
            if (is_string($detected) && $detected !== '') {
                $whmcsVersion = $detected;
            }
        } catch (\Throwable) {
        }

        $requiredTables = [Schema::STATUS, Schema::TARGETS, Schema::SETTINGS, Schema::RUNS];
        $missingTables = array_values(array_filter($requiredTables, static fn (string $table): bool => !Capsule::schema()->hasTable($table)));
        $activeDomains = Capsule::table('tbldomains')->where('status', 'Active')->count();
        $targetCount = count($this->infrastructure->all());
        $running = $this->runs->running();
        $phpCompatible = version_compare(PHP_VERSION, '8.2.0', '>=') && version_compare(PHP_VERSION, '8.4.0', '<');
        $whmcsCompatible = preg_match('/^9\.0(?:\.|$)/', $whmcsVersion) === 1;
        $resolvReadable = @is_readable('/etc/resolv.conf');
        $nativeAvailable = function_exists('dns_get_record');
        $openBasedir = trim((string) ini_get('open_basedir'));

        return [
            'target_count' => $targetCount,
            'checks' => [
            $this->diagnosticItem('Versão do WHMCS', $whmcsCompatible ? 'success' : 'warning', $whmcsVersion, $whmcsCompatible ? 'Compatível com a linha homologada 9.0.x.' : 'A versão não pertence à linha 9.0.x ou não pôde ser identificada.'),
            $this->diagnosticItem('Versão do PHP', $phpCompatible ? 'success' : 'danger', PHP_VERSION, $phpCompatible ? 'Compatível com PHP 8.2 e 8.3.' : 'Esta versão ainda não foi homologada para o módulo.'),
            $this->diagnosticItem('Biblioteca NetDNS2', class_exists(\NetDNS2\Resolver::class) ? 'success' : 'danger', class_exists(\NetDNS2\Resolver::class) ? 'Carregada' : 'Indisponível', 'Biblioteca DNS incorporada ao pacote.'),
            $this->diagnosticItem('Arquivo de resolvedores do sistema', $resolvReadable ? 'success' : ($nativeAvailable ? 'warning' : 'danger'), $resolvReadable ? 'Leitura permitida' : 'Leitura bloqueada', $resolvReadable ? '/etc/resolv.conf pode ser utilizado pelo NetDNS2.' : ($nativeAvailable ? 'O módulo usará o fallback DNS nativo do PHP.' : 'Não existe fallback disponível para substituir /etc/resolv.conf.')),
            $this->diagnosticItem('Modo de resolução DNS', $mode === 'unavailable' ? 'danger' : ($mode === 'native_system' ? 'warning' : 'success'), DnsResolver::modeLabel($mode), $mode === 'native_system' ? 'Fallback automático sem resolvedores públicos adicionados.' : 'Mecanismo que será usado na próxima consulta.'),
            $this->diagnosticItem('Teste de consulta DNS', $dnsTest['ok'] && $dnsTest['records'] !== [] ? 'success' : 'danger', $dnsTest['ok'] && $dnsTest['records'] !== [] ? 'Consulta concluída' : 'Consulta falhou', $dnsTest['ok'] && $dnsTest['records'] !== [] ? 'example.com respondeu por meio do resolvedor selecionado.' : (string) ($dnsTest['error_message'] ?: 'Nenhum registro A foi retornado para example.com.')),
            $this->diagnosticItem('Resolvedores configurados', $invalidResolvers === [] ? 'success' : 'danger', $validResolvers === [] ? 'Nenhum resolvedor explícito' : implode(', ', $validResolvers), $invalidResolvers === [] ? 'Sem configuração silenciosa de Google ou Cloudflare.' : 'Valores inválidos: ' . implode(', ', $invalidResolvers)),
            $this->diagnosticItem('Estrutura do banco', $missingTables === [] ? 'success' : 'danger', $missingTables === [] ? '4 tabelas disponíveis' : count($missingTables) . ' tabela(s) ausente(s)', $missingTables === [] ? 'Inventário, configurações, sinais e execuções disponíveis.' : 'Ausentes: ' . implode(', ', $missingTables)),
            $this->diagnosticItem('Domínios ativos no WHMCS', $activeDomains > 0 ? 'success' : 'warning', number_format((int) $activeDomains, 0, ',', '.'), $activeDomains > 0 ? 'Disponíveis para sincronização.' : 'Nenhum domínio com status Active foi encontrado.'),
            $this->diagnosticItem('Sinais de infraestrutura', $targetCount > 0 ? 'success' : 'warning', number_format($targetCount, 0, ',', '.'), $targetCount > 0 ? 'Hostnames, IPs ou redes disponíveis para comparação.' : 'Configure servidores no WHMCS ou sinais adicionais no addon.'),
            $this->diagnosticItem('Configurações do addon', $settings !== [] ? 'success' : 'danger', $settings !== [] ? 'Carregadas' : 'Indisponíveis', 'Parâmetros nativos e valores preservados.'),
            $this->diagnosticItem('Atualização da lista', $running === null ? 'success' : 'warning', $running === null ? 'Nenhuma execução pendente' : 'Execução em andamento', $running === null ? 'O módulo está livre para iniciar uma nova atualização.' : (int) $running['processed_domains'] . ' de ' . (int) $running['total_domains'] . ' domínios processados.'),
            $this->diagnosticItem('Restrição open_basedir', $openBasedir === '' ? 'success' : 'warning', $openBasedir === '' ? 'Desativada' : 'Ativa', $openBasedir === '' ? 'O PHP não restringe caminhos locais.' : $openBasedir),
            ],
        ];
    }

    private function diagnosticItem(string $label, string $state, string $value, string $detail): array
    {
        return compact('label', 'state', 'value', 'detail');
    }

    private function ajax(): void
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['csrf_token'] ?? null)) {
                throw new \RuntimeException('Requisição administrativa inválida.');
            }
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'start_refresh') {
                $data = $this->inventory->start((int) ($_SESSION['adminid'] ?? 0));
            } elseif ($action === 'process_batch') {
                $token = (string) ($_POST['run_token'] ?? '');
                if (!preg_match('/^[a-f0-9]{48}$/', $token)) throw new \RuntimeException('Identificador de execução inválido.');
                $data = $this->refresh->process($token);
            } elseif ($action === 'refresh_one') {
                $data = $this->refresh->refreshOne(max(1, (int) ($_POST['status_id'] ?? 0)));
            } else {
                throw new \RuntimeException('Ação desconhecida.');
            }
            $this->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $exception) {
            $this->json(['success' => false, 'message' => $exception->getMessage()], 400);
        }
    }

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
