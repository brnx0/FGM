<script setup lang="ts">
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import TabelaPaginada from '../../../Components/Retaguarda/TabelaPaginada.vue';
import RetaguardaLayout from '../../../Layouts/RetaguardaLayout.vue';
import type { RetaguardaProponentesIndexProps } from '../../../types';

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaProponentesIndexProps>();

// Caminhos das rotas retaguarda.proponentes.* (o front não tem helper de rotas nomeadas).
const BASE = '/retaguarda/proponentes';

const filtros = reactive({ ...props.filtros });

// Só os filtros preenchidos vão para a URL; a lista volta para a página 1.
const filtrar = (): void => {
    const consulta = Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== ''));
    router.get(BASE, consulta, { preserveState: true, preserveScroll: true, replace: true });
};

const limpar = (): void => {
    filtros.busca = '';
    filtros.situacao = '';
    filtros.tipo = '';
    filtrar();
};

const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave';
const classeRotulo = 'text-[0.9375rem] font-semibold';
const classeAcao = 'pressionavel inline-flex min-h-11 items-center gap-1.5 rounded-pill px-3 text-[0.8125rem] font-bold text-acao hover:bg-nevoa';
const classeSelo = 'inline-flex rounded-pill px-2.5 py-0.5 text-[0.8125rem] font-semibold';
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 pt-8 lg:px-6">
        <Head title="Proponentes · Retaguarda" />

        <div>
            <p class="text-[0.75rem] font-bold tracking-[0.12em] text-acao uppercase">Retaguarda · Cadastro</p>
            <h1 class="mt-1 text-[1.75rem] leading-tight font-bold">Proponentes</h1>
            <p class="mt-1 text-[0.9375rem] text-tinta-suave">Cadastros feitos no portal, só para consulta.</p>
        </div>

        <form
            role="search"
            aria-label="Filtrar proponentes"
            class="grid gap-4 rounded-card border border-hairline bg-superficie p-4 md:grid-cols-2 md:items-end xl:grid-cols-[1fr_12rem_12rem_auto]"
            @submit.prevent="filtrar"
        >
            <div class="flex flex-col gap-1.5">
                <label for="busca" :class="classeRotulo">Buscar pelo nome, e-mail ou CPF</label>
                <input id="busca" v-model="filtros.busca" type="search" maxlength="255" placeholder="Parte do nome, e-mail ou CPF completo" :class="classeCampo" />
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="situacao" :class="classeRotulo">Situação</label>
                <select id="situacao" v-model="filtros.situacao" :class="classeCampo" @change="filtrar">
                    <option value="">Todas</option>
                    <option v-for="opcao in situacoes" :key="opcao.valor" :value="opcao.valor">{{ opcao.rotulo }}</option>
                </select>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="tipo" :class="classeRotulo">Tipo</label>
                <select id="tipo" v-model="filtros.tipo" :class="classeCampo" @change="filtrar">
                    <option value="">Todos</option>
                    <option v-for="opcao in tiposCadastro" :key="opcao.valor" :value="opcao.valor">{{ opcao.rotulo }}</option>
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
                    v-if="filtros.busca || filtros.situacao || filtros.tipo"
                    type="button"
                    class="pressionavel inline-flex min-h-12 flex-1 items-center justify-center rounded-pill border border-tinta-suave/70 bg-superficie px-5 text-[0.8125rem] font-bold tracking-[0.12em] uppercase hover:border-acao hover:text-acao md:flex-none"
                    @click="limpar"
                >
                    Limpar
                </button>
            </div>
        </form>

        <TabelaPaginada
            legenda="Proponentes"
            :links="proponentes.links"
            :total="proponentes.total"
            :de="proponentes.from"
            :ate="proponentes.to"
            vazio="Nenhum cadastro encontrado."
        >
            <template #cabecalho>
                <tr>
                    <th scope="col" class="px-4 py-3">Nome</th>
                    <th scope="col" class="px-4 py-3">CPF</th>
                    <th scope="col" class="px-4 py-3">Cidade</th>
                    <th scope="col" class="px-4 py-3">Tipo</th>
                    <th scope="col" class="px-4 py-3">Cadastrado em</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Ações</span></th>
                </tr>
            </template>

            <tr v-for="proponente in proponentes.data" :key="proponente.id" class="align-middle">
                <th scope="row" class="min-w-56 px-4 py-3 text-left font-normal">
                    <span class="block font-semibold">{{ proponente.nome }}</span>
                    <span v-if="proponente.nomeSocial" class="block text-[0.8125rem] text-tinta-suave">Nome social: {{ proponente.nomeSocial }}</span>
                    <span v-if="proponente.email" class="block text-[0.8125rem] text-tinta-suave">{{ proponente.email }}</span>
                </th>
                <td class="px-4 py-3 whitespace-nowrap tabular-nums">{{ proponente.cpf }}</td>
                <td class="px-4 py-3">{{ proponente.cidade ?? '—' }}</td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1.5">
                        <span v-for="tipo in proponente.tipos" :key="tipo" :class="[classeSelo, 'bg-nevoa text-acao']">{{ tipo }}</span>
                        <span v-if="!proponente.finalizado" :class="[classeSelo, 'border border-hairline text-tinta-suave']">Em andamento</span>
                    </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap tabular-nums">{{ proponente.cadastradoEm ?? '—' }}</td>
                <td class="px-2 py-1">
                    <div class="flex items-center justify-end whitespace-nowrap">
                        <Link :href="`${BASE}/${proponente.id}`" :class="classeAcao">
                            Ver<span class="sr-only"> o cadastro de {{ proponente.nome }}</span>
                        </Link>
                    </div>
                </td>
            </tr>
        </TabelaPaginada>
    </div>
</template>
