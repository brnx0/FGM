<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AvisoCamposObrigatorios from '../../../Components/AvisoCamposObrigatorios.vue';
import CampoCadastro from '../../../Components/CampoCadastro.vue';
import { focarPrimeiroErro } from '../../../Components/focarPrimeiroErro';
import SecaoCadastro from '../../../Components/SecaoCadastro.vue';
import { classeCampo } from '../../../Components/Retaguarda/listaEditavel';
import RetaguardaLayout from '../../../Layouts/RetaguardaLayout.vue';
import type { RetaguardaAvaliadoresFormProps } from '../../../types';

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaAvaliadoresFormProps>();

// Caminhos das rotas retaguarda.avaliadores.* (o front não tem helper de rotas nomeadas).
const BASE = '/retaguarda/avaliadores';

const salvo = props.avaliador;
const editando = salvo !== null;

/** 52998224725 → 529.982.247-25 enquanto se digita. */
const formatarCpf = (valor: string): string =>
    valor
        .replace(/\D/g, '')
        .slice(0, 11)
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');

// Data no formato do campo de data (AAAA-MM-DD): o limite impede escolher hoje ou depois.
const ontem = ((): string => {
    const data = new Date();
    data.setDate(data.getDate() - 1);
    return `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}-${String(data.getDate()).padStart(2, '0')}`;
})();

const form = useForm({
    nome: salvo?.nome ?? '',
    data_nascimento: salvo?.data_nascimento ?? '',
    cpf: salvo?.cpf ?? '',
    email: salvo?.email ?? '',
});

// Atualiza o próprio campo: se a máscara descarta o que foi digitado, o valor do form não muda e o Vue não redesenha.
const digitarCpf = (evento: Event): void => {
    const campo = evento.target as HTMLInputElement;
    campo.value = formatarCpf(campo.value);
    form.cpf = campo.value;
    form.clearErrors('cpf');
};

const salvar = (): void => {
    const opcoes = {
        preserveScroll: true,
        onError: () => focarPrimeiroErro('#formulario-avaliador'),
    };

    if (editando) {
        form.put(`${BASE}/${salvo.id}`, opcoes);
    } else {
        form.post(BASE, opcoes);
    }
};
</script>

<template>
    <div class="mx-auto flex max-w-4xl flex-col gap-6 px-4 pt-8 lg:px-6">
        <Head :title="editando ? 'Editar avaliador · Retaguarda' : 'Novo avaliador · Retaguarda'" />

        <div>
            <Link :href="BASE" class="inline-flex min-h-11 items-center gap-1.5 text-[0.9375rem] font-semibold text-acao hover:underline">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M11 6l-6 6 6 6" />
                </svg>
                Avaliadores
            </Link>
            <h1 class="mt-1 text-[1.75rem] leading-tight font-bold break-words">{{ salvo?.nome ?? 'Novo avaliador' }}</h1>
            <p v-if="salvo && !salvo.ativo" class="mt-1 text-[0.9375rem] text-tinta-suave">Este avaliador está desativado.</p>
        </div>

        <form id="formulario-avaliador" novalidate class="flex flex-col gap-6" @submit.prevent="salvar">
            <AvisoCamposObrigatorios />

            <SecaoCadastro id="secao-avaliador-dados" :numero="1" titulo="Dados do avaliador">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="4" />
                        <path d="M4 21a8 8 0 0 1 16 0" />
                    </svg>
                </template>

                <div class="grid gap-x-6 gap-y-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <CampoCadastro id="nome" rotulo="Nome completo" obrigatorio :erro="form.errors.nome" v-slot="{ descricao, invalido }">
                            <input
                                id="nome"
                                v-model="form.nome"
                                type="text"
                                maxlength="120"
                                autocomplete="off"
                                required
                                :class="classeCampo"
                                :aria-invalid="invalido || undefined"
                                :aria-describedby="descricao"
                                @input="form.clearErrors('nome')"
                            />
                        </CampoCadastro>
                    </div>

                    <CampoCadastro id="data_nascimento" rotulo="Data de nascimento" obrigatorio :erro="form.errors.data_nascimento" v-slot="{ descricao, invalido }">
                        <input
                            id="data_nascimento"
                            v-model="form.data_nascimento"
                            type="date"
                            min="1900-01-02"
                            :max="ontem"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('data_nascimento')"
                        />
                    </CampoCadastro>

                    <CampoCadastro id="cpf" rotulo="CPF" obrigatorio :erro="form.errors.cpf" v-slot="{ descricao, invalido }">
                        <input
                            id="cpf"
                            :value="form.cpf"
                            type="text"
                            inputmode="numeric"
                            maxlength="14"
                            autocomplete="off"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="digitarCpf"
                        />
                    </CampoCadastro>

                    <div class="md:col-span-2">
                        <CampoCadastro id="email" rotulo="E-mail" obrigatorio :erro="form.errors.email" v-slot="{ descricao, invalido }">
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                maxlength="255"
                                autocomplete="off"
                                required
                                :class="classeCampo"
                                :aria-invalid="invalido || undefined"
                                :aria-describedby="descricao"
                                @input="form.clearErrors('email')"
                            />
                        </CampoCadastro>
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
                        {{ form.processing ? 'Salvando…' : 'Salvar avaliador' }}
                    </button>
                    <p v-if="form.hasErrors" role="alert" class="text-[0.9375rem] font-semibold text-invalido-texto">Confira os campos destacados acima.</p>
                </div>
            </div>
        </form>
    </div>
</template>
