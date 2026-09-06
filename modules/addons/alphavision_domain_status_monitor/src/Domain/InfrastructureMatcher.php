<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Domain;

final class InfrastructureMatcher
{
    public function __construct(private readonly array $targets)
    {
    }

    public function matchesNameserver(string $host): bool
    {
        return $this->matchesHost($host, 'ns_exact', 'ns_suffix');
    }

    public function matchesCname(string $host): bool
    {
        return $this->matchesHost($host, 'host_exact', 'host_suffix');
    }

    public function matchesIp(string $ip): bool
    {
        $binary = @inet_pton($ip);
        $normalizedIp = $binary === false ? strtolower($ip) : strtolower((string) inet_ntop($binary));
        foreach ($this->targets as $target) {
            if (empty($target['enabled'])) {
                continue;
            }
            $type = (string) $target['type'];
            $value = (string) $target['normalized_value'];
            if (($type === 'ipv4' || $type === 'ipv6') && $normalizedIp === strtolower($value)) {
                return true;
            }
            if (($type === 'ipv4_cidr' || $type === 'ipv6_cidr') && $this->inCidr($ip, $value)) {
                return true;
            }
        }
        return false;
    }

    private function matchesHost(string $host, string $exactType, string $suffixType): bool
    {
        $host = strtolower(rtrim($host, '.'));
        foreach ($this->targets as $target) {
            if (empty($target['enabled'])) {
                continue;
            }
            $value = strtolower((string) $target['normalized_value']);
            if ($target['type'] === $exactType && $host === $value) {
                return true;
            }
            if ($target['type'] === $suffixType && ($host === $value || str_ends_with($host, '.' . $value))) {
                return true;
            }
        }
        return false;
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        [$network, $prefixText] = explode('/', $cidr, 2);
        $ipBinary = @inet_pton($ip);
        $networkBinary = @inet_pton($network);
        if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary)) {
            return false;
        }

        $prefix = (int) $prefixText;
        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;
        if ($wholeBytes > 0 && substr($ipBinary, 0, $wholeBytes) !== substr($networkBinary, 0, $wholeBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($ipBinary[$wholeBytes]) & $mask) === (ord($networkBinary[$wholeBytes]) & $mask);
    }
}
