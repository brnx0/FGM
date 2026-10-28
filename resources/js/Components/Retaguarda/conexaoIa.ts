import type { ProvedorIa } from '../../types';

/**
 * "Buscar" modelos e "Testar conexão" da Integração com IA. Quem fala com o provedor é o servidor; aqui é POST porque a
 * chave digitada vai no corpo, nunca na URL. Sempre resolve: qualquer falha vira { ok: false, mensagem } para a tela.
 */

type FalhaIa = { ok: false; mensagem: string };

export type RespostaModelosIa = { ok: true; modelos: string[] } | FalhaIa;
export type RespostaTesteIa = { ok: true; mensagem: string } | FalhaIa;

export interface PedidoIa {
    provedor: ProvedorIa;
    /** Em branco, o servidor usa a chave guardada do provedor. */
    chave_api: string;
    modelo?: string;
}

// Caminhos das rotas retaguarda.integracao-ia.modelos e retaguarda.integracao-ia.teste (o front não tem helper de rotas nomeadas).
const URL_MODELOS = '/retaguarda/integracao-ia/modelos';
const URL_TESTE = '/retaguarda/integracao-ia/teste';

const SESSAO_EXPIRADA = 'Sua sessão expirou. Recarregue a página e entre de novo.';

const tokenXsrf = (): string => {
    const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
};

const mensagemDeFalha = async (resposta: Response): Promise<string> => {
    if (resposta.status === 422) {
        const corpo = (await resposta.json().catch(() => ({}))) as { errors?: Record<string, string[]> };
        return Object.values(corpo.errors ?? {})[0]?.[0] ?? 'Confira os campos e tente de novo.';
    }
    if (resposta.status === 429) return 'Muitas tentativas seguidas. Aguarde um minuto e tente de novo.';
    if (resposta.status === 401 || resposta.status === 419) return SESSAO_EXPIRADA;
    return 'Não foi possível falar com o servidor. Tente de novo.';
};

const postar = async <T>(url: string, pedido: PedidoIa): Promise<T | FalhaIa> => {
    try {
        const resposta = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            // Quem deixou de ser gestor é mandado para o login: não segue o redirecionamento, trata como sessão expirada.
            redirect: 'manual',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': tokenXsrf(),
            },
            body: JSON.stringify(pedido),
        });
        if (resposta.type === 'opaqueredirect') return { ok: false, mensagem: SESSAO_EXPIRADA };
        if (!resposta.ok) return { ok: false, mensagem: await mensagemDeFalha(resposta) };
        return (await resposta.json()) as T;
    } catch {
        return { ok: false, mensagem: 'Sem conexão com o servidor. Verifique a internet e tente de novo.' };
    }
};

export const buscarModelosIa = (pedido: PedidoIa): Promise<RespostaModelosIa> => postar<RespostaModelosIa>(URL_MODELOS, pedido);

export const testarConexaoIa = (pedido: PedidoIa): Promise<RespostaTesteIa> => postar<RespostaTesteIa>(URL_TESTE, pedido);
