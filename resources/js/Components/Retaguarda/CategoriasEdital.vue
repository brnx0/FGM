<script setup lang="ts">
import CampoCadastro from '../CampoCadastro.vue';
import { classeBotaoCartao, classeCampo, classeCampoBase, inteiro, useListaEditavel } from './listaEditavel';
import type { CategoriaEditavel, CotaEdital } from '../../types';

const props = defineProps<{
    /** Erros do useForm; os das categorias vêm como "categorias.N.campo". */
    erros: Partial<Record<string, string>>;
    /** NaturezaProponente: as opções de quem pode concorrer. */
    naturezas: { valor: string; rotulo: string }[];
}>();

const emit = defineEmits<{
    /** Campos de erro a limpar no useForm. */
    limparErros: [campos: string[]];
}>();

const categorias = defineModel<CategoriaEditavel[]>({ required: true });

/** Mesmo limite do SalvarEditalRequest. */
const MAXIMO = 30;

/** Mesmo limite de vagas do SalvarEditalRequest. */
const MAXIMO_VAGAS = 10000;

const COTAS: { chave: CotaEdital; rotulo: string }[] = [
    { chave: 'negros', rotulo: 'Pessoas negras' },
    { chave: 'indigenas', rotulo: 'Pessoas indígenas' },
    { chave: 'pcd', rotulo: 'Pessoas com deficiência' },
];

// Chave do cartão de uma categoria nova (as salvas usam o id): não muda ao reordenar.
let proximaChave = 0;
const novaChave = (): string => `nova-${++proximaChave}`;

const idCampo = (categoria: CategoriaEditavel, campo: string): string => `categoria-${categoria.chave}-${campo}`;

// Sanfona (os já salvos começam recolhidos; o novo abre), erros por índice, subir/descer e remoção confirmada.
const {
    estaAberto: estaAberta,
    alternar,
    todosAbertos: todasAbertas,
    alternarTodos: alternarTodas,
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
} = useListaEditavel<CategoriaEditavel>({
    lista: categorias,
    prefixo: 'categorias',
    erros: () => props.erros,
    limparErros: (campos) => emit('limparErros', campos),
    idCampo,
    textoMovido: (posicao, total) => `Categoria movida para a posição ${posicao} de ${total}.`,
    textoRemovido: 'Categoria removida.',
});

const erroNaturezas = (indice: number): string | undefined => erroDaLista(indice, 'naturezas');
const limparNaturezas = (indice: number): void => limparLista(indice, 'naturezas');

const erroCota = (indice: number, cota: CotaEdital): string | undefined => erro(indice, `cotas.${cota}.valor`) ?? erro(indice, `cotas.${cota}.unidade`);

/** Mesma conta de EditalCategoria::vagasReservadas: percentual arredonda para cima. */
const vagasDaCota = (categoria: CategoriaEditavel, cota: CotaEdital): number => {
    const { valor, unidade } = categoria.cotas[cota];
    if (valor === null) return 0;
    if (unidade === 'vagas') return valor;

    return Math.ceil(((categoria.vagas ?? 0) * valor) / 100);
};

const totalReservado = (categoria: CategoriaEditavel): number => COTAS.reduce((soma, cota) => soma + vagasDaCota(categoria, cota.chave), 0);
const passouDoTotal = (categoria: CategoriaEditavel): boolean => totalReservado(categoria) > (categoria.vagas ?? 0);

const vagas = (quantidade: number): string => `${quantidade} ${quantidade === 1 ? 'vaga' : 'vagas'}`;

// Valor em reais com máscara: os dígitos digitados são centavos (1500000 → R$ 15.000,00).
const formatoReais = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const textoValor = (valor: number | null): string => (valor === null ? '' : formatoReais.format(valor));

const resumo = (categoria: CategoriaEditavel): string =>
    [categoria.valor === null ? null : textoValor(categoria.valor), categoria.vagas === null ? null : vagas(categoria.vagas)].filter(Boolean).join(' · ') || 'Valor e vagas a preencher';

const digitarValor = (indice: number, categoria: CategoriaEditavel, evento: Event): void => {
    const campo = evento.target as HTMLInputElement;
    const digitos = campo.value.replace(/\D/g, '').replace(/^0+/, '').slice(0, 11);
    categoria.valor = digitos === '' ? null : Number(digitos) / 100;
    campo.value = textoValor(categoria.valor);
    limpar(indice, 'valor');
};

