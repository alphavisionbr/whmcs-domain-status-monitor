<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Repository;

use Alphavision\DomainMonitor\Database\Schema;
use WHMCS\Database\Capsule;

final class RunRepository
{
    public function running(): ?array
    {
        $row = Capsule::table(Schema::RUNS)->where('status', 'running')->orderByDesc('id')->first();
        if (!$row) return null;

        if (strtotime((string) $row->updated_at) < time() - 7200) {
            Capsule::table(Schema::RUNS)->where('id', $row->id)->update([
                'status' => 'failed', 'error_message' => 'Execução interrompida por inatividade.',
                'completed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            Capsule::table(Schema::STATUS)->where('candidate_run_token', $row->run_token)->update([
                'candidate_run_token' => null, 'candidate_whmcs_status' => null,
            ]);
            return null;
        }
        return (array) $row;
    }

    public function create(string $token, int $adminId, int $total): array
    {
        $now = date('Y-m-d H:i:s');
        $id = Capsule::table(Schema::RUNS)->insertGetId([
            'run_token' => $token, 'admin_id' => $adminId ?: null, 'status' => 'running',
            'total_domains' => $total, 'processed_domains' => 0, 'successful_domains' => 0,
            'failed_domains' => 0, 'started_at' => $now, 'updated_at' => $now,
        ]);
        return (array) Capsule::table(Schema::RUNS)->where('id', $id)->first();
    }

    public function findRunningByToken(string $token): ?array
    {
        $row = Capsule::table(Schema::RUNS)->where('run_token', $token)->where('status', 'running')->first();
        return $row ? (array) $row : null;
    }

    public function advance(int $id, int $processed, int $successful, int $failed): void
    {
        Capsule::table(Schema::RUNS)->where('id', $id)->update([
            'processed_domains' => Capsule::raw('processed_domains + ' . (int) $processed),
            'successful_domains' => Capsule::raw('successful_domains + ' . (int) $successful),
            'failed_domains' => Capsule::raw('failed_domains + ' . (int) $failed),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function complete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table(Schema::RUNS)->where('id', $id)->update([
            'status' => 'completed', 'completed_at' => $now, 'updated_at' => $now,
        ]);
        $keep = Capsule::table(Schema::RUNS)->orderByDesc('id')->limit(20)->pluck('id')->all();
        if ($keep !== []) Capsule::table(Schema::RUNS)->whereNotIn('id', $keep)->delete();
    }
}
