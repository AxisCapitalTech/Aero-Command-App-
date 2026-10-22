# AERO Command Center — fonte recuperada para revisão técnica

**Status em 22/10/2026:** código recuperado **parcialmente** do módulo AERO nativo do PR1 News. Este repositório está **público temporariamente para revisão do Grok**, conforme autorização do proprietário. Não há segredos ou credenciais de produção neste conjunto. Evite adicionar chaves ou dados pessoais enquanto público.

> **NÃO É A IMPLEMENTAÇÃO COMPLETA DO AERO V13 INDEPENDENTE.** A aplicação original hospedada na Vercel e os backends `/api/inmet-alerts`, `/api/lightning`, `/api/radar-frame` não estão totalmente recuperados. Não afirmar que está pronto para publicar ou que raios estão LIVE.

## Estrutura

- `wordpress-adapter/wp-content/themes/portal-regional-tema/`: somente os módulos do AERO incorporados ao tema PR1 News (não é o tema completo; copiar ao portal sem revisão pode causar regressões).
- `wordpress-adapter/tests/`: testes e fixtures do AERO nativo.
- `docs/`: proveniência e pendências.
- `AGENTS.md` e `docs/REVISAO-GROK.md`: orientação da auditoria por agentes.

## Execução de testes offline

```bash
node wordpress-adapter/tests/js/test-aero-state.js
php -l wordpress-adapter/wp-content/themes/portal-regional-tema/includes/pr1-aero-logic.php
php wordpress-adapter/tests/test-aero-native.php
```

Esses comandos não comprovam WordPress+MariaDB ou execução do AERO completo. Testes de rede/serviços só em homologação autorizada.

## Segurança e operação

- Nenhum merge/deploy e nenhuma ativação automática de coleta, cron, IA, newsletter, pagamento, push, e-mail ou WhatsApp.
- Módulos de fornecedores precisam de autorização contratual/comercial antes do uso em produção.
- Sem relatar dados simulados como tempo atual ou alertas oficiais.
- Raios permanecem INDISPONÍVEIS sem fonte autorizada que comprove frescor.
- Revisar qualquer contribuição externa antes de integrar no `portal-regional`.

Repositório-alvo de revisão: `AxisCapitalTech/Aero-Command-App-` (público por decisão do proprietário; tornar privado antes de inserir configurações privadas).
