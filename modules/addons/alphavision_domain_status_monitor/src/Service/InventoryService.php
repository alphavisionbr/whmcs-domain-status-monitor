<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Service;

use Alphavision\DomainMonitor\Database\Schema;
use Alphavision\DomainMonitor\Domain\DomainNormalizer;
use Alphavision\DomainMonitor\Repository\RunRepository;
use WHMCS\Database\Capsule;

final class InventoryService
{
    public function __construct(
        private readonly DomainNormalizer $normalizer,
        private readonly RunRepository $runs
    ) {
    }

    public function start(int $adminId): array
    {
        $connection = Capsule::connection();
        $lock = $connection->selectOne("SELECT GET_LOCK('alphavision_domain_status_monitor_inventory', 5) AS acquired");
        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw new \RuntimeException('Outra sincronização está sendo iniciada. Tente novamente em alguns segundos.');
        }

        try {
            $running = $this->runs->running();
            if ($running !== null) return $running;

            $token = bin2hex(random_bytes(24));
            $activeDomains = Capsule::table('tbldomains')->where('status', 'Active')
                ->select('id', 'userid', 'domain', 'registrar', 'status')->orderBy('id')->get();
            $now = date('Y-m-d H:i:s');

            Capsule::connection()->transaction(function () use ($activeDomains, $token, $now): void {
            $activeIds = [];
            foreach ($activeDomains as $domain) {
                $domainId = (int) $domain->id;
                $activeIds[] = $domainId;
                try {
                    $normalized = $this->normalizer->normalize((string) $domain->domain);
                } catch (\Throwable) {
                    $normalized = strtolower(trim((string) $domain->domain));
                }
                $values = [
                    'client_id' => (int) $domain->userid ?: null,
                    'domain_name' => (string) $domain->domain,
                    'domain_normalized' => $normalized,
                    'registrar' => (string) ($domain->registrar ?? ''),
                    'whmcs_status' => 'Active',
                    'tracking_status' => 'active',
                    'inactive_check_count' => 0,
                    'candidate_run_token' => null,
                    'candidate_whmcs_status' => null,
                    'last_seen_active_at' => $now,
                    'pending_since' => null,
                    'updated_at' => $now,
                ];
                $existingStatus = Capsule::table(Schema::STATUS)->where('domain_id', $domainId)->first();
                if ($existingStatus) {
                    Capsule::table(Schema::STATUS)->where('domain_id', $domainId)->update($values);
                } else {
                    Capsule::table(Schema::STATUS)->insert(['domain_id' => $domainId, 'created_at' => $now] + $values);
                }
            }

            $existing = Capsule::table(Schema::STATUS)->pluck('domain_id')->map(static fn ($id): int => (int) $id)->all();
            $inactiveIds = array_values(array_diff($existing, $activeIds));
            if ($inactiveIds === []) return;

            $currentStatuses = Capsule::table('tbldomains')->whereIn('id', $inactiveIds)->pluck('status', 'id')->all();
            foreach (array_chunk($inactiveIds, 500) as $chunk) {
                $rows = Capsule::table(Schema::STATUS)->whereIn('domain_id', $chunk)->get();
                foreach ($rows as $row) {
                    $status = isset($currentStatuses[$row->domain_id]) ? (string) $currentStatuses[$row->domain_id] : 'Not Found';
                    Capsule::table(Schema::STATUS)->where('id', $row->id)->update([
                        'tracking_status' => 'pending_recheck',
                        'whmcs_status' => $status,
                        'candidate_run_token' => $token,
                        'candidate_whmcs_status' => $status,
                        'pending_since' => $row->pending_since ?: $now,
                        'updated_at' => $now,
                    ]);
                }
            }
            });

            return $this->runs->create($token, $adminId, count($activeDomains));
        } finally {
            $connection->selectOne("SELECT RELEASE_LOCK('alphavision_domain_status_monitor_inventory') AS released");
        }
    }

    public function finalize(string $token, int $threshold): array
    {
        $removed = 0;
        $pending = 0;
        Capsule::connection()->transaction(static function () use ($token, $threshold, &$removed, &$pending): void {
            $rows = Capsule::table(Schema::STATUS)->where('candidate_run_token', $token)->lockForUpdate()->get();
            foreach ($rows as $row) {
                $count = (int) $row->inactive_check_count + 1;
                if ($count >= $threshold) {
                    Capsule::table(Schema::STATUS)->where('id', $row->id)->delete();
                    $removed++;
                    continue;
                }
                Capsule::table(Schema::STATUS)->where('id', $row->id)->update([
                    'inactive_check_count' => $count,
                    'candidate_run_token' => null,
                    'candidate_whmcs_status' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $pending++;
            }
        });
        return ['removed' => $removed, 'pending' => $pending];
    }
}
