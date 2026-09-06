<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Admin;

use Alphavision\DomainMonitor\Support\Json;

final class View
{
    private string $name;

    public function __construct(
        private readonly string $moduleLink,
        private readonly array $settings,
        private readonly string $csrf
    ) {
        $name = trim((string) ($settings['infrastructure_name'] ?? ''));
        $this->name = $name === '' ? 'Empresa' : $name;
    }

    public function domains(
        array $rows,
        int $total,
        int $page,
        int $perPage,
        array $filters,
        array $registrars,
        array $counts,
        int $targetCount,
        ?array $running,
        ?array $message
    ): void {
        $this->start('domains', $message);

        echo '<div class="av-stats">';
        $this->stat('Domínios monitorados', (int) $counts['total'], 'Registros ativos sincronizados');
        $this->stat('DNS ' . $this->name, (int) $counts['managed_dns'], 'Nameservers reconhecidos');
        $this->stat('Destino ' . $this->name, (int) $counts['managed_destination'], 'Raiz ou www na infraestrutura');
        $this->stat('Pendentes', (int) $counts['pending'], 'Aguardando nova sincronização');
        echo '</div>';

        if ($targetCount === 0) {
            echo '<div class="alert alert-warning">Nenhum sinal de infraestrutura foi encontrado. Ative a leitura dos servidores do WHMCS ou informe hostnames, IPs e redes adicionais na configuração do addon.</div>';
        }

        $this->progress($running);

        echo '<section class="av-card"><div class="av-card-head"><div><p class="av-kicker">Consulta operacional</p><h3>Status dos domínios</h3></div>';
        echo '<button id="avdm-refresh-all" class="btn btn-primary" type="button">' . ($running ? 'Continuar atualização' : 'Atualizar lista') . '</button></div>';
        $this->filters($filters, $registrars);
        echo '<div class="table-responsive"><table class="table table-hover av-table"><thead><tr>';
        echo '<th>Domínio</th><th>Registrador</th><th>Nameservers</th><th>Destino</th><th>Estado consolidado</th><th>Última verificação</th><th>Ação</th>';
        echo '</tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="7" class="av-empty">Nenhum domínio encontrado. Clique em Atualizar lista para sincronizar.</td></tr>';
        }
        foreach ($rows as $row) {
            $this->domainRow($row);
        }
        echo '</tbody></table></div>';
        $this->pagination($total, $page, $perPage, $filters);
        echo '</section>';

