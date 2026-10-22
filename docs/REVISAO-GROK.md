# Roteiro de revisão — AERO / Grok (22/10/2026)

## Alvo e limitações
Repositório público temporário para auditoria autorizada: `AxisCapitalTech/Aero-Command-App-`.
A integração AERO no PR1 News foi recuperada parcialmente. O projeto original independente V13 hospedado na Vercel não foi exportado integralmente. Não afirmar conclusão nem dados LIVE sem evidência.

## Distribuição
1. Agente meteorologia: consultar código REST, previsões, radar, INMET, validade temporal, licenças e fornecedores.
2. Agente backend/segurança: inspecionar todas as rotas, controle de host, escaping, cache, validação e operações externas.
3. Agente QA/acessibilidade: executar o que for possível com PHP/Node locais, documentar falhas, verificar navegação e a11y.

## Formato de entrega
- Resumo executivo do que funciona e do que é dependência externa.
- Achados por severidade com caminhos/linhas e evidências reais.
- Plano de patches pequenos, com testes de regressão.
- Lista explícita de testes não realizados.
- Nenhuma alteração em produção; todas as melhorias dependem de revisão e aprovação.

## Comandos de verificação quando o código nativo estiver importado
```bash
node wordpress-adapter/tests/js/test-aero-state.js
php -l wordpress-adapter/wp-content/themes/portal-regional-tema/includes/pr1-aero-logic.php
php wordpress-adapter/tests/test-aero-native.php
```
