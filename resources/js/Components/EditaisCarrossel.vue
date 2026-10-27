<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { fotosVitrine, fotoVitrine } from './fotosVitrine';
import type { EditalResumo, SituacaoEdital } from '../types';

const props = defineProps<{
    editais: EditalResumo[];
}>();

const ID_SLIDES = 'editais-slides';
const INTERVALO_MS = 7000;
// O leque do anexo tem cinco cards (o do centro e dois de cada lado).
const CARDS_NO_LEQUE = 5;
// Deslocamento horizontal mínimo para o gesto contar como troca de slide.
const ARRASTO_MINIMO_PX = 50;

// As fotos da vitrine vão para os cards pela posição na roda.
const fotos = fotosVitrine;
const foto = fotoVitrine;

// Sem rótulo próprio, vale o situacaoRotulo do backend.
const situacoes: Record<SituacaoEdital, { cor: string; rotulo?: string }> = {
    aberto: { cor: 'bg-situacao-aberto', rotulo: 'Inscrições abertas' },
    em_breve: { cor: 'bg-situacao-em-breve' },
    encerrado: { cor: 'bg-situacao-encerrado' },
};

const regiao = ref<HTMLElement | null>(null);
const total = computed(() => props.editais.length);

/** Menor número de voltas da lista para editais e fotos fecharem o ciclo juntos na roda. */
const voltasAteFecharFotos = (n: number): number => {
    let voltas = 1;
    while ((voltas * n) % fotos.length !== 0) voltas++;
    return voltas;
};

// Com menos editais que cards no leque, a lista se repete na roda para as laterais não ficarem vazias;
// fechando o ciclo junto com o das fotos, os cards à vista nunca repetem foto.
const slides = computed(() => {
    const copias = total.value > 1 && total.value < CARDS_NO_LEQUE ? voltasAteFecharFotos(total.value) : 1;

    return Array.from({ length: total.value * copias }, (_, i) => ({
        edital: props.editais[i % total.value],
        indiceEdital: i % total.value,
        chave: `${props.editais[i % total.value].slug}-${Math.floor(i / total.value)}`,
    }));
});
const tamanhoRoda = computed(() => slides.value.length);

// Índice em slides, não em editais.
const ativo = ref<number>(0);
const editalAtivo = computed(() => slides.value[ativo.value]?.indiceEdital ?? 0);

// Abaixo de md só o vizinho de cada lado aparece; com movimento reduzido não há troca automática.
const estreito = ref<boolean>(false);
const reduzMovimento = ref<boolean>(false);

/** Distância até o slide ativo pelo caminho mais curto da roda: 0 no centro, ±1 e ±2 nas laterais. */
const posicao = (indice: number): number => {
    const n = tamanhoRoda.value;
    let distancia = (((indice - ativo.value) % n) + n) % n;
    if (distancia > n / 2) distancia -= n;
    return distancia;
};
const visivel = (indice: number): boolean => Math.abs(posicao(indice)) <= (estreito.value ? 1 : 2);

/** Para leitores de tela cada edital aparece uma vez só: na cópia mais perto do centro (a do centro é sempre ela). */
const principais = computed(() => {
    const escolhidos = new Map<number, number>();
    slides.value.forEach((slide, i) => {
        const atual = escolhidos.get(slide.indiceEdital);
        if (atual === undefined || Math.abs(posicao(i)) < Math.abs(posicao(atual))) escolhidos.set(slide.indiceEdital, i);
    });

    return new Set(escolhidos.values());
});

// Troca automática (anexo: 7 s), parada pelo botão Pausar e suspensa com o mouse ou o foco no carrossel.
const pausado = ref<boolean>(false);
const mouseDentro = ref<boolean>(false);
const focoDentro = ref<boolean>(false);
const podeRodar = computed(() => total.value > 1 && !reduzMovimento.value);
const rodando = computed(() => podeRodar.value && !pausado.value && !mouseDentro.value && !focoDentro.value);

// Os slides laterais continuam visíveis, então a troca é anunciada por este texto (só quando o usuário troca).
const anuncio = ref<string>('');

let relogio: number | undefined;

const reiniciarRelogio = (): void => {
    window.clearInterval(relogio);
    relogio = rodando.value ? window.setInterval(() => irPara(ativo.value + 1, true), INTERVALO_MS) : undefined;
};

const irPara = (indice: number, automatico = false): void => {
    if (!total.value) return;
    ativo.value = ((indice % tamanhoRoda.value) + tamanhoRoda.value) % tamanhoRoda.value;
    if (!automatico) anuncio.value = `Edital ${editalAtivo.value + 1} de ${total.value}: ${props.editais[editalAtivo.value].titulo}`;
    reiniciarRelogio();
};

