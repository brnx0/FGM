<?php

namespace App\Http\Controllers;

use App\Enums\IdentidadeGenero;
use App\Enums\RacaCor;
use App\Enums\Territorio;
use App\Enums\TipoCadastro;
use App\Http\Requests\SalvarPessoaFisicaRequest;
use App\Http\Requests\SalvarTipoCadastroRequest;
use App\Models\PessoaFisica;
use App\Support\Cpf;
use App\Support\Estados;
use App\Support\Paises;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CadastroController extends Controller
{
    /** Campos do formulário que guardam escolhas sim/não. */
    private const array MARCACOES = [
        'cultura_principal_renda', 'ja_foi_avaliador', 'interesse_avaliador', 'representa_coletivo', 'pcd',
    ];

    /** Formulário de pessoa física. Nome, CPF, e-mail e telefone vêm do gov.br e não podem ser alterados. */
    public function completar(Request $request): Response
    {
        $user = $request->user();
        $salvo = $user->pessoaFisica;

        return Inertia::render('Cadastro/Completar', [
            'govbr' => [
                'nome' => $user->name,
                'cpf' => Cpf::formatar($user->cpf),
                'email' => $user->email,
                'telefone' => $user->phone !== null ? preg_replace('/\D/', '', $user->phone) : null,
            ],
            'cadastro' => $salvo ? [
                ...$salvo->only(array_diff($salvo->getFillable(), PessoaFisica::ARQUIVOS)),
                // Dos arquivos, a tela só precisa saber se já foram enviados.
                'arquivos' => collect(PessoaFisica::ARQUIVOS)->mapWithKeys(fn (string $campo): array => [$campo => $salvo->{$campo} !== null]),
            ] : null,
            'opcoes' => [
                'paises' => Paises::TODOS,
                'estados' => collect(Estados::TODOS)->map(fn (string $nome, string $sigla): array => ['valor' => $sigla, 'rotulo' => $nome])->values(),
                'identidadesGenero' => self::opcoes(IdentidadeGenero::cases()),
                'racasCores' => self::opcoes(RacaCor::cases()),
                'territorios' => self::opcoes(Territorio::cases()),
            ],
        ]);
    }

    public function salvar(SalvarPessoaFisicaRequest $request): RedirectResponse
    {
        $user = $request->user();
        $cadastro = $user->pessoaFisica ?? $user->pessoaFisica()->make();

        $cadastro->fill([
            ...$request->safe()->except([...PessoaFisica::ARQUIVOS, 'telefone', 'email']),
            ...collect(self::MARCACOES)->mapWithKeys(fn (string $campo): array => [$campo => $request->boolean($campo)]),
            // Quando o gov.br informa e-mail e telefone, valem esses, e não o que veio no formulário.
            'telefone' => $user->phone !== null ? preg_replace('/\D/', '', $user->phone) : $request->validated('telefone'),
            'email' => $user->email ?? $request->validated('email'),
        ]);

        $substituidos = [];
        foreach (PessoaFisica::ARQUIVOS as $campo) {
            if ($request->hasFile($campo)) {
                $substituidos[] = $cadastro->{$campo};
                $cadastro->{$campo} = $request->file($campo)->store("cadastros/{$user->id}", 'local');
            }
        }

        $cadastro->save();
        Storage::disk('local')->delete(array_filter($substituidos));

        $user->profile_completed_at ??= now();
        $user->save();

        // Enquanto o tipo de cadastro não é escolhido, o cadastro segue pelas etapas seguintes.
        if (! $user->cadastroFinalizado()) {
            return redirect()->route('cadastro.organizacao')->with('sucesso', 'Dados salvos. Você também pode cadastrar uma Pessoa Jurídica.');
        }

        return redirect()->route('cadastro')->with('sucesso', 'Cadastro salvo.');
    }

    /** Etapa opcional: depois da pessoa física, a pessoa decide se cadastra uma organização. */
    public function organizacao(): Response
    {
        return Inertia::render('Cadastro/Organizacao');
    }

    /** Última etapa: Proponente, Patrocinador ou os dois. Pode ser trocado depois, e o menu do portal acompanha. */
    public function tipo(Request $request): Response
    {
        return Inertia::render('Cadastro/Tipo', [
            'tiposAtuais' => $request->user()->tipos_cadastro?->map(fn (TipoCadastro $tipo): string => $tipo->value)->all() ?? [],
            'opcoes' => self::opcoes(TipoCadastro::cases()),
        ]);
    }

    public function salvarTipo(SalvarTipoCadastroRequest $request): RedirectResponse
    {
        $user = $request->user();
        $marcados = $request->validated('tipos_cadastro');
        // Guardados na ordem do enum: a tela em que o portal abre não depende da ordem das marcações.
        $user->tipos_cadastro = array_values(array_filter(
            TipoCadastro::cases(),
            fn (TipoCadastro $tipo): bool => in_array($tipo->value, $marcados, true),
        ));
        $user->save();

        return redirect()->route($user->rotaInicialDoPortal())->with('sucesso', 'Cadastro finalizado.');
    }

    /**
     * @param  list<IdentidadeGenero|RacaCor|Territorio|TipoCadastro>  $casos
     * @return list<array{valor: string, rotulo: string}>
     */
    private static function opcoes(array $casos): array
    {
        return array_map(fn (IdentidadeGenero|RacaCor|Territorio|TipoCadastro $caso): array => ['valor' => $caso->value, 'rotulo' => $caso->rotulo()], $casos);
    }
}
