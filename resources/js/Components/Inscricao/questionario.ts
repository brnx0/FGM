import type { PerguntaDaInscricao, RespostaDaInscricao, SecaoDaInscricao } from '../../types';

// Chaves fixas de App\Enums\TipoPergunta.
export const CHAVE_SIM = 'sim';
export const CHAVE_NAO = 'nao';
export const CHAVE_DECLARACAO = 'declaro';

/** Quebras de linha "\r\n" contam como uma só, como no servidor (SalvarRespostasRequest). */
export const normalizarTexto = (texto: string): string => texto.replace(/\r\n/g, '\n');

/** Caracteres como o limite do servidor conta: por caractere, não por unidade de UTF-16. */
export const caracteres = (texto: string): number => [...normalizarTexto(texto)].length;

/**
 * As perguntas visíveis com estas respostas, pelo id: mesma regra de QuestionarioDaInscricao::visiveis. A condicionada
 * só aparece se a origem está visível e a resposta dela tem alguma das opções da condição; origem fora das seções (que
 * não vale para a inscrição) e ciclo escondem.
 */
export const perguntasVisiveis = (secoes: SecaoDaInscricao[], respostas: Record<number, RespostaDaInscricao>): Set<number> => {
    const perguntas = new Map<number, PerguntaDaInscricao>();
    for (const secao of secoes) {
        for (const pergunta of secao.perguntas) {
            perguntas.set(pergunta.id, pergunta);
        }
    }

    const visibilidade = new Map<number, boolean>();
    const estaVisivel = (pergunta: PerguntaDaInscricao, caminho: Set<number>): boolean => {
        const calculada = visibilidade.get(pergunta.id);
        if (calculada !== undefined) {
            return calculada;
        }
        if (pergunta.condicao === null) {
            visibilidade.set(pergunta.id, true);
            return true;
        }

        const origem = perguntas.get(pergunta.condicao.pergunta);
        if (origem === undefined || caminho.has(pergunta.id)) {
            visibilidade.set(pergunta.id, false);
            return false;
        }

        const escolhidas = respostas[origem.id]?.opcoes ?? [];
        const condicao = pergunta.condicao;
        const visivel = estaVisivel(origem, new Set([...caminho, pergunta.id])) && escolhidas.some((opcao) => condicao.opcoes.includes(opcao));
        visibilidade.set(pergunta.id, visivel);
        return visivel;
    };

    return new Set([...perguntas.values()].filter((pergunta) => estaVisivel(pergunta, new Set())).map((pergunta) => pergunta.id));
};

/** As chaves que a resposta da pergunta pode ter (EditalPergunta::chavesDasOpcoes). */
export const chavesDaPergunta = (pergunta: PerguntaDaInscricao): string[] => {
    switch (pergunta.tipo) {
        case 'sim_nao':
            return [CHAVE_SIM, CHAVE_NAO];
        case 'declaracao':
            return [CHAVE_DECLARACAO];
        case 'escolha_unica':
        case 'multipla_escolha':
            return pergunta.opcoes.map((opcao) => opcao.chave);
        default:
            return [];
    }
};

/** "2026-11-15" → "15/11/2026"; o que não for data fica como veio. */
const dataBrasileira = (texto: string): string => {
    const partes = /^(\d{4})-(\d{2})-(\d{2})$/.exec(texto);
    return partes ? `${partes[3]}/${partes[2]}/${partes[1]}` : texto;
};

/** "1234.56" → "1.234,56"; o que não for número fica como veio. */
const numeroBrasileiro = (texto: string): string => {
    const valor = Number(texto);
    return texto.trim() === '' || !Number.isFinite(valor) ? texto : valor.toLocaleString('pt-BR', { maximumFractionDigits: 20 });
};

/** A resposta como a revisão mostra; vazio quando não há resposta. */
export const textoDaResposta = (pergunta: PerguntaDaInscricao, resposta: RespostaDaInscricao | undefined): string => {
    if (resposta === undefined) {
        return '';
    }

    switch (pergunta.tipo) {
        case 'sim_nao':
            return resposta.opcoes.includes(CHAVE_SIM) ? 'Sim' : resposta.opcoes.includes(CHAVE_NAO) ? 'Não' : '';
        case 'declaracao':
            return resposta.opcoes.includes(CHAVE_DECLARACAO) ? 'Declarado' : '';
        case 'escolha_unica':
        case 'multipla_escolha':
            return pergunta.opcoes
                .filter((opcao) => resposta.opcoes.includes(opcao.chave))
                .map((opcao) => (opcao.outro && resposta.outro ? `${opcao.texto}: ${resposta.outro}` : opcao.texto))
                .join(', ');
        case 'data':
            return dataBrasileira(resposta.texto ?? '');
        case 'numero':
            return numeroBrasileiro(resposta.texto ?? '');
        default:
            return resposta.texto ?? '';
    }
};

/** Resposta enquanto o proponente edita: textos vazios em vez de nulos, para os campos. */
export interface RespostaEditavel {
    texto: string;
    opcoes: string[];
    outro: string;
}

/** A pergunta tem a opção "Outro" escolhida (abre o "Qual?"). */
export const escolheuOutro = (pergunta: PerguntaDaInscricao, opcoes: string[]): boolean =>
    pergunta.opcoes.some((opcao) => opcao.outro && opcoes.includes(opcao.chave));
