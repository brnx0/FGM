<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import type { OpcaoCadastro } from '../../types';

// Lista com busca no padrão combobox da ARIA (APG "editable combobox with list autocomplete"):
// digitar filtra, setas percorrem, Enter escolhe, Esc fecha. Usada na natureza jurídica (lista local)
// e no município (consulta ao servidor, com espera entre as teclas).
const props = withDefaults(
    defineProps<{
        id: string;
        /** Nome da lista de opções para o leitor de tela. */
        rotulo: string;
        selecionado: OpcaoCadastro | null;
        /** Devolve as opções do termo; null quando a consulta falhou. */
        buscar: (termo: string, sinal: AbortSignal) => Promise<OpcaoCadastro[] | null> | OpcaoCadastro[];
        /** Caracteres antes de buscar. */
        minimo?: number;
        /** Espera depois da última tecla, em ms. */
        atraso?: number;
        placeholder?: string;
        textoVazio?: string;
        obrigatorio?: boolean;
        /** aria-describedby vindo do CampoCadastro. */
        descricao?: string;
        invalido?: boolean;
    }>(),
    { minimo: 0, atraso: 0, placeholder: undefined, textoVazio: 'Nenhum resultado.', descricao: undefined },
);

const emit = defineEmits<{ escolher: [opcao: OpcaoCadastro | null] }>();

const texto = ref<string>(props.selecionado?.rotulo ?? '');
const aberto = ref<boolean>(false);
const opcoes = ref<OpcaoCadastro[]>([]);
const ativo = ref<number>(-1);
const carregando = ref<boolean>(false);
const falhou = ref<boolean>(false);

const idLista = computed(() => `${props.id}-lista`);
const idOpcao = (indice: number): string => `${props.id}-opcao-${indice}`;
const idStatus = computed(() => `${props.id}-status`);

// Escolha vinda de fora (dados públicos do CNPJ, edição). O null que o próprio campo emite ao digitar
// não apaga o texto.
watch(
    () => props.selecionado,
    (opcao) => {
        if (opcao) texto.value = opcao.rotulo;
    },
);

let espera: ReturnType<typeof setTimeout> | undefined;
let controle: AbortController | null = null;

const cancelar = (): void => {
    clearTimeout(espera);
    controle?.abort();
    controle = null;
};

onBeforeUnmount(cancelar);

const executarBusca = async (termo: string): Promise<void> => {
    controle?.abort();
    const atual = new AbortController();
    controle = atual;
    carregando.value = true;
    falhou.value = false;

    const resultado = await props.buscar(termo, atual.signal);
    if (atual.signal.aborted) return;

    controle = null;
    carregando.value = false;
    falhou.value = resultado === null;
    opcoes.value = resultado ?? [];
    ativo.value = -1;
};

const buscar = (): void => {
    cancelar();
    const termo = texto.value.trim();

    if (termo.length < props.minimo) {
        carregando.value = false;
        opcoes.value = [];
        aberto.value = false;
        return;
    }

    aberto.value = true;
    if (props.atraso > 0) {
        carregando.value = true;
        espera = setTimeout(() => executarBusca(termo), props.atraso);
    } else {
        executarBusca(termo);
    }
};

const digitar = (evento: Event): void => {
    texto.value = (evento.target as HTMLInputElement).value;
    if (props.selecionado) emit('escolher', null);
    buscar();
};

const fechar = (): void => {
    aberto.value = false;
    ativo.value = -1;
};

const escolher = (opcao: OpcaoCadastro): void => {
    cancelar();
    texto.value = opcao.rotulo;
    fechar();
    emit('escolher', opcao);
};

const moverPara = (indice: number): void => {
    ativo.value = indice;
    nextTick(() => document.getElementById(idOpcao(indice))?.scrollIntoView({ block: 'nearest' }));
};

const teclar = (evento: KeyboardEvent): void => {
    const total = opcoes.value.length;

    if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
        evento.preventDefault();
        if (!aberto.value) {
            buscar();
            return;
        }
        if (total === 0) return;
        const passo = evento.key === 'ArrowDown' ? 1 : -1;
        moverPara(ativo.value < 0 ? (passo === 1 ? 0 : total - 1) : (ativo.value + passo + total) % total);
    } else if (evento.key === 'Enter') {
        // Com a lista aberta, Enter escolhe e não envia o formulário.
        if (aberto.value) {
            evento.preventDefault();
            const opcao = opcoes.value[ativo.value];
            if (opcao) escolher(opcao);
        }
    } else if (evento.key === 'Escape') {
        if (aberto.value) {
            evento.preventDefault();
            cancelar();
            carregando.value = false;
            fechar();
        }
    }
};

// O texto que sobrou sem escolha fica no campo; o servidor avisa "Selecione … da lista".
const sair = (): void => {
    cancelar();
    carregando.value = false;
    fechar();
};

const status = computed(() => {
    if (!aberto.value) return '';
    if (carregando.value) return 'Buscando…';
    if (falhou.value) return 'Não foi possível buscar agora. Tente de novo em instantes.';
    if (opcoes.value.length === 0) return props.textoVazio;
    return opcoes.value.length === 1 ? '1 opção. Use as setas para escolher.' : `${opcoes.value.length} opções. Use as setas para escolher.`;
});

</script>

<template>
    <div class="relative">
        <input
            :id="id"
            :value="texto"
            type="text"
            role="combobox"
            autocomplete="off"
            aria-autocomplete="list"
            :aria-expanded="aberto"
            :aria-controls="idLista"
            :aria-activedescendant="aberto && ativo >= 0 ? idOpcao(ativo) : undefined"
            :aria-required="obrigatorio || undefined"
            :aria-invalid="invalido || undefined"
            :aria-describedby="descricao"
            :placeholder="placeholder"
            class="h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie pr-11 pl-4 text-base text-home-tinta placeholder:text-tinta-suave"
            @input="digitar"
            @keydown="teclar"
            @blur="sair"
        />
        <svg
            aria-hidden="true"
            viewBox="0 0 24 24"
            class="pointer-events-none absolute top-1/2 right-4 size-4 -translate-y-1/2 text-tinta-suave"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
        >
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
        </svg>

        <!-- O mousedown não tira o foco do campo, para o clique escolher antes do blur fechar a lista. -->
        <ul
            v-show="aberto"
            :id="idLista"
            role="listbox"
            :aria-label="rotulo"
            class="absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-[12px] border border-hairline bg-superficie py-1 shadow-[0_18px_40px_-20px_rgba(6,36,74,0.5)]"
            @mousedown.prevent
        >
            <li
                v-for="(opcao, indice) in opcoes"
                :id="idOpcao(indice)"
                :key="opcao.valor"
                role="option"
                :aria-selected="indice === ativo"
                class="cursor-pointer px-4 py-2.5 text-[0.9375rem] leading-[1.4] text-home-tinta"
                :class="indice === ativo ? 'bg-nevoa font-semibold text-acao outline-2 -outline-offset-2 outline-acao' : 'hover:bg-nevoa'"
                @click="escolher(opcao)"
                @mousemove="ativo = indice"
            >
                {{ opcao.rotulo }}
            </li>
            <li v-if="opcoes.length === 0" role="presentation" class="px-4 py-2.5 text-[0.9375rem] text-tinta-suave">{{ status }}</li>
        </ul>

        <p :id="idStatus" aria-live="polite" class="sr-only">{{ status }}</p>
    </div>
</template>
