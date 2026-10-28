<script setup lang="ts">
import CampoCadastro from '../CampoCadastro.vue';
import { classeBotaoCartao, classeCaixa, classeCampo, classeCampoBase, classeMarcacao, inteiro, useListaEditavel } from './listaEditavel';
import type { CategoriaEditavel, DocumentoEditavel } from '../../types';

const props = defineProps<{
    /** Erros do useForm; os dos documentos vêm como "documentos.N.campo". */
    erros: Partial<Record<string, string>>;
    /** NaturezaProponente: as mesmas opções das categorias. */
    naturezas: { valor: string; rotulo: string }[];
    /** Categorias atuais da aba Categorias, na ordem em que estão. */
    categorias: CategoriaEditavel[];
}>();

const emit = defineEmits<{
    /** Campos de erro a limpar no useForm. */
    limparErros: [campos: string[]];
}>();

const documentos = defineModel<DocumentoEditavel[]>({ required: true });

/** Mesmo limite do SalvarEditalRequest. */
const MAXIMO = 50;

/** Tamanho máximo de cada arquivo, em MB (SalvarEditalRequest: 1 a 20, padrão 2). */
const TAMANHO_MAXIMO_MB = 20;
const TAMANHO_PADRAO_MB = 2;

/** Campos que aparecem no cartão: só os erros deles contam no selo (id, obrigatorio e todas_categorias não mostram mensagem). */
const CAMPOS_VISIVEIS = /^(nome|orientacao|tamanho_max_mb|naturezas|categorias)(\.|$)/;

// Chave do cartão de um documento novo (os salvos usam o id): não muda ao reordenar.
let proximaChave = 0;
const novaChave = (): string => `novo-${++proximaChave}`;

const idCampo = (documento: DocumentoEditavel, campo: string): string => `documento-${documento.chave}-${campo}`;

// Sanfona (os já salvos começam recolhidos; o novo abre), erros por índice, subir/descer e remoção confirmada.
const {
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
} = useListaEditavel<DocumentoEditavel>({
    lista: documentos,
    prefixo: 'documentos',
    erros: () => props.erros,
    limparErros: (campos) => emit('limparErros', campos),
    idCampo,
    textoMovido: (posicao, total) => `Documento movido para a posição ${posicao} de ${total}.`,
    textoRemovido: 'Documento removido.',
    contaNoSelo: (campo) => CAMPOS_VISIVEIS.test(campo),
});

/** Rótulo de uma categoria da aba Categorias: o nome, ou a posição enquanto não tem nome. */
const rotuloDaCategoria = (categoria: CategoriaEditavel, posicao: number): string => categoria.nome.trim() || `Categoria ${posicao + 1}`;

const naturezasNoResumo = (quantidade: number): string => {
    if (quantidade === 0) return 'nenhuma natureza';

    return `${quantidade} ${quantidade === 1 ? 'natureza' : 'naturezas'}`;
};

const resumo = (documento: DocumentoEditavel): string =>
    [
        documento.obrigatorio ? 'Obrigatório' : 'Opcional',
        documento.tamanho_max_mb === null ? 'PDF' : `PDF até ${documento.tamanho_max_mb} MB`,
        naturezasNoResumo(documento.naturezas.length),
    ].join(' · ');

const digitarTamanho = (indice: number, documento: DocumentoEditavel, evento: Event): void => {
    documento.tamanho_max_mb = inteiro(evento, TAMANHO_MAXIMO_MB);
    limpar(indice, 'tamanho_max_mb');
};

const escolherTodas = (indice: number, documento: DocumentoEditavel, todas: boolean): void => {
    documento.todas_categorias = todas;
    limparLista(indice, 'categorias');
};

const adicionar = (): void => {
    acrescentar(
        {
            chave: novaChave(),
            id: null,
            nome: '',
            orientacao: '',
            obrigatorio: true,
            naturezas: [],
            todas_categorias: true,
            categorias: [],
            tamanho_max_mb: TAMANHO_PADRAO_MB,
        },
        'nome',
    );
};

const nomeDoDocumento = (indice: number): string => documentos.value[indice]?.nome.trim() || `Documento ${indice + 1}`;
</script>

