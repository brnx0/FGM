<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AvisoCamposObrigatorios from '../../Components/AvisoCamposObrigatorios.vue';
import CampoCadastro from '../../Components/CampoCadastro.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import { focarPrimeiroErro } from '../../Components/focarPrimeiroErro';
import GovBrButton from '../../Components/GovBrButton.vue';
import InstitutionalBar from '../../Components/InstitutionalBar.vue';
import VidroLiquido from '../../Components/VidroLiquido.vue';
import HomeLayout from '../../Layouts/HomeLayout.vue';

// Sem o topo e o rodapé do portal: só a barra institucional e a marca.
defineOptions({ layout: HomeLayout });

const form = useForm({ email: '', senha: '' });

const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave';

// Recusado, ou barrado pelo limite de tentativas (que o servidor devolve como erro do e-mail):
// a senha é apagada e o foco vai ao primeiro campo com erro (o e-mail, onde o servidor mostra a recusa).
const entrar = (): void => {
    form.post('/retaguarda/login', {
        onFinish: () => form.reset('senha'),
        onError: () => focarPrimeiroErro('#formulario-login'),
    });
};
</script>

<template>
    <VidroLiquido class="flex flex-1 flex-col bg-parchment text-home-tinta">
        <Head title="Entrar na retaguarda" />
        <InstitutionalBar />

        <header class="border-b border-white/10 bg-noite text-white [html.alto-contraste_&]:bg-fundo">
            <div class="mx-auto flex max-w-6xl items-center px-4 py-3.5">
                <Link href="/" class="block min-w-0 py-1 focus-visible:outline-white">
                    <img
                        src="/images/logo-fgm-prefeitura-branco.png"
                        alt="FGM – Fundação Gregório de Mattos e Prefeitura de Salvador (voltar ao site)"
                        width="1395"
                        height="210"
                        class="block h-7 w-auto max-w-full md:h-9"
                    />
                </Link>
            </div>
        </header>

        <main id="conteudo" tabindex="-1" class="flex-1 px-4 pb-16 focus:outline-none">
            <FlashMessage />

            <section aria-labelledby="titulo-login" class="mx-auto mt-8 max-w-md rounded-card border border-hairline bg-superficie p-6 sm:mt-12 sm:p-8">
                <p class="text-[0.75rem] font-bold tracking-[0.12em] text-acao uppercase">Retaguarda</p>
                <h1 id="titulo-login" class="mt-1 text-[1.625rem] leading-tight font-bold">Entrar</h1>
                <p class="mt-2 text-[0.9375rem] text-tinta-suave">Acesso restrito aos usuários da retaguarda do portal da FGM.</p>

                <form id="formulario-login" class="mt-6 flex flex-col gap-5" novalidate @submit.prevent="entrar">
                    <AvisoCamposObrigatorios />

                    <CampoCadastro id="email" rotulo="E-mail" obrigatorio :erro="form.errors.email" v-slot="{ descricao, invalido }">
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                        />
                    </CampoCadastro>

                    <CampoCadastro id="senha" rotulo="Senha" obrigatorio :erro="form.errors.senha" v-slot="{ descricao, invalido }">
                        <input
                            id="senha"
                            v-model="form.senha"
                            type="password"
                            autocomplete="current-password"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                        />
                    </CampoCadastro>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="pressionavel inline-flex min-h-12 items-center justify-center rounded-pill bg-acao px-8 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro disabled:cursor-wait disabled:opacity-70"
                    >
                        {{ form.processing ? 'Entrando…' : 'Entrar' }}
                    </button>
                </form>

                <p class="my-6 flex items-center gap-3 text-[0.8125rem] text-tinta-suave before:h-px before:flex-1 before:bg-hairline after:h-px after:flex-1 after:bg-hairline">ou</p>

                <GovBrButton href="/retaguarda/govbr" class="w-full" />
            </section>
        </main>
    </VidroLiquido>
</template>
