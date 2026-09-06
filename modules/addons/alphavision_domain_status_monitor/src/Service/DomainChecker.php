<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Service;

use Alphavision\DomainMonitor\Dns\DnsResolver;
use Alphavision\DomainMonitor\Domain\DomainNormalizer;
use Alphavision\DomainMonitor\Domain\InfrastructureMatcher;

final class DomainChecker
{
    public function __construct(
        private readonly DnsResolver $resolver,
        private readonly InfrastructureMatcher $matcher,
        private readonly DomainNormalizer $normalizer
    ) {
    }

    public function check(string $domain): array
    {
        $checkedAt = date('Y-m-d H:i:s');
        try {
            $domain = $this->normalizer->normalize($domain);
        } catch (\Throwable $exception) {
            return $this->errorResult('invalid_domain', $exception->getMessage(), $checkedAt);
        }

        $nsQuery = $this->resolver->query($domain, 'NS');
        $soaQuery = $this->resolver->query($domain, 'SOA');
        $nameservers = $this->values($nsQuery['records'], 'NS', 'target');
        $soa = array_values(array_filter($soaQuery['records'], static fn (array $record): bool => $record['type'] === 'SOA'));
        $nsStatus = $this->classifyNameservers($nsQuery, $nameservers);

        $root = $this->resolveHost($domain);
        $www = $this->resolveHost('www.' . $domain);
        $destination = $this->classifyDestination($root, $www);
        $errors = array_merge($root['errors'], $www['errors']);
        if (!$nsQuery['ok'] && !$nsQuery['nxdomain']) {
            $errors[] = ['code' => $nsQuery['error_code'], 'message' => $nsQuery['error_message']];
        }

        $queryResult = ($nsStatus === 'query_error' || $destination['status'] === 'query_error') ? 'error' : 'success';
        $firstError = $errors[0] ?? null;

        return [
            'domain_normalized' => $domain,
            'ns_status' => $nsStatus,
            'destination_status' => $destination['status'],
            'consolidated_status' => $this->consolidate($nsStatus, $destination['status']),
            'nameservers' => $nameservers,
            'ipv4' => array_values(array_unique(array_merge($root['ipv4'], $www['ipv4']))),
            'ipv6' => array_values(array_unique(array_merge($root['ipv6'], $www['ipv6']))),
            'cnames' => array_values(array_unique(array_merge($root['cnames'], $www['cnames']))),
            'soa' => $soa,
            'hosts' => ['root' => $root, 'www' => $www],
            'query_result' => $queryResult,
            'error_code' => $firstError['code'] ?? null,
            'error_message' => $firstError['message'] ?? null,
            'checked_at' => $checkedAt,
            'last_success_at' => $queryResult === 'success' ? $checkedAt : null,
        ];
    }

    private function resolveHost(string $host): array
    {
        $ipv4 = [];
        $ipv6 = [];
        $cnames = [];
        $errors = [];
        $current = $host;
        $visited = [];

        for ($depth = 0; $depth < 8; $depth++) {
            if (isset($visited[$current])) {
                $errors[] = ['code' => 'cname_loop', 'message' => 'Loop de CNAME detectado em ' . $current];
                break;
            }
            $visited[$current] = true;

            foreach (['A', 'AAAA'] as $type) {
                $query = $this->resolver->query($current, $type);
                if ($query['ok']) {
                    $values = $this->values($query['records'], $type, 'address');
                    if ($type === 'A') {
                        $ipv4 = array_merge($ipv4, $values);
                    } else {
                        $ipv6 = array_merge($ipv6, $values);
                    }
                } elseif (!$query['nxdomain']) {
                    $errors[] = ['code' => $query['error_code'], 'message' => $query['error_message']];
                }
            }

            $cnameQuery = $this->resolver->query($current, 'CNAME');
            if (!$cnameQuery['ok']) {
                if (!$cnameQuery['nxdomain']) {
                    $errors[] = ['code' => $cnameQuery['error_code'], 'message' => $cnameQuery['error_message']];
                }
                break;
            }
            $targets = $this->values($cnameQuery['records'], 'CNAME', 'target');
            if ($targets === []) {
                break;
            }
            $current = $targets[0];
            $cnames[] = $current;
        }

        $ipv4 = array_values(array_unique($ipv4));
        $ipv6 = array_values(array_unique($ipv6));
        $cnames = array_values(array_unique($cnames));
        sort($ipv4);
        sort($ipv6);
        sort($cnames);

        return [
            'host' => $host,
            'ipv4' => $ipv4,
            'ipv6' => $ipv6,
            'cnames' => $cnames,
            'resolved' => $ipv4 !== [] || $ipv6 !== [] || $cnames !== [],
            'errors' => $errors,
        ];
    }

