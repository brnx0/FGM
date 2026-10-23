<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import EditalCard from '../Components/EditalCard.vue';
import EventoCard from '../Components/EventoCard.vue';
import GovBrButton from '../Components/GovBrButton.vue';

const props = defineProps({
    totalAbertos: { type: Number, required: true },
    editais: { type: Array, required: true },
    eventos: { type: Array, required: true },
});

const usuario = computed(() => usePage().props.auth?.user);
const textoAbertos = computed(() =>
    props.totalAbertos === 1 ? 'edital com inscrições abertas.' : 'editais com inscrições abertas.',
);
</script>

<template>
    <Head title="Início" />

    <!-- Abertura: ilustração de Salvador (decorativa) sob o véu azul. -->
    <section class="abertura relative isolate flex min-h-[520px] items-center overflow-hidden text-white md:min-h-[70vh]" aria-labelledby="abertura-titulo">
        <img src="/images/hero-salvador.jpg" alt="" role="presentation" class="abertura-imagem absolute inset-0 -z-10 size-full object-cover" />
        <div class="abertura-veu absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="relative z-10 mx-auto w-full max-w-6xl px-4 pt-16 pb-28 text-center md:pb-36">
            <h1 id="abertura-titulo" class="text-[1.875rem] sm:text-[2.75rem] lg:text-[3.5rem]">Sua cultura começa aqui.</h1>
            <p class="mx-auto mt-4 max-w-xl text-[1.0625rem] sm:text-[1.25rem]">
                Inscreva-se nos editais da Fundação Gregório de Mattos usando sua conta gov.br.
            </p>
            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <Link
                    v-if="usuario"
                    href="/cadastro"
                    class="pressionavel inline-flex min-h-12 items-center rounded-pill border border-transparent bg-fundo px-6 font-semibold text-acao no-underline hover:bg-nevoa"
                >
                    Continuar meu cadastro
                </Link>
                <GovBrButton v-else size="lg" />
                <a
                    href="#editais"
                    class="pressionavel inline-flex min-h-12 items-center rounded-pill border border-white px-6 font-semibold text-white no-underline hover:bg-white/10"
                >
                    Ver editais
                </a>
            </div>
        </div>

        <svg class="onda absolute inset-x-0 -bottom-px z-10 h-12 w-full md:h-20" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true" focusable="false">
            <path d="M0 48c160 28 320 32 480 16S800 8 960 16s320 40 480 32v32H0z" />
        </svg>
    </section>

    <section id="editais" class="faixa-editais scroll-mt-20 bg-acao px-4 pt-14 pb-20 text-sobre-acao" aria-labelledby="editais-titulo">
        <div class="mx-auto max-w-6xl">
            <div class="text-center">
                <p class="text-[4rem] leading-none font-extrabold tracking-[-0.04em] sm:text-[5.5rem]" aria-hidden="true">{{ totalAbertos }}</p>
                <h2 id="editais-titulo" class="mt-2 text-[1.75rem] sm:text-[2.25rem]">
                    <span class="sr-only">{{ totalAbertos }} </span>{{ textoAbertos }}
                </h2>
            </div>

            <ul v-if="editais.length" class="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 grade3:grid-cols-3">
                <li v-for="edital in editais" :key="edital.slug">
                    <EditalCard :edital="edital" />
                </li>
            </ul>
            <p v-else class="mt-8 text-center text-[1.1875rem]">
                Nenhum edital com inscrições abertas no momento. Volte em breve.
            </p>
        </div>
    </section>

    <section id="eventos" class="scroll-mt-20 bg-nevoa px-4 py-16" aria-labelledby="eventos-titulo">
        <div class="mx-auto max-w-6xl">
            <div class="text-center">
                <h2 id="eventos-titulo" class="text-[1.75rem] sm:text-[2.5rem]">Já aconteceu.</h2>
                <p class="mt-2 text-tinta-suave">O que a cultura de Salvador viveu nos últimos meses.</p>
            </div>
            <ul v-if="eventos.length" class="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 grade3:grid-cols-3">
                <li v-for="(evento, i) in eventos" :key="i">
                    <EventoCard :evento="evento" />
                </li>
            </ul>
            <p v-else class="mt-8 text-center text-[1.1875rem]">Nenhum evento publicado ainda.</p>
        </div>
    </section>
</template>