// O botão "Mostrar…" some quando o slide vira o ativo: o foco passa para o link dele.
const escolher = (indice: number): void => {
    irPara(indice);
    nextTick(() => regiao.value?.querySelector<HTMLElement>('[data-posicao="0"] .slide-acao a')?.focus({ preventScroll: true }));
};

const aoTeclar = (evento: KeyboardEvent): void => {
    if (evento.key !== 'ArrowLeft' && evento.key !== 'ArrowRight') return;
    evento.preventDefault();
    irPara(ativo.value + (evento.key === 'ArrowRight' ? 1 : -1));
};

const aoSairFoco = (evento: FocusEvent): void => {
    focoDentro.value = regiao.value?.contains(evento.relatedTarget as Node | null) ?? false;
};

// Arrastar para os lados (toque ou mouse) troca o slide; o clique que encerra o gesto é descartado.
let inicioX: number | null = null;
let arrastou = false;

const aoPressionar = (evento: PointerEvent): void => {
    if (!evento.isPrimary || evento.button !== 0) return;
    inicioX = evento.clientX;
    arrastou = false;
};

const aoSoltar = (evento: PointerEvent): void => {
    if (inicioX === null) return;
    const deslocamento = evento.clientX - inicioX;
    inicioX = null;
    if (total.value < 2 || Math.abs(deslocamento) < ARRASTO_MINIMO_PX) return;
    arrastou = true;
    // O clique do gesto vem logo após o pointerup; um toque que só arrastou não gera clique,
    // e a marca não pode sobrar para o próximo clique (ou Enter) de verdade.
    window.setTimeout(() => {
        arrastou = false;
    });
    irPara(ativo.value + (deslocamento < 0 ? 1 : -1));
};

const cancelarArrasto = (): void => {
    inicioX = null;
};

const descartarCliqueDoArrasto = (evento: MouseEvent): void => {
    if (!arrastou) return;
    arrastou = false;
    evento.preventDefault();
    evento.stopPropagation();
};

let consultaEstreita: MediaQueryList | undefined;
let consultaMovimento: MediaQueryList | undefined;

const lerMidia = (): void => {
    estreito.value = consultaEstreita?.matches ?? false;
    reduzMovimento.value = consultaMovimento?.matches ?? false;
};

watch(rodando, reiniciarRelogio);
watch(tamanhoRoda, () => {
    if (ativo.value >= tamanhoRoda.value) ativo.value = 0;
    reiniciarRelogio();
});

onMounted(() => {
    consultaEstreita = window.matchMedia('(width < 48rem)');
    consultaMovimento = window.matchMedia('(prefers-reduced-motion: reduce)');
    consultaEstreita.addEventListener('change', lerMidia);
    consultaMovimento.addEventListener('change', lerMidia);
    lerMidia();
    reiniciarRelogio();
});

onBeforeUnmount(() => {
    window.clearInterval(relogio);
    consultaEstreita?.removeEventListener('change', lerMidia);
    consultaMovimento?.removeEventListener('change', lerMidia);
});
</script>

