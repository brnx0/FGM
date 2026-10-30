<script setup lang="ts">
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import TabelaPaginada from '../../../Components/Retaguarda/TabelaPaginada.vue';
import RetaguardaLayout from '../../../Layouts/RetaguardaLayout.vue';
import type { RetaguardaAvaliadoresIndexProps } from '../../../types';

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaAvaliadoresIndexProps>();

// Caminhos das rotas retaguarda.avaliadores.* (o front não tem helper de rotas nomeadas).
const BASE = '/retaguarda/avaliadores';

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
        <Head title="Avaliadores · Retaguarda" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[0.75rem] font-bold tracking-[0.12em] text-acao uppercase">Cadastro</p>
                <h1 class="mt-1 text-[1.75rem] leading-tight font-bold">Avaliadores</h1>
            </div>
            <Link
                :href="`${BASE}/create`"
                class="pressionavel inline-flex min-h-12 items-center justify-center gap-2 rounded-pill bg-acao px-6 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro"
            >
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                Novo avaliador
            </Link>
        </div>

        <form
            role="search"
            aria-label="Buscar avaliadores"
            class="grid gap-4 rounded-card border border-hairline bg-superficie p-4 md:grid-cols-[1fr_auto] md:items-end"
            @submit.prevent="filtrar"
        >
            <div class="flex flex-col gap-1.5">
                <label for="busca" :class="classeRotulo">Buscar por nome, e-mail ou CPF completo</label>
                <input id="busca" v-model="filtros.busca" type="search" maxlength="120" placeholder="Ex.: Maria da Silva" :class="classeCampo" />
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
            legenda="Avaliadores"
            :links="avaliadores.links"
            :total="avaliadores.total"
            :de="avaliadores.from"
            :ate="avaliadores.to"
            vazio="Nenhum avaliador encontrado."
        >
            <template #cabecalho>
                <tr>
                    <th scope="col" class="px-4 py-3">Nome</th>
                    <th scope="col" class="px-4 py-3">CPF</th>
                    <th scope="col" class="px-4 py-3">E-mail</th>
                    <th scope="col" class="px-4 py-3">Ativo</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Ações</span></th>
                </tr>
            </template>

            <tr v-for="avaliador in avaliadores.data" :key="avaliador.id" class="align-middle">
                <th scope="row" class="min-w-48 px-4 py-3 text-left font-semibold">{{ avaliador.nome }}</th>
                <td class="px-4 py-3 whitespace-nowrap tabular-nums">{{ avaliador.cpf }}</td>
                <td class="px-4 py-3 break-all">{{ avaliador.email }}</td>
                <td class="px-4 py-3">
                    <span
                        class="inline-flex rounded-pill px-2.5 py-0.5 text-[0.8125rem] font-semibold"
                        :class="avaliador.ativo ? 'bg-nevoa text-acao' : 'border border-hairline text-tinta-suave'"
                    >
                        {{ avaliador.ativo ? 'Sim' : 'Não' }}
                    </span>
                </td>
                <td class="px-2 py-1">
                    <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                        <Link :href="`${BASE}/${avaliador.id}/edit`" :class="classeAcao">
                            Editar<span class="sr-only"> {{ avaliador.nome }}</span>
                        </Link>
                        <Link :href="`${BASE}/${avaliador.id}/ativo`" method="patch" as="button" preserve-scroll :class="classeAcao">
                            {{ avaliador.ativo ? 'Desativar' : 'Ativar' }}<span class="sr-only"> {{ avaliador.nome }}</span>
                        </Link>
                    </div>
                </td>
            </tr>
        </TabelaPaginada>
    </div>
</template>
