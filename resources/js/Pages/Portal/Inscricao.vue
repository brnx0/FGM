<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AvisoCamposObrigatorios from '../../Components/AvisoCamposObrigatorios.vue';
import FaixaPortal from '../../Components/FaixaPortal.vue';
import { focarPrimeiroErro } from '../../Components/focarPrimeiroErro';
import EscolhasDaInscricao from '../../Components/Inscricao/EscolhasDaInscricao.vue';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';
import type { PortalInscricaoProps } from '../../types';

defineOptions({ layout: PortalProponenteLayout });

const props = defineProps<PortalInscricaoProps>();

const formulario = useForm<{ cadastro: string; categoria: number | null; cotas: string[] }>({
    cadastro: '',
    categoria: null,
    cotas: [],
});

const criar = (): void => {
    // Rota portal.inscricoes.store (o front não tem helper de rotas nomeadas).
    formulario.post(`/portal/editais/${props.edital.slug}/inscricoes`, {
        onError: () => focarPrimeiroErro('#formulario-inscricao'),
    });
};
</script>

<template>
    <div>
        <Head :title="`Inscrição — ${edital.titulo}`" />

        <FaixaPortal aria-labelledby="inscricao-titulo">
            <div class="flex flex-col gap-3">
                <!-- Caminho da rota portal.editais (o front não tem helper de rotas nomeadas). -->
                <Link
                    href="/portal/editais"
                    class="inline-flex min-h-11 items-center gap-2 self-start text-[0.8125rem] font-semibold tracking-[0.12em] text-white/85 uppercase no-underline hover:text-white"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M11 6l-6 6 6 6" />
                    </svg>
                    Voltar aos editais
                </Link>
                <h1 id="inscricao-titulo" class="font-barlow text-[2.6rem] leading-[0.95] font-extrabold tracking-[0.01em] break-words uppercase md:text-[3.5rem]">
                    Inscrição
                </h1>
                <p class="max-w-2xl text-base leading-[1.7] text-white/85 md:text-[1.0625rem]">
                    <strong class="font-semibold text-white">{{ edital.titulo }}</strong> · {{ edital.categoria }}<template v-if="edital.periodo"
                        ><br />Inscrições de {{ edital.periodo }}.</template
                    >
                </p>
            </div>
        </FaixaPortal>

        <div class="relative mx-auto -mt-20 flex max-w-6xl flex-col gap-6 px-4 pb-16 md:-mt-24">
            <div
                v-if="perfilIncompleto"
                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-[16px] border border-aviso-texto/40 bg-aviso-fundo px-5 py-4 text-aviso-texto"
            >
                <p class="font-semibold">Complete o seu perfil no cadastro de Pessoa Física para se inscrever.</p>
                <!-- Caminho da rota cadastro (o front não tem helper de rotas nomeadas). -->
                <Link href="/cadastro" class="inline-flex min-h-11 items-center font-bold text-aviso-texto underline">Ir para o cadastro</Link>
            </div>

            <section
                v-if="inscricoes.length > 0"
                aria-labelledby="inscricoes-titulo"
                class="rounded-[24px] border border-hairline bg-superficie p-6 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:p-10"
            >
                <h2 id="inscricoes-titulo" class="text-[1.25rem] font-bold">Suas inscrições neste edital</h2>
                <ul class="mt-4 divide-y divide-hairline border-y border-hairline">
                    <li v-for="inscricao in inscricoes" :key="inscricao.id" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3">
                        <div class="min-w-0">
                            <p class="font-semibold break-words">{{ inscricao.cadastro }}</p>
                            <p class="text-[0.875rem] text-tinta-suave">
                                {{ inscricao.situacaoRotulo }}<template v-if="inscricao.numero"> · nº {{ inscricao.numero }}</template
                                ><template v-if="inscricao.categoria"> · {{ inscricao.categoria }}</template>
                            </p>
                        </div>
                        <Link
                            :href="`/portal/inscricoes/${inscricao.id}`"
                            class="pressionavel inline-flex min-h-11 items-center rounded-pill px-3 text-[0.8125rem] font-bold text-acao hover:bg-nevoa"
                        >
                            {{ inscricao.situacao === 'rascunho' ? 'Continuar' : 'Ver' }}
                            <span class="sr-only"> inscrição de {{ inscricao.cadastro }}</span>
                        </Link>
                    </li>
                </ul>
            </section>

            <section
                aria-labelledby="nova-titulo"
                class="rounded-[24px] border border-hairline bg-superficie p-6 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:p-10"
            >
                <h2 id="nova-titulo" class="text-[1.25rem] font-bold">Nova inscrição</h2>
                <p class="mt-2 max-w-2xl leading-[1.6] text-tinta-suave">
                    Escolha com qual cadastro você vai se inscrever. Depois você anexa os documentos exigidos. Confira no
                    <a :href="edital.pdf" target="_blank" rel="noopener" class="font-semibold text-acao">PDF do edital<span class="sr-only"> (abre em nova aba)</span></a>
                    o que separar.
                </p>

                <form id="formulario-inscricao" novalidate class="mt-6 flex flex-col gap-6" @submit.prevent="criar">
                    <AvisoCamposObrigatorios />
                    <EscolhasDaInscricao
                        v-model:cadastro="formulario.cadastro"
                        v-model:categoria="formulario.categoria"
                        v-model:cotas-escolhidas="formulario.cotas"
                        :cadastros="cadastros"
                        :categorias="categorias"
                        :cotas="cotas"
                        :veda-negro-indigena="vedaNegroIndigena"
                        :erros="formulario.errors"
                        @limpar-erro="(campo) => formulario.clearErrors(campo)"
                    />

                    <div class="flex">
                        <button
                            type="submit"
                            :disabled="formulario.processing || formulario.cadastro === ''"
                            class="pressionavel inline-flex min-h-12 items-center justify-center rounded-pill bg-acao px-8 text-center text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            {{ formulario.processing ? 'Criando…' : 'Criar inscrição' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>
