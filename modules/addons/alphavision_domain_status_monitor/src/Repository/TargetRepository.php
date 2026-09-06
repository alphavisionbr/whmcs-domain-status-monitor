<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Repository;

use Alphavision\DomainMonitor\Database\Schema;
use WHMCS\Database\Capsule;

final class TargetRepository
{
    public function all(bool $enabledOnly = false): array
    {
        $query = Capsule::table(Schema::TARGETS)->orderBy('type')->orderBy('normalized_value');
        if ($enabledOnly) {
            $query->where('enabled', 1);
        }
        return array_map(static fn ($row): array => (array) $row, $query->get()->all());
    }

    public function add(string $type, string $value, string $normalized, string $label): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table(Schema::TARGETS)->insert([
            'type' => $type,
            'value' => $value,
            'normalized_value' => $normalized,
            'label' => $label !== '' ? $label : null,
            'enabled' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function delete(int $id): void
    {
        Capsule::table(Schema::TARGETS)->where('id', $id)->delete();
    }

    public function toggle(int $id): void
    {
        $row = Capsule::table(Schema::TARGETS)->where('id', $id)->first();
        if ($row) {
            Capsule::table(Schema::TARGETS)->where('id', $id)->update([
                'enabled' => ((int) $row->enabled) === 1 ? 0 : 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