    private function classifyNameservers(array $query, array $nameservers): string
    {
        if ($query['nxdomain'] || ($query['ok'] && $nameservers === [])) {
            return 'no_delegation';
        }
        if (!$query['ok']) {
            return 'query_error';
        }
        $managed = 0;
        foreach ($nameservers as $nameserver) {
            $managed += $this->matcher->matchesNameserver($nameserver) ? 1 : 0;
        }
        if ($managed === count($nameservers)) {
            return 'managed';
        }
        return $managed === 0 ? 'external' : 'inconsistent';
    }

    private function classifyDestination(array $root, array $www): array
    {
        $resolvedHosts = (int) $root['resolved'] + (int) $www['resolved'];
        if ($resolvedHosts === 0) {
            return ['status' => ($root['errors'] !== [] || $www['errors'] !== []) ? 'query_error' : 'no_resolution'];
        }

        $signals = [];
        foreach ([$root, $www] as $host) {
            foreach (array_merge($host['ipv4'], $host['ipv6']) as $ip) {
                $signals[] = $this->matcher->matchesIp($ip);
            }
            foreach ($host['cnames'] as $cname) {
                $signals[] = $this->matcher->matchesCname($cname);
            }
        }

        $managed = count(array_filter($signals));
        if ($managed > 0 && $managed < count($signals)) {
            return ['status' => 'inconsistent'];
        }
        if ($resolvedHosts === 1) {
            return ['status' => 'partial'];
        }
        return ['status' => $managed === count($signals) && $signals !== [] ? 'managed' : 'external'];
    }

    private function consolidate(string $ns, string $destination): string
    {
        if ($ns === 'not_verified' || $destination === 'not_verified') {
            return 'unknown';
        }
        if ($ns === 'query_error' || $destination === 'query_error'
            || $ns === 'inconsistent' || in_array($destination, ['inconsistent', 'partial'], true)) {
            return 'attention';
        }
        if ($ns === 'no_delegation' || $destination === 'no_resolution') {
            return 'no_pointing';
        }
        if ($ns === 'managed' && $destination === 'managed') {
            return 'managed_ok';
        }
        if ($ns === 'external' && $destination === 'managed') {
            return 'external_dns_managed_destination';
        }
        if ($ns === 'managed' && $destination === 'external') {
            return 'managed_dns_external_destination';
        }
        return 'external';
    }

    private function values(array $records, string $type, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (($record['type'] ?? '') === $type && isset($record[$key])) {
                $values[] = strtolower(rtrim((string) $record[$key], '.'));
            }
        }
        $values = array_values(array_unique($values));
        sort($values);
        return $values;
    }

    private function errorResult(string $code, string $message, string $checkedAt): array
    {
        return [
            'domain_normalized' => '', 'ns_status' => 'query_error', 'destination_status' => 'query_error',
            'consolidated_status' => 'attention', 'nameservers' => [], 'ipv4' => [], 'ipv6' => [],
            'cnames' => [], 'soa' => [], 'hosts' => [], 'query_result' => 'error', 'error_code' => $code,
            'error_message' => $message, 'checked_at' => $checkedAt, 'last_success_at' => null,
        ];
    }
}