const digitarVagas = (indice: number, categoria: CategoriaEditavel, evento: Event): void => {
    categoria.vagas = inteiro(evento, MAXIMO_VAGAS);
    limpar(indice, 'vagas', 'cotas');
};

const digitarCota = (indice: number, categoria: CategoriaEditavel, cota: CotaEdital, evento: Event): void => {
    categoria.cotas[cota].valor = inteiro(evento, categoria.cotas[cota].unidade === 'percentual' ? 100 : MAXIMO_VAGAS);
    limpar(indice, `cotas.${cota}.valor`, `cotas.${cota}.unidade`, 'cotas');
};

const adicionar = (): void => {
    acrescentar(
        {
            chave: novaChave(),
            id: null,
            nome: '',
            valor: null,
            vagas: null,
            naturezas: [],
            cotas: {
                negros: { valor: null, unidade: 'vagas' },
                indigenas: { valor: null, unidade: 'vagas' },
                pcd: { valor: null, unidade: 'vagas' },
            },
        },
        'nome',
    );
};

const nomeDaCategoria = (indice: number): string => categorias.value[indice]?.nome.trim() || `Categoria ${indice + 1}`;
</script>

<template>
    <div class="flex flex-col gap-5">
        <p v-if="erros.categorias" role="alert" class="text-[0.9375rem] font-semibold text-erro-texto">{{ erros.categorias }}</p>

        <p v-if="categorias.length === 0" class="rounded-[16px] border border-dashed border-tinta-suave/50 p-5 text-[0.9375rem] leading-[1.6] text-tinta-suave">
            Nenhuma categoria cadastrada. O edital pode ser publicado sem categorias; a inscrição pelo portal vai exigir ao menos uma.
        </p>

        <div v-if="categorias.length >= 2" class="-mb-2">
            <button type="button" class="pressionavel inline-flex min-h-11 items-center rounded-pill px-3 text-[0.8125rem] font-bold text-acao hover:bg-nevoa" @click="alternarTodas">
                {{ todasAbertas() ? 'Recolher todas' : 'Expandir todas' }}
            </button>
        </div>

        <ol v-if="categorias.length > 0" class="flex flex-col gap-5">
            <li v-for="(categoria, indice) in categorias" :key="categoria.chave">
                <article
                    :id="idCampo(categoria, 'cartao')"
                    tabindex="-1"
                    :aria-labelledby="idCampo(categoria, 'titulo')"
                    class="rounded-[20px] border border-hairline p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acao md:p-6"
                >
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                        <h3 :id="idCampo(categoria, 'titulo')" class="min-w-0 flex-1 basis-64 text-[1.125rem] font-bold">
                            <button
                                :id="idCampo(categoria, 'alternar')"
                                type="button"
                                :aria-expanded="estaAberta(categoria)"
                                :aria-controls="idCampo(categoria, 'corpo')"
                                class="pressionavel flex min-h-11 w-full items-center gap-3 rounded-[12px] py-1 text-left"
                                @click="alternar(categoria)"
                            >
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 24 24"
                                    class="size-5 shrink-0 text-acao transition-transform duration-200 motion-reduce:transition-none"
                                    :class="estaAberta(categoria) ? 'rotate-180' : ''"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M6 9l6 6 6-6" />
                                </svg>
                                <span class="flex min-w-0 flex-col">
                                    <span class="truncate">Categoria {{ indice + 1 }}<span class="font-semibold"> · {{ categoria.nome.trim() || 'Sem nome' }}</span></span>
                                    <span class="text-[0.875rem] font-normal text-tinta-suave tabular-nums">{{ resumo(categoria) }}</span>
                                </span>
                                <span
                                    v-if="quantidadeDeErros(indice) > 0"
                                    class="ml-auto shrink-0 rounded-pill bg-erro-fundo px-2.5 py-0.5 text-[0.8125rem] font-bold text-erro-texto"
                                >
                                    {{ quantidadeDeErros(indice) }}<span class="sr-only"> {{ quantidadeDeErros(indice) === 1 ? 'erro' : 'erros' }} nesta categoria</span>
                                </span>
                            </button>
                        </h3>

                        <div class="-mx-3 flex flex-wrap items-center">
                            <button
                                :id="idCampo(categoria, 'subir')"
                                type="button"
                                :disabled="indice === 0"
                                :class="classeBotaoCartao"
                                @click="mover(indice, -1, 'subir')"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 19V5M6 11l6-6 6 6" />
                                </svg>
                                Subir<span class="sr-only"> categoria {{ indice + 1 }}</span>
                            </button>
                            <button
                                :id="idCampo(categoria, 'descer')"
                                type="button"
                                :disabled="indice === categorias.length - 1"
                                :class="classeBotaoCartao"
                                @click="mover(indice, 1, 'descer')"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 5v14M6 13l6 6 6-6" />
                                </svg>
                                Descer<span class="sr-only"> categoria {{ indice + 1 }}</span>
                            </button>
                            <button
                                type="button"
                                class="pressionavel inline-flex min-h-11 items-center gap-1.5 rounded-pill px-3 text-[0.8125rem] font-bold text-erro-texto hover:bg-erro-fundo"
                                @click="pedirRemocao(indice)"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
                                </svg>
                                Remover<span class="sr-only"> categoria {{ indice + 1 }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Consultas de contêiner: a largura do cartão depende das abas laterais, não só da tela. -->
                    <div :id="idCampo(categoria, 'corpo')" v-show="estaAberta(categoria)" class="@container mt-4 grid gap-x-6 gap-y-5">
                        <CampoCadastro :id="idCampo(categoria, 'nome')" rotulo="Nome" obrigatorio :erro="erro(indice, 'nome')" v-slot="{ descricao, invalido }">
                            <input
                                :id="idCampo(categoria, 'nome')"
                                v-model="categoria.nome"
                                type="text"
                                maxlength="255"
                                required
                                placeholder="Ex.: Produção de curta-metragem"
                                :class="classeCampo"
                                :aria-invalid="invalido || undefined"
                                :aria-describedby="descricao"
                                @input="limpar(indice, 'nome')"
                            />
                        </CampoCadastro>

                        <div class="grid gap-x-6 gap-y-5 @lg:grid-cols-2">
                            <CampoCadastro :id="idCampo(categoria, 'valor')" rotulo="Valor por proposta" obrigatorio :erro="erro(indice, 'valor')" v-slot="{ descricao, invalido }">
                                <input
                                    :id="idCampo(categoria, 'valor')"
                                    :value="textoValor(categoria.valor)"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    required
                                    placeholder="R$ 0,00"
                                    :class="[classeCampo, 'tabular-nums']"
                                    :aria-invalid="invalido || undefined"
                                    :aria-describedby="descricao"
                                    @input="digitarValor(indice, categoria, $event)"
                                />
                            </CampoCadastro>

                            <CampoCadastro :id="idCampo(categoria, 'vagas')" rotulo="Vagas" obrigatorio :erro="erro(indice, 'vagas')" v-slot="{ descricao, invalido }">
                                <input
                                    :id="idCampo(categoria, 'vagas')"
                                    :value="categoria.vagas ?? ''"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    required
                                    :class="[classeCampo, 'tabular-nums']"
                                    :aria-invalid="invalido || undefined"
                                    :aria-describedby="descricao"
                                    @input="digitarVagas(indice, categoria, $event)"
                                />
                            </CampoCadastro>
                        </div>

                        <fieldset class="min-w-0" :aria-describedby="erroNaturezas(indice) ? idCampo(categoria, 'naturezas-erro') : undefined">
                            <legend class="text-[0.9375rem] font-semibold">
                                Naturezas que podem concorrer<span aria-hidden="true" class="ml-0.5 text-erro-texto">*</span>
                            </legend>
                            <div class="mt-1.5 grid gap-x-6 @lg:grid-cols-2">
                                <label v-for="natureza in naturezas" :key="natureza.valor" class="flex min-h-11 cursor-pointer items-start gap-3 py-2 text-[0.9375rem] leading-[1.5]">
                                    <input
                                        v-model="categoria.naturezas"
                                        type="checkbox"
                                        :value="natureza.valor"
                                        class="mt-0.5 size-5 shrink-0 cursor-pointer accent-acao"
                                        :aria-invalid="erroNaturezas(indice) ? true : undefined"
                                        @change="limparNaturezas(indice)"
                                    />
                                    {{ natureza.rotulo }}
                                </label>
                            </div>
                            <p v-if="erroNaturezas(indice)" :id="idCampo(categoria, 'naturezas-erro')" class="mt-1 text-[0.875rem] font-semibold text-erro-texto">
                                {{ erroNaturezas(indice) }}
                            </p>
                        </fieldset>

                        <fieldset class="min-w-0">
                            <legend class="text-[0.9375rem] font-semibold">Reserva de cotas</legend>
                            <p class="mt-1 text-[0.8125rem] leading-[1.5] text-tinta-suave">Opcional. Informe em número de vagas ou em percentual das vagas da categoria.</p>

                            <div class="mt-3 flex flex-col gap-3">
                                <div v-for="cota in COTAS" :key="cota.chave" class="flex flex-col gap-1.5">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                        <label :for="idCampo(categoria, `cota-${cota.chave}`)" class="w-full text-[0.9375rem] @lg:w-48 @lg:shrink-0">{{ cota.rotulo }}</label>
                                        <input
                                            :id="idCampo(categoria, `cota-${cota.chave}`)"
                                            :value="categoria.cotas[cota.chave].valor ?? ''"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            :class="[classeCampoBase, 'w-24 tabular-nums']"
                                            :aria-invalid="erroCota(indice, cota.chave) || (erro(indice, 'cotas') && categoria.cotas[cota.chave].valor !== null) ? true : undefined"
                                            :aria-describedby="
                                                [
                                                    erroCota(indice, cota.chave) && idCampo(categoria, `cota-${cota.chave}-erro`),
                                                    erro(indice, 'cotas') && idCampo(categoria, 'cotas-erro'),
                                                ]
                                                    .filter(Boolean)
                                                    .join(' ') || undefined
                                            "
                                            @input="digitarCota(indice, categoria, cota.chave, $event)"
                                        />
                                        <select
                                            v-model="categoria.cotas[cota.chave].unidade"
                                            :aria-label="`Unidade da cota de ${cota.rotulo.toLowerCase()}`"
                                            :class="[classeCampoBase, 'w-28']"
                                            @change="limpar(indice, `cotas.${cota.chave}.valor`, `cotas.${cota.chave}.unidade`, 'cotas')"
                                        >
                                            <option value="vagas">vagas</option>
                                            <option value="percentual">%</option>
                                        </select>
                                        <span
                                            v-if="categoria.cotas[cota.chave].unidade === 'percentual' && categoria.cotas[cota.chave].valor !== null"
                                            class="text-[0.9375rem] whitespace-nowrap text-tinta-suave tabular-nums"
                                        >
                                            = {{ vagas(vagasDaCota(categoria, cota.chave)) }}
                                        </span>
                                    </div>
                                    <p v-if="erroCota(indice, cota.chave)" :id="idCampo(categoria, `cota-${cota.chave}-erro`)" class="text-[0.875rem] font-semibold text-erro-texto">
                                        {{ erroCota(indice, cota.chave) }}
                                    </p>
                                </div>
                            </div>

                            <p class="mt-4 text-[0.9375rem] font-semibold tabular-nums" :class="passouDoTotal(categoria) ? 'text-erro-texto' : 'text-home-tinta'">
                                Total reservado: {{ totalReservado(categoria) }} de {{ vagas(categoria.vagas ?? 0) }}
                            </p>
                            <p v-if="erro(indice, 'cotas')" :id="idCampo(categoria, 'cotas-erro')" class="mt-1 text-[0.875rem] font-semibold text-erro-texto">
                                {{ erro(indice, 'cotas') }}
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
                :disabled="categorias.length >= MAXIMO"
                class="pressionavel inline-flex min-h-12 items-center justify-center gap-2 rounded-pill border border-acao bg-superficie px-6 text-[0.8125rem] font-bold tracking-[0.12em] text-acao uppercase hover:bg-nevoa disabled:cursor-not-allowed disabled:opacity-50"
                @click="adicionar"
            >
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                Adicionar categoria
            </button>
            <p v-if="categorias.length >= MAXIMO" class="text-[0.8125rem] text-tinta-suave">O edital pode ter no máximo {{ MAXIMO }} categorias.</p>
        </div>

        <p class="sr-only" aria-live="polite">{{ anuncio }}</p>

        <dialog
            ref="dialogo"
            aria-labelledby="remover-categoria-titulo"
            class="m-auto w-[min(28rem,calc(100vw-2rem))] rounded-card border border-hairline bg-superficie p-6 text-home-tinta backdrop:bg-noite/60"
            @cancel="aRemover = null"
        >
            <h2 id="remover-categoria-titulo" class="text-[1.25rem] leading-tight font-bold">
                Remover “{{ aRemover === null ? '' : nomeDaCategoria(aRemover) }}”?
            </h2>
            <p class="mt-2 text-[0.9375rem] text-tinta-suave">A categoria sai da lista; a remoção só vale depois de salvar o edital.</p>

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
