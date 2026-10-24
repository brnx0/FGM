<script setup lang="ts">
import { computed } from 'vue';
import type { Evento } from '../types';

const props = defineProps<{
    evento: Evento;
    /** Posição no carrossel, começando em 1. */
    posicao: number;
    total: number;
}>();

// Cards sem foto alternam quatro pares de cor (fitas do Bonfim + azul da marca),
// cada um com um ícone grande próprio.
type Icone = 'ingresso' | 'musica' | 'patrimonio' | 'microfone';
type Tom = { fundo: string; icone: string; desenho: Icone };
const tons: readonly [Tom, ...Tom[]] = [
    { fundo: 'bg-ilustra-azul', icone: 'text-ilustra-azul-icone', desenho: 'ingresso' },
    { fundo: 'bg-ilustra-amarelo', icone: 'text-ilustra-amarelo-icone', desenho: 'musica' },
    { fundo: 'bg-ilustra-verde', icone: 'text-ilustra-verde-icone', desenho: 'patrimonio' },
    { fundo: 'bg-ilustra-vermelho', icone: 'text-ilustra-vermelho-icone', desenho: 'microfone' },
];

const tom = computed<Tom>(() => tons[(props.posicao - 1) % tons.length] ?? tons[0]);
const idBase = computed<string>(() => `evento-${props.posicao}`);
</script>

<template>
    <!-- Focável para o teclado percorrer os cards (← → movem o foco entre eles). -->
    <article
        tabindex="0"
        class="cartao-evento relative isolate flex size-full flex-col overflow-hidden rounded-cartao"
        :class="evento.imagem ? 'bg-profundo text-white' : 'bg-superficie text-tinta'"
        :aria-labelledby="`${idBase}-posicao ${idBase}-titulo`"
    >
        <span :id="`${idBase}-posicao`" class="sr-only">{{ posicao }} de {{ total }}:</span>

        <template v-if="evento.imagem">
            <img :src="evento.imagem" alt="" loading="lazy" class="cartao-midia absolute inset-0 -z-10 size-full object-cover" />
            <!-- Véu só no topo, para a legibilidade do título sobre a foto. -->
            <div class="veu-cartao absolute inset-x-0 top-0 -z-10 h-4/5" aria-hidden="true"></div>
        </template>

        <div class="px-6 pt-6 lg:px-8 lg:pt-8" :class="{ 'texto-sobre-foto': evento.imagem }">
            <p
                class="text-[0.8125rem] font-semibold first-letter:uppercase lg:text-[0.875rem]"
                :class="evento.imagem ? 'text-white/85' : 'text-tinta-suave'"
            >
                {{ evento.dataRealizacao }}<template v-if="evento.local"> · {{ evento.local }}</template>
            </p>
            <h3
                :id="`${idBase}-titulo`"
                class="mt-2 text-[1.5rem] text-balance lg:text-[1.75rem]"
                :class="evento.imagem ? 'text-white' : 'text-tinta'"
            >
                {{ evento.titulo }}
            </h3>
        </div>

        <template v-if="!evento.imagem">
            <!-- Composição ilustrativa: bloco de cor sólida, círculo de apoio e ícone grande. -->
            <div
                class="cartao-ilustracao relative mx-4 mt-5 flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-card lg:mx-5"
                :class="[tom.fundo, tom.icone]"
                aria-hidden="true"
            >
                <div class="cartao-midia flex size-full items-center justify-center">
                    <span class="absolute -right-12 -bottom-16 size-52 rounded-full bg-current opacity-[0.12] lg:size-64"></span>
                    <span class="absolute -top-10 -left-8 size-28 rounded-full bg-current opacity-[0.07] lg:size-32"></span>
                    <svg
                        viewBox="0 0 24 24"
                        class="relative size-24 lg:size-36"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.25"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <template v-if="tom.desenho === 'ingresso'">
                            <path d="M3 9a3 3 0 0 0 0 6v2a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z" />
                            <path d="M14 5v2M14 11v2M14 17v2" />
                        </template>
                        <template v-else-if="tom.desenho === 'musica'">
                            <path d="M9 18V5l12-2v13" />
                            <circle cx="6" cy="18" r="3" />
                            <circle cx="18" cy="16" r="3" />
                        </template>
                        <template v-else-if="tom.desenho === 'patrimonio'">
                            <path d="M3 21h18M5 18h14M6 18v-7M10 18v-7M14 18v-7M18 18v-7M12 3l8 5H4z" />
                        </template>
                        <template v-else>
                            <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z" />
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3" />
                        </template>
                    </svg>
                </div>
            </div>
            <p class="line-clamp-3 px-6 pt-4 pb-6 text-[0.9375rem] leading-snug text-tinta-suave lg:px-8 lg:pb-7">
                {{ evento.resumo }}
            </p>
        </template>
    </article>
</template>

<style scoped>
.veu-cartao {
    background-image: linear-gradient(
        180deg,
        rgba(11, 21, 38, 0.78) 0%,
        rgba(11, 21, 38, 0.55) 55%,
        rgba(11, 21, 38, 0) 100%
    );
}

/* Reforço sutil para fotos muito claras sob o título. */
.texto-sobre-foto {
    text-shadow: 0 1px 12px rgba(11, 21, 38, 0.45);
}
</style>
