# Changelog

## 1.0.4

* Bloco `Configuração ativa` transferido da aba `Domínios` para a aba `Diagnóstico`.
* Aba `Domínios` mantida exclusivamente para indicadores, alertas e consulta operacional.
* Critérios exibidos permanecem somente para leitura e apontam para a configuração nativa do addon.

## 1.0.3

* Correção exclusiva do rótulo do menu Addons.
* Links internos do módulo passaram a ser explicitamente ignorados pelo fallback visual.
* A substituição exige URL do addon e correspondência exata com o nome oficial completo.
* Restaurados os rótulos independentes `Domínios` e `Diagnóstico` nas abas internas.

## 1.0.2

* Rótulo curto do menu reforçado com fallback visual restrito ao link do addon.
* Mantido o nome oficial completo somente na configuração do módulo.
* Fallback para `dns_get_record` quando `/etc/resolv.conf` não puder ser lido pelo NetDNS2.
* Nenhum resolvedor público é adicionado pelo fallback.
* Nova aba de diagnóstico inspirada no Auto Login.
* Verificação de WHMCS, PHP, NetDNS2, resolução DNS, banco, configurações, domínios, infraestrutura e execuções pendentes.
* Teste DNS real utilizando `example.com` e o resolvedor selecionado pelo módulo.

## 1.0.1

* Diretório do addon padronizado como `alphavision_domain_status_monitor`.
* Arquivo principal e funções públicas alinhados ao novo identificador.
* Migração das configurações vinculadas ao identificador anterior `avdomainmonitor`.
* Configurações editáveis mantidas exclusivamente na configuração nativa do addon.
* Tela operacional reconstruída conforme o padrão visual do Auto Login 2.1.3.
* Cabeçalho, cards, filtros, tabela e bloco de apoio ao projeto padronizados.
* Menu administrativo corrigido para `Status de Domínios`.
* Árvore de distribuição reconstruída sem pasta de testes ou arquivos do ambiente de desenvolvimento.

## 1.0.0

* Primeira versão.
* Sincronização manual dos domínios ativos do WHMCS.
* Tolerância configurável por sincronizações consecutivas fora de `Active`.
* Consulta de NS, SOA, A, AAAA e CNAME para domínio raiz e `www`.
* Classificação independente de DNS e infraestrutura.
* Nome da infraestrutura configurável.
* Atualização geral em lotes e atualização individual.
* Dashboard e tabelas alinhados ao padrão visual dos addons Alphavision.
* Bloco de apoio ao projeto gratuito alinhado aos demais módulos.
* Configurações concentradas na configuração nativa do addon.
* Descoberta automática da infraestrutura a partir dos servidores habilitados do WHMCS.
* Hostnames, IPs e redes adicionais configuráveis como complemento.
* Pacote instalável sem a pasta de testes de desenvolvimento.
* Rótulo do menu Addons corrigido para `Status de Domínios` sem alterar o nome oficial do módulo.
