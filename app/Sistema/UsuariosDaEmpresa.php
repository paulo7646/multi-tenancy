<?php

namespace App\Sistema;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserLicense;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Operações sobre os usuários do banco de uma empresa, feitas a partir do banco pai.
 *
 * O pai não enxerga as linhas do cliente, então tudo roda dentro do banco dele (Tenant::executar,
 * que devolve a conexão ao estado anterior mesmo com erro) e sai daqui como array simples,
 * sem Eloquent preso à conexão do cliente. É a "API" que a aba Usuários de Empresas consome.
 *
 * O administrador da empresa (usuário com o e-mail da empresa) é protegido: não pode ser excluído
 * nem trocar de e-mail, e a senha dele é mantida igual à da linha da empresa.
 */
class UsuariosDaEmpresa
{
    public function __construct(private readonly Tenant $empresa) {}

    /**
     * @return array{dados: list<array<string, mixed>>, total: int, pagina: int, porPagina: int}
     */
    public function listar(?string $busca = null, int $pagina = 1, int $porPagina = 15, string $ordenarPor = 'name', string $direcao = 'asc'): array
    {
        $ordenarPor = in_array($ordenarPor, ['id', 'name', 'email', 'created_at'], true) ? $ordenarPor : 'name';
        $direcao = strtolower($direcao) === 'desc' ? 'desc' : 'asc';
        $pagina = max(1, $pagina);

        return $this->empresa->executar(function () use ($busca, $pagina, $porPagina, $ordenarPor, $direcao) {
            $consulta = User::withoutGlobalScopes()
                ->when(filled($busca), function ($q) use ($busca) {
                    $termo = '%'.addcslashes($busca, '%_\\').'%';

                    $q->where(fn ($w) => $w->where('name', 'ilike', $termo)->orWhere('email', 'ilike', $termo));
                });

            $total = (clone $consulta)->count();

            $dados = $consulta->orderBy($ordenarPor, $direcao)->orderBy('id')
                ->forPage($pagina, $porPagina)
                ->get()
                ->map(fn (User $u) => $this->paraArray($u))
                ->all();

            return ['dados' => $dados, 'total' => $total, 'pagina' => $pagina, 'porPagina' => $porPagina];
        });
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException se o usuário não existe nesta empresa
     */
    public function consultar(int|string $id): array
    {
        return $this->empresa->executar(fn () => $this->paraArray($this->achar($id)));
    }

    /**
     * @param  array{name?: mixed, email?: mixed, password?: mixed}  $dados
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function criar(array $dados): array
    {
        return $this->empresa->executar(function () use ($dados) {
            $validos = Validator::make($dados, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:8', 'max:255'],
            ], attributes: $this->nomes())->validate();

            $usuario = new User($validos);

            // sem eventos: o "creating" do escopo de filial e a auditoria leriam o usuário logado
            // no pai (que não existe neste banco) e gravariam a filial/autor errados.
            $usuario->saveQuietly();

            return $this->paraArray($usuario);
        });
    }

    /**
     * Senha em branco mantém a atual.
     *
     * @param  array{name?: mixed, email?: mixed, password?: mixed}  $dados
     * @return array<string, mixed>
     *
     * @throws RuntimeException se o usuário não existe ou é o administrador tentando trocar de e-mail
     * @throws ValidationException
     */
    public function atualizar(int|string $id, array $dados): array
    {
        [$usuario, $novaSenhaDoAdmin, $emailAntigo] = $this->empresa->executar(function () use ($id, $dados) {
            $usuario = $this->achar($id);
            $eAdmin = $this->eAdministrador($usuario);
            $emailAntigo = $usuario->email;

            if ($eAdmin && ($dados['email'] ?? $usuario->email) !== $usuario->email) {
                throw new RuntimeException('O e-mail do administrador da empresa não pode ser alterado.');
            }

            $validos = Validator::make($dados, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->getKey())],
                'password' => ['nullable', 'string', 'min:8', 'max:255'],
            ], attributes: $this->nomes())->validate();

            $senha = $validos['password'] ?? null;

            if (blank($senha)) {
                unset($validos['password']);
            }

            $usuario->fill($validos)->saveQuietly();

            return [$this->paraArray($usuario), $eAdmin && filled($senha) ? $senha : null, $emailAntigo];
        });

        // as licenças ficam no banco pai, ligadas pelo e-mail: trocar o e-mail leva as da empresa junto
        // (as globais, tenant_id nulo, não pertencem a esta empresa e ficam como estão)
        if ($emailAntigo !== $usuario['email']) {
            UserLicense::query()
                ->where('tenant_id', $this->empresa->getKey())
                ->where('user_email', $emailAntigo)
                ->update(['user_email' => $usuario['email']]);
        }

        // administrador: a senha da linha da empresa acompanha a do usuário
        if ($novaSenhaDoAdmin !== null) {
            $this->empresa->forceFill(['password' => $novaSenhaDoAdmin])->save();
        }

        return $usuario;
    }

    /**
     * @throws RuntimeException se o usuário não existe ou é o administrador da empresa
     */
    public function excluir(int|string $id): void
    {
        $this->empresa->executar(function () use ($id) {
            $usuario = $this->achar($id);

            if ($this->eAdministrador($usuario)) {
                throw new RuntimeException('O administrador da empresa não pode ser excluído.');
            }

            $usuario->delete();
        });
    }

    private function achar(int|string $id): User
    {
        return User::withoutGlobalScopes()->find($id)
            ?? throw new RuntimeException('Usuário não encontrado nesta empresa.');
    }

    private function eAdministrador(User $usuario): bool
    {
        return strcasecmp((string) $usuario->email, (string) $this->empresa->email) === 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function paraArray(User $u): array
    {
        return [
            'id' => $u->getKey(),
            'name' => $u->name,
            'email' => $u->email,
            'administrador' => $this->eAdministrador($u),
            'created_at' => $u->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function nomes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha'];
    }
}