<template>
    <div
        ref="regiao"
        class="flex flex-col gap-7"
        role="region"
        aria-roledescription="carrossel"
        aria-label="Editais em destaque"
        @keydown="aoTeclar"
        @mouseenter="mouseDentro = true"
        @mouseleave="mouseDentro = false"
        @focusin="focoDentro = true"
        @focusout="aoSairFoco"
    >
        <div
            class="relative h-[520px] touch-pan-y select-none md:h-[740px]"
            @pointerdown="aoPressionar"
            @pointerup="aoSoltar"
            @pointercancel="cancelarArrasto"
            @click.capture="descartarCliqueDoArrasto"
            @dragstart.prevent
        >
            <template v-if="total > 1">
                <button
                    type="button"
                    class="vidro vidro-escuro pressionavel absolute top-1/2 left-0 z-10 -mt-6 inline-flex size-12 items-center justify-center rounded-full text-white md:-mt-8 md:size-16"
                    aria-label="Edital anterior"
                    :aria-controls="ID_SLIDES"
                    @click="irPara(ativo - 1)"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M11 18l-6-6 6-6" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="vidro vidro-escuro pressionavel absolute top-1/2 right-0 z-10 -mt-6 inline-flex size-12 items-center justify-center rounded-full text-white md:-mt-8 md:size-16"
                    aria-label="Próximo edital"
                    :aria-controls="ID_SLIDES"
                    @click="irPara(ativo + 1)"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                </button>
            </template>

            <div :id="ID_SLIDES">
                <article
                    v-for="({ edital, indiceEdital, chave }, i) in slides"
                    :key="chave"
                    class="slide-edital home-escuro absolute top-1/2 left-1/2 grid w-[300px] max-w-[80%] overflow-hidden rounded-[28px] bg-[#0d1117] text-white shadow-[0_50px_90px_-40px_rgba(10,20,40,0.6)] md:w-[500px]"
                    :data-posicao="visivel(i) ? posicao(i) : 'fora'"
                    :inert="!visivel(i)"
                    :aria-hidden="principais.has(i) ? undefined : 'true'"
                    aria-roledescription="slide"
                    :aria-label="`${indiceEdital + 1} de ${total}: ${edital.titulo}`"
                >
                    <img
                        :src="foto(i).src"
                        alt=""
                        class="slide-foto home-foto absolute inset-0 size-full object-cover"
                        :style="{ objectPosition: foto(i).enquadramento }"
                        loading="lazy"
                        decoding="async"
                    />
                    <div
                        aria-hidden="true"
                        class="home-veu absolute inset-0 bg-[linear-gradient(0deg,rgba(4,26,56,0.94)_0%,rgba(4,26,56,0.62)_42%,rgba(4,26,56,0)_72%)]"
                    ></div>
                    <button
                        v-if="posicao(i) !== 0"
                        type="button"
                        class="absolute inset-0 z-[5] cursor-pointer"
                        :tabindex="principais.has(i) ? undefined : -1"
                        :aria-label="`Mostrar ${edital.titulo}`"
                        @click="escolher(i)"
                    ></button>

                    <!-- O espaçador dá a proporção 20/27 do anexo; se o texto for mais alto (título longo, A+), o card cresce. -->
                    <div aria-hidden="true" class="col-start-1 row-start-1 aspect-[20/27]"></div>
                    <div class="slide-texto relative col-start-1 row-start-1 flex flex-col justify-end gap-2.5 p-[22px] md:gap-4 md:p-10">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                            <span class="text-[0.8125rem] font-bold tracking-[0.2em] uppercase">{{ edital.categoria }}</span>
                            <span class="vidro vidro-pilula inline-flex items-center gap-2 rounded-pill px-3.5 py-1.5 text-[0.8125rem] font-semibold">
                                <span aria-hidden="true" class="size-2 rounded-full" :class="situacoes[edital.situacao].cor"></span>
                                {{ situacoes[edital.situacao].rotulo ?? edital.situacaoRotulo }}
                            </span>
                        </div>
                        <h3 class="font-barlow text-[1.9rem] leading-[0.95] font-extrabold tracking-normal uppercase md:text-[3.1rem]">
                            {{ edital.titulo }}
                        </h3>
                        <div aria-hidden="true" class="h-1 w-16 bg-white"></div>
                        <dl class="flex flex-wrap gap-x-11 gap-y-4">
                            <div class="flex flex-col gap-1">
                                <dt class="text-[0.75rem] font-semibold tracking-[0.16em] text-white/80 uppercase">Abertura</dt>
                                <dd class="text-[1.25rem] font-medium">{{ edital.inscricoesInicio }}</dd>
                            </div>
                            <div class="flex flex-col gap-1">
                                <dt class="text-[0.75rem] font-semibold tracking-[0.16em] text-white/80 uppercase">Encerramento</dt>
                                <dd class="text-[1.25rem] font-medium">{{ edital.inscricoesFim }}</dd>
                            </div>
                        </dl>
                        <div class="slide-acao mt-1.5">
                            <a
                                :href="edital.pdf"
                                :download="`edital-${edital.slug}.pdf`"
                                type="application/pdf"
                                class="pressionavel inline-flex min-h-[46px] items-center gap-3 rounded-pill px-[22px] text-[0.8125rem] font-bold tracking-[0.12em] uppercase no-underline md:min-h-[54px] md:px-8"
                                :class="edital.situacao === 'aberto' ? 'botao-claro bg-white text-home-azul' : 'vidro text-white'"
                            >
                                Baixar edital<span class="sr-only">: {{ edital.titulo }} (PDF)</span>
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 4v11M7 10l5 5 5-5M5 20h14" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <p class="sr-only" aria-live="polite">{{ anuncio }}</p>

        <div v-if="podeRodar" class="flex justify-center">
            <button
                type="button"
                class="pressionavel inline-flex min-h-11 items-center gap-2 rounded-pill px-4 text-[0.75rem] font-bold tracking-[0.12em] text-tinta-suave uppercase hover:text-home-tinta"
                @click="pausado = !pausado"
            >
                <svg v-if="pausado" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="currentColor">
                    <path d="M8 5.5v13l10.5-6.5z" />
                </svg>
                <svg v-else aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="currentColor">
                    <path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z" />
                </svg>
                {{ pausado ? 'Continuar' : 'Pausar' }}<span class="sr-only"> a troca automática dos editais</span>
            </button>
        </div>
    </div>
</template>
