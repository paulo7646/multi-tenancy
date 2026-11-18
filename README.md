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
php artisan template:init "Nome do Projeto"   # cria o .env, ajusta nome, domínio e banco, gera a chave
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

Salvar no dicionário só grava o cadastro. Os arquivos (migration, model, controller,
Resource, permissões, `database/md`) saem no botão **Criar arquivos** de cada tela
(módulo, tabela, formulário e gerenciamento), que mostra o que vai ser feito e só
gera depois da confirmação. Vale para a base pai e para os clientes. A tabela
precisa ter os arquivos criados antes do formulário e da tela. A migration é
testada numa transação desfeita antes de gerar.

- Tabelas **lista** e **conteúdo**: campos `NOME` e `VALOR`; os valores entram
  em "Valores da lista" e o botão "Gerar constantes" recria os enums do módulo.
- Tabela de **ligação** tem o parâmetro **Exibe o filho** (marcado por padrão): a tab page abre
  o formulário do filho (o outro lado da ligação) e a ligação não tem formulário nem gerenciamento
  (as abas deles somem). Desmarcado, ela ganha os dela e a tab page abre o formulário da própria ligação.
- Tabelas com **situação**: coluna `MD_STATUS_ID`, chave estrangeira de
  `md_status` (copiada para cada cliente com os mesmos ids).
- Um módulo vale para a base pai (`central`) ou para os clientes (`tenant`).

### Plataforma (`app/Plataforma`)

Recursos de base, sem regra de negócio, na base pai e nos clientes:

- **Documentos:** pastas, versões (cada envio novo guarda a anterior), tags, lixeira,
  cota por empresa e bloqueio de extensões perigosas. O disco é o `DOCUMENTOS_DISCO`
  (`local` por empresa, ou `s3`). Toda tela gerada pelo dicionário ganha a aba
  **Anexos**, que liga documentos ao registro.
- **Impressão:** toda tela gerada pelo dicionário ganha **Exportar** (PDF, Excel, CSV,
  respeitando filtros e busca) e **Imprimir** (ficha do registro em PDF). Os
  **Modelos de impressão** definem papel, título, rodapé e o corpo da ficha com
  marcadores `{CAMPO}`. O cabeçalho vem de **Dados da empresa** (nome, logo, rodapé).
- **Assinatura eletrônica:** pedido com vários assinantes, em ordem ou não. Cada
  um recebe um link pessoal por e-mail, confirma um código de 6 dígitos e assina;
  IP, navegador e hash do arquivo ficam gravados numa trilha encadeada por hash.
  Ao fim sai um certificado em PDF com QR, e `/verificar/{código}` confere a
  autenticidade (inclusive se o arquivo foi alterado). Só PDF.
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
`php artisan tenants:migrate`. A lista de empresas mostra quantas migrations
faltam em cada banco.

Com `WEBSERVICE_FILA=true` no `.env` (e `php artisan queue:work` rodando), o
botão Executar e o Atualizar todos rodam em segundo plano. Outras opções:
`WEBSERVICE_RETENCAO_DIAS` (integrações mais antigas são apagadas todo dia pelo
`webservice:limpar`) e `WEBSERVICE_LIMITE_POR_MINUTO` (limite da entrada `/ws`).

## Conexões com o banco

O Laravel abre **uma conexão por requisição** (na primeira consulta) e a reaproveita em todas as
consultas dela, mas a fecha no fim. Não existe fila de conexões abertas entre requisições. Abrir
uma conexão custa cerca de 35 ms (uma consulta simples, 0,15 ms), e com bancos por cliente
são duas por requisição (base pai e cliente).

Medido neste projeto (40 requisições na tela de login): 105 ms sem persistência, 61 ms com.

| Opção no `.env` | Efeito |
|---|---|
| `DB_PERSISTENT=true` | A conexão da **base pai** fica aberta no processo do PHP e a próxima requisição a reaproveita. Custo: 1 conexão por processo. Recomendado ligar. |
| `DB_TENANT_PERSISTENT=true` | O mesmo para os **clientes**. Desligado por padrão: cada processo guardaria uma conexão por cliente atendido (processos x clientes passam do `max_connections`) e o PostgreSQL não consegue apagar o banco de um cliente enquanto houver sessão aberta. |
| `DB_SSLMODE=disable` | Em banco local ou em rede privada, evita a negociação de SSL (`prefer` é o padrão). |
| `DB_EMULATE_PREPARES=true` | Só atrás de PgBouncer em modo transaction anterior à 1.21. |

