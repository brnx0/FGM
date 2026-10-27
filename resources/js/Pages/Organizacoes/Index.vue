<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FaixaPortal from '../../Components/FaixaPortal.vue';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';
import type { OrganizacoesIndexProps } from '../../types';

defineOptions({ layout: PortalProponenteLayout });

defineProps<OrganizacoesIndexProps>();

// "Associação Cultural Axé" → "AA": primeira letra da primeira e da última palavra, para quem não tem logo.
const iniciais = (nome: string): string => {
    const palavras = nome.trim().split(/\s+/);
    return ((palavras[0]?.[0] ?? '') + (palavras.length > 1 ? (palavras.at(-1)?.[0] ?? '') : '')).toUpperCase();
};

const classeBotaoPrimario =
    'pressionavel inline-flex min-h-12 items-center justify-center rounded-pill bg-acao px-8 text-center text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase no-underline hover:bg-acao-escuro';
const classeBotaoSecundario =
    'pressionavel inline-flex min-h-11 items-center justify-center rounded-pill border border-hairline px-5 text-center text-[0.8125rem] font-bold tracking-[0.12em] text-tinta uppercase no-underline';
</script>

<template>
    <div>
        <Head title="Minhas organizações" />

        <FaixaPortal aria-labelledby="organizacoes-titulo">
            <div class="flex flex-col gap-3">
                <p class="text-[0.75rem] font-semibold tracking-[0.24em] text-white/75 uppercase">Portal do proponente</p>
                <h1 id="organizacoes-titulo" class="font-barlow text-[2.6rem] leading-[0.95] font-extrabold tracking-[0.01em] break-words uppercase md:text-[3.5rem]">
                    Minhas organizações
                </h1>
            </div>
        </FaixaPortal>

        <div class="relative mx-auto -mt-20 flex max-w-6xl flex-col gap-6 px-4 pb-16 md:-mt-24">
            <section
                v-if="organizacoes.length === 0"
                aria-labelledby="organizacoes-vazio"
                class="rounded-[24px] border border-hairline bg-superficie p-6 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:p-10"
            >
                <h2 id="organizacoes-vazio" class="sr-only">Nenhuma organização</h2>
                <p class="max-w-2xl leading-[1.6] text-tinta-suave">Você ainda não está vinculado a nenhuma organização.</p>
                <div class="mt-8 flex">
                    <Link href="/cadastro/pessoa-juridica" :class="classeBotaoPrimario">Cadastrar organização</Link>
                </div>
            </section>

            <template v-else>
                <ul class="grid gap-4 md:grid-cols-2">
                    <li
                        v-for="organizacao in organizacoes"
                        :key="organizacao.id"
                        class="flex flex-col gap-5 rounded-[24px] border border-hairline bg-superficie p-6 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)]"
                    >
                        <div class="flex items-center gap-4">
                            <img
                                v-if="organizacao.logoUrl"
                                :src="organizacao.logoUrl"
                                alt=""
                                class="size-16 shrink-0 rounded-[16px] border border-hairline bg-white object-contain"
                            />
                            <span
                                v-else
                                aria-hidden="true"
                                class="inline-flex size-16 shrink-0 items-center justify-center rounded-[16px] bg-nevoa font-barlow text-[1.5rem] font-extrabold text-acao"
                            >
                                {{ iniciais(organizacao.nome) }}
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-[1.125rem] leading-tight font-bold break-words">{{ organizacao.nome }}</h2>
                                <p class="mt-1 text-[0.875rem] text-tinta-suave tabular-nums">CNPJ {{ organizacao.cnpj }}</p>
                                <p
                                    v-if="organizacao.responsavel"
                                    class="mt-2 inline-flex rounded-pill bg-nevoa px-2.5 py-0.5 text-[0.6875rem] font-bold tracking-[0.08em] text-acao uppercase"
                                >
                                    Responsável
                                </p>
                            </div>
                        </div>

                        <div class="mt-auto flex flex-col gap-3 sm:flex-row">
                            <Link :href="`/organizacoes/${organizacao.id}/editar`" :class="classeBotaoSecundario">
                                Editar<span class="sr-only"> {{ organizacao.nome }}</span>
                            </Link>
                            <Link :href="`/organizacoes/${organizacao.id}/pessoas`" :class="classeBotaoSecundario">
                                Pessoas vinculadas<span class="sr-only"> de {{ organizacao.nome }}</span>
                            </Link>
                        </div>
                    </li>
                </ul>

                <div class="flex">
                    <Link href="/cadastro/pessoa-juridica" :class="classeBotaoPrimario">Cadastrar organização</Link>
                </div>
            </template>
        </div>
    </div>
</template>
