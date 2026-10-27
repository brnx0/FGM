<script setup lang="ts">
import { nextTick } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CampoCadastro from '../../../Components/CampoCadastro.vue';
import SecaoCadastro from '../../../Components/SecaoCadastro.vue';
import RetaguardaLayout from '../../../Layouts/RetaguardaLayout.vue';
import type { RetaguardaUsuariosFormProps } from '../../../types';

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaUsuariosFormProps>();

// Caminhos das rotas retaguarda.usuarios.* (o front não tem helper de rotas nomeadas).
const BASE = '/retaguarda/usuarios';

const salvo = props.usuario;
const editando = salvo !== null;

/** 52998224725 → 529.982.247-25 enquanto se digita. */
const formatarCpf = (valor: string): string =>
    valor
        .replace(/\D/g, '')
        .slice(0, 11)
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');

// Na edição, senha em branco mantém a atual.
const form = useForm({
    name: salvo?.name ?? '',
    email: salvo?.email ?? '',
    cpf: salvo?.cpf ?? '',
    senha: '',
    senha_confirmation: '',
    // Com um papel só (hoje, administrador), ele já vem marcado no cadastro novo.
    papeis: salvo?.papeis ?? (props.papeisDisponiveis.length === 1 ? props.papeisDisponiveis.map((papel) => papel.slug) : []),
});

// Atualiza o próprio campo: se a máscara descarta o que foi digitado, o valor do form não muda e o Vue não redesenha.
const digitarCpf = (evento: Event): void => {
    const campo = evento.target as HTMLInputElement;
    campo.value = formatarCpf(campo.value);
    form.cpf = campo.value;
    form.clearErrors('cpf');
};

// CPF salvo inválido (o do primeiro administrador) chega em branco e precisa ser informado.
const ajudaCpf = !editando
    ? 'Se o CPF já for de um proponente, ele passa a ser usuário da retaguarda sem perder o cadastro.'
    : salvo.cpf === ''
      ? 'O CPF salvo não é válido. Informe o CPF correto.'
      : undefined;

const salvar = (): void => {
    const opcoes = {
        preserveScroll: true,
        onError: () => nextTick(() => document.querySelector<HTMLElement>('#formulario-usuario [aria-invalid="true"]')?.focus()),
        onFinish: () => form.reset('senha', 'senha_confirmation'),
    };

    if (editando) {
        form.put(`${BASE}/${salvo.id}`, opcoes);
    } else {
        form.post(BASE, opcoes);
    }
};

const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave aria-invalid:border-erro-texto aria-invalid:ring-1 aria-invalid:ring-erro-texto';
const classeMarcacao = 'flex min-h-11 cursor-pointer items-start gap-3 py-2 text-[0.9375rem] leading-[1.5]';
const classeCaixa = 'mt-0.5 size-5 shrink-0 cursor-pointer accent-acao';
</script>

<template>
    <div class="mx-auto flex max-w-4xl flex-col gap-6 px-4 pt-8 lg:px-6">
        <Head :title="editando ? 'Editar usuário · Retaguarda' : 'Novo usuário · Retaguarda'" />

        <div>
            <Link :href="BASE" class="inline-flex min-h-11 items-center gap-1.5 text-[0.9375rem] font-semibold text-acao hover:underline">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M11 6l-6 6 6 6" />
                </svg>
                Usuários
            </Link>
            <h1 class="mt-1 text-[1.75rem] leading-tight font-bold break-words">{{ salvo?.name ?? 'Novo usuário' }}</h1>
            <p v-if="salvo && !salvo.ativo" class="mt-1 text-[0.9375rem] text-tinta-suave">Este usuário está desativado e não entra na retaguarda.</p>
        </div>

        <form id="formulario-usuario" novalidate class="flex flex-col gap-6" @submit.prevent="salvar">
            <SecaoCadastro id="secao-usuario-dados" :numero="1" titulo="Dados do usuário">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="4" />
                        <path d="M4 21a8 8 0 0 1 16 0" />
                    </svg>
                </template>

                <div class="grid gap-x-6 gap-y-5 md:grid-cols-2">
                    <CampoCadastro id="name" class="md:col-span-2" rotulo="Nome" obrigatorio :erro="form.errors.name" v-slot="{ descricao, invalido }">
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            maxlength="255"
                            autocomplete="off"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('name')"
                        />
                    </CampoCadastro>

                    <CampoCadastro
                        id="cpf"
                        rotulo="CPF"
                        obrigatorio
                        :ajuda="ajudaCpf"
                        :erro="form.errors.cpf"
                        v-slot="{ descricao, invalido }"
                    >
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

                    <CampoCadastro id="email" rotulo="E-mail" obrigatorio ajuda="Usado para entrar na retaguarda." :erro="form.errors.email" v-slot="{ descricao, invalido }">
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
            </SecaoCadastro>

            <SecaoCadastro id="secao-usuario-acesso" :numero="2" titulo="Acesso">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="11" width="14" height="10" rx="2" />
                        <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                    </svg>
                </template>

                <div class="grid gap-x-6 gap-y-5 md:grid-cols-2">
                    <CampoCadastro
                        id="senha"
                        rotulo="Senha"
                        :obrigatorio="!editando"
                        :ajuda="editando ? 'Deixe em branco para manter a senha atual. Mínimo de 8 caracteres.' : 'Mínimo de 8 caracteres.'"
                        :erro="form.errors.senha"
                        v-slot="{ descricao, invalido }"
                    >
                        <input
                            id="senha"
                            v-model="form.senha"
                            type="password"
                            autocomplete="new-password"
                            :required="!editando"
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('senha')"
                        />
                    </CampoCadastro>

                    <CampoCadastro id="senha_confirmation" rotulo="Confirme a senha" :obrigatorio="!editando" v-slot="{ descricao }">
                        <input
                            id="senha_confirmation"
                            v-model="form.senha_confirmation"
                            type="password"
                            autocomplete="new-password"
                            :required="!editando"
                            :class="classeCampo"
                            :aria-describedby="descricao"
                        />
                    </CampoCadastro>

                    <fieldset class="md:col-span-2" :aria-describedby="form.errors.papeis ? 'papeis-erro' : undefined">
                        <legend class="text-[0.9375rem] font-semibold">Papéis<span aria-hidden="true" class="ml-0.5 text-erro-texto">*</span></legend>
                        <label v-for="papel in papeisDisponiveis" :key="papel.slug" :class="classeMarcacao">
                            <input
                                v-model="form.papeis"
                                type="checkbox"
                                :value="papel.slug"
                                :class="classeCaixa"
                                :aria-invalid="form.errors.papeis ? true : undefined"
                                @change="form.clearErrors('papeis')"
                            />
                            {{ papel.nome }}
                        </label>
                        <p v-if="form.errors.papeis" id="papeis-erro" class="text-[0.875rem] font-semibold text-erro-texto">{{ form.errors.papeis }}</p>
                    </fieldset>
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
                        {{ form.processing ? 'Salvando…' : 'Salvar usuário' }}
                    </button>
                    <p v-if="form.hasErrors" role="alert" class="text-[0.9375rem] font-semibold text-erro-texto">Confira os campos destacados acima.</p>
                </div>
            </div>
        </form>
    </div>
</template>
