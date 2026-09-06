<?php
/**
 * Alphavision® WHMCS Domain Status Monitor
 *
 * Hooks administrativos do módulo.
 *
 * @package   AlphavisionWHMCSDomainStatusMonitor
 * @author    Alphavision®
 * @copyright Copyright (c) 2026 Alphavision®
 * @license   MIT
 * @version   1.0.4
 * @link      https://alphavision.com.br/
 */

declare(strict_types=1);

defined('WHMCS') || die('Acesso direto não permitido.');

add_hook('AdminAreaPage', 1, static function (array $vars): array {
    if (!isset($vars['addon_modules']) || !is_array($vars['addon_modules'])) {
        return [];
    }

    $modules = $vars['addon_modules'];
    $moduleKey = 'alphavision_domain_status_monitor';
    $officialName = 'Alphavision® WHMCS Domain Status Monitor';
    $menuName = 'Status de Domínios';

    if (array_key_exists($moduleKey, $modules)) {
        if (is_array($modules[$moduleKey])) {
            foreach (['name', 'displayName', 'displayname', 'label', 'title'] as $field) {
                if (array_key_exists($field, $modules[$moduleKey])) {
                    $modules[$moduleKey][$field] = $menuName;
                }
            }
        } else {
            $modules[$moduleKey] = $menuName;
        }
    }

    foreach ($modules as $key => &$module) {
        if (!is_array($module)) {
            if ((string) $module === $officialName && (string) $key === $moduleKey) {
                $module = $menuName;
            }
            continue;
        }

        $identifier = (string) ($module['module'] ?? $module['filename'] ?? $module['key'] ?? $key);
        $currentName = (string) ($module['name'] ?? $module['displayName'] ?? $module['displayname'] ?? $module['label'] ?? $module['title'] ?? '');

        if ($identifier !== $moduleKey && $currentName !== $officialName) {
            continue;
        }

        foreach (['name', 'displayName', 'displayname', 'label', 'title'] as $field) {
            if (array_key_exists($field, $module)) {
                $module[$field] = $menuName;
            }
        }
    }
    unset($module);

    return ['addon_modules' => $modules];
});

/**
 * Fallback visual restrito ao link deste addon.
 *
 * Algumas composições do menu administrativo no WHMCS 9 preservam o nome
 * oficial mesmo após a alteração de addon_modules. O script abaixo não toca
 * em outros itens e mantém o nome completo nas telas de configuração.
 */
add_hook('AdminAreaFooterOutput', 9999, static function (array $vars): string {
    unset($vars);

    return <<<'HTML'
<script id="alphavision-domain-status-monitor-menu-label">
(function () {
    'use strict';

    var moduleKey = 'alphavision_domain_status_monitor';
    var officialName = 'Alphavision® WHMCS Domain Status Monitor';
    var menuName = 'Status de Domínios';

    function shortenMenuLabel() {
        document.querySelectorAll('a[href]').forEach(function (anchor) {
            var href = (anchor.getAttribute('href') || '').replace(/&amp;/g, '&');
            var currentText = anchor.textContent.replace(/\s+/g, ' ').trim();
            var isModulePage = href.indexOf('addonmodules.php') !== -1
                && href.indexOf('module=' + moduleKey) !== -1;
            var menuContainer = anchor.closest('.dropdown-menu, [id*="Addon"], [data-menu-item-name*="Addon"]');

            if (menuContainer && !anchor.closest('.av-admin') && isModulePage && currentText === officialName) {
                anchor.textContent = menuName;
                anchor.setAttribute('title', menuName);
            }
        });
    }

    shortenMenuLabel();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', shortenMenuLabel, {once: true});
    }

    var observer = new MutationObserver(shortenMenuLabel);
    observer.observe(document.documentElement, {childList: true, subtree: true});
    window.setTimeout(function () { observer.disconnect(); }, 10000);
})();
</script>
HTML;
});
