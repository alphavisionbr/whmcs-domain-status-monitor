# Alphavision® WHMCS Domain Status Monitor

Addon administrativo para monitoramento e diagnóstico do estado DNS e da infraestrutura dos domínios ativos cadastrados no WHMCS.

**Versão atual:** 1.0.4

[English version](README.en.md)

## Recursos

- Sincronização manual dos domínios com status `Active`.
- Consulta de NS, SOA, A, AAAA e CNAME para domínio raiz e `www`.
- Classificação independente de DNS e infraestrutura.
- Descoberta automática da infraestrutura a partir dos servidores habilitados do WHMCS.
- Hostnames, IPs e redes adicionais configuráveis.
- Atualização geral em lotes e atualização individual.
- Reconciliação configurável de domínios que deixam de estar ativos.
- Aba de diagnóstico do ambiente.
- Fallback para `dns_get_record` quando o NetDNS2 não puder utilizar `/etc/resolv.conf`.
- Configurações concentradas na interface nativa de Addons do WHMCS.
- Nenhuma alteração no domínio, registrador ou DNS.

## Compatibilidade

- WHMCS 9.0.x
- PHP 8.2 e 8.3
- MySQL ou MariaDB compatível com o WHMCS

A versão deve ser homologada no ambiente de testes antes do uso em produção.

## Instalação

Extraia o ZIP instalável da Release diretamente na raiz da instalação do WHMCS.

Estrutura principal:

```text
modules/
└── addons/
    └── alphavision_domain_status_monitor/
```

Depois:

1. acesse **Configuração > Configurações do Sistema > Módulos Addon**;
2. ative **Alphavision® WHMCS Domain Status Monitor**;
3. defina os grupos administrativos autorizados;
4. revise as configurações do addon;
5. abra **Addons > Status de Domínios**;
6. clique em **Atualizar lista**.

O WHMCS documenta que Addon Modules ficam em `/modules/addons/` e são ativados e configurados pela interface de módulos Addon.

## Atualização das versões 1.0.0 e 1.0.1

As versões iniciais utilizavam o identificador `avdomainmonitor`.

Para atualizar:

1. desative o addon antigo `avdomainmonitor`;
2. remova somente `modules/addons/avdomainmonitor` após confirmar a desativação;
3. instale a versão atual;
4. ative o addon em `alphavision_domain_status_monitor`;
5. revise e salve as configurações.

As tabelas `mod_av_domainmonitor_*` são reutilizadas. A lista e os últimos resultados não são apagados pela atualização.

## Funcionamento

A lista considera registros de `tbldomains` com status `Active`.

Domínios novos entram como `Não verificado`. A atualização geral consulta o domínio raiz e `www` em lotes manuais.

Quando um domínio deixa de estar ativo, ele permanece pendente durante a quantidade configurada de sincronizações completas. Se continuar fora de `Active`, seu resultado é removido da tabela do módulo.

Servidores habilitados do WHMCS podem fornecer automaticamente hostnames, IPs e nameservers. Informações adicionais podem ser configuradas no próprio addon.

## Diagnóstico DNS

A aba **Diagnóstico** verifica o ambiente e executa uma consulta DNS de teste sem alterar configurações.

O módulo incorpora NetDNS2. Quando `/etc/resolv.conf` não puder ser lido, utiliza `dns_get_record` como fallback do resolvedor do próprio servidor.

Nenhum resolvedor público é adicionado automaticamente por esse fallback.

## Banco de dados

O módulo utiliza tabelas próprias com o prefixo:

```text
mod_av_domainmonitor_
```

Faça backup do banco de dados antes de desativar, remover ou atualizar o addon.

## Dependência incorporada

O pacote inclui:

- `mikepultz/netdns2`
- versão `2.0.8`
- licença MIT

Os avisos de terceiros estão em [docs/THIRD-PARTY-NOTICES.md](docs/THIRD-PARTY-NOTICES.md), e o texto integral da licença acompanha a biblioteca em:

```text
modules/addons/alphavision_domain_status_monitor/vendor/mikepultz/netdns2/LICENSE
```

## Documentação

- [Arquitetura](docs/ARQUITETURA.md)
- [Especificação funcional v1.0.4](docs/ESPECIFICACAO-FUNCIONAL-v1.0.4.md)
- [Componentes de terceiros](docs/THIRD-PARTY-NOTICES.md)
- [Histórico de versões](CHANGELOG.md)
- [Segurança](SECURITY.md)
- [Suporte](SUPPORT.md)

## Licença

O código do **Alphavision® WHMCS Domain Status Monitor** é distribuído sob a **MIT License**.

A dependência NetDNS2 mantém sua própria licença MIT e respectivos avisos.

## Alphavision®

Desenvolvido e mantido pela **Alphavision®**.

**Projeto:** https://github.com/alphavisionbr/whmcs-domain-status-monitor  
**Site:** https://alphavision.com.br  
**Contato:** contato@alphavision.com.br

---

**Alphavision®**  
Web Platforms, Cloud Infrastructure & Digital Solutions
