<?php

namespace App\Http\Controllers\Auth;

use App\Actions\EntrarNaRetaguardaPorGovBr;
use App\Actions\LoginGovBrUser;
use App\Auth\GovBr\GovBrClient;
use App\Auth\GovBr\GovBrException;
use App\Auth\GovBr\GovBrIdentity;
use App\Auth\GovBr\TrustLevelFetcher;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Retorno do Login Único gov.br (callback OIDC) e retorno do logout. O início do fluxo fica em GovBrRedirectController. */
class GovBrController extends Controller
{
    private const ERRO = 'Não foi possível entrar com o gov.br. Tente novamente.';

    private const SEM_ACESSO = 'Seu CPF não tem acesso à retaguarda.';

    public function callback(
        Request $request,
        GovBrClient $govbr,
        TrustLevelFetcher $trustLevel,
        LoginGovBrUser $login,
        EntrarNaRetaguardaPorGovBr $entrarNaRetaguarda,
    ): RedirectResponse {
        // Consumido sempre, para um login de proponente posterior nunca ser desviado.
        $daRetaguarda = $request->session()->pull('govbr.destino') === 'retaguarda';

        // Consumidos sempre: um state/nonce/verifier só vale para um único retorno.
        $state = $request->session()->pull('govbr.state');
        $nonce = $request->session()->pull('govbr.nonce');
        $verifier = $request->session()->pull('govbr.verifier');

        $stateRecebido = $request->query('state');
        $code = $request->query('code');

        if ($request->has('error')
            || ! is_string($state) || ! is_string($nonce) || ! is_string($verifier)
            || ! is_string($stateRecebido) || ! hash_equals($state, $stateRecebido)
            || ! is_string($code) || $code === '') {
            return $this->falhou($login, $daRetaguarda);
        }

        try {
            $tokens = $govbr->exchangeCode($code, $verifier);
            $identidade = $govbr->identityFromIdToken($tokens['id_token'], $nonce);
        } catch (GovBrException $e) {
            report($e);

            return $this->falhou($login, $daRetaguarda);
        }

        if ($daRetaguarda) {
            return $entrarNaRetaguarda->handle($identidade->cpf) !== null
                ? redirect()->route('retaguarda.inicio')
                : redirect()->route('retaguarda.login')->with('erro', self::SEM_ACESSO);
        }

        $nivel = $tokens['access_token'] !== null
            ? $trustLevel->buscar($identidade->cpf, $tokens['access_token'])
            : null;

        $user = $login->handle(new GovBrIdentity(
            cpf: $identidade->cpf,
            nome: $identidade->nome,
            email: $identidade->email,
            emailVerificado: $identidade->emailVerificado,
            telefone: $identidade->telefone,
            nivelConfiabilidade: $nivel,
        ));

        return redirect($login->destinoPosLogin($user));
    }

    /** Destino do post_logout_redirect_uri: o gov.br devolve o usuário para cá. */
    public function logout(): RedirectResponse
    {
        return redirect('/');
    }

    private function falhou(LoginGovBrUser $login, bool $daRetaguarda): RedirectResponse
    {
        if ($daRetaguarda) {
            return redirect()->route('retaguarda.login')->with('erro', self::ERRO);
        }

        // Retorno repetido (duplo clique) com sessão já autenticada: segue o fluxo, sem erro.
        if (auth()->check()) {
            return redirect($login->destinoPosLogin(auth()->user()));
        }

        return redirect('/')->with('erro', self::ERRO);
    }
}
