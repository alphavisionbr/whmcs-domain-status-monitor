# Alphavision® WHMCS Domain Status Monitor

Administrative WHMCS addon for monitoring and diagnosing DNS and infrastructure status for active domains.

**Current version:** 1.0.4

[Versão em Português](README.md)

## Features

- Manual synchronization of WHMCS domains with `Active` status.
- NS, SOA, A, AAAA and CNAME queries for the root domain and `www`.
- Independent DNS and infrastructure classification.
- Automatic infrastructure discovery from enabled WHMCS servers.
- Configurable additional hostnames, IP addresses and networks.
- Batch and individual refresh operations.
- Configurable reconciliation for domains that are no longer active.
- Environment diagnostics.
- `dns_get_record` fallback when NetDNS2 cannot read `/etc/resolv.conf`.
- Native WHMCS Addon Module configuration.

## Compatibility

- WHMCS 9.0.x
- PHP 8.2 and 8.3
- MySQL or MariaDB supported by WHMCS

Test the addon in a staging environment before production use.

## Installation

Extract the installable Release ZIP at the root of the WHMCS installation:

```text
modules/
└── addons/
    └── alphavision_domain_status_monitor/
```

Then activate **Alphavision® WHMCS Domain Status Monitor** in the WHMCS Addon Modules configuration and review its settings.

## Upgrading from 1.0.0 or 1.0.1

Early releases used the `avdomainmonitor` identifier. Disable the old addon before removing its directory and activating `alphavision_domain_status_monitor`.

Existing `mod_av_domainmonitor_*` tables are reused.

## Bundled dependency

The distribution includes `mikepultz/netdns2` version 2.0.8 under the MIT License.

See [docs/THIRD-PARTY-NOTICES.md](docs/THIRD-PARTY-NOTICES.md).

## License

The Alphavision® project code is distributed under the **MIT License**. Bundled third-party components retain their respective licenses.

## Alphavision®

**Project:** https://github.com/alphavisionbr/whmcs-domain-status-monitor  
**Website:** https://alphavision.com.br
