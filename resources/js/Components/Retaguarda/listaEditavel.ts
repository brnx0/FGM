import { nextTick, reactive, ref, watch, type Ref } from 'vue';

/**
 * Partes comuns das listas editáveis em cartões do formulário do edital (CategoriasEdital, DocumentosEdital):
 * filtro de inteiros, classes dos campos e a sanfona com subir/descer/remover.
 */

/** Só dígitos (sem sinal, ponto, vírgula ou "e"), limitado a `maximo`; devolve o número e reescreve o campo. Vazio ou zero vira null. */
export const inteiro = (evento: Event, maximo: number): number | null => {
    const campo = evento.target as HTMLInputElement;
    const digitos = campo.value.replace(/\D/g, '').replace(/^0+/, '').slice(0, 6);
    const numero = digitos === '' ? null : Math.min(Number(digitos), maximo);

    campo.value = numero === null ? '' : String(numero);

    return numero;
};

// Sem largura: campos de largura fixa a acrescentam; os demais ocupam a linha (classeCampo).
export const classeCampoBase =
    'h-12 rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave aria-invalid:border-erro-texto aria-invalid:ring-1 aria-invalid:ring-erro-texto';
export const classeCampo = `${classeCampoBase} w-full`;
export const classeMarcacao = 'flex min-h-11 cursor-pointer items-start gap-3 py-2 text-[0.9375rem] leading-[1.5]';
export const classeCaixa = 'mt-0.5 size-5 shrink-0 cursor-pointer accent-acao';
export const classeBotaoCartao =
    'pressionavel inline-flex min-h-11 items-center gap-1.5 rounded-pill px-3 text-[0.8125rem] font-bold text-acao hover:bg-nevoa disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent';

export interface OpcoesListaEditavel<T> {
    /** A lista (defineModel) do componente. */
    lista: Ref<T[]>;
    /** Prefixo das chaves de erro: "categorias" para "categorias.N.campo". */
    prefixo: string;
    erros: () => Partial<Record<string, string>>;
    limparErros: (campos: string[]) => void;
    idCampo: (item: T, campo: string) => string;
    textoMovido: (posicao: number, total: number) => string;
    textoRemovido: string;
    /** Quais campos com erro contam no selo do cartão (o resto de "prefixo.N."); sem isso, todos. */
    contaNoSelo?: (campo: string) => boolean;
}

/**
 * Sanfona de cartões com chave estável, erros por índice, subir/descer e remoção confirmada num <dialog>.
 * O componente liga `dialogo` e `botaoAdicionar` pelo atributo ref do template.
 */
