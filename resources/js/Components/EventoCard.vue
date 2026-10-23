<script setup lang="ts">
import { computed } from 'vue';
import type { Evento } from '../types';

const props = defineProps<{
    evento: Evento;
    /** Posição no carrossel, começando em 1. */
    posicao: number;
    total: number;
}>();

// Cards sem foto alternam entre três azuis da paleta, cada um com um ícone discreto.
type Tom = { fundo: string; icone: 'ingresso' | 'musica' | 'patrimonio' };
const tons: readonly Tom[] = [
    { fundo: 'bg-cartao-1', icone: 'ingresso' },
    { fundo: 'bg-cartao-2', icone: 'musica' },
    { fundo: 'bg-cartao-3', icone: 'patrimonio' },
];

const tom = computed<Tom>(() => tons[(props.posicao - 1) % tons.length] ?? tons[0]!);
const idBase = computed<string>(() => `evento-${props.posicao}`);
</script>

<template>
    <article
        class="cartao-evento relative isolate flex size-full flex-col overflow-hidden rounded-cartao text-white"
        :class="evento.imagem ? 'bg-profundo' : tom.fundo"
        :aria-labelledby="`${idBase}-posicao ${idBase}-titulo`"
    >
        <span :id="`${idBase}-posicao`" class="sr-only">{{ posicao }} de {{ total }}:</span>

        <template v-if="evento.imagem">
            <img :src="evento.imagem" alt="" loading="lazy" class="absolute inset-0 -z-10 size-full object-cover" />
            <!-- Véu só no topo, para a legibilidade do título sobre a foto. -->
            <div class="veu-cartao absolute inset-x-0 top-0 -z-10 h-3/5" aria-hidden="true"></div>
        </template>

        <div class="p-6 lg:p-8">
            <p class="text-[0.8125rem] font-semibold text-white/85 first-letter:uppercase lg:text-[0.875rem]">
                {{ evento.dataRealizacao }}<template v-if="evento.local"> · {{ evento.local }}</template>
            </p>
            <h3 :id="`${idBase}-titulo`" class="mt-2 text-[1.5rem] text-balance text-white lg:text-[1.75rem]">
                {{ evento.titulo }}
            </h3>
        </div>

        <div v-if="!evento.imagem" class="mt-auto p-6 lg:p-8">
            <svg
                aria-hidden="true"
                viewBox="0 0 24 24"
                class="size-12 text-white/45 lg:size-14"
                fill="none"
                stroke="currentColor"
                stroke-width="1.25"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <template v-if="tom.icone === 'ingresso'">
                    <path d="M3 9a3 3 0 0 0 0 6v2a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z" />
                    <path d="M14 5v2M14 11v2M14 17v2" />
                </template>
                <template v-else-if="tom.icone === 'musica'">
                    <path d="M9 18V5l12-2v13" />
                    <circle cx="6" cy="18" r="3" />
                    <circle cx="18" cy="16" r="3" />
                </template>
                <template v-else>
                    <path d="M3 21h18M5 18h14M6 18v-7M10 18v-7M14 18v-7M18 18v-7M12 3l8 5H4z" />
                </template>
            </svg>
            <p class="mt-4 line-clamp-3 text-[0.9375rem] text-white/85">{{ evento.resumo }}</p>
        </div>
    </article>
</template>

<style scoped>
.veu-cartao {
    background-image: linear-gradient(180deg, rgba(11, 21, 38, 0.66), rgba(11, 21, 38, 0));
}
</style>
