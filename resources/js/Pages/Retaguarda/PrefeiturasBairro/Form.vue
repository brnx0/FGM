<script setup lang="ts">
import { computed, nextTick } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CampoCadastro from '../../../Components/CampoCadastro.vue';
import SecaoCadastro from '../../../Components/SecaoCadastro.vue';
import { classeCampo, classeCampoBase, inteiro } from '../../../Components/Retaguarda/listaEditavel';
import RetaguardaLayout from '../../../Layouts/RetaguardaLayout.vue';
import type { RetaguardaPrefeiturasBairroFormProps } from '../../../types';

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaPrefeiturasBairroFormProps>();

// Caminhos das rotas retaguarda.prefeituras-bairro.* (o front não tem helper de rotas nomeadas).
const BASE = '/retaguarda/prefeituras-bairro';

const salva = props.prefeitura;
const editando = salva !== null;

/** Bairro na tela; a chave segue a linha quando outra é removida e não vai para o servidor. */
type BairroEditavel = { chave: string; id: number | null; nome: string };

let novos = 0;
const bairroNovo = (): BairroEditavel => ({ chave: `novo-${++novos}`, id: null, nome: '' });

const form = useForm({
    numero: salva?.numero ?? (null as number | null),
    nome: salva?.nome ?? '',
    bairros: (salva?.bairros ?? []).map((bairro): BairroEditavel => ({ chave: `salvo-${bairro.id}`, ...bairro })),
});

// Os erros dos bairros chegam por posição ("bairros.3.nome").
const erros = computed(() => form.errors as Partial<Record<string, string>>);
const erroDoBairro = (indice: number): string | undefined => erros.value[`bairros.${indice}.nome`];
const limparErrosDosBairros = (): void => {
    form.clearErrors(...(Object.keys(form.errors).filter((campo) => campo.startsWith('bairros')) as (keyof typeof form.errors)[]));
};

const adicionarBairro = (): void => {
    const bairro = bairroNovo();
    form.bairros.push(bairro);
    nextTick(() => document.getElementById(`bairro-${bairro.chave}`)?.focus());
};

// A posição dos erros deixa de valer quando uma linha sai.
const removerBairro = (indice: number): void => {
    form.bairros.splice(indice, 1);
    limparErrosDosBairros();
};

const salvar = (): void => {
    const opcoes = {
        preserveScroll: true,
        onError: () => nextTick(() => document.querySelector<HTMLElement>('#formulario-prefeitura [aria-invalid="true"]')?.focus()),
    };

    form.transform((dados) => ({ ...dados, bairros: dados.bairros.map(({ id, nome }) => ({ id, nome })) }));

    if (editando) {
        form.put(`${BASE}/${salva.id}`, opcoes);
    } else {
        form.post(BASE, opcoes);
    }
};

const classeBotaoLinha =
    'pressionavel inline-flex size-12 shrink-0 items-center justify-center rounded-pill text-tinta-suave hover:bg-nevoa hover:text-erro-texto';
</script>

