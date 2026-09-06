# Alphavision WHMCS Domain Status Monitor

## Especificação funcional consolidada da versão 1.0.4

**Ambiente inicial:** WHMCS 9.0.5 e PHP 8.3  
**Compatibilidade declarada:** WHMCS 9.0.x, PHP 8.2 e PHP 8.3  
**Tipo:** Addon administrativo com hooks estruturais  
**Operação:** consulta manual sob demanda

**Identificador definitivo:** `alphavision_domain_status_monitor`

## 1. Objetivo

Exibir os domínios ativos cadastrados no WHMCS e consultar seu estado DNS, independentemente do TLD e do registrador.

O addon informa:

* delegação por nameservers;
* classificação dos nameservers;
* resolução do domínio raiz e de `www`;
* classificação dos destinos;
* estado consolidado;
* data e resultado da última consulta.

O addon não registra, renova, transfere, altera DNS ou executa monitoramento contínuo.

## 2. Fonte de domínios

A fonte é `tbldomains`. Todos os registros com status `Active` participam da verificação, sem restrição por registrador ou TLD.

O registrador é apenas informação e filtro.

## 3. Atualização manual

O botão **Atualizar lista**:

1. reconcilia os registros locais com `tbldomains`;
2. adiciona domínios ativos novos;
3. restaura domínios que voltaram para `Active`;
4. marca domínios fora de `Active` como pendentes;
5. processa DNS dos ativos em lotes;
6. ao concluir toda a execução, confirma uma observação de inatividade;
7. remove registros que atingiram o limite configurado.

Recarregar a página, aplicar filtros, atualizar um domínio isolado ou processar um lote não conta como nova consulta de inventário.

## 4. Tolerância para domínios fora de Active

A configuração **Consultas consecutivas antes da remoção** aceita valores de 1 a 100 e inicia em 2.

Com valor 2:

* primeira atualização completa fora de `Active`: `Pendente de nova verificação`;
* segunda atualização completa consecutiva fora de `Active`: remoção física do resultado local.

Qualquer status diferente de `Active`, inclusive registro não encontrado, conta como inativo. Mudança entre dois status inativos não zera o contador. O retorno para `Active` zera o contador.

Domínios pendentes preservam o último resultado, mas não recebem nova consulta DNS.

Não existe arquivamento permanente. A tabela contém apenas ativos e pendentes.

## 5. Consulta DNS

Tipos consultados:

* NS;
* SOA;
* A;
* AAAA;
* CNAME.

Hosts consultados:

* domínio raiz;
* `www`.

CNAME é seguido por no máximo oito saltos e possui detecção de loop.

São tratados NXDOMAIN, SERVFAIL, REFUSED, timeout, resposta vazia, múltiplos endereços e IPv6.

NetDNS2 2.0.8 é incorporado ao módulo. Sem configuração explícita, utiliza os resolvedores do servidor. Nenhum resolvedor público é adicionado silenciosamente.

Se `/etc/resolv.conf` não estiver acessível ao PHP, o módulo utiliza `dns_get_record` como fallback nativo. O fallback não adiciona Google, Cloudflare ou qualquer resolvedor externo.

## 6. Infraestrutura configurável

O nome da infraestrutura é configurável e aplicado apenas na interface. Estados internos permanecem neutros.

Por padrão, o addon lê os servidores habilitados em `tblservers` e aproveita os campos disponíveis de hostname, IP principal, IPs atribuídos, nameservers e IPs dos nameservers.

A descoberta automática pode ser desativada. A configuração nativa do addon também aceita hostnames, IPs e redes adicionais para elementos que não estejam cadastrados como servidores no WHMCS.

Sinais suportados:

* nameserver exato;
* sufixo de nameserver;
* IPv4;
* rede IPv4 CIDR;
* IPv6;
* rede IPv6 CIDR;
* host ou CNAME exato;
* sufixo de host ou CNAME.

Hostnames iniciados por `*.` são tratados como sufixos. Hostnames sem curinga são exatos. Não há regex livre nem associação silenciosa com Google, Cloudflare ou qualquer outro provedor.

## 7. Estados de delegação

* `managed`: todos os NS pertencem à infraestrutura configurada;
* `external`: nenhum NS pertence à infraestrutura;
* `no_delegation`: NXDOMAIN ou resposta válida sem NS;
* `inconsistent`: mistura entre NS configurados e externos;
* `not_verified`: sem consulta;
* `query_error`: falha técnica.

## 8. Estados de destino

* `managed`: os sinais resolvidos pertencem à infraestrutura configurada;
* `external`: os sinais resolvidos são externos;
* `no_resolution`: raiz e `www` não resolvem;
* `partial`: somente raiz ou `www` resolve;
* `inconsistent`: coexistem sinais internos e externos;
* `not_verified`: sem consulta;
* `query_error`: falha técnica sem resultado conclusivo.

## 9. Estado consolidado

O consolidado é derivado dos estados anteriores:

* OK + nome configurado;
* DNS externo + infraestrutura configurada;
* DNS configurado + destino externo;
* Infraestrutura externa;
* Sem apontamento;
* Requer atenção;
* Desconhecido.

## 10. Interface

* Cards de resumo;
* lista paginada;
* filtros por acompanhamento, DNS, destino e registrador;
* pesquisa por domínio ou cliente;
* atualização geral com barra de progresso;
* atualização individual;
* resumo somente leitura da configuração ativa;
* configurações editáveis somente na configuração nativa do addon;
* bloco de apoio ao projeto gratuito;
* aba de diagnóstico somente leitura com teste DNS efetivo;
* IDs contextualizados ao lado de domínio e cliente;
* nome curto `Status de Domínios` somente no menu Addons.

## 11. Recursos excluídos

* cron;
* widget da home do WHMCS;
* alertas;
* histórico permanente de mudanças;
* confirmação dupla de resultado DNS;
* integração com Registro.br ou qualquer registrador;
* registro, renovação e transferência;
* HTTP, uptime e SSL;
* DNSSEC avançado;
* SPF, DKIM e DMARC;
* alteração automática de DNS.

## 12. Desativação

A desativação preserva configurações, sinais legados e resultados atuais. As configurações nativas são copiadas para a tabela interna antes da desativação e restauradas na reativação. Não há exclusão automática das tabelas.
