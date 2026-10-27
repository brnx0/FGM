<script setup lang="ts">
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ConfirmarExclusao from '../../../Components/Retaguarda/ConfirmarExclusao.vue';
import TabelaPaginada from '../../../Components/Retaguarda/TabelaPaginada.vue';
import RetaguardaLayout from '../../../Layouts/RetaguardaLayout.vue';
import type { RetaguardaEditaisIndexProps, SituacaoEdital } from '../../../types';

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaEditaisIndexProps>();

// Caminhos das rotas retaguarda.editais.* (o front não tem helper de rotas nomeadas).
const BASE = '/retaguarda/editais';

const filtros = reactive({ ...props.filtros });

// Só os filtros preenchidos vão para a URL; a lista volta para a página 1.
const filtrar = (): void => {
    const consulta = Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== ''));
    router.get(BASE, consulta, { preserveState: true, preserveScroll: true, replace: true });
};

const limpar = (): void => {
    Object.assign(filtros, { busca: '', situacao: '', publicado: '' });
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
    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 pt-8 lg:px-6">
        <Head title="Editais · Retaguarda" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[0.75rem] font-bold tracking-[0.12em] text-acao uppercase">Retaguarda</p>
                <h1 class="mt-1 text-[1.75rem] leading-tight font-bold">Editais</h1>
            </div>
            <Link
                :href="`${BASE}/create`"
                class="pressionavel inline-flex min-h-12 items-center justify-center gap-2 rounded-pill bg-acao px-6 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro"
            >
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                Novo edital
            </Link>
        </div>

        <form role="search" aria-label="Filtrar editais" class="grid gap-4 rounded-card border border-hairline bg-superficie p-4 md:grid-cols-[1fr_12rem_12rem_auto] md:items-end" @submit.prevent="filtrar">
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
            <div class="flex flex-col gap-1.5">
                <label for="publicado" :class="classeRotulo">Publicação</label>
                <select id="publicado" v-model="filtros.publicado" :class="classeCampo" @change="filtrar">
                    <option value="">Todos</option>
                    <option value="sim">Publicados</option>
                    <option value="nao">Não publicados</option>
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
                    v-if="filtros.busca || filtros.situacao || filtros.publicado"
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
                    <th scope="col" class="px-4 py-3">Publicado</th>
                    <th scope="col" class="px-4 py-3">PDF</th>
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
                <td class="px-4 py-3">
                    <span
                        class="inline-flex rounded-pill px-2.5 py-0.5 text-[0.8125rem] font-semibold"
                        :class="edital.publicado ? 'bg-nevoa text-acao' : 'border border-hairline text-tinta-suave'"
                    >
                        {{ edital.publicado ? 'Sim' : 'Não' }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <span v-if="edital.temPdf">Sim</span>
                    <span v-else class="font-semibold text-erro-texto">Falta</span>
                </td>
                <td class="px-2 py-1">
                    <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                        <Link :href="`${BASE}/${edital.slug}/edit`" :class="classeAcao">
                            Editar<span class="sr-only"> {{ edital.titulo }}</span>
                        </Link>
                        <Link
                            :href="`${BASE}/${edital.slug}/publicacao`"
                            method="patch"
                            as="button"
                            preserve-scroll
                            :class="classeAcao"
                        >
                            {{ edital.publicado ? 'Despublicar' : 'Publicar' }}<span class="sr-only"> {{ edital.titulo }}</span>
                        </Link>
                        <ConfirmarExclusao
                            :url="`${BASE}/${edital.slug}`"
                            :nome="edital.titulo"
                            aviso="O edital sai do portal e da lista. Os arquivos continuam guardados."
                        />
                    </div>
                </td>
            </tr>
        </TabelaPaginada>
    </div>
</template>
