# CLAUDE.md

Template de sistemas grandes (multi-tenant): Laravel 12, Filament 5, Shield, Stancl Tenancy
(um banco PostgreSQL por cliente), Vue/Vuetify nas páginas de cliente. Cada projeto novo
nasce de uma cópia deste repositório e evolui sozinho. Detalhes de uso: `README.md`.

Responda e escreva textos de tela, mensagens e comentários em português (pt-BR).

## Regra principal: código sempre com o plugin ECC

Todo trabalho de código neste projeto usa o plugin ECC (`affaan-m/ECC`, instalado em
`~/.claude/plugins/cache/ecc`): agentes (Agent tool, `ecc:<nome>`), skills (Skill tool,
`ecc:<nome>`) e comandos (`/<nome>`). Não improvise o que o plugin já cobre. Antes de
começar, escolha a ferramenta na tabela; depois de terminar, passe pela revisão.

| Quando | Use |
|---|---|
| Funcionalidade nova ou mudança grande | `/plan` (espera confirmação antes de mexer em código); para guiar do começo ao fim, `/feature-dev` |
| Decisão de arquitetura, nova camada, novo módulo | agente `ecc:architect`; para mapear o que já existe, `ecc:code-explorer` |
| Escrever código Laravel (controller, Eloquent, filas, eventos, jobs) | skill `ecc:laravel-patterns` |
| Escrever testes ou corrigir bug | skill `ecc:laravel-tdd` e agente `ecc:tdd-guide` (teste primeiro, depois o código) |
| Antes de dar algo como pronto | skill `ecc:laravel-verification`, depois `/quality-gate` |
| Migration, query, índice, modelagem no PostgreSQL | skill `ecc:database-migrations`, `ecc:postgres-patterns` e agente `ecc:database-reviewer` |
| Entrada de usuário, login, tokens, upload, assinatura, webservice de entrada | skill `ecc:laravel-security` e agente `ecc:security-reviewer` |
| Revisar PHP depois de escrever ou alterar | agente `ecc:php-reviewer` (sempre), e `/code-review` no que está sem commit |
| Código Vue (`views/`) | skill `ecc:vue-patterns` e `/vue-review` |
| Lentidão, consultas demais, memória | agente `ecc:performance-optimizer` |
| Tratamento de erro, `catch` vazio, falha engolida | agente `ecc:silent-failure-hunter` |
| Limpar código morto ou duplicado | `/refactor-clean` ou agente `ecc:refactor-cleaner`; simplificar com `ecc:code-simplifier` |
| Erro de build (npm, Vite, composer) | `/build-fix` |
| Cobertura de testes fraca | `/test-coverage` |
| README, mapa do projeto | `/update-docs` e `/update-codemaps` |
| Mensagem de commit | agente `ecc:commit-reviewer` (Conventional Commits, no padrão do histórico) |
| Revisar PR | `/review-pr` |
| Auditoria de segurança do ambiente do agente | `/security-scan` |

O plugin não tem skill de Filament: para telas Filament use `ecc:laravel-patterns` mais as
convenções abaixo.

Fluxo padrão de uma mudança: `/plan` → teste primeiro (`ecc:laravel-tdd`) → implementar →
`ecc:php-reviewer` (+ `ecc:database-reviewer` se tocou em migration ou query, + `ecc:security-reviewer`
se tocou em entrada de usuário ou autenticação) → `vendor/bin/pint` e `php artisan test` →
`/quality-gate`.

## Convenções do projeto

- **Dois bancos, dois conjuntos de migrations.** O que vale para a base pai fica em
  `database/migrations` e o que vale para os clientes em `database/migrations/tenant`. Estruturas
  compartilhadas (webservices, plataforma, permissões) têm a mesma migration nas duas pastas.
  Ao mexer em estrutura já migrada, siga o padrão do projeto: consolidar na migration de criação
  e avisar que bancos já migrados precisam do ajuste.
- **Arquivos do dicionário só no botão "Criar arquivos".** Salvar módulo, tabela, formulário ou
  gerenciamento só grava o cadastro. Nunca gere migration, model, controller ou Resource
  (`app/Modulos/...`) por conta própria sem o usuário pedir. Código escrito à mão em `app/Modulos`
  fica fora do bloco `<estrutura>` do model.
- **Permissões.** Toda tela nova tem policy do Shield (`php artisan shield:generate --resource=...`)
  e uma migration que cria as permissões na base pai e nos clientes.
- **Nomes de parâmetro do Filament** em closures: `$record`, `$state`, `$data`, `$query`, `$get`,
  `$set`, `$livewire`. Outro nome não é injetado (já causou erro em tela).
- **Cache dentro de cliente.** O cache padrão usa tags (Tenancy) e o driver `database` não
  suporta. Use `Cache::store('central')` para o que não deve ser separado por cliente.
- **Textos de tela em português**, com `app/Webservice` e `app/Plataforma` como exemplo de estilo.
- **Sem regra de negócio no template:** só recursos de base (documentos, impressão, assinatura,
  webservices, dicionário, permissões).

## Testes

- `php artisan test` usa o banco `obra_facil_testing` (nunca o de desenvolvimento).
- Telas Filament: teste com Livewire (`Livewire::test(...)`, `TestAction`), como em `tests/Feature`.
- Teste que gera arquivos de verdade (dicionário) precisa apagar tudo no `afterEach`: arquivos,
  tabelas e linhas de migration.
- Rode `vendor/bin/pint` antes de commitar.

## Combinados de trabalho

- **Não commite nem dê push sem o usuário pedir.** O único remoto é `origin`
  (`paulo7646/multi-tenancy`); o repositório obra_facil foi excluído.
- **Nada destrutivo no banco de desenvolvimento** (apagar linhas, `migrate:fresh`, rollback)
  sem pedir. Dados de teste que você criou, remova no fim e diga o que removeu.
- Se o que o plugin sugere conflita com uma convenção acima, vale a convenção do projeto.
- Diga com clareza o que foi testado de verdade e o que não foi.
