<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Interruptor from '../../Interruptor.vue';
import { classeCaixa, classeDica, classeMarcacao } from '../listaEditavel';
import { naturezasOferecidas } from '../questionario';
import type { CategoriaDoQuestionario, OpcaoFormulario, VinculoEditavel } from '../../../types';

/**
 * Para quais categorias e naturezas uma pergunta vale (no cartão da pergunta e no vínculo em lote).
 * "Algumas categorias" sem nenhuma marcada continua sem vínculo até marcar uma.
 */
const props = defineProps<{
    /** Prefixo dos ids, único na página. */
    id: string;
    /** Categorias gravadas do edital, na ordem em que estão. */
    categorias: CategoriaDoQuestionario[];
    /** Naturezas aceitas pelo edital (aba Participação). */
    naturezasDoEdital: string[];
    /** Todas as naturezas (rótulos). */
    naturezas: OpcaoFormulario[];
    erroCategorias?: string;
    erroNaturezas?: string;
}>();

const emit = defineEmits<{
    /** Campo mudado: o erro dele sai. */
    limpar: [campo: 'categorias' | 'naturezas'];
}>();

const vinculo = defineModel<VinculoEditavel>({ required: true });

type Modo = 'todas' | 'algumas' | 'sem';

const modoDoVinculo = (atual: VinculoEditavel, anterior?: Modo): Modo => {
    if (atual.todas_categorias) return 'todas';
    if (atual.categorias.length > 0 || anterior === 'algumas') return 'algumas';

    return 'sem';
};

const modo = ref<Modo>(modoDoVinculo(vinculo.value));

// Vínculo mudado por fora (vínculo em lote, categoria apagada): a escolha acompanha.
watch(
    () => [vinculo.value.todas_categorias, vinculo.value.categorias.length] as const,
    () => {
        modo.value = modoDoVinculo(vinculo.value, modo.value);
    },
);

const escolherModo = (novo: Modo): void => {
    modo.value = novo;
    vinculo.value.todas_categorias = novo === 'todas';
    if (novo !== 'algumas') vinculo.value.categorias = [];
    emit('limpar', 'categorias');
};

const OPCOES_MODO: { valor: Modo; rotulo: string }[] = [
    { valor: 'todas', rotulo: 'Todas as categorias' },
    { valor: 'algumas', rotulo: 'Algumas categorias' },
    { valor: 'sem', rotulo: 'Sem vínculo' },
];

const rotuloDaCategoria = (categoria: CategoriaDoQuestionario, posicao: number): string => categoria.nome.trim() || `Categoria ${posicao + 1}`;

const marcarCategoria = (chave: string, marcada: boolean): void => {
    const sem = vinculo.value.categorias.filter((outra) => outra !== chave);
    vinculo.value.categorias = marcada ? [...sem, chave] : sem;
    emit('limpar', 'categorias');
};

const oferecidas = computed<OpcaoFormulario[]>(() => naturezasOferecidas(vinculo.value, props.categorias, props.naturezasDoEdital, props.naturezas));

// Natureza que deixou de ser oferecida (categoria desmarcada) sai da escolha: nenhuma marcada = todas as oferecidas.
watch(
    () => oferecidas.value.map((natureza) => natureza.valor).join('|'),
    () => {
        const valores = new Set(oferecidas.value.map((natureza) => natureza.valor));
        if (vinculo.value.naturezas.some((natureza) => !valores.has(natureza))) {
            vinculo.value.naturezas = vinculo.value.naturezas.filter((natureza) => valores.has(natureza));
        }
    },
);

const marcarNatureza = (valor: string, marcada: boolean): void => {
    const sem = vinculo.value.naturezas.filter((outra) => outra !== valor);
    vinculo.value.naturezas = marcada ? [...sem, valor] : sem;
    emit('limpar', 'naturezas');
};
</script>

