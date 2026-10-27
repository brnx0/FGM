<script setup lang="ts">
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import FaixaPortal from '../../Components/FaixaPortal.vue';
import TabelaPaginada from '../../Components/Retaguarda/TabelaPaginada.vue';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';
import type { PortalEditaisProps, SituacaoEdital } from '../../types';

defineOptions({ layout: PortalProponenteLayout });

const props = defineProps<PortalEditaisProps>();

// Caminho da rota portal.editais (o front não tem helper de rotas nomeadas).
const BASE = '/portal/editais';

const filtros = reactive({ ...props.filtros });

// Só os filtros preenchidos vão para a URL; a lista volta para a página 1.
const filtrar = (): void => {
    const consulta = Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== ''));
    router.get(BASE, consulta, { preserveState: true, preserveScroll: true, replace: true });
};

const limpar = (): void => {
    Object.assign(filtros, { busca: '', situacao: '' });
    filtrar();
};

const corSituacao: Record<SituacaoEdital, string> = {
    aberto: 'bg-situacao-aberto',
    em_breve: 'bg-situacao-em-breve',
    encerrado: 'bg-situacao-encerrado',
};

const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave';
const classeRotulo = 'text-[0.9375rem] font-semibold';
const classeAcao = 'pressionavel inline-flex min-h-11 items-center gap-1.5 rounded-pill px-3 text-[0.8125rem] font-bold text-acao hover:bg-nevoa';
</script>

<template>
    <div>
        <Head title="Editais" />

        <FaixaPortal aria-labelledby="editais-titulo">
            <div class="flex flex-col gap-3">
                <p class="text-[0.75rem] font-semibold tracking-[0.24em] text-white/75 uppercase">Portal do proponente</p>
                <h1 id="editais-titulo" class="font-barlow text-[2.6rem] leading-[0.95] font-extrabold tracking-[0.01em] break-words uppercase md:text-[3.5rem]">
                    Editais
                </h1>
                <p class="max-w-2xl text-base leading-[1.7] text-white/85 md:text-[1.0625rem]">
                    Os editais de fomento à cultura da Fundação. Abra um edital para ver os detalhes e o PDF.
                </p>
            </div>
        </FaixaPortal>

        <div class="relative mx-auto -mt-20 flex max-w-6xl flex-col gap-6 px-4 pb-16 md:-mt-24">
            <form
                role="search"
                aria-label="Filtrar editais"
                class="grid gap-4 rounded-[24px] border border-hairline bg-superficie p-5 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:grid-cols-[1fr_12rem_auto] md:items-end md:p-6"
                @submit.prevent="filtrar"
            >
                <div class="flex flex-col gap-1.5">
                    <label for="busca" :class="classeRotulo">Buscar pelo título</label>
                    <input id="busca" v-model="filtros.busca" type="search" maxlength="255" placeholder="Parte do título" :class="classeCampo" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="situacao" :class="classeRotulo">Situação</label>
                    <select id="situacao" v-model="filtros.situacao" :class="classeCampo" @change="filtrar">
                        <option value="">Todas</option>
                        <option v-for="opcao in situacoes" :key="opcao.valor" :value="opcao.valor">{{ opcao.rotulo }}</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="pressionavel inline-flex min-h-12 flex-1 items-center justify-center rounded-pill bg-acao px-6 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro md:flex-none"
                    >
                        Filtrar
                    </button>
                    <button
                        v-if="filtros.busca || filtros.situacao"
                        type="button"
                        class="pressionavel inline-flex min-h-12 flex-1 items-center justify-center rounded-pill border border-tinta-suave/70 bg-superficie px-5 text-[0.8125rem] font-bold tracking-[0.12em] uppercase hover:border-acao hover:text-acao md:flex-none"
                        @click="limpar"
                    >
                        Limpar
                    </button>
                </div>
            </form>

            <TabelaPaginada
                legenda="Editais"
                :links="editais.links"
                :total="editais.total"
                :de="editais.from"
                :ate="editais.to"
                vazio="Nenhum edital encontrado."
            >
                <template #cabecalho>
                    <tr>
                        <th scope="col" class="px-4 py-3">Título e categoria</th>
                        <th scope="col" class="px-4 py-3">Inscrições</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Ações</span></th>
                    </tr>
                </template>

                <tr v-for="edital in editais.data" :key="edital.slug" class="align-middle">
                    <th scope="row" class="min-w-56 px-4 py-3 text-left font-normal">
                        <span class="block font-semibold">{{ edital.titulo }}</span>
                        <span class="block text-[0.8125rem] text-tinta-suave">{{ edital.categoria }}</span>
                    </th>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="block tabular-nums">{{ edital.periodo }}</span>
                        <span class="mt-0.5 inline-flex items-center gap-2 text-[0.8125rem] text-tinta-suave">
                            <span aria-hidden="true" class="size-2.5 rounded-full" :class="corSituacao[edital.situacao]" />
                            {{ edital.situacaoRotulo }}
                        </span>
                    </td>
                    <td class="px-2 py-1">
                        <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                            <Link :href="`/editais/${edital.slug}`" :class="classeAcao">
                                Ver edital<span class="sr-only"> {{ edital.titulo }}</span>
                            </Link>
                            <a :href="edital.pdf" :class="classeAcao" target="_blank" rel="noopener">
                                PDF<span class="sr-only"> do edital {{ edital.titulo }} (abre em nova aba)</span>
                            </a>
                        </div>
                    </td>
                </tr>
            </TabelaPaginada>
        </div>
    </div>
</template>