<template>
    <div class="mx-auto flex max-w-4xl flex-col gap-6 px-4 pt-8 lg:px-6">
        <Head :title="editando ? 'Editar Prefeitura-Bairro · Retaguarda' : 'Nova Prefeitura-Bairro · Retaguarda'" />

        <div>
            <Link :href="BASE" class="inline-flex min-h-11 items-center gap-1.5 text-[0.9375rem] font-semibold text-acao hover:underline">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M11 6l-6 6 6 6" />
                </svg>
                Prefeituras-Bairro
            </Link>
            <h1 class="mt-1 text-[1.75rem] leading-tight font-bold break-words">{{ salva?.nome ?? 'Nova Prefeitura-Bairro' }}</h1>
            <p v-if="salva && !salva.ativo" class="mt-1 text-[0.9375rem] text-tinta-suave">
                Esta Prefeitura-Bairro está desativada e não aparece no cadastro do proponente.
            </p>
        </div>

        <form id="formulario-prefeitura" novalidate class="flex flex-col gap-6" @submit.prevent="salvar">
            <SecaoCadastro id="secao-prefeitura-dados" :numero="1" titulo="Prefeitura-Bairro">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6" />
                    </svg>
                </template>

                <div class="grid gap-x-6 gap-y-5 md:grid-cols-[10rem_1fr]">
                    <CampoCadastro
                        id="numero"
                        rotulo="Número"
                        obrigatorio
                        ajuda="Em algarismos comuns: 4 vira IV."
                        :erro="form.errors.numero"
                        v-slot="{ descricao, invalido }"
                    >
                        <input
                            id="numero"
                            :value="form.numero ?? ''"
                            type="text"
                            inputmode="numeric"
                            maxlength="2"
                            autocomplete="off"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="
                                form.numero = inteiro($event, 99);
                                form.clearErrors('numero');
                            "
                        />
                    </CampoCadastro>

                    <CampoCadastro
                        id="nome"
                        rotulo="Nome"
                        obrigatorio
                        ajuda="É o que o proponente vê no cadastro do portal. Ex.: Centro/Brotas."
                        :erro="form.errors.nome"
                        v-slot="{ descricao, invalido }"
                    >
                        <input
                            id="nome"
                            v-model="form.nome"
                            type="text"
                            maxlength="60"
                            autocomplete="off"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('nome')"
                        />
                    </CampoCadastro>
                </div>
            </SecaoCadastro>

            <SecaoCadastro id="secao-prefeitura-bairros" :numero="2" titulo="Bairros">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z" />
                        <circle cx="12" cy="10" r="2.5" />
                    </svg>
                </template>

                <div class="flex flex-col gap-4">
                    <p class="text-[0.9375rem] text-tinta-suave">
                        {{ form.bairros.length === 1 ? '1 bairro' : `${form.bairros.length} bairros` }}. Um bairro pertence a uma só Prefeitura-Bairro: para
                        mudá-lo de Prefeitura-Bairro, remova-o da atual antes.
                    </p>

                    <ul v-if="form.bairros.length > 0" class="grid gap-x-6 gap-y-3 md:grid-cols-2">
                        <li v-for="(bairro, indice) in form.bairros" :key="bairro.chave" class="flex flex-col gap-1">
                            <div class="flex items-center gap-1">
                                <label :for="`bairro-${bairro.chave}`" class="sr-only">Bairro {{ indice + 1 }}</label>
                                <input
                                    :id="`bairro-${bairro.chave}`"
                                    v-model="bairro.nome"
                                    type="text"
                                    maxlength="80"
                                    autocomplete="off"
                                    :class="[classeCampoBase, 'min-w-0 flex-1']"
                                    :aria-invalid="erroDoBairro(indice) ? true : undefined"
                                    :aria-describedby="erroDoBairro(indice) ? `bairro-${bairro.chave}-erro` : undefined"
                                    @input="form.clearErrors(`bairros.${indice}.nome` as keyof typeof form.errors)"
                                />
                                <button type="button" :class="classeBotaoLinha" @click="removerBairro(indice)">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                        <path d="M6 6l12 12M18 6L6 18" />
                                    </svg>
                                    <span class="sr-only">Remover {{ bairro.nome || `bairro ${indice + 1}` }}</span>
                                </button>
                            </div>
                            <p v-if="erroDoBairro(indice)" :id="`bairro-${bairro.chave}-erro`" class="text-[0.875rem] font-semibold text-erro-texto">
                                {{ erroDoBairro(indice) }}
                            </p>
                        </li>
                    </ul>

                    <p v-if="form.errors.bairros" class="text-[0.875rem] font-semibold text-erro-texto">{{ form.errors.bairros }}</p>

                    <div>
                        <button
                            type="button"
                            class="pressionavel inline-flex min-h-12 items-center gap-2 rounded-pill border border-tinta-suave/70 bg-superficie px-5 text-[0.8125rem] font-bold tracking-[0.12em] uppercase hover:border-acao hover:text-acao"
                            @click="adicionarBairro"
                        >
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            Adicionar bairro
                        </button>
                    </div>
                </div>
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
                        {{ form.processing ? 'Salvando…' : 'Salvar Prefeitura-Bairro' }}
                    </button>
                    <p v-if="form.hasErrors" role="alert" class="text-[0.9375rem] font-semibold text-erro-texto">Confira os campos destacados acima.</p>
                </div>
            </div>
        </form>
    </div>
</template>