        $this->contribution();
        $this->refreshScript($running['run_token'] ?? null);
        echo '</div>';
    }

    public function diagnostic(array $checks, int $targetCount): void
    {
        $this->start('diagnostic', null);
        $this->configurationCard($targetCount);
        echo '<section class="av-card"><div class="av-card-head"><div><p class="av-kicker">Verificação do ambiente</p><h3>Diagnóstico</h3></div>';
        echo '<span class="av-help" title="Os itens refletem o ambiente atual e não alteram configurações.">?</span></div>';
        echo '<div class="av-diagnostic">';
        foreach ($checks as $check) {
            $state = in_array($check['state'], ['success', 'warning', 'danger'], true) ? $check['state'] : 'danger';
            $icon = match ($state) {
                'success' => '✓',
                'warning' => '!',
                default => '×',
            };
            echo '<div class="av-check"><span class="av-check-icon is-' . $state . '">' . $icon . '</span><div><strong>' . $this->e($check['label']) . '</strong><small>' . $this->e($check['detail']) . '</small></div>';
            echo '<span class="av-badge is-' . $state . '">' . $this->e($check['value']) . '</span></div>';
        }
        echo '</div><p class="av-diagnostic-note text-muted">O diagnóstico é somente leitura. Ajustes continuam disponíveis exclusivamente na configuração nativa do addon.</p></section>';
        $this->contribution();
        echo '</div>';
    }

    private function start(string $active, ?array $message): void
    {
        $assetBase = '../modules/addons/alphavision_domain_status_monitor/assets/';
        echo '<link rel="stylesheet" href="' . $this->e($assetBase . 'css/admin.css?v=1.0.4') . '">';
        echo '<div class="av-admin"><header class="av-header"><div><p class="av-eyebrow">Alphavision WHMCS</p>';
        echo '<h2>Status de Domínios <span>v1.0.4</span></h2>';
        echo '<p>Consulta DNS manual dos domínios ativos cadastrados no WHMCS.</p></div>';
        echo '<div class="av-header-status"><span class="av-dot"></span>Módulo ativo</div></header>';
        echo '<nav class="av-tabs" aria-label="Navegação do módulo">';
        foreach (['domains' => 'Domínios', 'diagnostic' => 'Diagnóstico'] as $tab => $label) {
            echo '<a class="av-tab' . ($active === $tab ? ' is-active' : '') . '" href="' . $this->e($this->moduleLink . '&tab=' . $tab) . '">' . $this->e($label) . '</a>';
        }
        echo '</nav>';
        if ($message !== null) {
            echo '<div class="alert alert-' . $this->e($message['type']) . '">' . $this->e($message['text']) . '</div>';
        }
    }

    private function stat(string $label, int $value, string $description): void
    {
        echo '<div class="av-stat"><span>' . number_format($value, 0, ',', '.') . '</span><strong>' . $this->e($label) . '</strong><small>' . $this->e($description) . '</small></div>';
    }

    private function setting(string $label, string $value): void
    {
        echo '<div class="av-setting-row"><strong>' . $this->e($label) . '</strong><span class="av-badge is-muted">' . $this->e($value) . '</span></div>';
    }

    private function configurationCard(int $targetCount): void
    {
        echo '<section class="av-card av-config-card"><div class="av-card-head"><div><p class="av-kicker">Configuração ativa</p><h3>Critérios de comparação</h3></div>';
        echo '<a class="btn btn-default btn-sm" href="configaddonmods.php">Configurar módulo</a></div>';
        echo '<div class="av-setting-list">';
        $this->setting('Servidores habilitados do WHMCS', $this->enabled((string) ($this->settings['use_whmcs_servers'] ?? 'on')) ? 'Ativo' : 'Inativo');
        $this->setting('Sinais de infraestrutura reconhecidos', number_format($targetCount, 0, ',', '.'));
        $this->setting('Consultas antes da remoção', (string) max(1, (int) ($this->settings['inactive_checks_before_removal'] ?? 2)));
        echo '</div></section>';
    }

    private function progress(?array $running): void
    {
        $processed = (int) ($running['processed_domains'] ?? 0);
        $total = (int) ($running['total_domains'] ?? 0);
        $percent = $total > 0 ? min(100, (int) floor($processed * 100 / $total)) : 0;
        echo '<div id="avdm-progress" class="av-progress"' . ($running ? '' : ' style="display:none"') . '>';
        echo '<div class="av-progress-head"><strong>Atualizando status DNS</strong><span id="avdm-progress-text">' . $processed . ' de ' . $total . ' domínios verificados</span></div>';
        echo '<div class="progress"><div id="avdm-progress-bar" class="progress-bar" style="width:' . $percent . '%"></div></div></div>';
    }

    private function filters(array $filters, array $registrars): void
    {
        echo '<form method="get" class="av-filters"><input type="hidden" name="module" value="alphavision_domain_status_monitor">';
        echo '<input type="search" name="search" value="' . $this->e($filters['search']) . '" placeholder="Domínio ou cliente">';
        $this->select('tracking', ['' => 'Todos os registros', 'active' => 'Ativos', 'pending_recheck' => 'Pendentes'], $filters['tracking']);
        $this->select('ns', ['' => 'Todos os DNS', 'managed' => 'DNS ' . $this->name, 'external' => 'DNS externo', 'no_delegation' => 'Sem delegação', 'inconsistent' => 'Inconsistente', 'query_error' => 'Erro'], $filters['ns']);
        $this->select('destination', ['' => 'Todos os destinos', 'managed' => 'Destino ' . $this->name, 'external' => 'Destino externo', 'no_resolution' => 'Sem resolução', 'partial' => 'Resolução parcial', 'inconsistent' => 'Inconsistente', 'query_error' => 'Erro'], $filters['destination']);
        $options = ['' => 'Todos os registradores'];
        foreach ($registrars as $registrar) {
            $options[$registrar] = $registrar;
        }
        $this->select('registrar', $options, $filters['registrar']);
        echo '<button class="btn btn-default" type="submit">Filtrar</button>';
        echo '<a class="btn btn-default" href="' . $this->e($this->moduleLink) . '">Limpar</a></form>';
    }

    private function domainRow(array $row): void
    {
        $pending = $row['tracking_status'] === 'pending_recheck';
        $client = trim((string) ($row['companyname'] ?: trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''))));
        $nameservers = Json::decode($row['nameservers_json'] ?? null);
        $addresses = array_merge(Json::decode($row['ipv4_json'] ?? null), Json::decode($row['ipv6_json'] ?? null));

        echo '<tr class="' . ($pending ? 'av-pending-row' : '') . '"><td><strong>' . $this->e($row['domain_name']) . '</strong> <span class="av-record-id">(' . (int) $row['domain_id'] . ')</span>';
        if ($client !== '') {
            echo '<small>' . $this->e($client) . ' (' . (int) $row['client_id'] . ')</small>';
        }
        if ($pending) {
            echo '<span class="av-badge is-warning">Pendente de nova verificação</span><small>Status no WHMCS: ' . $this->e($row['whmcs_status']) . ' | Consultas: ' . (int) $row['inactive_check_count'] . ' de ' . max(1, (int) ($this->settings['inactive_checks_before_removal'] ?? 2)) . '</small>';
        }
        echo '</td><td>' . $this->e($row['registrar'] ?: 'Não informado') . '</td>';
        echo '<td><span class="av-badge ' . Labels::badgeClass($row['ns_status']) . '">' . $this->e(Labels::ns($row['ns_status'], $this->name)) . '</span><small>' . $this->e(implode(', ', $nameservers)) . '</small></td>';
        echo '<td><span class="av-badge ' . Labels::badgeClass($row['destination_status']) . '">' . $this->e(Labels::destination($row['destination_status'], $this->name)) . '</span><small>' . $this->e(implode(', ', $addresses)) . '</small></td>';
        echo '<td><span class="av-badge ' . Labels::badgeClass($row['consolidated_status']) . '">' . $this->e(Labels::consolidated($row['consolidated_status'], $this->name)) . '</span>';
        if ($row['error_message']) {
            echo '<small class="text-danger">' . $this->e($row['error_message']) . '</small>';
        }
        echo '</td><td class="av-col-datetime">' . $this->e($this->formatDate($row['checked_at'] ?? null)) . '</td><td>';
        if (!$pending) {
            echo '<button type="button" class="btn btn-xs btn-default avdm-refresh-one" data-id="' . (int) $row['id'] . '">Atualizar</button>';
        }
        echo '</td></tr>';
    }

    private function select(string $name, array $options, string $selected): void
    {
        echo '<select name="' . $this->e($name) . '">';
        foreach ($options as $value => $label) {
            echo '<option value="' . $this->e((string) $value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>' . $this->e($label) . '</option>';
        }
        echo '</select>';
    }

    private function pagination(int $total, int $page, int $perPage, array $filters): void
    {
        $pages = (int) ceil($total / $perPage);
        if ($pages <= 1) {
            return;
        }
        echo '<div class="av-pagination">';
        if ($page > 1) {
            echo '<a class="btn btn-default btn-sm" href="' . $this->pageUrl($page - 1, $filters) . '">Anterior</a>';
        }
        echo '<span>Página ' . $page . ' de ' . $pages . '</span>';
        if ($page < $pages) {
            echo '<a class="btn btn-default btn-sm" href="' . $this->pageUrl($page + 1, $filters) . '">Próxima</a>';
        }
        echo '</div>';
    }

    private function pageUrl(int $page, array $filters): string
    {
        $query = array_filter(array_merge(['module' => 'alphavision_domain_status_monitor', 'p' => $page], $filters), static fn ($value): bool => $value !== '');
        return 'addonmodules.php?' . $this->e(http_build_query($query));
    }

    private function contribution(): void
    {
        $projectUrl = 'https://alphavision.com.br/whmcs';
        $reportUrl = 'mailto:contato@alphavision.com.br?subject=Relato%20sobre%20o%20Alphavision%20WHMCS%20Domain%20Status%20Monitor';
        echo '<section class="av-card av-contribution"><div><p class="av-kicker">Projeto gratuito</p>';
        echo '<h3>Apoie o desenvolvimento</h3><p>Este módulo é disponibilizado gratuitamente pela Alphavision®. Contribuições ajudam a manter testes de compatibilidade, correções e novas melhorias.</p></div>';
        echo '<div class="av-contribution-actions">';
        echo '<button type="button" class="btn btn-primary" disabled title="O canal de contribuição será disponibilizado em breve.">Fazer uma contribuição</button>';
        echo '<a class="btn btn-default" target="_blank" rel="noopener" href="' . $this->e($projectUrl) . '">Página do projeto</a>';
        echo '<a class="btn btn-default" href="' . $this->e($reportUrl) . '">Reportar um problema</a>';
        echo '</div></section>';
    }

    private function refreshScript(?string $runningToken): void
    {
        $url = json_encode($this->moduleLink . '&ajax=1', JSON_UNESCAPED_SLASHES);
        $csrf = json_encode($this->csrf);
        $running = json_encode($runningToken);
        echo <<<HTML
<script>
(function () {
    const endpoint = {$url};
    const csrf = {$csrf};
    let runToken = {$running};
    const button = document.getElementById('avdm-refresh-all');
    const box = document.getElementById('avdm-progress');
    const bar = document.getElementById('avdm-progress-bar');
    const text = document.getElementById('avdm-progress-text');

    async function request(data) {
        data.csrf_token = csrf;
        const response = await fetch(endpoint, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: new URLSearchParams(data)});
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.message || 'Falha na atualização.');
        return payload.data;
    }

    async function process() {
        const data = await request({action: 'process_batch', run_token: runToken});
        box.style.display = '';
        const percent = data.total > 0 ? Math.min(100, Math.floor(data.processed * 100 / data.total)) : 100;
        bar.style.width = percent + '%';
        text.textContent = data.processed + ' de ' + data.total + ' domínios verificados';
        if (data.complete) {
            window.location.reload();
            return;
        }
        await process();
    }

    button.addEventListener('click', async function () {
        button.disabled = true;
        try {
            if (!runToken) {
                const run = await request({action: 'start_refresh'});
                runToken = run.run_token;
            }
            await process();
        } catch (error) {
            button.disabled = false;
            alert(error.message);
        }
    });

    document.querySelectorAll('.avdm-refresh-one').forEach(function (item) {
        item.addEventListener('click', async function () {
            item.disabled = true;
            try {
                await request({action: 'refresh_one', status_id: item.dataset.id});
                window.location.reload();
            } catch (error) {
                item.disabled = false;
                alert(error.message);
            }
        });
    });
})();
</script>
HTML;
    }

    private function enabled(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'on', 'yes', 'true'], true);
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function formatDate(?string $value): string
    {
        if (!$value) {
            return 'Nunca';
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
    }
}
