<?php

namespace App\Forms\Generators;

use App\Forms\Enums\ButtonAction;
use App\Models\FormButton;
use App\Models\FormDefinition;

/**
 * Gera o controller com um método por botão "Executar método do controller".
 *
 * O controller é criado uma vez e depois só recebe os métodos dos botões
 * novos: o código que você escrever nele nunca é sobrescrito.
 */
class ControllerWriter
{
    public static function path(FormDefinition $form): string
    {
        return app_path("Http/Controllers/Forms/{$form->controller_class}.php");
    }

    public static function write(FormDefinition $form): void
    {
        $path = static::path($form);

        if (! is_file($path)) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, static::skeleton($form));
        }

        $code = file_get_contents($path);

        $missing = $form->buttons()->get()
            ->filter(fn (FormButton $button) => $button->action === ButtonAction::Controller)
            ->reject(fn (FormButton $button) => preg_match('/function\s+' . preg_quote($button->method, '/') . '\s*\(/', $code));

        if ($missing->isEmpty()) {
            return;
        }

        $methods = $missing->map(fn (FormButton $button) => static::method($form, $button))->implode('');

        // insere antes da última chave (fim da classe)
        $code = substr_replace($code, $methods, strrpos($code, '}'), 0);

        file_put_contents($path, $code);
    }

    protected static function skeleton(FormDefinition $form): string
    {
        return <<<PHP
<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Models\Forms\\{$form->model_class};
use Illuminate\Support\Collection;

/**
 * Ações dos botões do formulário "{$form->title}".
 *
 * Criado pelo construtor de formulários. Pode editar à vontade: quando você
 * cadastra um botão novo, o método dele é adicionado aqui e nada é
 * sobrescrito. Retorne uma URL (string) para redirecionar após a ação.
 */
class {$form->controller_class} extends Controller
{
}

PHP;
    }

    protected static function method(FormDefinition $form, FormButton $button): string
    {
        $signature = $button->position->methodSignature($form->model_class);

        return <<<PHP
    /**
     * Botão "{$button->label}" ({$button->position->getLabel()}).
     */
    public function {$button->method}({$signature}): ?string
    {
        // TODO: implementar a ação do botão

        return null;
    }

PHP;
    }
}
