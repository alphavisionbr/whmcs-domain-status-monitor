<?php
/**
 * Alphavision® WHMCS Domain Status Monitor
 *
 * Consulta manual do estado DNS dos domínios ativos no WHMCS.
 *
 * @package   AlphavisionWHMCSDomainStatusMonitor
 * @author    Alphavision®
 * @copyright Copyright (c) 2026 Alphavision®
 * @license   MIT
 * @version   1.0.4
 * @link      https://alphavision.com.br/
 *
 * Compatibilidade declarada:
 * - WHMCS 9.0.x
 * - PHP 8.2 e 8.3
 *
 * Esta versão deve ser homologada em ambiente de testes antes do uso em produção.
 */

declare(strict_types=1);

use Alphavision\DomainMonitor\Admin\Controller;
use Alphavision\DomainMonitor\Database\Schema;
use Alphavision\DomainMonitor\Repository\SettingsRepository;

defined('WHMCS') || die('Acesso direto não permitido.');

require_once __DIR__ . '/bootstrap.php';

function alphavision_domain_status_monitor_config(): array
{
    $help = static function (string $text): string {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return '<button type="button" class="av-config-help" title="' . $escaped
            . '" aria-label="Ajuda: ' . $escaped . '">?</button>';
    };

    $section = static function (string $title, string $description, bool $includeAssets = false): string {
        $assets = '';
        if ($includeAssets) {
            $assets = <<<'HTML'
<style id="av-config-styles">
.av-config-section-marker,.av-config-row-marker{display:none!important}
.av-config-help{display:inline-flex;align-items:center;justify-content:center;width:23px;height:23px;margin-left:8px;padding:0;border:1px solid #8db5d2;border-radius:50%;background:#e8f3fa;color:#075b91;font-size:13px;font-weight:700;line-height:1;cursor:help;vertical-align:middle;box-shadow:none}
.av-config-help:hover,.av-config-help:focus{background:#075b91;color:#fff;border-color:#075b91;outline:0}
tr.av-config-section-row>td,tr.av-config-section-row>th{padding-top:12px!important;padding-bottom:12px!important;background:#e2ebf2!important;border-top:0!important}
tr.av-config-section-row>td:first-child,tr.av-config-section-row>th:first-child{color:#002f57!important;font-size:15px!important;font-weight:700!important;vertical-align:top!important}
tr.av-config-section-row>td:last-child{color:#536675!important;font-size:13px!important;line-height:1.55!important}
tr.av-config-standard-row>td,tr.av-config-standard-row>th{padding-top:9px!important;padding-bottom:9px!important}
tr.av-config-standard-row>td:first-child,tr.av-config-standard-row>th:first-child{font-weight:600!important;vertical-align:top!important}
tr.av-config-section-row input[type=text]{display:none!important}
body.av-domainmonitor-config table.form tr:not(.av-config-section-row)>td,body.av-domainmonitor-config table.form tr:not(.av-config-section-row)>th{background-color:var(--av-config-cell-bg)!important}
body.av-domainmonitor-config table.form tr:not(.av-config-section-row):hover>td,body.av-domainmonitor-config table.form tr:not(.av-config-section-row):hover>th{background-color:var(--av-config-cell-bg)!important}
</style>
<script>
(function(){
  function initAlphavisionConfig(){
    document.body.classList.add('av-domainmonitor-config');
    var style=document.getElementById('av-config-styles');
    if(style&&style.parentNode!==document.head) document.head.appendChild(style);
    document.querySelectorAll('.av-config-section-marker').forEach(function(marker){
      var row=marker.closest('tr');
      if(!row||row.dataset.avReady==='1') return;
      row.dataset.avReady='1';
      row.classList.add('av-config-section-row');
      var cells=row.querySelectorAll(':scope > th, :scope > td');
      if(cells.length<2) return;
      cells[0].textContent=marker.getAttribute('data-title')||'';
      cells[1].innerHTML='<span class="av-config-section-description">'+(marker.getAttribute('data-description')||'')+'</span>';
    });
    document.querySelectorAll('.av-config-row-marker').forEach(function(marker){
      var row=marker.closest('tr');
      if(!row) return;
      row.classList.add('av-config-standard-row');
      marker.remove();
    });
    document.querySelectorAll('table.form tr:not(.av-config-section-row) > td, table.form tr:not(.av-config-section-row) > th').forEach(function(cell){
      if(cell.style.getPropertyValue('--av-config-cell-bg')) return;
      cell.style.setProperty('--av-config-cell-bg',window.getComputedStyle(cell).backgroundColor);
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',initAlphavisionConfig);
  else initAlphavisionConfig();
})();
</script>
HTML;
        }

        return $assets . '<span class="av-config-section-marker" data-title="'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" data-description="'
            . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '"></span>';
    };

    $row = static fn (): string => '<span class="av-config-row-marker"></span>';

    return [
        'name' => 'Alphavision® WHMCS Domain Status Monitor',
        'description' => 'Consulta manual do estado DNS dos domínios ativos cadastrados no WHMCS.',
        'version' => '1.0.4',
        'author' => 'Alphavision®',
        'language' => 'portuguese-br',
        'fields' => [
            'identity_section' => [
                'FriendlyName' => $section('Identificação', 'Defina o nome exibido nas classificações da infraestrutura monitorada.', true),
                'Type' => 'text',
                'Size' => '1',
                'Default' => '',
            ],
            'infrastructure_name' => [
                'FriendlyName' => 'Nome da empresa ou infraestrutura',
                'Type' => 'text',
                'Size' => '40',
                'Default' => 'Alphavision',
                'Description' => $row() . 'Usado somente nos textos da interface.' . $help('Os estados internos permanecem neutros e não gravam o nome da empresa.'),
            ],
            'infrastructure_section' => [
                'FriendlyName' => $section('Infraestrutura própria', 'Use os servidores habilitados do WHMCS como fonte automática e complemente somente o que não estiver cadastrado neles.'),
                'Type' => 'text',
                'Size' => '1',
                'Default' => '',
            ],
            'use_whmcs_servers' => [
                'FriendlyName' => 'Servidores do WHMCS',
                'Type' => 'yesno',
                'Description' => $row() . 'Usar hostname, IPs e nameservers dos servidores habilitados.' . $help('Servidores desabilitados são ignorados. Nenhum provedor externo é presumido.'),
                'Default' => 'on',
            ],
            'infrastructure_hosts' => [
                'FriendlyName' => 'Hostnames adicionais',
                'Type' => 'textarea',
                'Rows' => '5',
                'Cols' => '70',
                'Default' => '',
                'Description' => $row() . 'Um hostname por linha. Use *.exemplo.com.br para aceitar todo o sufixo.' . $help('Informe nameservers, destinos CNAME ou hostnames que não estejam nos servidores cadastrados no WHMCS.'),
            ],
            'infrastructure_ips' => [
                'FriendlyName' => 'IPs e redes adicionais',
                'Type' => 'textarea',
                'Rows' => '5',
                'Cols' => '70',
                'Default' => '',
                'Description' => $row() . 'Um IPv4, IPv6 ou bloco CIDR por linha.' . $help('Use redes CIDR somente quando todo o bloco pertencer à infraestrutura monitorada.'),
            ],
            'reconciliation_section' => [
                'FriendlyName' => $section('Reconciliação de domínios', 'Defina quantas sincronizações completas confirmarão que um domínio deixou de estar ativo.'),
                'Type' => 'text',
                'Size' => '1',
                'Default' => '',
            ],
            'inactive_checks_before_removal' => [
                'FriendlyName' => 'Consultas antes da remoção',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '2',
                'Description' => $row() . 'Valor entre 1 e 100.' . $help('Com 2, o domínio fica pendente na primeira sincronização completa e é removido na segunda se continuar fora de Active.'),
            ],
            'dns_section' => [
                'FriendlyName' => $section('Consulta DNS', 'Configure o processamento das consultas. Sem resolvedores explícitos, será usada a configuração DNS do servidor.'),
                'Type' => 'text',
                'Size' => '1',
                'Default' => '',
            ],
            'resolver_ips' => [
                'FriendlyName' => 'Resolvedores DNS',
                'Type' => 'textarea',
                'Rows' => '4',
                'Cols' => '70',
                'Default' => '',
                'Description' => $row() . 'Um IPv4 ou IPv6 por linha. Deixe vazio para usar os resolvedores do servidor.' . $help('Google e Cloudflare não são configurados automaticamente.'),
            ],
            'dns_timeout' => [
                'FriendlyName' => 'Timeout por consulta',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '3.0',
                'Description' => $row() . 'Segundos, entre 0,5 e 10.',
            ],
            'batch_size' => [
                'FriendlyName' => 'Domínios por lote',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '10',
                'Description' => $row() . 'Valor entre 1 e 50.' . $help('Lotes menores reduzem o risco de timeout em servidores com consultas DNS mais lentas.'),
            ],
            'access_control_section' => [
                'FriendlyName' => $section('Controle de acesso', 'O Access Control nativo do WHMCS, exibido abaixo, define quais grupos administrativos podem abrir o addon.'),
                'Type' => 'text',
                'Size' => '1',
                'Default' => '',
            ],
        ],
    ];
}

function alphavision_domain_status_monitor_activate(): array
{
    try {
        Schema::install();
        (new SettingsRepository())->restoreNative();
        return ['status' => 'success', 'description' => 'Módulo ativado e estrutura de banco criada.'];
    } catch (Throwable $exception) {
        return ['status' => 'error', 'description' => 'Falha ao ativar: ' . $exception->getMessage()];
    }
}

function alphavision_domain_status_monitor_deactivate(): array
{
    (new SettingsRepository())->backupNative();
    return [
        'status' => 'success',
        'description' => 'Módulo desativado. Configurações e resultados foram preservados.',
    ];
}

function alphavision_domain_status_monitor_upgrade(array $vars): void
{
    Schema::install();
    (new SettingsRepository())->restoreNative();
}

function alphavision_domain_status_monitor_output(array $vars): void
{
    (new Controller($vars))->dispatch();
}
