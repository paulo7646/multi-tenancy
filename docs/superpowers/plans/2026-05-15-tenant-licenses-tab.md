# Tenant Licenses Tab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adicionar uma tab "Licenças" no TenantResource que exibe as licenças específicas do tenant e as globais, com capacidade de criar novas licenças diretamente.

**Architecture:** Criar um `UserLicensesRelationManager` dentro de `TenantResource/RelationManagers/` que sobrescreve `getTableQuery()` para incluir tanto licenças com `tenant_id = current_tenant` quanto licenças globais (`tenant_id = null`). O formulário de criação pré-preenche `tenant_id` com o tenant atual, permitindo também criar licenças globais. O RelationManager é registrado em `TenantResource::getRelations()`, o que faz a tab aparecer automaticamente nas páginas View e Edit.

**Tech Stack:** Laravel 11, Filament 3, PHP 8.2, stancl/tenancy

---

## Mapa de Arquivos

| Ação | Arquivo | Responsabilidade |
|------|---------|-----------------|
| Modificar | `app/Models/Tenant.php` | Adicionar relação `userLicenses()` |
| Criar | `app/Filament/Resources/TenantResource/RelationManagers/UserLicensesRelationManager.php` | Tab de licenças com query customizada |
| Modificar | `app/Filament/Resources/TenantResource.php` | Registrar o RelationManager |

---

### Task 1: Adicionar relação `userLicenses()` no model Tenant

**Files:**
- Modify: `app/Models/Tenant.php`

- [ ] **Step 1: Adicionar o import e o método de relação**

Abrir `app/Models/Tenant.php` e adicionar após o import existente e após o método `domains()`:

```php
use App\Models\UserLicense;
// já existe: use Illuminate\Database\Eloquent\Relations\HasMany;

public function userLicenses(): HasMany
{
    return $this->hasMany(UserLicense::class);
}
```

