<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GovBrButton from '../../Components/GovBrButton.vue';
import type { EditaisShowProps, EditalCotaPortal } from '../../types';

defineProps<EditaisShowProps>();

const usuario = computed(() => usePage().props.auth?.user);

function pluralizar(quantidade: number, singular: string, plural: string): string {
    return `${quantidade.toLocaleString('pt-BR')} ${quantidade === 1 ? singular : plural}`;
}

function quantidadeDaCota(cota: EditalCotaPortal): string {
    const emRecursos = cota.base === 'recursos';
    const partes: string[] = [];

    if (cota.percentual !== null) {
        partes.push(`${cota.percentual.toLocaleString('pt-BR')}% ${emRecursos ? 'dos recursos' : 'das vagas'}`);
    }
    if (cota.quantidade !== null) {
        partes.push(
            emRecursos
                ? cota.quantidade.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
                : pluralizar(cota.quantidade, 'vaga', 'vagas'),
        );
    }

    return partes.join(' · ') || 'Reservada';
}

function detalheDoItem(valor: string | null, vagas: number | null, suplentes: number | null): string {
    return [
        valor,
        vagas !== null ? pluralizar(vagas, 'vaga', 'vagas') : null,
        suplentes ? pluralizar(suplentes, 'suplente', 'suplentes') : null,
    ]
        .filter(Boolean)
        .join(' · ');
}
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
        <h1 class="mt-2 text-[2rem] break-words sm:text-[3rem]">{{ edital.titulo }}</h1>

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

        <p class="mt-8 text-[1.1875rem] whitespace-pre-line">{{ edital.resumo }}</p>

        <section v-if="edital.objeto || edital.numero || edital.tipoRotulo" class="mt-12" aria-labelledby="secao-objeto">
            <h2 id="secao-objeto" class="text-[1.5rem] sm:text-[2rem]">Objeto</h2>
            <p v-if="edital.tipoRotulo || edital.numero" class="mt-2 text-[0.875rem] text-tinta-suave">
                {{ [edital.tipoRotulo, edital.numero ? `nº ${edital.numero}` : null].filter(Boolean).join(' ') }}
            </p>
            <p v-if="edital.objeto" class="mt-3 break-words whitespace-pre-line">{{ edital.objeto }}</p>
        </section>

        <section v-if="edital.chamadas.length > 0 || edital.inscricoesPorProponente" class="mt-12" aria-labelledby="secao-inscricoes">
            <h2 id="secao-inscricoes" class="text-[1.5rem] sm:text-[2rem]">Inscrições</h2>
            <ul v-if="edital.chamadas.length > 0" class="mt-4 grid gap-3">
                <li
                    v-for="(chamada, indice) in edital.chamadas"
                    :key="indice"
                    class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 rounded-card border border-hairline p-4"
                >
                    <span class="min-w-0 font-semibold break-words">{{ chamada.nome }}</span>
                    <span class="text-tinta-suave">
                        <template v-if="chamada.inicio && chamada.fim">{{ chamada.inicio }} a {{ chamada.fim }}</template>
                        <template v-if="chamada.situacaoRotulo">
                            <span v-if="chamada.inicio && chamada.fim" aria-hidden="true"> · </span>
                            <span :class="chamada.situacao === 'aberto' ? 'font-semibold text-acao' : ''">{{ chamada.situacaoRotulo }}</span>
                        </template>
                    </span>
                </li>
            </ul>
            <p v-if="edital.inscricoesPorProponente" class="mt-4">
                Cada proponente pode fazer até
                <strong>{{ pluralizar(edital.inscricoesPorProponente, 'inscrição', 'inscrições') }}</strong>.
            </p>
        </section>

        <section v-if="edital.naturezas.length > 0" class="mt-12" aria-labelledby="secao-quem">
            <h2 id="secao-quem" class="text-[1.5rem] sm:text-[2rem]">Quem pode participar</h2>
            <ul class="mt-4 flex flex-wrap gap-2">
                <li v-for="natureza in edital.naturezas" :key="natureza" class="rounded-pill border border-hairline px-4 py-1 font-semibold">
                    {{ natureza }}
                </li>
            </ul>
            <p v-if="edital.aceitaColetivos" class="mt-4">Coletivos sem CNPJ também podem se inscrever, representados por uma pessoa física.</p>
        </section>

        <section v-if="edital.categorias.length > 0" class="mt-12" aria-labelledby="secao-categorias">
            <h2 id="secao-categorias" class="text-[1.5rem] sm:text-[2rem]">Categorias</h2>
            <ul class="mt-4 grid gap-4">
                <li v-for="(categoria, indice) in edital.categorias" :key="indice" class="rounded-card border border-hairline p-5">
                    <h3 class="text-[1.25rem] font-extrabold break-words">{{ categoria.nome }}</h3>
                    <p v-if="categoria.descricao" class="mt-2 break-words whitespace-pre-line">{{ categoria.descricao }}</p>
                    <dl v-if="categoria.valor || categoria.vagas !== null" class="mt-3 flex flex-wrap gap-x-8 gap-y-2">
                        <div v-if="categoria.valor">
                            <dt class="text-[0.875rem] text-tinta-suave">Valor</dt>
                            <dd class="font-semibold">{{ categoria.valor }}</dd>
                        </div>
                        <div v-if="categoria.vagas !== null">
                            <dt class="text-[0.875rem] text-tinta-suave">Vagas</dt>
                            <dd class="font-semibold">{{ categoria.vagas }}</dd>
                        </div>
                    </dl>
                    <ul v-if="categoria.itens.length > 0" class="mt-4 divide-y divide-hairline border-t border-hairline">
                        <li v-for="(item, posicao) in categoria.itens" :key="posicao" class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2">
                            <span class="min-w-0 break-words">{{ item.nome }}</span>
                            <span class="text-tinta-suave">{{ detalheDoItem(item.valor, item.vagas, item.suplentes) }}</span>
                        </li>
                    </ul>
                </li>
            </ul>
        </section>

        <section v-if="edital.cotas.length > 0" class="mt-12" aria-labelledby="secao-cotas">
            <h2 id="secao-cotas" class="text-[1.5rem] sm:text-[2rem]">Cotas</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                <li v-for="cota in edital.cotas" :key="cota.modalidade" class="rounded-card border border-hairline p-4">
                    <p class="font-semibold">{{ cota.modalidade }}</p>
                    <p class="text-tinta-suave">{{ quantidadeDaCota(cota) }}</p>
                </li>
            </ul>
        </section>

        <section v-if="edital.documentosInscricao.length > 0" class="mt-12" aria-labelledby="secao-documentos">
            <h2 id="secao-documentos" class="text-[1.5rem] sm:text-[2rem]">Documentos para a inscrição</h2>
            <ul class="mt-4 grid gap-3">
                <li v-for="(documento, indice) in edital.documentosInscricao" :key="indice" class="rounded-card border border-hairline p-4">
                    <p class="font-semibold break-words">
                        {{ documento.nome }}
                        <span class="ml-1 text-[0.8125rem] font-bold tracking-wide uppercase" :class="documento.obrigatorio ? 'text-acao' : 'text-tinta-suave'">
                            {{ documento.obrigatorio ? 'Obrigatório' : 'Opcional' }}
                        </span>
                    </p>
                    <p v-if="documento.orientacao" class="mt-1 break-words whitespace-pre-line">{{ documento.orientacao }}</p>
                    <p v-if="documento.naturezas.length > 0" class="mt-1 text-[0.875rem] text-tinta-suave">
                        Para: {{ documento.naturezas.join(', ') }}
                    </p>
                    <p v-if="documento.categorias.length > 0" class="mt-1 text-[0.875rem] text-tinta-suave">
                        Categorias: {{ documento.categorias.join(', ') }}
                    </p>
                    <a
                        v-if="documento.modeloUrl"
                        :href="documento.modeloUrl"
                        class="mt-2 inline-flex min-h-11 items-center font-semibold text-acao hover:underline"
                    >
                        Baixar o modelo<span v-if="documento.modeloNome" class="sr-only"> ({{ documento.modeloNome }})</span>
                    </a>
                </li>
            </ul>
        </section>

        <section v-if="edital.informacoes.length > 0" class="mt-12" aria-labelledby="secao-informacoes">
            <h2 id="secao-informacoes" class="text-[1.5rem] sm:text-[2rem]">Outras informações do edital</h2>
            <div class="mt-4 divide-y divide-hairline rounded-card border border-hairline">
                <details v-for="secao in edital.informacoes" :key="secao.chave" class="group px-5 py-1">
                    <summary class="flex min-h-12 cursor-pointer items-center justify-between gap-4 font-semibold">
                        {{ secao.titulo }}
                        <span aria-hidden="true" class="text-acao transition-transform group-open:rotate-180">⌄</span>
                    </summary>
                    <p class="pt-1 pb-4 break-words whitespace-pre-line">{{ secao.texto }}</p>
                </details>
            </div>
        </section>

        <div v-if="edital.situacao === 'encerrado'" role="note" class="mt-12 rounded-card border border-hairline bg-parchment p-6">
            <p class="text-[1.25rem] font-extrabold tracking-[-0.025em]">Inscrições encerradas em {{ edital.inscricoesFim }}.</p>
        </div>

        <div v-else role="note" class="mt-12 rounded-card border border-acao bg-nevoa p-6">
            <p class="text-[1.25rem] font-extrabold tracking-[-0.025em]">Inscrição pela plataforma em breve.</p>
            <p class="mt-1 text-tinta-suave">
                Por enquanto, siga as orientações do edital. Entre com sua conta gov.br para adiantar o seu cadastro.
            </p>
        </div>

        <div v-if="edital.situacao !== 'encerrado'" class="mt-8">
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
