# Revisão do AERO — instruções para três agentes Grok

**Escopo:** revisão de código, evidências e propostas. Este projeto contém apenas código AERO recuperado parcialmente do PR1 News; não é o AERO V13 independente completo.

## Agente 1 — Meteorologia
Verificar validade dos dados, radar, INMET, status LIVE/DESATUALIZADO/INDISPONÍVEL, cache, falhas e licenças comerciais Open-Meteo/RainViewer. Raios: sempre INDISPONÍVEL enquanto não houver fonte autorizada, configurada e recente.

## Agente 2 — PHP/WordPress e segurança
Examinar REST, permissões, SSRF, escaping, XSS, sanitização, configuração PR1_AERO_HOST, CSP, SRI, vazamento de dados e travas comerciais. Não habilitar licenças, dados simulados ou integrações.

## Agente 3 — Testes, acessibilidade e UX
Validar testes existentes, navegação mobile, mapa com alternativa textual, leitura de tela, foco, aria-live, contraste, desempenho e documentação. Identificar os testes que não puderem ser executados.

## Critérios do relatório
Para cada achado: severidade, arquivo e linha, causa, risco, como reproduzir, evidência, correção mínima e teste de regressão. Separar testado de não testado. Propor PR em rascunho somente com aprovação de escrita.

## Restrições
Não fazer deploy, merge, force-push, publicação, cron, coleta automática, IA, pagamento, envio de mensagens nem alteração de Vercel/Railway. Não editar o repositório `AxisCapitalTech/portal-regional`. Manter intactos `enabled=0`, `ai_enabled=0`, `pending`, `prevent_unreviewed_publish` e `record_editorial_approval`.
