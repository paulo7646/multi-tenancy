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
