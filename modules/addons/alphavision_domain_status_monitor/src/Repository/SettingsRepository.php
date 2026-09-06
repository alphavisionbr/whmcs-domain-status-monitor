<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Repository;

use Alphavision\DomainMonitor\Database\Schema;
use WHMCS\Database\Capsule;

final class SettingsRepository
{
    private const MODULE = 'alphavision_domain_status_monitor';
    private const LEGACY_MODULE = 'avdomainmonitor';

    private const DEFAULTS = [
        'infrastructure_name' => 'Alphavision',
        'use_whmcs_servers' => 'on',
        'infrastructure_hosts' => '',
        'infrastructure_ips' => '',
        'inactive_checks_before_removal' => '2',
        'batch_size' => '10',
        'dns_timeout' => '3.0',
        'resolver_ips' => '',
    ];

    public function all(): array
    {
        $settings = self::DEFAULTS;

        if (Capsule::schema()->hasTable(Schema::SETTINGS)) {
            foreach (Capsule::table(Schema::SETTINGS)->get() as $row) {
                $key = (string) $row->setting_key;
                if (array_key_exists($key, self::DEFAULTS)) {
                    $settings[$key] = (string) ($row->setting_value ?? '');
                }
            }
        }

        foreach ([self::LEGACY_MODULE, self::MODULE] as $module) {
            foreach (Capsule::table('tbladdonmodules')->where('module', $module)->get() as $row) {
                $key = (string) $row->setting;
                if (array_key_exists($key, self::DEFAULTS)) {
                    $settings[$key] = (string) ($row->value ?? '');
                }
            }
        }

        return $settings;
    }

    public function get(string $key, string $default = ''): string
    {
        $settings = $this->all();
        return array_key_exists($key, $settings) ? (string) $settings[$key] : $default;
    }

    public function setMany(array $settings): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::connection()->transaction(static function () use ($settings, $now): void {
            foreach ($settings as $key => $value) {
                Capsule::table(Schema::SETTINGS)->updateOrInsert(
                    ['setting_key' => (string) $key],
                    ['setting_value' => (string) $value, 'updated_at' => $now]
                );
            }
        });
    }

    public function backupNative(): void
    {
        $this->setMany($this->all());
    }

    public function restoreNative(): void
    {
        if (!Capsule::schema()->hasTable(Schema::SETTINGS)) {
            return;
        }

        foreach ($this->all() as $key => $value) {
            Capsule::table('tbladdonmodules')->updateOrInsert(
                ['module' => self::MODULE, 'setting' => $key],
                ['value' => (string) $value]
            );
        }
    }
}
