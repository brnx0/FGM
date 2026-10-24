<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import EventoCard from './EventoCard.vue';
import type { Evento } from '../types';

const props = defineProps<{
    eventos: Evento[];
}>();

const ID_TRILHO = 'eventos-trilho';

const trilho = ref<HTMLUListElement | null>(null);
const noInicio = ref<boolean>(true);
const noFim = ref<boolean>(false);
// Começa como "cabe tudo" para as setas não piscarem antes da primeira medição.
const cabeTudo = ref<boolean>(true);

let observador: ResizeObserver | undefined;
let quadro = 0;

const atualizar = (): void => {
    const el = trilho.value;
    if (!el) return;
    const maximo = el.scrollWidth - el.clientWidth;
    cabeTudo.value = maximo <= 1;
    noInicio.value = el.scrollLeft <= 1;
    noFim.value = el.scrollLeft >= maximo - 1;
};

// Um evento de rolagem por quadro basta para o estado das setas.
const aoRolar = (): void => {
    if (quadro) return;
    quadro = requestAnimationFrame(() => {
        quadro = 0;
        atualizar();
    });
};

const comportamento = (): ScrollBehavior =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';

/** Largura de um card mais o espaço entre cards. */
const passo = (el: HTMLElement): number => {
    const card = el.querySelector<HTMLElement>(':scope > li');
    const vao = parseFloat(getComputedStyle(el).columnGap) || 0;
    return (card?.offsetWidth ?? el.clientWidth) + vao;
};

const rolar = (direcao: 1 | -1): void => {
    const el = trilho.value;
    if (!el) return;
    if ((direcao < 0 && noInicio.value) || (direcao > 0 && noFim.value)) return;
    el.scrollBy({ left: direcao * passo(el), behavior: comportamento() });
};

const rolarPara = (posicao: 'inicio' | 'fim'): void => {
    const el = trilho.value;
    if (!el) return;
    el.scrollTo({ left: posicao === 'inicio' ? 0 : el.scrollWidth, behavior: comportamento() });
};

/** Com o foco num card, ← → / Home / End movem o foco entre os cards (que rolam junto). */
const focarCard = (atual: HTMLElement, tecla: string): boolean => {
    const itens = Array.from(trilho.value?.querySelectorAll<HTMLElement>(':scope > li > article') ?? []);
    const indice = itens.indexOf(atual);
    if (indice < 0) return false;
    let destino: number;
    switch (tecla) {
        case 'ArrowRight':
            destino = Math.min(indice + 1, itens.length - 1);
            break;
        case 'ArrowLeft':
            destino = Math.max(indice - 1, 0);
            break;
        case 'Home':
            destino = 0;
            break;
        case 'End':
            destino = itens.length - 1;
            break;
        default:
            return false;
    }
    itens[destino]?.focus();
    return true;
};

const aoTeclar = (evento: KeyboardEvent): void => {
    if (evento.target instanceof HTMLElement && evento.target !== trilho.value) {
        if (focarCard(evento.target, evento.key)) evento.preventDefault();
        return;
    }
    switch (evento.key) {
        case 'ArrowRight':
            rolar(1);
            break;
        case 'ArrowLeft':
            rolar(-1);
            break;
        case 'Home':
            rolarPara('inicio');
            break;
        case 'End':
            rolarPara('fim');
            break;
        default:
            return;
    }
    evento.preventDefault();
};

// Observa o trilho e o texto do primeiro card. Os cards têm largura em px, mas o recuo
// do trilho e o texto são em rem: o texto muda de altura com A- / A / A+ e avisa que o
// recuo (e, portanto, a rolagem máxima) pode ter mudado.
const observar = (): void => {
    const el = trilho.value;
    if (!observador || !el) return;
    observador.disconnect();
    observador.observe(el);
    const texto = el.querySelector<HTMLElement>(':scope > li h3');
    if (texto) observador.observe(texto);
};

watch(
    () => props.eventos.length,
    () =>
        nextTick(() => {
            observar();
            atualizar();
        }),
);

onMounted(() => {
    observador = new ResizeObserver(atualizar);
    observar();
    atualizar();
});

onBeforeUnmount(() => {
    observador?.disconnect();
    if (quadro) cancelAnimationFrame(quadro);
});
</script>

<template>
    <div>
        <!-- O trilho recebe foco para rolar pelo teclado (setas, Home e End). O respiro
             vertical evita cortar o card ampliado e a sombra do hover. -->
        <ul
            :id="ID_TRILHO"
            ref="trilho"
            tabindex="0"
            aria-label="Eventos realizados"
            class="trilho-carrossel flex gap-5 overflow-x-auto pt-4 pb-10 focus-visible:outline-offset-[-2px]"
            @scroll.passive="aoRolar"
            @keydown="aoTeclar"
        >
            <li
                v-for="(evento, i) in eventos"
                :key="`${i}-${evento.titulo}`"
                class="h-[400px] w-[280px] shrink-0 snap-start sm:h-[440px] sm:w-[320px] lg:h-[500px] lg:w-[372px]"
            >
                <EventoCard :evento="evento" :posicao="i + 1" :total="eventos.length" />
            </li>
        </ul>

        <div v-show="!cabeTudo" class="-mt-4 px-4 sm:px-8 lg:px-16">
            <div class="flex justify-end gap-4">
                <button
                    type="button"
                    class="seta-carrossel pressionavel inline-flex size-11 items-center justify-center rounded-pill bg-seta text-seta-icone backdrop-blur-sm hover:bg-seta-hover aria-disabled:cursor-default aria-disabled:opacity-40 aria-disabled:hover:bg-seta"
                    aria-label="Eventos anteriores"
                    :aria-controls="ID_TRILHO"
                    :aria-disabled="noInicio ? 'true' : 'false'"
                    @click="rolar(-1)"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m14.5 6-6 6 6 6" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="seta-carrossel pressionavel inline-flex size-11 items-center justify-center rounded-pill bg-seta text-seta-icone backdrop-blur-sm hover:bg-seta-hover aria-disabled:cursor-default aria-disabled:opacity-40 aria-disabled:hover:bg-seta"
                    aria-label="Próximos eventos"
                    :aria-controls="ID_TRILHO"
                    :aria-disabled="noFim ? 'true' : 'false'"
                    @click="rolar(1)"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m9.5 6 6 6-6 6" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
