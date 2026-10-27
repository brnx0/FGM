<script setup lang="ts">
import { nextTick } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import CampoCadastro from '../../Components/CampoCadastro.vue';
import SecaoCadastro from '../../Components/SecaoCadastro.vue';
import RetaguardaLayout from '../../Layouts/RetaguardaLayout.vue';
import type { RetaguardaMinhaSenhaProps } from '../../types';

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaMinhaSenhaProps>();

// Caminho da rota retaguarda.senha.update (o front não tem helper de rotas nomeadas).
const URL_SENHA = '/retaguarda/minha-senha';

const form = useForm({
    senha_atual: '',
    nova_senha: '',
    nova_senha_confirmation: '',
});

const salvar = (): void => {
    form.put(URL_SENHA, {
        preserveScroll: true,
        onError: () => nextTick(() => document.querySelector<HTMLElement>('#formulario-senha [aria-invalid="true"]')?.focus()),
        onFinish: () => form.reset(),
    });
};

const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave aria-invalid:border-erro-texto aria-invalid:ring-1 aria-invalid:ring-erro-texto';
</script>

<template>
    <div class="mx-auto flex max-w-2xl flex-col gap-6 px-4 pt-8 lg:px-6">
        <Head title="Trocar senha · Retaguarda" />

        <div>
            <p class="text-[0.75rem] font-bold tracking-[0.12em] text-acao uppercase">Retaguarda</p>
            <h1 class="mt-1 text-[1.75rem] leading-tight font-bold">Trocar senha</h1>
            <p v-if="!props.temSenha" class="mt-1 text-[0.9375rem] text-tinta-suave">
                Você entra pelo gov.br e ainda não tem senha. Defina uma para também entrar com e-mail e senha.
            </p>
        </div>

        <form id="formulario-senha" novalidate class="flex flex-col gap-6" @submit.prevent="salvar">
            <SecaoCadastro id="secao-senha" :numero="1" titulo="Sua senha">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="11" width="14" height="10" rx="2" />
                        <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                    </svg>
                </template>

                <div class="grid gap-x-6 gap-y-5">
                    <CampoCadastro v-if="props.temSenha" id="senha_atual" rotulo="Senha atual" obrigatorio :erro="form.errors.senha_atual" v-slot="{ descricao, invalido }">
                        <input
                            id="senha_atual"
                            v-model="form.senha_atual"
                            type="password"
                            autocomplete="current-password"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('senha_atual')"
                        />
                    </CampoCadastro>

                    <CampoCadastro id="nova_senha" rotulo="Nova senha" obrigatorio ajuda="Mínimo de 8 caracteres." :erro="form.errors.nova_senha" v-slot="{ descricao, invalido }">
                        <input
                            id="nova_senha"
                            v-model="form.nova_senha"
                            type="password"
                            autocomplete="new-password"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('nova_senha')"
                        />
                    </CampoCadastro>

                    <CampoCadastro id="nova_senha_confirmation" rotulo="Confirme a nova senha" obrigatorio v-slot="{ descricao }">
                        <input
                            id="nova_senha_confirmation"
                            v-model="form.nova_senha_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            :class="classeCampo"
                            :aria-describedby="descricao"
                        />
                    </CampoCadastro>
                </div>
            </SecaoCadastro>

            <div class="flex flex-col gap-2 sm:items-end">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="pressionavel inline-flex min-h-12 items-center justify-center gap-2 rounded-pill bg-acao px-8 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro disabled:cursor-wait disabled:opacity-70"
                >
                    {{ form.processing ? 'Salvando…' : 'Salvar senha' }}
                </button>
                <p v-if="form.hasErrors" role="alert" class="text-[0.9375rem] font-semibold text-erro-texto">Confira os campos destacados acima.</p>
            </div>
        </form>
    </div>
</template>
