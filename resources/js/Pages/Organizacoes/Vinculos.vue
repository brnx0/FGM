<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import CampoCadastro from '../../Components/CampoCadastro.vue';
import { formatarCpf } from '../../Components/Organizacao/mascaras';
import ConfirmarExclusao from '../../Components/Retaguarda/ConfirmarExclusao.vue';
import FaixaPortal from '../../Components/FaixaPortal.vue';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';
import type { OrganizacaoVinculosProps } from '../../types';

defineOptions({ layout: PortalProponenteLayout });

const props = defineProps<OrganizacaoVinculosProps>();

const base = `/organizacoes/${props.organizacao.id}/pessoas`;

const formulario = useForm({ cpf: '', nome: '' });

const vincular = (): void => {
    formulario.post(base, {
        preserveScroll: true,
        onSuccess: () => formulario.reset(),
    });
};

const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave aria-invalid:border-erro-texto aria-invalid:ring-1 aria-invalid:ring-erro-texto';
</script>

<template>
    <div>
        <Head :title="`Pessoas vinculadas · ${organizacao.nome}`" />

        <FaixaPortal aria-labelledby="vinculos-titulo">
            <div class="flex flex-col gap-3">
                <Link
                    href="/organizacoes"
                    class="inline-flex min-h-11 items-center gap-2 self-start text-[0.8125rem] font-semibold tracking-[0.12em] text-white/85 uppercase no-underline hover:text-white"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M11 6l-6 6 6 6" />
                    </svg>
                    Voltar para Minhas organizações
                </Link>
                <p class="text-[0.75rem] font-semibold tracking-[0.24em] text-white/75 uppercase break-words">{{ organizacao.nome }}</p>
                <h1 id="vinculos-titulo" class="font-barlow text-[2.6rem] leading-[0.95] font-extrabold tracking-[0.01em] break-words uppercase md:text-[3.5rem]">
                    Pessoas vinculadas
                </h1>
            </div>
        </FaixaPortal>

        <div class="relative mx-auto -mt-20 flex max-w-6xl flex-col gap-6 px-4 pb-16 md:-mt-24">
            <section
                aria-labelledby="vinculos-lista"
                class="rounded-[24px] border border-hairline bg-superficie p-6 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:p-10"
            >
                <h2 id="vinculos-lista" class="text-[1.25rem] font-bold">Quem pode acessar esta organização</h2>
                <p class="mt-2 max-w-2xl leading-[1.6] text-tinta-suave">
                    Quem está vinculado vê e edita os dados da organização.
                    <template v-if="podeGerenciar">Como responsável, você vincula e desvincula pessoas pelo CPF.</template>
                    <template v-else>Só o responsável vincula e desvincula pessoas.</template>
                </p>

                <ul class="mt-6 divide-y divide-hairline border-y border-hairline">
                    <li v-for="vinculo in vinculos" :key="vinculo.id" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3">
                        <div class="min-w-0">
                            <p class="font-semibold break-words">
                                {{ vinculo.nome }}
                                <span v-if="vinculo.ehVoce" class="font-normal text-tinta-suave">(você)</span>
                            </p>
                            <p class="text-[0.875rem] text-tinta-suave tabular-nums">CPF {{ vinculo.cpf }}</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                v-if="vinculo.responsavel"
                                class="inline-flex rounded-pill bg-nevoa px-2.5 py-0.5 text-[0.6875rem] font-bold tracking-[0.08em] text-acao uppercase"
                            >
                                Responsável
                            </span>
                            <ConfirmarExclusao
                                v-else-if="vinculo.ehVoce"
                                :url="`${base}/${vinculo.id}`"
                                :nome="organizacao.nome"
                                acao="Sair da organização"
                                acao-em-andamento="Saindo…"
                                :pergunta="`Sair de “${organizacao.nome}”?`"
                                aviso="Você deixa de ver e editar esta organização. O responsável pode vincular você de novo."
                            />
                            <ConfirmarExclusao
                                v-else-if="podeGerenciar"
                                :url="`${base}/${vinculo.id}`"
                                :nome="vinculo.nome"
                                acao="Desvincular"
                                acao-em-andamento="Desvinculando…"
                                :pergunta="`Desvincular ${vinculo.nome}?`"
                                aviso="A pessoa deixa de ver e editar esta organização."
                            />
                        </div>
                    </li>
                </ul>
            </section>

            <section
                v-if="podeGerenciar"
                aria-labelledby="vinculos-novo"
                class="rounded-[24px] border border-hairline bg-superficie p-6 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:p-10"
            >
                <h2 id="vinculos-novo" class="text-[1.25rem] font-bold">Vincular uma pessoa</h2>
                <p class="mt-2 max-w-2xl leading-[1.6] text-tinta-suave">
                    Informe o CPF e o nome. A pessoa vê a organização assim que entrar no portal e completar o cadastro.
                </p>

                <form novalidate class="mt-6 flex flex-col gap-5" @submit.prevent="vincular">
                    <div class="grid gap-x-6 gap-y-5 md:grid-cols-2">
                        <CampoCadastro id="vinculo-cpf" rotulo="CPF" obrigatorio :erro="formulario.errors.cpf" v-slot="{ descricao, invalido }">
                            <input
                                id="vinculo-cpf"
                                :value="formulario.cpf"
                                type="text"
                                inputmode="numeric"
                                maxlength="14"
                                required
                                placeholder="000.000.000-00"
                                :class="[classeCampo, 'tabular-nums']"
                                :aria-invalid="invalido || undefined"
                                :aria-describedby="descricao"
                                @input="
                                    formulario.cpf = formatarCpf(($event.target as HTMLInputElement).value);
                                    formulario.clearErrors('cpf');
                                "
                            />
                        </CampoCadastro>

                        <CampoCadastro id="vinculo-nome" rotulo="Nome" obrigatorio :erro="formulario.errors.nome" v-slot="{ descricao, invalido }">
                            <input
                                id="vinculo-nome"
                                v-model="formulario.nome"
                                type="text"
                                maxlength="255"
                                required
                                placeholder="Nome completo"
                                :class="classeCampo"
                                :aria-invalid="invalido || undefined"
                                :aria-describedby="descricao"
                                @input="formulario.clearErrors('nome')"
                            />
                        </CampoCadastro>
                    </div>

                    <div class="flex">
                        <button
                            type="submit"
                            :disabled="formulario.processing"
                            class="pressionavel inline-flex min-h-12 items-center justify-center rounded-pill bg-acao px-8 text-center text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro disabled:cursor-wait disabled:opacity-70"
                        >
                            {{ formulario.processing ? 'Vinculando…' : 'Vincular pessoa' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>
