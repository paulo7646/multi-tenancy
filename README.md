# Template multi-tenant

Base para criar aplicações rapidamente (academia, gestão de obra, etc.). Cada
projeto novo nasce de uma cópia deste repositório, com banco central e bancos
de clientes próprios, e evolui sozinho: não há sincronização com o template.

Laravel 12, Filament 5, Shield (permissões), Stancl Tenancy (um banco por
cliente) e PostgreSQL.

## Instalação

```bash
composer install
npm install
cp .env.example .env        # ajuste APP_NAME, APP_URL e o banco
php artisan key:generate
php artisan migrate          # banco central
php artisan shield:super-admin
php artisan shield:generate --all
npm run build
```

O domínio central é o host do `APP_URL` (`config/tenancy.php`). No Herd, use o
mesmo nome do site (ex.: `meu-projeto.test`).

## Como a aplicação é dividida

| Parte | O que é |
|---|---|
| Painel `/admin` (central) | Empresas (clientes), domínios, licenças, dicionário de dados, webservices da base pai |
| Painel `/admin` do cliente | Usuários, filiais e as telas dos módulos do cliente |
| `database/migrations` | Banco central |
| `database/migrations/tenant` | Banco de cada cliente |

Cadastro de uma empresa: informe o domínio, o e-mail e a senha do
administrador. A pasta do frontend é opcional: sem ela, o endereço da empresa
abre direto o `/admin`. Com ela, `views/clientes/{pasta}` guarda uma página Vue
própria da empresa.

### Dicionário de dados

Telas em Dicionário (central). Descreve módulos, tabelas, campos, formulários e
gerenciamentos, e gera sozinho migrations, models, controllers e Resources em
`app/Modulos/{Modulo}/{TABELA}/`. O que o dicionário descreve também vai para
`database/md/*.json` (versionado), e `php artisan md:importar` recria
tudo a partir deles.

- Tabelas **lista** e **conteúdo**: campos `NOME` e `VALOR`; os valores entram
  em "Valores da lista" e o botão "Gerar constantes" recria os enums do módulo.
- Tabelas com **situação**: coluna `MD_STATUS_ID`, chave estrangeira de
  `md_status` (copiada para cada cliente com os mesmos ids).
- Um módulo vale para a base pai (`central`) ou para os clientes (`tenant`).

### Webservices (`app/Webservice`)

Cadastro de webservices consumidos ou disponibilizados, com operações,
parâmetros, versões de layout, objetos, eventos, campos de busca e atributos.
"Executar" busca a URL, guarda o arquivo e processa o retorno em lotes (cada
lote é uma transação com rollback). Agendamentos rodam pelo comando
`php artisan webservice:agendamentos` (agendado a cada minuto: mantenha o
`schedule:run` ativo no servidor). A entrada dos serviços disponibilizados é
`/ws/{id do webservice}/{rota}`.

## Atualizar os clientes

Depois de um deploy com migrations novas: botão **Atualizar** em cada empresa,
**Atualizar todos os clientes** no dashboard, ou
`php artisan tenants:migrate`.

## Testes

```bash
php artisan test
```

Usam o banco `obra_facil_testing` (crie-o no Postgres uma vez). Nunca rodam no
banco de desenvolvimento.

## Criar um projeto novo a partir do template

1. Copie o repositório e ajuste `APP_NAME`, `APP_URL` e `composer.json`/`package.json`.
2. Esvazie `database/md/*.json` do que não for do núcleo (módulos e tabelas).
3. Apague `views/clientes/*` de exemplo e crie as páginas do projeto.
4. Rode a instalação acima.