Com `DB_PERSISTENT` ligado, o PostgreSQL precisa de `max_connections` maior que o número de processos do PHP. O `AppServiceProvider` desfaz no fim da requisição qualquer
transação que tenha ficado aberta, para ela não passar para a requisição seguinte.

Ao apagar um cliente, o `EncerrarSessoesDoBanco` encerra as sessões que ainda estiverem no
banco dele (conexão persistente ou pool deixam sessões abertas) antes do `DROP DATABASE`.

### Muitos clientes: PgBouncer (produção)

O **PgBouncer** (gratuito, código aberto) é o pool de verdade: fica entre o app e o PostgreSQL,
mantém conexões reais abertas, e empresta uma a cada transação (modo `transaction`). Serve também
para os bancos dos clientes, sem multiplicar conexões por processo. Com ele, `DB_PERSISTENT` e
`DB_TENANT_PERSISTENT` ficam desligados (o app só conecta no pooler, que é rápido).

1. Instale o PgBouncer no servidor (Linux: `apt install pgbouncer`).
2. No PostgreSQL, conectado ao **banco da base pai**, rode `deploy/pgbouncer/preparar-postgres.sql`
   (usuário `pgbouncer` e a função que devolve os hashes de senha, só de usuários que não são
   superusuários). Opcional: `ALTER ROLE <usuário do app> SET idle_in_transaction_session_timeout = '30s'`.
3. Copie `deploy/pgbouncer/pgbouncer.ini` para `/etc/pgbouncer/` e `userlist.txt.example` para
   `/etc/pgbouncer/userlist.txt` (com os hashes de `pgbouncer` e `pgbouncer_admin`). Troque
   `auth_dbname=obra_facil` pelo nome do banco da base pai: é nele que a consulta de senhas roda
   (os bancos dos clientes não têm a função). A linha `* = host=... port=5432` cobre a base pai e
   todos os bancos de clientes, inclusive os criados depois.
4. No `.env` do app:

```
DB_HOST=127.0.0.1          # o PgBouncer
DB_PORT=6432
DB_DIRECT_HOST=127.0.0.1   # o PostgreSQL de verdade
DB_DIRECT_PORT=5432
DB_SSLMODE=disable         # app e pooler na mesma máquina ou rede privada
DB_PERSISTENT=false
DB_TENANT_PERSISTENT=false
DB_EMULATE_PREPARES=false  # true só se o PgBouncer for anterior à 1.21
```

Com `DB_DIRECT_HOST` definido, vão **direto ao PostgreSQL**: `migrate`, `tenants:migrate*`,
`tenants:rollback`, `tenants:seed`, `tenants:run`, `db:*`, `schema:*` e também criar e apagar o
banco de um cliente pelo painel (o `CREATE` e o `DROP DATABASE`). O resto (requisições, filas,
agendamentos) passa pelo pooler. A volta ao pooler é garantida mesmo se o comando falhar.
`DB_DIRECT_SSLMODE` (padrão: o `DB_SSLMODE`) vale só para essa conexão direta.

Cuidados: em modo `transaction` não vale o que depende de sessão fixa (`SET` de sessão, inclusive
o `search_path` e o fuso que o Laravel define ao conectar, e travas de aplicação; hoje o padrão
`public` e UTF8 mascara isso); confira `SHOW POOLS;` e `SHOW STATS;` no console do PgBouncer
(`psql -p 6432 pgbouncer`); e `max_db_connections` x quantidade de clientes precisa caber no
`max_connections` do PostgreSQL. O PgBouncer não roda nativo no Windows: use-o no servidor Linux
e a conexão persistente na sua máquina de desenvolvimento.

## Testes

```bash
php artisan test
```

Usam o banco `obra_facil_testing` (crie-o no Postgres uma vez). Nunca rodam no
banco de desenvolvimento.

## Criar um projeto novo a partir do template

1. Copie o repositório (cada projeto tem o seu).
2. `php artisan template:init "Nome do Projeto"` ajusta `.env`, chave e deixa o dicionário vazio.
3. Apague os exemplos de `views/clientes/*` e crie as páginas do projeto, se houver.
4. Crie o banco central no PostgreSQL e siga a instalação acima.
