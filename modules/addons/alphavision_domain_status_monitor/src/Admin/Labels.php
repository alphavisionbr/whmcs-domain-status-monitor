<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Admin;

final class Labels
{
    public static function ns(string $status, string $name): string
    {
        return match ($status) {
            'managed' => 'DNS ' . $name,
            'external' => 'DNS externo',
            'no_delegation' => 'Sem delegação',
            'inconsistent' => 'Inconsistente',
            'query_error' => 'Erro de consulta',
            default => 'Não verificado',
        };
    }

    public static function destination(string $status, string $name): string
    {
        return match ($status) {
            'managed' => 'Infraestrutura ' . $name,
            'external' => 'Infraestrutura externa',
            'no_resolution' => 'Sem resolução',
            'partial' => 'Resolução parcial',
            'inconsistent' => 'Inconsistente',
            'query_error' => 'Erro de consulta',
            default => 'Não verificado',
        };
    }

    public static function consolidated(string $status, string $name): string
    {
        return match ($status) {
            'managed_ok' => 'OK ' . $name,
            'external_dns_managed_destination' => 'DNS externo + infraestrutura ' . $name,
            'managed_dns_external_destination' => 'DNS ' . $name . ' + destino externo',
            'external' => 'Infraestrutura externa',
            'no_pointing' => 'Sem apontamento',
            'attention' => 'Requer atenção',
            default => 'Desconhecido',
        };
    }

    public static function badgeClass(string $status): string
    {
        return match ($status) {
            'managed', 'managed_ok', 'success' => 'is-success',
            'external', 'external_dns_managed_destination', 'managed_dns_external_destination' => 'is-info',
            'not_verified', 'unknown' => 'is-muted',
            'pending_recheck', 'partial' => 'is-warning',
            default => 'is-danger',
        };
    }
}