export function useListaEditavel<T extends { chave: string }>(opcoes: OpcoesListaEditavel<T>) {
    const { lista, prefixo, erros, limparErros, idCampo } = opcoes;

    // Cartões abertos, pela chave. Recolher só esconde (v-show): nada se perde.
    const abertos = reactive(new Set<string>());
    const estaAberto = (item: T): boolean => abertos.has(item.chave);
    const alternar = (item: T): void => {
        if (!abertos.delete(item.chave)) abertos.add(item.chave);
    };
    const todosAbertos = (): boolean => lista.value.length > 0 && lista.value.every((item) => abertos.has(item.chave));
    const alternarTodos = (): void => {
        const abrirTodos = !todosAbertos();
        for (const item of lista.value) {
            if (abrirTodos) abertos.add(item.chave);
            else abertos.delete(item.chave);
        }
    };

    const anuncio = ref<string>('');
    const botaoAdicionar = ref<HTMLButtonElement | null>(null);

    const erro = (indice: number, campo: string): string | undefined => erros()[`${prefixo}.${indice}.${campo}`];

    /** Quantos campos do cartão têm erro (para o selo do cabeçalho). */
    const quantidadeDeErros = (indice: number): number => {
        const inicio = `${prefixo}.${indice}.`;
        const todos = erros();

        return Object.keys(todos).filter((chave) => chave.startsWith(inicio) && todos[chave] && (opcoes.contaNoSelo?.(chave.slice(inicio.length)) ?? true)).length;
    };

    // Depois de um envio recusado, abre todo cartão com erro (antes do foco no primeiro campo inválido, que o formulário dá no próximo tick).
    watch(
        () => {
            const todos = erros();

            return Object.keys(todos)
                .filter((chave) => chave.startsWith(`${prefixo}.`) && todos[chave])
                .join('|');
        },
        () => {
            lista.value.forEach((item, indice) => {
                if (quantidadeDeErros(indice) > 0) abertos.add(item.chave);
            });
        },
    );

    /** Erro de uma lista de caixas: o do campo ou o do primeiro item ("campo.0"). */
    const erroDaLista = (indice: number, campo: string): string | undefined => {
        const todos = erros();
        const doItem = Object.keys(todos).find((chave) => chave.startsWith(`${prefixo}.${indice}.${campo}.`) && todos[chave]);

        return erro(indice, campo) ?? (doItem ? todos[doItem] : undefined);
    };

    const limpar = (indice: number, ...campos: string[]): void => {
        limparErros(campos.map((campo) => `${prefixo}.${indice}.${campo}`));
    };

    const limparLista = (indice: number, campo: string): void => {
        const chave = `${prefixo}.${indice}.${campo}`;
        limparErros([chave, ...Object.keys(erros()).filter((outra) => outra.startsWith(`${chave}.`))]);
    };

    // Remover ou reordenar muda os índices: os erros do servidor deixam de apontar para o cartão certo.
    const limparTodosOsErros = (): void => {
        const chaves = Object.keys(erros()).filter((chave) => chave === prefixo || chave.startsWith(`${prefixo}.`));
        if (chaves.length > 0) limparErros(chaves);
    };

    const focar = async (id: string): Promise<void> => {
        await nextTick();
        // Depois do fechamento do <dialog>, que devolve o foco ao botão que o abriu (já removido).
        await new Promise((resolver) => setTimeout(resolver, 0));
        document.getElementById(id)?.focus();
    };

    /** Novo item entra no fim (os índices dos outros não mudam, então os erros deles continuam valendo), aberto e com foco em `campo`. */
    const acrescentar = (item: T, campo: string): void => {
        lista.value = [...lista.value, item];
        abertos.add(item.chave);
        void focar(idCampo(item, campo));
    };

    // Subir/Descer: o foco fica no mesmo botão do cartão movido (ou no cartão, se o botão ficou desabilitado).
    const mover = (indice: number, direcao: -1 | 1, botao: 'subir' | 'descer'): void => {
        const destino = indice + direcao;
        const nova = [...lista.value];
        const movido = nova[indice];
        if (!movido || destino < 0 || destino >= nova.length) return;

        nova.splice(indice, 1);
        nova.splice(destino, 0, movido);
        lista.value = nova;
        limparTodosOsErros();
        anuncio.value = opcoes.textoMovido(destino + 1, nova.length);

        const nasPontas = (botao === 'subir' && destino === 0) || (botao === 'descer' && destino === nova.length - 1);
        void focar(nasPontas ? idCampo(movido, 'cartao') : idCampo(movido, botao));
    };

    // Remoção pede confirmação num <dialog> nativo (como ConfirmarExclusao), mas só tira da lista: vale ao salvar.
    const dialogo = ref<HTMLDialogElement | null>(null);
    const aRemover = ref<number | null>(null);

    const pedirRemocao = (indice: number): void => {
        aRemover.value = indice;
        dialogo.value?.showModal();
    };

    const cancelarRemocao = (): void => {
        dialogo.value?.close();
        aRemover.value = null;
    };

    const remover = (): void => {
        const indice = aRemover.value;
        dialogo.value?.close();
        aRemover.value = null;
        if (indice === null) return;

        lista.value = lista.value.filter((_, posicao) => posicao !== indice);
        limparTodosOsErros();
        anuncio.value = opcoes.textoRemovido;

        // Foca o anterior; se removeu o primeiro, o que ocupa o lugar dele; sem nenhum, o botão de adicionar.
        const anterior = lista.value[indice - 1] ?? lista.value[indice];
        if (anterior) {
            void focar(idCampo(anterior, 'cartao'));
        } else {
            void nextTick(() => setTimeout(() => botaoAdicionar.value?.focus(), 0));
        }
    };

    return {
        estaAberto,
        alternar,
        todosAbertos,
        alternarTodos,
        anuncio,
        botaoAdicionar,
        erro,
        quantidadeDeErros,
        erroDaLista,
        limpar,
        limparLista,
        acrescentar,
        mover,
        dialogo,
        aRemover,
        pedirRemocao,
        cancelarRemocao,
        remover,
    };
}
