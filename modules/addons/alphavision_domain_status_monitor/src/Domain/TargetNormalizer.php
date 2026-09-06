<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Domain;

use InvalidArgumentException;

final class TargetNormalizer
{
    public const TYPES = [
        'ns_exact' => 'Nameserver exato',
        'ns_suffix' => 'Sufixo de nameserver',
        'ipv4' => 'IPv4',
        'ipv4_cidr' => 'Rede IPv4 (CIDR)',
        'ipv6' => 'IPv6',
        'ipv6_cidr' => 'Rede IPv6 (CIDR)',
        'host_exact' => 'Host/CNAME exato',
        'host_suffix' => 'Sufixo de host/CNAME',
    ];

    public function normalize(string $type, string $value): string
    {
        $value = trim($value);
        if (!isset(self::TYPES[$type]) || $value === '') {
            throw new InvalidArgumentException('Tipo ou valor de infraestrutura inválido.');
        }

        if (in_array($type, ['ns_exact', 'ns_suffix', 'host_exact', 'host_suffix'], true)) {
            $value = strtolower(ltrim(rtrim($value, '.'), '.'));
            if (strlen($value) > 253) {
                throw new InvalidArgumentException('Host ou sufixo inválido.');
            }
            foreach (explode('.', $value) as $label) {
                if ($label === '' || strlen($label) > 63 || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) {
                    throw new InvalidArgumentException('Host ou sufixo inválido.');
                }
            }
            return $value;
        }

        if ($type === 'ipv4' || $type === 'ipv6') {
            $family = $type === 'ipv4' ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6;
            if (filter_var($value, FILTER_VALIDATE_IP, $family) === false) {
                throw new InvalidArgumentException('Endereço IP inválido.');
            }
            $binary = inet_pton($value);
            return strtolower($binary === false ? $value : (string) inet_ntop($binary));
        }

        [$network, $prefix] = array_pad(explode('/', $value, 2), 2, null);
        $isV4 = $type === 'ipv4_cidr';
        $max = $isV4 ? 32 : 128;
        $family = $isV4 ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6;
        if ($prefix === null || filter_var($network, FILTER_VALIDATE_IP, $family) === false
            || !ctype_digit($prefix) || (int) $prefix < 0 || (int) $prefix > $max) {
            throw new InvalidArgumentException('Rede CIDR inválida.');
        }
        $binary = inet_pton($network);
        $normalizedNetwork = $binary === false ? $network : (string) inet_ntop($binary);
        return strtolower($normalizedNetwork . '/' . (int) $prefix);
    }
}
