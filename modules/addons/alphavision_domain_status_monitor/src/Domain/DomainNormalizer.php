<?php
declare(strict_types=1);

namespace Alphavision\DomainMonitor\Domain;

use InvalidArgumentException;

final class DomainNormalizer
{
    public function normalize(string $domain): string
    {
        $domain = strtolower(rtrim(trim($domain), '.'));
        if ($domain === '') {
            throw new InvalidArgumentException('Domínio vazio.');
        }

        if (preg_match('/[^\x20-\x7E]/', $domain)) {
            if (!function_exists('idn_to_ascii')) {
                throw new InvalidArgumentException('Domínio internacional requer a extensão PHP Intl.');
            }
            $converted = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($converted === false) {
                throw new InvalidArgumentException('Domínio internacional inválido.');
            }
            $domain = strtolower($converted);
        }

        if (strlen($domain) > 253 || !str_contains($domain, '.')) {
            throw new InvalidArgumentException('Nome de domínio inválido.');
        }

        foreach (explode('.', $domain) as $label) {
            if ($label === '' || strlen($label) > 63 || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) {
                throw new InvalidArgumentException('Nome de domínio inválido.');
            }
        }

        return $domain;
    }
}

