<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Service;

use Alphavision\DomainMonitor\Dns\DnsResolver;
use Alphavision\DomainMonitor\Domain\DomainNormalizer;
use Alphavision\DomainMonitor\Domain\InfrastructureMatcher;
use Alphavision\DomainMonitor\Repository\DomainStatusRepository;
use Alphavision\DomainMonitor\Repository\RunRepository;
use Alphavision\DomainMonitor\Repository\SettingsRepository;

final class RefreshService
{
    public function __construct(
        private readonly DomainStatusRepository $statuses,
        private readonly RunRepository $runs,
        private readonly SettingsRepository $settings,
        private readonly InfrastructureTargetProvider $infrastructure,
        private readonly InventoryService $inventory
    ) {
    }

    public function process(string $token): array
    {
        $connection = \WHMCS\Database\Capsule::connection();
        $lockName = 'alphavision_domain_status_monitor_' . substr(hash('sha256', $token), 0, 24);
        $lock = $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw new \RuntimeException('Este lote já está sendo processado por outra requisição.');
        }

        try {
            $run = $this->runs->findRunningByToken($token);
            if ($run === null) throw new \RuntimeException('Execução não encontrada ou já concluída.');

            $batchSize = max(1, min(50, (int) $this->settings->get('batch_size', '10')));
            $batch = $this->statuses->activeBatch((int) $run['processed_domains'], $batchSize);
            $checker = $this->checker();
            $successful = 0;
            $failed = 0;

            foreach ($batch as $domain) {
                $result = $checker->check((string) $domain['domain_name']);
                $this->statuses->saveCheck((int) $domain['id'], $result);
                $result['query_result'] === 'success' ? $successful++ : $failed++;
            }

            $processed = count($batch);
            if ($processed > 0) {
                $this->runs->advance((int) $run['id'], $processed, $successful, $failed);
            }
            $newProcessed = (int) $run['processed_domains'] + $processed;
            $complete = $newProcessed >= (int) $run['total_domains'] || $processed === 0;
            $finalized = ['removed' => 0, 'pending' => 0];
            if ($complete) {
                $threshold = max(1, min(100, (int) $this->settings->get('inactive_checks_before_removal', '2')));
                $finalized = $this->inventory->finalize($token, $threshold);
                $this->runs->complete((int) $run['id']);
            }

            return [
                'complete' => $complete,
                'total' => (int) $run['total_domains'],
                'processed' => $newProcessed,
                'successful' => (int) $run['successful_domains'] + $successful,
                'failed' => (int) $run['failed_domains'] + $failed,
                'removed' => $finalized['removed'],
                'pending' => $finalized['pending'],
            ];
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }

    public function refreshOne(int $statusId): array
    {
        $domain = $this->statuses->find($statusId);
        if ($domain === null || $domain['tracking_status'] !== 'active') {
            throw new \RuntimeException('Domínio ativo não encontrado.');
        }
        $result = $this->checker()->check((string) $domain['domain_name']);
        $this->statuses->saveCheck($statusId, $result);
        return $result;
    }

    private function checker(): DomainChecker
    {
        $resolverText = $this->settings->get('resolver_ips', '');
        $resolverIps = preg_split('/[\s,;]+/', $resolverText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $resolverIps = array_values(array_filter($resolverIps, static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false));
        $timeout = (float) $this->settings->get('dns_timeout', '3.0');
        return new DomainChecker(
            new DnsResolver($resolverIps, $timeout),
            new InfrastructureMatcher($this->infrastructure->all()),
            new DomainNormalizer()
        );
    }
}
