# Arquitetura consolidada da versão 1.0.4

## Escopo

O addon é uma ferramenta administrativa de consulta sob demanda. Não é um monitor contínuo e não possui cron, alertas, widget ou integração com módulos registradores.

O identificador definitivo do addon é `alphavision_domain_status_monitor`, utilizado no diretório, arquivo principal, funções públicas, rotas administrativas e configurações nativas.

## Fonte de dados

Todos os domínios com `tbldomains.status = Active`, independentemente de TLD ou registrador.

## Atualização

O botão **Atualizar lista** inicia uma execução manual com identificador único. Primeiro reconcilia o inventário e depois verifica DNS em lotes. Somente uma execução pode permanecer ativa.

Domínios fora de `Active` acumulam uma observação por sincronização completa. O limite é configurável. Ao atingir o limite, o resultado local é excluído. Se voltarem para `Active` antes disso, o contador é zerado.

## Estados técnicos

Delegação: `managed`, `external`, `no_delegation`, `inconsistent`, `not_verified`, `query_error`.

Destino: `managed`, `external`, `no_resolution`, `partial`, `inconsistent`, `not_verified`, `query_error`.

Os valores persistidos são neutros. O nome configurável da infraestrutura é aplicado somente na interface.

## Identificação da infraestrutura

A fonte é híbrida. O módulo pode extrair automaticamente hostname, IPs e nameservers dos servidores habilitados em `tblservers` e combinar esses dados com hostnames, IPs e blocos CIDR informados na configuração nativa do addon.

A tabela interna de targets permanece compatível com registros criados durante builds anteriores, mas a interface operacional não edita configurações.

## Segurança

* Área exclusivamente administrativa.
* Proteção CSRF própria associada à sessão administrativa.
* Queries por Capsule.
* Validação estrita de domínios, IPs e CIDR.
* Nenhuma execução de shell.
* CSS e JavaScript escopados ao módulo.
* Atualizações administrativas via POST.

## Banco

* `mod_av_domainmonitor_status`: inventário atual e último resultado.
* `mod_av_domainmonitor_targets`: compatibilidade com sinais de infraestrutura previamente cadastrados.
* `mod_av_domainmonitor_settings`: backup das configurações nativas para preservação durante desativação e reativação.
* `mod_av_domainmonitor_runs`: controle transitório das atualizações manuais.

## Dependência DNS

NetDNS2 2.0.8 incorporado ao pacote. Por padrão utiliza os resolvedores configurados no servidor. Google e Cloudflare não são configurados automaticamente.

Quando o PHP não puder ler `/etc/resolv.conf`, a resolução passa automaticamente para `dns_get_record`. Esse fallback continua usando o mecanismo DNS nativo do servidor e não injeta resolvedores externos.

## Diagnóstico

A aba de diagnóstico é somente leitura e verifica compatibilidade, dependências, banco, configurações, inventário, sinais de infraestrutura e execuções pendentes. Uma consulta A para `example.com` confirma o funcionamento efetivo do resolvedor selecionado.
