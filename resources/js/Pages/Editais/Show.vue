<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GovBrButton from '../../Components/GovBrButton.vue';

defineProps({
    edital: { type: Object, required: true },
});

const usuario = computed(() => usePage().props.auth?.user);
</script>

<template>
    <Head :title="edital.titulo" />

    <article class="mx-auto max-w-3xl px-4 py-12 sm:py-16">
        <Link href="/#editais" class="inline-flex min-h-11 items-center font-semibold text-acao hover:underline">
            <span aria-hidden="true">‹</span>&nbsp;Todos os editais
        </Link>

        <p
            class="mt-6 text-[0.8125rem] font-bold tracking-wide uppercase"
            :class="edital.situacao === 'aberto' ? 'text-acao' : 'text-tinta-suave'"
        >
            {{ edital.situacaoRotulo }}
        </p>
        <h1 class="mt-2 text-[2rem] sm:text-[3rem]">{{ edital.titulo }}</h1>

        <dl class="mt-8 grid gap-4 rounded-card border border-hairline p-6 sm:grid-cols-3">
            <div>
                <dt class="text-[0.875rem] text-tinta-suave">Situação</dt>
                <dd class="font-semibold">{{ edital.situacaoRotulo }}</dd>
            </div>
            <div>
                <dt class="text-[0.875rem] text-tinta-suave">Inscrições</dt>
                <dd class="font-semibold">{{ edital.inscricoesInicio }} a {{ edital.inscricoesFim }}</dd>
            </div>
            <div>
                <dt class="text-[0.875rem] text-tinta-suave">Categoria</dt>
                <dd class="font-semibold">{{ edital.categoria }}</dd>
            </div>
        </dl>

        <p class="mt-8 text-[1.1875rem]">{{ edital.resumo }}</p>

        <div role="note" class="mt-8 rounded-card border border-acao bg-nevoa p-6">
            <p class="text-[1.25rem] font-extrabold tracking-[-0.025em]">Documentos e inscrição em breve.</p>
            <p class="mt-1 text-tinta-suave">
                O edital completo e o formulário de inscrição serão publicados aqui. Entre com sua conta gov.br para adiantar o seu cadastro.
            </p>
        </div>

        <div class="mt-8">
            <Link
                v-if="usuario"
                href="/cadastro"
                class="pressionavel inline-flex min-h-12 items-center rounded-pill bg-acao px-6 font-semibold text-sobre-acao no-underline hover:bg-acao-escuro"
            >
                Continuar meu cadastro
            </Link>
            <GovBrButton v-else size="lg" />
        </div>
    </article>
</template>
