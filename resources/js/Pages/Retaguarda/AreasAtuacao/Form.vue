<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AvisoCamposObrigatorios from '../../../Components/AvisoCamposObrigatorios.vue';
import CampoCadastro from '../../../Components/CampoCadastro.vue';
import { focarPrimeiroErro } from '../../../Components/focarPrimeiroErro';
import SecaoCadastro from '../../../Components/SecaoCadastro.vue';
import { classeCampo } from '../../../Components/Retaguarda/listaEditavel';
import RetaguardaLayout from '../../../Layouts/RetaguardaLayout.vue';
import type { RetaguardaAreasAtuacaoFormProps } from '../../../types';

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaAreasAtuacaoFormProps>();

// Caminhos das rotas retaguarda.areas-atuacao.* (o front não tem helper de rotas nomeadas).
const BASE = '/retaguarda/areas-atuacao';

const salva = props.area;
const editando = salva !== null;

const form = useForm({ nome: salva?.nome ?? '' });

const salvar = (): void => {
    const opcoes = {
        preserveScroll: true,
        onError: () => focarPrimeiroErro('#formulario-area'),
    };

    if (editando) {
        form.put(`${BASE}/${salva.id}`, opcoes);
    } else {
        form.post(BASE, opcoes);
    }
};
</script>

<template>
    <div class="mx-auto flex max-w-4xl flex-col gap-6 px-4 pt-8 lg:px-6">
        <Head :title="editando ? 'Editar área de atuação · Retaguarda' : 'Nova área de atuação · Retaguarda'" />

        <div>
            <Link :href="BASE" class="inline-flex min-h-11 items-center gap-1.5 text-[0.9375rem] font-semibold text-acao hover:underline">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M11 6l-6 6 6 6" />
                </svg>
                Áreas de atuação
            </Link>
            <h1 class="mt-1 text-[1.75rem] leading-tight font-bold break-words">{{ salva?.nome ?? 'Nova área de atuação' }}</h1>
            <p v-if="salva && !salva.ativo" class="mt-1 text-[0.9375rem] text-tinta-suave">Esta área de atuação está desativada e não aparece nas opções.</p>
        </div>

        <form id="formulario-area" novalidate class="flex flex-col gap-6" @submit.prevent="salvar">
            <AvisoCamposObrigatorios />

            <SecaoCadastro id="secao-area-dados" :numero="1" titulo="Área de atuação">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" />
                    </svg>
                </template>

                <CampoCadastro id="nome" rotulo="Nome" obrigatorio ajuda="Ex.: Artes Cênicas." :erro="form.errors.nome" v-slot="{ descricao, invalido }">
                    <input
                        id="nome"
                        v-model="form.nome"
                        type="text"
                        maxlength="100"
                        autocomplete="off"
                        required
                        :class="classeCampo"
                        :aria-invalid="invalido || undefined"
                        :aria-describedby="descricao"
                        @input="form.clearErrors('nome')"
                    />
                </CampoCadastro>
            </SecaoCadastro>

            <div class="flex flex-col-reverse gap-4 sm:flex-row sm:items-start sm:justify-between">
                <Link
                    :href="BASE"
                    class="pressionavel inline-flex min-h-12 items-center justify-center gap-2 rounded-pill border border-tinta-suave/70 bg-superficie px-6 text-[0.8125rem] font-bold tracking-[0.12em] text-home-tinta uppercase no-underline hover:border-acao hover:text-acao"
                >
                    Cancelar
                </Link>

                <div class="flex flex-col gap-2 sm:items-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="pressionavel inline-flex min-h-12 items-center justify-center gap-2 rounded-pill bg-acao px-8 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro disabled:cursor-wait disabled:opacity-70"
                    >
                        {{ form.processing ? 'Salvando…' : 'Salvar área de atuação' }}
                    </button>
                    <p v-if="form.hasErrors" role="alert" class="text-[0.9375rem] font-semibold text-invalido-texto">Confira os campos destacados acima.</p>
                </div>
            </div>
        </form>
    </div>
</template>
