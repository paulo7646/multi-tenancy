<?php

namespace App\Forms;

use App\Forms\Enums\FormConnection;
use App\Forms\Generators\ControllerWriter;
use App\Forms\Generators\Files;
use App\Forms\Generators\MigrationWriter;
use App\Forms\Generators\ModelWriter;
use App\Forms\Generators\ResourceWriter;
use App\Forms\Schema\SchemaException;
use App\Forms\Schema\SchemaSnapshot;
use App\Models\FormDefinition;
use App\Models\Tenant;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Gera os arquivos de um formulário (para ir ao git): migration, model,
 * tela do Filament, controller e o JSON da definição. Em desenvolvimento a
 * migration também é aplicada na hora; o cliente aplica com migrate.
 */
class FormGenerator
{
    /**
     * Testa a migration que sairia do que está na tela: roda o up() numa
     * transação desfeita, no central ou em cada empresa local.
     *
     * @throws SchemaException
     */
    public static function check(FormDefinition $form, array $fieldsState, ?array $roles): void
    {
        $code = static::migrationCode($form, SchemaSnapshot::fromState($fieldsState, $roles));

        if ($code === null) {
            return;
        }

        $file = storage_path('framework/form-check-' . bin2hex(random_bytes(6)) . '.php');
        Files::put($file, $code);

        try {
            $migration = require $file;
            $problems = [];

            foreach (static::databases($form) as $where => $run) {
                $error = $run(function () use ($migration) {
                    $connection = DB::connection();
                    $connection->beginTransaction();

                    try {
                        $migration->up();

                        return null;
                    } catch (Throwable $e) {
                        return static::friendlyError($e);
                    } finally {
                        $connection->rollBack();
                    }
                });

                if ($error !== null) {
                    $problems[$where] = [$error];
                }
            }

            if ($problems !== []) {
                throw new SchemaException($problems);
            }
        } finally {
            Files::delete($file);
        }
    }

    /**
     * @return list<string> resumo do que foi gerado
     */
    public static function publish(FormDefinition $form): array
    {
        $form->refresh();
        $snapshot = SchemaSnapshot::of($form);
        $code = static::migrationCode($form, $snapshot);
        $summary = [];

        if ($code !== null) {
            $summary[] = static::writeAndRun($form, $form->isGenerated() ? 'alter' : 'create', $code);
        }

        $form->forceFill(['generated_schema' => $snapshot, 'generated_at' => now()])->saveQuietly();

        ModelWriter::write($form);
        ResourceWriter::write($form);
        ControllerWriter::write($form);
        FormExporter::export($form);

        return [
            ...$summary,
            "Model {$form->modelFqcn()}",
            "Tela {$form->resourceFqcn()}",
            "Controller {$form->controllerFqcn()}",
            'Definição ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', FormExporter::path($form)),
        ];
    }

    /**
     * Gera a migration que apaga a tabela e remove os arquivos gerados.
     * O controller é mantido (tem código escrito à mão).
     */
    public static function destroy(FormDefinition $form): void
    {
        $referencedBy = $form->referencedBy()->with('form')->get();

        if ($referencedBy->isNotEmpty()) {
            throw new RuntimeException('Este formulário é usado como lookup em: '
                . $referencedBy->map(fn ($field) => "{$field->form->title} ({$field->label})")->join(', ')
                . '. Remova esses campos antes.');
        }

        if ($form->isGenerated()) {
            static::writeAndRun($form, 'drop', MigrationWriter::drop($form, $form->generated_schema ?? SchemaSnapshot::empty()));
        }

        ModelWriter::delete($form);
        ResourceWriter::delete($form);
        FormExporter::delete($form);
    }

    protected static function migrationCode(FormDefinition $form, array $snapshot): ?string
    {
        return $form->isGenerated()
            ? MigrationWriter::alter($form, $form->generated_schema ?? SchemaSnapshot::empty(), $snapshot)
            : MigrationWriter::create($form, $snapshot);
    }

    protected static function writeAndRun(FormDefinition $form, string $action, string $code): string
    {
        $directory = $form->database->migrationsPath();
        $name = date('Y_m_d_His') . "_{$action}_{$form->table_name}_table";

        for ($i = 2; is_file("{$directory}/{$name}.php"); $i++) {
            $name = date('Y_m_d_His') . "_{$action}_{$form->table_name}_table_{$i}";
        }

        $file = "{$directory}/{$name}.php";
        Files::put($file, $code);

        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);

        try {
            if ($form->database === FormConnection::Central) {
                static::artisan('migrate', ['--path' => $file, '--realpath' => true, '--force' => true]);
            } else {
                static::artisan('tenants:migrate', ['--force' => true]);
            }
        } catch (Throwable $e) {
            // central: a migration roda em transação, nada ficou aplicado
            if ($form->database === FormConnection::Central) {
                Files::delete($file);
            }

            throw new RuntimeException("Migration {$relative} falhou: " . static::friendlyError($e), previous: $e);
        }

        return "Migration {$relative} (aplicada aqui; no cliente: php artisan "
            . ($form->database === FormConnection::Central ? 'migrate' : 'tenants:migrate') . ')';
    }

    protected static function artisan(string $command, array $parameters): void
    {
        $exitCode = Artisan::call($command, $parameters);
        $output = Artisan::output();

        if ($exitCode !== 0 || str_contains($output, 'FAIL')) {
            throw new RuntimeException(trim($output));
        }
    }

    /**
     * Bancos onde a tabela vive, cada um com um executor no contexto certo.
     *
     * @return array<string, Closure(Closure): mixed>
     */
    protected static function databases(FormDefinition $form): array
    {
        if ($form->database === FormConnection::Central) {
            return ['central' => fn (Closure $callback) => $callback()];
        }

        return Tenant::with('domains')->get()
            ->mapWithKeys(fn (Tenant $tenant) => [$tenant->displayName() => fn (Closure $callback) => $tenant->run($callback)])
            ->all();
    }

    protected static function friendlyError(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            $message = preg_replace('/^SQLSTATE\[\w+\]: [^:]+: \d+ /', '', $e->getPrevious()?->getMessage() ?? $e->getMessage());

            return 'O banco recusou a alteração: ' . trim(strtok($message, "\n"));
        }

        return $e->getMessage();
    }
}
