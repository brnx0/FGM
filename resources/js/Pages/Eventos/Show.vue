<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { EventosShowProps } from '../../types';

defineProps<EventosShowProps>();
</script>

<template>
    <article class="mx-auto max-w-3xl px-4 py-12 sm:py-16">
        <Head :title="evento.titulo" />

        <Link href="/#eventos" class="inline-flex min-h-11 items-center font-semibold text-acao hover:underline">
            <span aria-hidden="true">‹</span>&nbsp;Voltar para a página inicial
        </Link>

        <img v-if="evento.imagem" :src="evento.imagem" alt="" class="mt-6 aspect-[16/9] w-full rounded-card border border-hairline object-cover" />

        <p class="mt-6 text-[0.8125rem] font-bold tracking-wide text-acao uppercase">Evento</p>
        <h1 class="mt-2 text-[2rem] break-words sm:text-[3rem]">{{ evento.titulo }}</h1>

        <dl class="mt-8 grid gap-4 rounded-card border border-hairline p-6 sm:grid-cols-2">
            <div>
                <dt class="text-[0.875rem] text-tinta-suave">Data</dt>
                <dd class="font-semibold">{{ evento.dataRealizacao }}</dd>
            </div>
            <div v-if="evento.local">
                <dt class="text-[0.875rem] text-tinta-suave">Local</dt>
                <dd class="font-semibold">{{ evento.local }}</dd>
            </div>
        </dl>

        <p class="mt-8 text-[1.1875rem] whitespace-pre-line">{{ evento.resumo }}</p>

        <!-- HTML limpo ao salvar pela allowlist de App\Support\HtmlSeguro. -->
        <div v-if="evento.descricao" class="conteudo-rico mt-10 border-t border-hairline pt-10" v-html="evento.descricao"></div>

        <Link
            href="/#eventos"
            class="pressionavel mt-12 inline-flex min-h-12 items-center rounded-pill border border-tinta-suave/70 px-6 font-semibold text-home-tinta no-underline hover:border-acao hover:text-acao"
        >
            Voltar para a página inicial
        </Link>
    </article>
</template>
