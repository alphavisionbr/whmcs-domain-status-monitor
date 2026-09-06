<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Database;

use WHMCS\Database\Capsule;

final class Schema
{
    public const STATUS = 'mod_av_domainmonitor_status';
    public const TARGETS = 'mod_av_domainmonitor_targets';
    public const SETTINGS = 'mod_av_domainmonitor_settings';
    public const RUNS = 'mod_av_domainmonitor_runs';

    public static function install(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable(self::STATUS)) {
            $schema->create(self::STATUS, static function ($table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('domain_id')->unique();
                $table->unsignedInteger('client_id')->nullable()->index();
                $table->string('domain_name', 255);
                $table->string('domain_normalized', 255)->index();
                $table->string('registrar', 100)->nullable()->index();
                $table->string('whmcs_status', 50)->nullable();
                $table->string('tracking_status', 32)->default('active')->index();
                $table->unsignedInteger('inactive_check_count')->default(0);
                $table->string('candidate_run_token', 64)->nullable()->index();
                $table->string('candidate_whmcs_status', 50)->nullable();
                $table->string('ns_status', 32)->default('not_verified')->index();
                $table->string('destination_status', 32)->default('not_verified')->index();
                $table->string('consolidated_status', 64)->default('unknown')->index();
                $table->longText('nameservers_json')->nullable();
                $table->longText('ipv4_json')->nullable();
                $table->longText('ipv6_json')->nullable();
                $table->longText('cname_json')->nullable();
                $table->longText('soa_json')->nullable();
                $table->string('query_result', 32)->default('not_verified')->index();
                $table->string('error_code', 64)->nullable();
                $table->text('error_message')->nullable();
                $table->dateTime('checked_at')->nullable()->index();
                $table->dateTime('last_success_at')->nullable();
                $table->dateTime('last_seen_active_at')->nullable();
                $table->dateTime('pending_since')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
            });
        }

        if (!$schema->hasTable(self::TARGETS)) {
            $schema->create(self::TARGETS, static function ($table): void {
                $table->bigIncrements('id');
                $table->string('type', 32)->index();
                $table->string('value', 255);
                $table->string('normalized_value', 255);
                $table->string('label', 120)->nullable();
                $table->boolean('enabled')->default(true)->index();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->unique(['type', 'normalized_value'], 'avdm_target_unique');
            });
        }

        if (!$schema->hasTable(self::SETTINGS)) {
            $schema->create(self::SETTINGS, static function ($table): void {
                $table->string('setting_key', 100)->primary();
                $table->text('setting_value')->nullable();
                $table->dateTime('updated_at');
            });
        }

        if (!$schema->hasTable(self::RUNS)) {
            $schema->create(self::RUNS, static function ($table): void {
                $table->bigIncrements('id');
                $table->string('run_token', 64)->unique();
                $table->unsignedInteger('admin_id')->nullable();
                $table->string('status', 24)->default('running')->index();
                $table->unsignedInteger('total_domains')->default(0);
                $table->unsignedInteger('processed_domains')->default(0);
                $table->unsignedInteger('successful_domains')->default(0);
                $table->unsignedInteger('failed_domains')->default(0);
                $table->text('error_message')->nullable();
                $table->dateTime('started_at');
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('updated_at');
            });
        }

        self::seedSettings();
        Capsule::table(self::SETTINGS)->where('setting_key', 'schema_version')->update([
            'setting_value' => '1.0.4',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function seedSettings(): void
    {
        $now = date('Y-m-d H:i:s');
        $defaults = [
            'schema_version' => '1.0.4',
            'infrastructure_name' => 'Alphavision',
            'inactive_checks_before_removal' => '2',
            'batch_size' => '10',
            'dns_timeout' => '3.0',
            'resolver_ips' => '',
        ];

        foreach ($defaults as $key => $value) {
            if (!Capsule::table(self::SETTINGS)->where('setting_key', $key)->exists()) {
                Capsule::table(self::SETTINGS)->insert([
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
