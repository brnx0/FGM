<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CampoCadastro from '../../Components/CampoCadastro.vue';
import FaixaPortal from '../../Components/FaixaPortal.vue';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';
import type { CadastroDaInscricao, PortalInscricaoProps } from '../../types';

defineOptions({ layout: PortalProponenteLayout });

const props = defineProps<PortalInscricaoProps>();

const formulario = useForm<{ cadastro: string; categoria: number | null; cotas: string[] }>({
    cadastro: '',
    categoria: null,
    cotas: [],
});

const cadastroEscolhido = computed(() => props.cadastros.find((cadastro) => cadastro.chave === formulario.cadastro) ?? null);

// Categorias que a natureza do cadastro escolhido pode escolher (lista vazia vale para todas).
const categoriasDisponiveis = computed(() => {
    const natureza = cadastroEscolhido.value?.natureza;
    if (natureza === undefined) {
        return [];
    }
    return props.categorias.filter((categoria) => categoria.naturezas.length === 0 || categoria.naturezas.includes(natureza));
});

const motivoIndisponivel = (cadastro: CadastroDaInscricao): string | null => {
    if (!cadastro.aceito) {
        return 'O edital não aceita esta natureza de cadastro.';
    }
    if (cadastro.limiteAtingido) {
        return 'Este cadastro já atingiu o limite de inscrições deste edital.';
    }
    return null;
};

const escolherCadastro = (cadastro: CadastroDaInscricao): void => {
    formulario.cadastro = cadastro.chave;
    formulario.clearErrors('cadastro', 'categoria');
    if (!categoriasDisponiveis.value.some((categoria) => categoria.id === formulario.categoria)) {
        formulario.categoria = null;
    }
};

// Com a vedação do edital, pessoas negras e indígenas não podem ser marcadas juntas.
const cotaBloqueada = (valor: string): boolean => {
    if (!props.vedaNegroIndigena) {
        return false;
    }
    return (valor === 'negros' && formulario.cotas.includes('indigenas')) || (valor === 'indigenas' && formulario.cotas.includes('negros'));
};

const criar = (): void => {
    // Rota portal.inscricoes.store (o front não tem helper de rotas nomeadas).
    formulario.post(`/portal/editais/${props.edital.slug}/inscricoes`);
};

const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta aria-invalid:border-erro-texto aria-invalid:ring-1 aria-invalid:ring-erro-texto';
const classeCaixa = 'mt-0.5 size-5 shrink-0 cursor-pointer accent-acao';
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
                    <strong class="font-semibold text-white">{{ edital.titulo }}</strong> · {{ edital.categoria }}<br />
                    Inscrições de {{ edital.periodo }}.
                </p>
            </div>
        </FaixaPortal>

        <div class="relative mx-auto -mt-20 flex max-w-6xl flex-col gap-6 px-4 pb-16 md:-mt-24">
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

                <form novalidate class="mt-6 flex flex-col gap-6" @submit.prevent="criar">
                    <fieldset class="flex min-w-0 flex-col gap-3">
                        <legend class="text-[0.9375rem] font-semibold">Cadastro<span aria-hidden="true" class="ml-0.5 text-erro-texto">*</span></legend>
                        <label
                            v-for="cadastro in cadastros"
                            :key="cadastro.chave"
                            class="flex items-start gap-3 rounded-[12px] border border-hairline p-4"
                            :class="motivoIndisponivel(cadastro) ? 'opacity-70' : 'cursor-pointer'"
                        >
                            <input
                                type="radio"
                                name="cadastro"
                                :value="cadastro.chave"
                                :checked="formulario.cadastro === cadastro.chave"
                                :disabled="motivoIndisponivel(cadastro) !== null"
                                :class="classeCaixa"
                                @change="escolherCadastro(cadastro)"
                            />
                            <span class="min-w-0">
                                <span class="block font-semibold break-words">{{ cadastro.nome }}</span>
                                <span class="block text-[0.875rem] text-tinta-suave">{{ cadastro.naturezaRotulo }} · {{ cadastro.documento }}</span>
                                <span v-if="motivoIndisponivel(cadastro)" class="mt-1 block text-[0.875rem] font-semibold text-erro-texto">{{ motivoIndisponivel(cadastro) }}</span>
                                <span v-else-if="cadastro.inscricaoDeOutro" class="mt-1 block text-[0.875rem] text-tinta-suave">
                                    Já existe inscrição deste CNPJ feita por outra pessoa ({{ cadastro.inscricaoDeOutro === 'finalizada' ? 'finalizada' : 'rascunho' }}).
                                </span>
                            </span>
                        </label>
                        <p v-if="formulario.errors.cadastro" role="alert" class="text-[0.875rem] font-semibold text-erro-texto">{{ formulario.errors.cadastro }}</p>
                    </fieldset>

                    <CampoCadastro
                        v-if="categoriasDisponiveis.length > 0"
                        id="inscricao-categoria"
                        rotulo="Categoria"
                        obrigatorio
                        :erro="formulario.errors.categoria"
                        v-slot="{ descricao, invalido }"
                    >
                        <select
                            id="inscricao-categoria"
                            v-model="formulario.categoria"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @change="formulario.clearErrors('categoria')"
                        >
                            <option :value="null">Selecione a categoria</option>
                            <option v-for="categoria in categoriasDisponiveis" :key="categoria.id" :value="categoria.id">{{ categoria.nome }}</option>
                        </select>
                    </CampoCadastro>

                    <fieldset v-if="cotas.length > 0" class="flex min-w-0 flex-col gap-3">
                        <legend class="text-[0.9375rem] font-semibold">Concorrer por cota</legend>
                        <p class="text-[0.8125rem] leading-[1.5] text-tinta-suave">
                            Marque só se for concorrer por cota; ela pode pedir documentos a mais.
                            <template v-if="vedaNegroIndigena">O edital não permite marcar pessoas negras e indígenas ao mesmo tempo.</template>
                        </p>
                        <label v-for="cota in cotas" :key="cota.valor" class="flex items-start gap-3" :class="cotaBloqueada(cota.valor) ? 'opacity-70' : 'cursor-pointer'">
                            <input
                                v-model="formulario.cotas"
                                type="checkbox"
                                :value="cota.valor"
                                :disabled="cotaBloqueada(cota.valor)"
                                :class="classeCaixa"
                                @change="formulario.clearErrors('cotas')"
                            />
                            <span>{{ cota.rotulo }}</span>
                        </label>
                        <p v-if="formulario.errors.cotas" role="alert" class="text-[0.875rem] font-semibold text-erro-texto">{{ formulario.errors.cotas }}</p>
                    </fieldset>

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
