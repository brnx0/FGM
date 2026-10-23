<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { EditalResumo } from '../types';

defineProps<{
    edital: EditalResumo;
}>();
</script>

<template>
    <article class="flex h-full flex-col rounded-card border border-hairline bg-fundo p-6 text-left text-tinta">
        <p
            class="text-[0.8125rem] font-bold tracking-wide uppercase"
            :class="edital.situacao === 'aberto' ? 'text-acao' : 'text-tinta-suave'"
        >
            <template v-if="edital.situacao === 'em_breve'">
                {{ edital.situacaoRotulo }} · inscrições a partir de {{ edital.inscricoesInicio }}
            </template>
            <template v-else>{{ edital.situacaoRotulo }} · até {{ edital.inscricoesFim }}</template>
        </p>
        <h3 class="mt-2 text-[1.375rem]">{{ edital.titulo }}</h3>
        <p class="mt-1 text-[0.9375rem] text-tinta-suave">{{ edital.categoria }}</p>
        <Link
            :href="`/editais/${edital.slug}`"
            class="mt-auto inline-flex min-h-11 items-center self-start pt-3 font-semibold text-acao hover:underline"
        >
            Saiba mais<span class="sr-only"> sobre {{ edital.titulo }}</span>&nbsp;<span aria-hidden="true">›</span>
        </Link>
    </article>
</template>
