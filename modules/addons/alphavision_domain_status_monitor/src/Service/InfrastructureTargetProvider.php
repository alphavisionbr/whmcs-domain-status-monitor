<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Service;

use Alphavision\DomainMonitor\Domain\TargetNormalizer;
use Alphavision\DomainMonitor\Repository\SettingsRepository;
use Alphavision\DomainMonitor\Repository\TargetRepository;
use WHMCS\Database\Capsule;

final class InfrastructureTargetProvider
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly TargetRepository $legacyTargets,
        private readonly TargetNormalizer $normalizer
    ) {
    }

    public function all(): array
    {
        $targets = $this->legacyTargets->all(true);
        $settings = $this->settings->all();

        if ($this->enabled((string) ($settings['use_whmcs_servers'] ?? 'on'))) {
            $targets = array_merge($targets, $this->fromWhmcsServers());
        }

        foreach ($this->lines((string) ($settings['infrastructure_hosts'] ?? '')) as $host) {
            $suffix = str_starts_with($host, '*.');
            $value = $suffix ? substr($host, 2) : $host;
            foreach ($suffix ? ['ns_suffix', 'host_suffix'] : ['ns_exact', 'host_exact'] as $type) {
                $this->append($targets, $type, $value, 'Configuração adicional');
            }
        }

        foreach ($this->lines((string) ($settings['infrastructure_ips'] ?? '')) as $value) {
            $type = $this->ipType($value);
            if ($type !== null) {
                $this->append($targets, $type, $value, 'Configuração adicional');
            }
        }

        $unique = [];
        foreach ($targets as $target) {
            if (empty($target['enabled'])) {
                continue;
            }
            $key = (string) $target['type'] . ':' . strtolower((string) $target['normalized_value']);
            $unique[$key] = $target;
        }

        return array_values($unique);
    }

    private function fromWhmcsServers(): array
    {
        if (!Capsule::schema()->hasTable('tblservers')) {
            return [];
        }

        $query = Capsule::table('tblservers');
        if (Capsule::schema()->hasColumn('tblservers', 'disabled')) {
            $query->where('disabled', 0);
        }

        $targets = [];
        foreach ($query->get() as $server) {
            $row = (array) $server;
            $label = trim((string) ($row['name'] ?? ''));
            $label = $label === '' ? 'Servidor WHMCS' : 'Servidor WHMCS: ' . $label;

            $hostname = trim((string) ($row['hostname'] ?? ''));
            if ($hostname !== '') {
                $this->append($targets, 'host_exact', $hostname, $label);
            }

            foreach (['ipaddress', 'assignedips'] as $field) {
                foreach ($this->extractIps((string) ($row[$field] ?? '')) as $ip) {
                    $type = $this->ipType($ip);
                    if ($type !== null) {
                        $this->append($targets, $type, $ip, $label);
                    }
                }
            }

            for ($number = 1; $number <= 4; $number++) {
                $nameserver = trim((string) ($row['nameserver' . $number] ?? ''));
                if ($nameserver !== '') {
                    $this->append($targets, 'ns_exact', $nameserver, $label);
                }
                foreach ($this->extractIps((string) ($row['nameserver' . $number . 'ip'] ?? '')) as $ip) {
                    $type = $this->ipType($ip);
                    if ($type !== null) {
                        $this->append($targets, $type, $ip, $label);
                    }
                }
            }
        }

        return $targets;
    }

    private function append(array &$targets, string $type, string $value, string $label): void
    {
        try {
            $normalized = $this->normalizer->normalize($type, $value);
        } catch (\Throwable) {
            return;
        }

        $targets[] = [
            'type' => $type,
            'value' => $value,
            'normalized_value' => $normalized,
            'label' => $label,
            'enabled' => 1,
        ];
    }

    private function lines(string $value): array
    {
        $lines = preg_split('/[\r\n,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }

    private function extractIps(string $value): array
    {
        preg_match_all('/(?<![0-9a-f:.])(?:\d{1,3}(?:\.\d{1,3}){3}|[0-9a-f]*:[0-9a-f:]+)(?![0-9a-f:.])/i', $value, $matches);
        return array_values(array_filter($matches[0] ?? [], static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false));
    }

    private function ipType(string $value): ?string
    {
        if (str_contains($value, '/')) {
            [$ip] = explode('/', $value, 2);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return 'ipv4_cidr';
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return 'ipv6_cidr';
            }
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 'ipv4';
        }
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 'ipv6';
        }
        return null;
    }

    private function enabled(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'on', 'yes', 'true'], true);
    }
}