O arquivo final de `app/Models/Tenant.php` deve ficar:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function userLicenses(): HasMany
    {
        return $this->hasMany(UserLicense::class);
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'password',
            'is_active',
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function setPasswordAttribute($value)
    {
        return $this->attributes['password'] = Hash::Make($value);
    }
}
```

- [ ] **Step 2: Verificar que o PHP não apresenta erros de sintaxe**

```bash
php artisan about
```

Esperado: saída sem erros de sintaxe.

- [ ] **Step 3: Commit**

```bash
git add app/Models/Tenant.php
git commit -m "feat(COD-18): adicionar relação userLicenses no model Tenant"
```

---

### Task 2: Criar o `UserLicensesRelationManager`

**Files:**
- Create: `app/Filament/Resources/TenantResource/RelationManagers/UserLicensesRelationManager.php`

- [ ] **Step 1: Criar o arquivo do RelationManager**

Criar o arquivo `app/Filament/Resources/TenantResource/RelationManagers/UserLicensesRelationManager.php` com o seguinte conteúdo:

```php
<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use App\Models\UserLicense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class UserLicensesRelationManager extends RelationManager
{
    protected static string $relationship = 'userLicenses';

    protected static ?string $title = 'Licenças';

    protected static ?string $modelLabel = 'Licença';

    protected static ?string $pluralModelLabel = 'Licenças';

    /**
     * Sobrescreve a query para incluir licenças do tenant E licenças globais.
     * Licenças globais têm tenant_id = null e se aplicam a todos os tenants.
     */
    protected function getTableQuery(): Builder
    {
        $tenantId = $this->getOwnerRecord()->getKey();

        return UserLicense::query()
            ->where(function (Builder $query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                      ->orWhereNull('tenant_id');
            })
            ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('created_at', 'desc');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_email')
                    ->label('E-mail do Usuário')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('tenant_id')
                    ->label('Escopo')
                    ->options(function () {
                        $tenantId = $this->getOwnerRecord()->getKey();
                        $tenantName = $this->getOwnerRecord()->name
                            ?? $this->getOwnerRecord()->domain
                            ?? $tenantId;

                        return [
                            $tenantId => "Somente esta empresa ({$tenantName})",
                        ];
                    })
                    ->default(fn () => $this->getOwnerRecord()->getKey())
                    ->placeholder('Global (todas as empresas)')
                    ->nullable()
                    ->helperText('Deixe em branco para criar uma licença global válida em todas as empresas.'),

                Forms\Components\TextInput::make('license_key')
                    ->label('Chave da Licença')
                    ->default(fn () => Str::uuid()->toString())
                    ->readOnly()
                    ->required(),

                Forms\Components\Toggle::make('status')
                    ->label('Licença Ativa')
                    ->default(true)
                    ->afterStateHydrated(fn ($component, $state) => $component->state($state === 'active'))
                    ->dehydrateStateUsing(fn ($state) => $state ? 'active' : 'inactive'),

                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('Expira em')
                    ->nullable()
                    ->helperText('Deixe em branco para licença sem expiração.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user_email')
            ->columns([
                Tables\Columns\TextColumn::make('user_email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('scope')
                    ->label('Escopo')
                    ->getStateUsing(fn ($record) => $record->tenant_id ? 'Tenant' : 'Global')
                    ->colors([
                        'primary' => 'Tenant',
                        'warning' => 'Global',
                    ]),

                Tables\Columns\TextColumn::make('license_key')
                    ->label('Chave')
                    ->limit(20)
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ])
                    ->formatStateUsing(fn ($state) => $state === 'active' ? 'Ativa' : 'Inativa'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Sem expiração'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativa',
                        'inactive' => 'Inativa',
                    ]),

                Tables\Filters\Filter::make('global_only')
                    ->label('Apenas Globais')
                    ->query(fn (Builder $query) => $query->whereNull('tenant_id')),

                Tables\Filters\Filter::make('tenant_only')
                    ->label('Apenas desta Empresa')
                    ->query(fn (Builder $query) => $query->whereNotNull('tenant_id')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nova Licença'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

- [ ] **Step 2: Verificar sintaxe**

```bash
php artisan about
```

Esperado: sem erros.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/TenantResource/RelationManagers/UserLicensesRelationManager.php
git commit -m "feat(COD-18): criar UserLicensesRelationManager para tab de licenças no TenantResource"
```

---

### Task 3: Registrar o RelationManager no TenantResource

**Files:**
- Modify: `app/Filament/Resources/TenantResource.php`

- [ ] **Step 1: Adicionar o import e registrar no método `getRelations()`**

Abrir `app/Filament/Resources/TenantResource.php`.

Adicionar o import após os imports existentes:

```php
use App\Filament\Resources\TenantResource\RelationManagers\UserLicensesRelationManager;
```

Substituir o método `getRelations()`:

```php
public static function getRelations(): array
{
    return [
        UserLicensesRelationManager::class,
    ];
}
```

- [ ] **Step 2: Verificar sintaxe e cache**

```bash
php artisan about
```

Esperado: sem erros.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/TenantResource.php
git commit -m "feat(COD-18): registrar UserLicensesRelationManager no TenantResource"
```

---

### Task 4: Verificação manual no browser

- [ ] **Step 1: Subir o servidor**

```bash
php artisan serve
```

- [ ] **Step 2: Navegar até um tenant existente**

Abrir `http://localhost:8000/admin/tenants/{id}` (página View) e `http://localhost:8000/admin/tenants/{id}/edit` (página Edit).

Verificar que:
- A tab "Licenças" aparece abaixo do formulário
- A tab lista licenças do tenant E licenças globais
- Licenças globais mostram badge "Global" em amarelo
- Licenças do tenant mostram badge "Tenant" em azul
- O botão "Nova Licença" abre o modal de criação
- O campo "Escopo" pré-seleciona o tenant atual
- É possível deixar o campo "Escopo" em branco para criar licença global

- [ ] **Step 3: Criar uma licença de teste e verificar que aparece na lista**

No modal de criação, preencher um e-mail e criar. Confirmar que a licença aparece na tabela com badge "Tenant".

Criar outra licença deixando o "Escopo" em branco. Confirmar que aparece com badge "Global".

---

## Notas de Implementação

- `getTableQuery()` sobrescreve a query padrão do RelationManager para incluir licenças globais além das específicas do tenant. Sem isso, apenas licenças com `tenant_id = current_tenant_id` seriam exibidas.
- A coluna `scope` é calculada (`getStateUsing`) — não existe na tabela de banco de dados.
- `BadgeColumn` foi mantido pois já é usado no `UserLicenseResource` existente — se o Filament 3 não aceitar, substituir por `TextColumn` com `badge()`.
- `UserLicense` usa `$connection = 'central'`, portanto opera no banco central, sem conflito com o banco do tenant.
