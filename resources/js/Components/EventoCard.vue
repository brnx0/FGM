<script setup lang="ts">
import type { Evento } from '../types';

defineProps<{
    evento: Evento;
}>();
</script>

<template>
    <article class="flex h-full flex-col overflow-hidden rounded-card border border-hairline bg-fundo text-left">
        <img
            v-if="evento.imagem"
            :src="evento.imagem"
            alt=""
            loading="lazy"
            class="aspect-[16/10] w-full object-cover"
        />
        <div v-else class="flex aspect-[16/10] w-full items-center justify-center bg-parchment text-tinta-suave" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="size-10" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5" width="18" height="14" rx="2" />
                <circle cx="9" cy="10" r="1.5" />
                <path d="m21 16-5-5-8 8" />
            </svg>
        </div>
        <div class="flex flex-1 flex-col p-5">
            <h3 class="text-[1.1875rem]">{{ evento.titulo }}</h3>
            <p class="mt-2 text-[0.9375rem] text-tinta-suave first-letter:uppercase">
                {{ evento.dataRealizacao }}<template v-if="evento.local"> · {{ evento.local }}</template>
            </p>
        </div>
    </article>
</template>
