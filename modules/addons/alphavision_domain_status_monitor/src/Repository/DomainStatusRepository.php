<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Repository;

use Alphavision\DomainMonitor\Database\Schema;
use Alphavision\DomainMonitor\Support\Json;
use WHMCS\Database\Capsule;

final class DomainStatusRepository
{
    public function counts(): array
    {
        $counts = [
            'total' => 0, 'managed_dns' => 0, 'external_dns' => 0, 'no_delegation' => 0,
            'managed_destination' => 0, 'external_destination' => 0, 'no_resolution' => 0,
            'attention' => 0, 'pending' => 0,
        ];
        $rows = Capsule::table(Schema::STATUS)
            ->select('tracking_status', 'ns_status', 'destination_status', 'consolidated_status', Capsule::raw('COUNT(*) AS amount'))
            ->groupBy('tracking_status', 'ns_status', 'destination_status', 'consolidated_status')->get();
        foreach ($rows as $row) {
            $amount = (int) $row->amount;
            if ($row->tracking_status === 'active') {
                $counts['total'] += $amount;
                if ($row->ns_status === 'managed') $counts['managed_dns'] += $amount;
                if ($row->ns_status === 'external') $counts['external_dns'] += $amount;
                if ($row->ns_status === 'no_delegation') $counts['no_delegation'] += $amount;
                if ($row->destination_status === 'managed') $counts['managed_destination'] += $amount;
                if ($row->destination_status === 'external') $counts['external_destination'] += $amount;
                if ($row->destination_status === 'no_resolution') $counts['no_resolution'] += $amount;
                if ($row->consolidated_status === 'attention') $counts['attention'] += $amount;
            } else {
                $counts['pending'] += $amount;
            }
        }
        return $counts;
    }

    public function paginate(array $filters, int $page, int $perPage = 50): array
    {
        $query = Capsule::table(Schema::STATUS . ' as s')
            ->leftJoin('tblclients as c', 'c.id', '=', 's.client_id')
            ->select('s.*', 'c.firstname', 'c.lastname', 'c.companyname');

        if (($filters['tracking'] ?? '') !== '') $query->where('s.tracking_status', $filters['tracking']);
        if (($filters['ns'] ?? '') !== '') $query->where('s.ns_status', $filters['ns']);
        if (($filters['destination'] ?? '') !== '') $query->where('s.destination_status', $filters['destination']);
        if (($filters['registrar'] ?? '') !== '') $query->where('s.registrar', $filters['registrar']);
        if (($filters['search'] ?? '') !== '') {
            $search = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']) . '%';
            $query->where(static function ($sub) use ($search): void {
                $sub->where('s.domain_name', 'like', $search)->orWhere('c.companyname', 'like', $search)
                    ->orWhere('c.firstname', 'like', $search)->orWhere('c.lastname', 'like', $search);
            });
        }

        $total = (clone $query)->count();
        $rows = $query->orderByRaw("CASE WHEN s.tracking_status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('s.domain_name')->offset(($page - 1) * $perPage)->limit($perPage)->get()->all();
        return ['rows' => array_map(static fn ($row): array => (array) $row, $rows), 'total' => $total];
    }

    public function registrars(): array
    {
        return Capsule::table(Schema::STATUS)->whereNotNull('registrar')->where('registrar', '<>', '')
            ->distinct()->orderBy('registrar')->pluck('registrar')->map(static fn ($value): string => (string) $value)->all();
    }

    public function find(int $id): ?array
    {
        $row = Capsule::table(Schema::STATUS)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function activeBatch(int $offset, int $limit): array
    {
        return array_map(static fn ($row): array => (array) $row, Capsule::table(Schema::STATUS)
            ->where('tracking_status', 'active')->orderBy('id')->offset($offset)->limit($limit)->get()->all());
    }

    public function saveCheck(int $id, array $result): void
    {
        $update = [
            'domain_normalized' => $result['domain_normalized'],
            'ns_status' => $result['ns_status'],
            'destination_status' => $result['destination_status'],
            'consolidated_status' => $result['consolidated_status'],
            'nameservers_json' => Json::encode($result['nameservers']),
            'ipv4_json' => Json::encode($result['ipv4']),
            'ipv6_json' => Json::encode($result['ipv6']),
            'cname_json' => Json::encode(['records' => $result['cnames'], 'hosts' => $result['hosts']]),
            'soa_json' => Json::encode($result['soa']),
            'query_result' => $result['query_result'],
            'error_code' => $result['error_code'],
            'error_message' => $result['error_message'],
            'checked_at' => $result['checked_at'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($result['last_success_at'] !== null) {
            $update['last_success_at'] = $result['last_success_at'];
        }
        Capsule::table(Schema::STATUS)->where('id', $id)->where('tracking_status', 'active')->update($update);
    }
}