<template>
    <div class="flex min-w-0 flex-col gap-4">
        <fieldset class="min-w-0" :aria-describedby="erroCategorias ? `${id}-categorias-erro` : undefined">
            <legend class="text-[0.9375rem] font-semibold">Vale para</legend>
            <!-- Com erro, o grupo inteiro fica destacado e os campos marcados como inválidos (o foco vai ao primeiro). -->
            <div class="mt-1.5" :class="{ 'campo-invalido rounded-[12px] px-3 py-1': erroCategorias }">
                <div class="flex flex-wrap gap-x-6">
                    <label v-for="opcao in OPCOES_MODO" :key="opcao.valor" :class="classeMarcacao">
                        <input
                            type="radio"
                            :name="`${id}-modo`"
                            :checked="modo === opcao.valor"
                            :class="classeCaixa"
                            :aria-invalid="erroCategorias ? true : undefined"
                            :aria-describedby="erroCategorias ? `${id}-categorias-erro` : undefined"
                            @change="escolherModo(opcao.valor)"
                        />
                        {{ opcao.rotulo }}
                    </label>
                </div>
                <p v-if="modo === 'sem'" :class="[classeDica, 'mt-1']">Pergunta sem vínculo não aparece na inscrição e impede a publicação do edital.</p>

                <div v-if="modo === 'algumas'" class="mt-1 grid gap-x-6 border-l-2 border-hairline pl-4 @lg:grid-cols-2">
                    <p v-if="categorias.length === 0" :class="[classeDica, 'py-2']">Cadastre as categorias no edital (aba Categorias).</p>
                    <Interruptor
                        v-for="(categoria, posicao) in categorias"
                        :id="`${id}-categoria-${categoria.chave}`"
                        :key="categoria.chave"
                        :rotulo="rotuloDaCategoria(categoria, posicao)"
                        variante="linha"
                        :invalido="Boolean(erroCategorias)"
                        :descricao="erroCategorias ? `${id}-categorias-erro` : undefined"
                        :model-value="vinculo.categorias.includes(categoria.chave)"
                        @update:model-value="marcarCategoria(categoria.chave, $event)"
                    />
                    <p v-if="categorias.length > 0 && vinculo.categorias.length === 0" :class="[classeDica, 'py-1 @lg:col-span-2']">Marque ao menos uma categoria.</p>
                </div>
            </div>
            <p v-if="erroCategorias" :id="`${id}-categorias-erro`" class="mt-1 text-[0.875rem] font-semibold text-invalido-texto">{{ erroCategorias }}</p>
        </fieldset>

        <fieldset
            v-if="oferecidas.length > 0 || erroNaturezas"
            class="min-w-0"
            :aria-describedby="[`${id}-naturezas-ajuda`, erroNaturezas && `${id}-naturezas-erro`].filter(Boolean).join(' ')"
        >
            <legend class="text-[0.9375rem] font-semibold">Naturezas</legend>
            <p :id="`${id}-naturezas-ajuda`" :class="[classeDica, 'mt-1']">Nenhuma marcada = todas as que as categorias aceitam.</p>
            <div class="mt-1 grid gap-x-6 @lg:grid-cols-2" :class="{ 'campo-invalido rounded-[12px] px-3 py-1': erroNaturezas }">
                <Interruptor
                    v-for="natureza in oferecidas"
                    :id="`${id}-natureza-${natureza.valor}`"
                    :key="natureza.valor"
                    :rotulo="natureza.rotulo"
                    variante="linha"
                    :invalido="Boolean(erroNaturezas)"
                    :descricao="erroNaturezas ? `${id}-naturezas-erro` : undefined"
                    :model-value="vinculo.naturezas.includes(natureza.valor)"
                    @update:model-value="marcarNatureza(natureza.valor, $event)"
                />
            </div>
            <p v-if="erroNaturezas" :id="`${id}-naturezas-erro`" class="mt-1 text-[0.875rem] font-semibold text-invalido-texto">{{ erroNaturezas }}</p>
        </fieldset>
    </div>
</template>