<template>
    <div class="flex flex-col gap-5">
        <p v-if="erros.documentos" role="alert" class="text-[0.9375rem] font-semibold text-erro-texto">{{ erros.documentos }}</p>

        <p v-if="documentos.length === 0" class="rounded-[16px] border border-dashed border-tinta-suave/50 p-5 text-[0.9375rem] leading-[1.6] text-tinta-suave">
            Nenhum documento exigido. Sem documentos, a inscrição não pede nenhum arquivo.
        </p>

        <div v-if="documentos.length >= 2" class="-mb-2">
            <button type="button" class="pressionavel inline-flex min-h-11 items-center rounded-pill px-3 text-[0.8125rem] font-bold text-acao hover:bg-nevoa" @click="alternarTodos">
                {{ todosAbertos() ? 'Recolher todos' : 'Expandir todos' }}
            </button>
        </div>

        <ol v-if="documentos.length > 0" class="flex flex-col gap-5">
            <li v-for="(documento, indice) in documentos" :key="documento.chave">
                <article
                    :id="idCampo(documento, 'cartao')"
                    tabindex="-1"
                    :aria-labelledby="idCampo(documento, 'titulo')"
                    class="rounded-[20px] border border-hairline p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acao md:p-6"
                >
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                        <h3 :id="idCampo(documento, 'titulo')" class="min-w-0 flex-1 basis-64 text-[1.125rem] font-bold">
                            <button
                                :id="idCampo(documento, 'alternar')"
                                type="button"
                                :aria-expanded="estaAberto(documento)"
                                :aria-controls="idCampo(documento, 'corpo')"
                                class="pressionavel flex min-h-11 w-full items-center gap-3 rounded-[12px] py-1 text-left"
                                @click="alternar(documento)"
                            >
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 24 24"
                                    class="size-5 shrink-0 text-acao transition-transform duration-200 motion-reduce:transition-none"
                                    :class="estaAberto(documento) ? 'rotate-180' : ''"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M6 9l6 6 6-6" />
                                </svg>
                                <span class="flex min-w-0 flex-col">
                                    <span class="truncate">Documento {{ indice + 1 }}<span class="font-semibold"> · {{ documento.nome.trim() || 'Sem nome' }}</span></span>
                                    <span class="text-[0.875rem] font-normal text-tinta-suave tabular-nums">{{ resumo(documento) }}</span>
                                </span>
                                <span
                                    v-if="quantidadeDeErros(indice) > 0"
                                    class="ml-auto shrink-0 rounded-pill bg-erro-fundo px-2.5 py-0.5 text-[0.8125rem] font-bold text-erro-texto"
                                >
                                    {{ quantidadeDeErros(indice) }}<span class="sr-only"> {{ quantidadeDeErros(indice) === 1 ? 'erro' : 'erros' }} neste documento</span>
                                </span>
                            </button>
                        </h3>

                        <div class="-mx-3 flex flex-wrap items-center">
                            <button
                                :id="idCampo(documento, 'subir')"
                                type="button"
                                :disabled="indice === 0"
                                :class="classeBotaoCartao"
                                @click="mover(indice, -1, 'subir')"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 19V5M6 11l6-6 6 6" />
                                </svg>
                                Subir<span class="sr-only"> documento {{ indice + 1 }}</span>
                            </button>
                            <button
                                :id="idCampo(documento, 'descer')"
                                type="button"
                                :disabled="indice === documentos.length - 1"
                                :class="classeBotaoCartao"
                                @click="mover(indice, 1, 'descer')"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 5v14M6 13l6 6 6-6" />
                                </svg>
                                Descer<span class="sr-only"> documento {{ indice + 1 }}</span>
                            </button>
                            <button
                                type="button"
                                class="pressionavel inline-flex min-h-11 items-center gap-1.5 rounded-pill px-3 text-[0.8125rem] font-bold text-erro-texto hover:bg-erro-fundo"
                                @click="pedirRemocao(indice)"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
                                </svg>
                                Remover<span class="sr-only"> documento {{ indice + 1 }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Consultas de contêiner: a largura do cartão depende das abas laterais, não só da tela. -->
                    <div :id="idCampo(documento, 'corpo')" v-show="estaAberto(documento)" class="@container mt-4 grid gap-x-6 gap-y-5">
                        <CampoCadastro :id="idCampo(documento, 'nome')" rotulo="Nome" obrigatorio :erro="erro(indice, 'nome')" v-slot="{ descricao, invalido }">
                            <input
                                :id="idCampo(documento, 'nome')"
                                v-model="documento.nome"
                                type="text"
                                maxlength="255"
                                required
                                placeholder="Ex.: Estatuto Social"
                                :class="classeCampo"
                                :aria-invalid="invalido || undefined"
                                :aria-describedby="descricao"
                                @input="limpar(indice, 'nome')"
                            />
                        </CampoCadastro>

                        <CampoCadastro
                            :id="idCampo(documento, 'orientacao')"
                            rotulo="Orientação"
                            ajuda="Opcional. Aparece para o proponente junto do documento."
                            :erro="erro(indice, 'orientacao')"
                            v-slot="{ descricao, invalido }"
                        >
                            <input
                                :id="idCampo(documento, 'orientacao')"
                                v-model="documento.orientacao"
                                type="text"
                                maxlength="500"
                                placeholder="Ex.: Em PDF, com assinatura do representante legal."
                                :class="classeCampo"
                                :aria-invalid="invalido || undefined"
                                :aria-describedby="descricao"
                                @input="limpar(indice, 'orientacao')"
                            />
                        </CampoCadastro>

                        <div class="grid gap-x-6 gap-y-5 @lg:grid-cols-2">
                            <CampoCadastro
                                :id="idCampo(documento, 'tamanho')"
                                rotulo="Tamanho máximo"
                                obrigatorio
                                ajuda="Só PDF. De 1 a 20 MB."
                                :erro="erro(indice, 'tamanho_max_mb')"
                                v-slot="{ descricao, invalido }"
                            >
                                <div class="flex items-center gap-3">
                                    <input
                                        :id="idCampo(documento, 'tamanho')"
                                        :value="documento.tamanho_max_mb ?? ''"
                                        type="text"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        required
                                        :class="[classeCampoBase, 'w-24 tabular-nums']"
                                        :aria-invalid="invalido || undefined"
                                        :aria-describedby="descricao"
                                        @input="digitarTamanho(indice, documento, $event)"
                                    />
                                    <span class="text-[0.9375rem] text-tinta-suave">MB</span>
                                </div>
                            </CampoCadastro>

                            <div class="flex items-end">
                                <label :class="classeMarcacao">
                                    <input v-model="documento.obrigatorio" type="checkbox" :class="classeCaixa" @change="limpar(indice, 'obrigatorio')" />
                                    Obrigatório
                                </label>
                            </div>
                        </div>

                        <fieldset class="min-w-0" :aria-describedby="erroDaLista(indice, 'naturezas') ? idCampo(documento, 'naturezas-erro') : undefined">
                            <legend class="text-[0.9375rem] font-semibold">Naturezas<span aria-hidden="true" class="ml-0.5 text-erro-texto">*</span></legend>
                            <div class="mt-1.5 grid gap-x-6 @lg:grid-cols-2">
                                <label v-for="natureza in naturezas" :key="natureza.valor" :class="classeMarcacao">
                                    <input
                                        v-model="documento.naturezas"
                                        type="checkbox"
                                        :value="natureza.valor"
                                        :class="classeCaixa"
                                        :aria-invalid="erroDaLista(indice, 'naturezas') ? true : undefined"
                                        @change="limparLista(indice, 'naturezas')"
                                    />
                                    {{ natureza.rotulo }}
                                </label>
                            </div>
                            <p v-if="erroDaLista(indice, 'naturezas')" :id="idCampo(documento, 'naturezas-erro')" class="mt-1 text-[0.875rem] font-semibold text-erro-texto">
                                {{ erroDaLista(indice, 'naturezas') }}
                            </p>
                        </fieldset>

                        <fieldset class="min-w-0" :aria-describedby="erroDaLista(indice, 'categorias') ? idCampo(documento, 'categorias-erro') : undefined">
                            <legend class="text-[0.9375rem] font-semibold">Categorias</legend>
                            <div class="mt-1.5 flex flex-col">
                                <label :class="classeMarcacao">
                                    <input
                                        type="radio"
                                        :name="idCampo(documento, 'abrangencia')"
                                        :checked="documento.todas_categorias"
                                        :class="classeCaixa"
                                        @change="escolherTodas(indice, documento, true)"
                                    />
                                    Todas as categorias
                                </label>
                                <label :class="classeMarcacao">
                                    <input
                                        type="radio"
                                        :name="idCampo(documento, 'abrangencia')"
                                        :checked="!documento.todas_categorias"
                                        :class="classeCaixa"
                                        :aria-invalid="erroDaLista(indice, 'categorias') ? true : undefined"
                                        @change="escolherTodas(indice, documento, false)"
                                    />
                                    Só algumas categorias
                                </label>
                            </div>

                            <div v-if="!documento.todas_categorias" class="mt-1 border-l-2 border-hairline pl-4">
                                <p v-if="categorias.length === 0" class="py-2 text-[0.875rem] text-tinta-suave">Cadastre as categorias na aba Categorias para escolher aqui.</p>
                                <div v-else class="grid gap-x-6 @lg:grid-cols-2">
                                    <label v-for="(categoria, posicao) in categorias" :key="categoria.chave" :class="classeMarcacao">
                                        <input
                                            v-model="documento.categorias"
                                            type="checkbox"
                                            :value="categoria.chave"
                                            :class="classeCaixa"
                                            :aria-invalid="erroDaLista(indice, 'categorias') ? true : undefined"
                                            @change="limparLista(indice, 'categorias')"
                                        />
                                        <span class="min-w-0 break-words">{{ rotuloDaCategoria(categoria, posicao) }}</span>
                                    </label>
                                </div>
                            </div>
                            <p v-if="erroDaLista(indice, 'categorias')" :id="idCampo(documento, 'categorias-erro')" class="mt-1 text-[0.875rem] font-semibold text-erro-texto">
                                {{ erroDaLista(indice, 'categorias') }}
                            </p>
                        </fieldset>
                    </div>
                </article>
            </li>
        </ol>

        <div class="flex flex-col items-start gap-2">
            <button
                ref="botaoAdicionar"
                type="button"
                :disabled="documentos.length >= MAXIMO"
                class="pressionavel inline-flex min-h-12 items-center justify-center gap-2 rounded-pill border border-acao bg-superficie px-6 text-[0.8125rem] font-bold tracking-[0.12em] text-acao uppercase hover:bg-nevoa disabled:cursor-not-allowed disabled:opacity-50"
                @click="adicionar"
            >
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                Adicionar documento
            </button>
            <p v-if="documentos.length >= MAXIMO" class="text-[0.8125rem] text-tinta-suave">O edital pode ter no máximo {{ MAXIMO }} documentos.</p>
        </div>

        <p class="sr-only" aria-live="polite">{{ anuncio }}</p>

        <dialog
            ref="dialogo"
            aria-labelledby="remover-documento-titulo"
            class="m-auto w-[min(28rem,calc(100vw-2rem))] rounded-card border border-hairline bg-superficie p-6 text-home-tinta backdrop:bg-noite/60"
            @cancel="aRemover = null"
        >
            <h2 id="remover-documento-titulo" class="text-[1.25rem] leading-tight font-bold">
                Remover “{{ aRemover === null ? '' : nomeDoDocumento(aRemover) }}”?
            </h2>
            <p class="mt-2 text-[0.9375rem] text-tinta-suave">O documento sai da lista; a remoção só vale depois de salvar o edital.</p>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    autofocus
                    class="pressionavel inline-flex min-h-11 items-center justify-center rounded-pill border border-tinta-suave/70 bg-superficie px-5 text-[0.8125rem] font-bold tracking-[0.12em] uppercase hover:border-acao hover:text-acao"
                    @click="cancelarRemocao"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="pressionavel inline-flex min-h-11 items-center justify-center rounded-pill border border-erro-texto bg-erro-fundo px-5 text-[0.8125rem] font-bold tracking-[0.12em] text-erro-texto uppercase"
                    @click="remover"
                >
                    Remover
                </button>
            </div>
        </dialog>
    </div>
</template>
