<script setup lang="ts">
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import TabelaPaginada from '../../../Components/Retaguarda/TabelaPaginada.vue';
import RetaguardaLayout from '../../../Layouts/RetaguardaLayout.vue';
import type { RetaguardaPrefeiturasBairroIndexProps } from '../../../types';

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaPrefeiturasBairroIndexProps>();

// Caminhos das rotas retaguarda.prefeituras-bairro.* (o front não tem helper de rotas nomeadas).
const BASE = '/retaguarda/prefeituras-bairro';

const filtros = reactive({ ...props.filtros });

// Só a busca preenchida vai para a URL; a lista volta para a página 1.
const filtrar = (): void => {
    const consulta = Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== ''));
    router.get(BASE, consulta, { preserveState: true, preserveScroll: true, replace: true });
};

const limpar = (): void => {
    filtros.busca = '';
    filtrar();
};

const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave';
const classeRotulo = 'text-[0.9375rem] font-semibold';
const classeAcao = 'pressionavel inline-flex min-h-11 items-center gap-1.5 rounded-pill px-3 text-[0.8125rem] font-bold text-acao hover:bg-nevoa';
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 pt-8 lg:px-6">
        <Head title="Prefeituras-Bairro · Retaguarda" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[0.75rem] font-bold tracking-[0.12em] text-acao uppercase">Parametrização</p>
                <h1 class="mt-1 text-[1.75rem] leading-tight font-bold">Prefeituras-Bairro</h1>
            </div>
            <Link
                :href="`${BASE}/create`"
                class="pressionavel inline-flex min-h-12 items-center justify-center gap-2 rounded-pill bg-acao px-6 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro"
            >
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                Nova Prefeitura-Bairro
            </Link>
        </div>

        <form
            role="search"
            aria-label="Buscar Prefeituras-Bairro"
            class="grid gap-4 rounded-card border border-hairline bg-superficie p-4 md:grid-cols-[1fr_auto] md:items-end"
            @submit.prevent="filtrar"
        >
            <div class="flex flex-col gap-1.5">
                <label for="busca" :class="classeRotulo">Buscar pela Prefeitura-Bairro ou por um bairro</label>
                <input id="busca" v-model="filtros.busca" type="search" maxlength="80" placeholder="Ex.: Pituba" :class="classeCampo" />
            </div>
            <div class="flex gap-2">
                <button
                    type="submit"
                    class="pressionavel inline-flex min-h-12 flex-1 items-center justify-center rounded-pill bg-acao px-6 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro md:flex-none"
                >
                    Buscar
                </button>
                <button
                    v-if="filtros.busca"
                    type="button"
                    class="pressionavel inline-flex min-h-12 flex-1 items-center justify-center rounded-pill border border-tinta-suave/70 bg-superficie px-5 text-[0.8125rem] font-bold tracking-[0.12em] uppercase hover:border-acao hover:text-acao md:flex-none"
                    @click="limpar"
                >
                    Limpar
                </button>
            </div>
        </form>

        <TabelaPaginada
            legenda="Prefeituras-Bairro"
            :links="prefeituras.links"
            :total="prefeituras.total"
            :de="prefeituras.from"
            :ate="prefeituras.to"
            vazio="Nenhuma Prefeitura-Bairro encontrada."
        >
            <template #cabecalho>
                <tr>
                    <th scope="col" class="px-4 py-3">Nº</th>
                    <th scope="col" class="px-4 py-3">Prefeitura-Bairro</th>
                    <th scope="col" class="px-4 py-3">Bairros</th>
                    <th scope="col" class="px-4 py-3">Ativa</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Ações</span></th>
                </tr>
            </template>

            <tr v-for="prefeitura in prefeituras.data" :key="prefeitura.id" class="align-middle">
                <td class="px-4 py-3 font-semibold whitespace-nowrap">{{ prefeitura.numero }}</td>
                <th scope="row" class="min-w-48 px-4 py-3 text-left font-semibold">{{ prefeitura.nome }}</th>
                <td class="px-4 py-3 tabular-nums">{{ prefeitura.totalBairros }}</td>
                <td class="px-4 py-3">
                    <span
                        class="inline-flex rounded-pill px-2.5 py-0.5 text-[0.8125rem] font-semibold"
                        :class="prefeitura.ativo ? 'bg-nevoa text-acao' : 'border border-hairline text-tinta-suave'"
                    >
                        {{ prefeitura.ativo ? 'Sim' : 'Não' }}
                    </span>
                </td>
                <td class="px-2 py-1">
                    <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                        <Link :href="`${BASE}/${prefeitura.id}/edit`" :class="classeAcao">
                            Editar<span class="sr-only"> {{ prefeitura.nome }}</span>
                        </Link>
                        <Link :href="`${BASE}/${prefeitura.id}/ativo`" method="patch" as="button" preserve-scroll :class="classeAcao">
                            {{ prefeitura.ativo ? 'Desativar' : 'Ativar' }}<span class="sr-only"> {{ prefeitura.nome }}</span>
                        </Link>
                    </div>
                </td>
            </tr>
        </TabelaPaginada>
    </div>
</template>
