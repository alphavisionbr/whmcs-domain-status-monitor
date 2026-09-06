<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Dns;

use NetDNS2\Exception as NetDnsException;
use NetDNS2\Resolver;

final class DnsResolver
{
    private ?Resolver $resolver = null;
    private string $mode;

    public function __construct(private readonly array $nameservers, float $timeout)
    {
        $options = ['timeout' => max(0.5, min(10.0, $timeout)), 'strict_query_mode' => false];

        if ($nameservers !== []) {
            $options['nameservers'] = $nameservers;
            $this->resolver = new Resolver($options);
            $this->mode = 'netdns_explicit';
            return;
        }

        if (@is_readable('/etc/resolv.conf')) {
            $this->resolver = new Resolver($options);
            $this->mode = 'netdns_system';
            return;
        }

        $this->mode = function_exists('dns_get_record') ? 'native_system' : 'unavailable';
    }

    public function query(string $host, string $type): array
    {
        if ($this->resolver === null) {
            return $this->nativeQuery($host, $type);
        }

        try {
            $response = $this->resolver->query($host, $type);
            $records = [];
            foreach ($response->answer as $record) {
                $records[] = $this->recordToArray($record);
            }
            return $this->success($records);
        } catch (NetDnsException $exception) {
            if ($this->canUseNativeFallback($exception)) {
                $this->mode = 'native_system';
                return $this->nativeQuery($host, $type);
            }

            $code = (int) $exception->getCode();
            return [
                'ok' => false,
                'nxdomain' => $code === 3,
                'records' => [],
                'error_code' => $this->errorCode($code),
                'error_message' => $exception->getMessage(),
            ];
        } catch (\Throwable $exception) {
            if ($this->canUseNativeFallback($exception)) {
                $this->mode = 'native_system';
                return $this->nativeQuery($host, $type);
            }

            return $this->failure('resolver_error', $exception->getMessage());
        }
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public static function modeLabel(string $mode): string
    {
        return match ($mode) {
            'netdns_explicit' => 'NetDNS2 com resolvedores configurados',
            'netdns_system' => 'NetDNS2 com resolvedores do servidor',
            'native_system' => 'Fallback DNS nativo do PHP',
            default => 'Nenhum resolvedor disponível',
        };
    }

    private function nativeQuery(string $host, string $type): array
    {
        if (!function_exists('dns_get_record')) {
            return $this->failure('native_resolver_unavailable', 'A função dns_get_record não está disponível e o resolvedor do sistema não pode ser lido.');
        }

        $constant = 'DNS_' . strtoupper($type);
        if (!defined($constant)) {
            return $this->failure('unsupported_record_type', 'Tipo de registro DNS não suportado: ' . $type);
        }

        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        });

        try {
            $nativeRecords = dns_get_record($host, constant($constant));
        } finally {
            restore_error_handler();
        }

        if ($nativeRecords === false) {
            $message = $warning ?: 'A resolução DNS nativa do servidor falhou.';
            $normalized = strtolower($message);
            $notFound = str_contains($normalized, 'not found')
                || str_contains($normalized, 'no address')
                || str_contains($normalized, 'nxdomain');

            return [
                'ok' => false,
                'nxdomain' => $notFound,
                'records' => [],
                'error_code' => $notFound ? 'nxdomain' : 'native_resolver_error',
                'error_message' => $message,
            ];
        }

        $records = [];
        foreach ($nativeRecords as $record) {
            $converted = $this->nativeRecordToArray($record);
            if ($converted !== null) {
                $records[] = $converted;
            }
        }

        return $this->success($records);
    }

    private function nativeRecordToArray(array $record): ?array
    {
        $type = strtoupper((string) ($record['type'] ?? ''));
        if ($type === '') {
            return null;
        }

        $data = [
            'type' => $type,
            'name' => strtolower(rtrim((string) ($record['host'] ?? ''), '.')),
        ];

        if ($type === 'A' && isset($record['ip'])) {
            $data['address'] = strtolower((string) $record['ip']);
        } elseif ($type === 'AAAA' && isset($record['ipv6'])) {
            $data['address'] = strtolower((string) $record['ipv6']);
        } elseif (($type === 'CNAME' || $type === 'NS') && isset($record['target'])) {
            $data['target'] = strtolower(rtrim((string) $record['target'], '.'));
        } elseif ($type === 'SOA') {
            $data['mname'] = strtolower(rtrim((string) ($record['mname'] ?? ''), '.'));
            $data['serial'] = (int) ($record['serial'] ?? 0);
        }

        return $data;
    }

    private function recordToArray(object $record): array
    {
        $class = get_class($record);
        $type = strtoupper(substr($class, strrpos($class, '\\') + 1));
        $data = ['type' => $type, 'name' => strtolower(rtrim((string) ($record->name ?? ''), '.'))];
        if ($type === 'A' || $type === 'AAAA') {
            $data['address'] = strtolower((string) $record->address);
        } elseif ($type === 'CNAME') {
            $data['target'] = strtolower(rtrim((string) $record->cname, '.'));
        } elseif ($type === 'NS') {
            $data['target'] = strtolower(rtrim((string) $record->nsdname, '.'));
        } elseif ($type === 'SOA') {
            $data['mname'] = strtolower(rtrim((string) $record->mname, '.'));
            $data['serial'] = (int) $record->serial;
        }
        return $data;
    }

    private function canUseNativeFallback(\Throwable $exception): bool
    {
        if ($this->nameservers !== [] || !function_exists('dns_get_record')) {
            return false;
        }

        $message = strtolower($exception->getMessage());
        return str_contains($message, 'resolv.conf')
            || str_contains($message, 'resolver file')
            || str_contains($message, 'not readable');
    }

    private function success(array $records): array
    {
        return ['ok' => true, 'nxdomain' => false, 'records' => $records, 'error_code' => null, 'error_message' => null];
    }

    private function failure(string $code, string $message): array
    {
        return ['ok' => false, 'nxdomain' => false, 'records' => [], 'error_code' => $code, 'error_message' => $message];
    }

    private function errorCode(int $code): string
    {
        return match ($code) {
            2 => 'servfail',
            3 => 'nxdomain',
            5 => 'refused',
            3854, 3855 => 'timeout_or_transport',
            default => 'dns_error_' . $code,
        };
    }
}
