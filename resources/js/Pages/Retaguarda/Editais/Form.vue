<script setup lang="ts">
import { computed, nextTick } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CampoArquivo from '../../../Components/CampoArquivo.vue';
import CampoCadastro from '../../../Components/CampoCadastro.vue';
import SecaoCadastro from '../../../Components/SecaoCadastro.vue';
import RetaguardaLayout from '../../../Layouts/RetaguardaLayout.vue';
import type { RetaguardaEditaisFormProps } from '../../../types';

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaEditaisFormProps>();

// Caminhos das rotas retaguarda.editais.* (o front não tem helper de rotas nomeadas).
const BASE = '/retaguarda/editais';

const salvo = props.edital;
const editando = salvo !== null;

// Arquivos só vão quando escolhidos; sem novo upload o servidor mantém os atuais.
const form = useForm({
    titulo: salvo?.titulo ?? '',
    categoria: salvo?.categoria ?? '',
    resumo: salvo?.resumo ?? '',
    inscricoes_inicio: salvo?.inscricoes_inicio ?? '',
    inscricoes_fim: salvo?.inscricoes_fim ?? '',
    publicado: salvo?.publicado ?? false,
    pdf: null as File | null,
    imagem: null as File | null,
    remover_imagem: false,
});

// Limites do servidor (max em KB na validação): o arquivo grande nem sai do navegador.
const LIMITES = { pdf: 20 * 1024 * 1024, imagem: 5 * 1024 * 1024 } as const;
const MENSAGENS_LIMITE = { pdf: 'O PDF deve ter no máximo 20 MB.', imagem: 'A imagem deve ter no máximo 5 MB.' } as const;

const escolherArquivo = (arquivo: File | null, campo: 'pdf' | 'imagem'): void => {
    form.clearErrors(campo);

    if (arquivo && arquivo.size > LIMITES[campo]) {
        form[campo] = null;
        form.setError(campo, MENSAGENS_LIMITE[campo]);
        return;
    }
    form[campo] = arquivo;
    if (campo === 'imagem' && arquivo) form.remover_imagem = false;
};

// Edição vai como POST com _method=put: PUT com multipart não chega ao PHP.
const salvar = (): void => {
    const opcoes = {
        forceFormData: true,
        preserveScroll: true,
        onError: () => nextTick(() => document.querySelector<HTMLElement>('#formulario-edital [aria-invalid="true"]')?.focus()),
    };

    if (editando) {
        form.transform((dados) => ({ ...dados, _method: 'put' })).post(`${BASE}/${salvo.slug}`, opcoes);
    } else {
        form.post(BASE, opcoes);
    }
};

const textoBotao = computed(() => {
    if (!form.processing) return 'Salvar edital';
    return form.progress?.percentage !== undefined ? `Enviando ${form.progress.percentage}%` : 'Salvando…';
});

const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave aria-invalid:border-erro-texto aria-invalid:ring-1 aria-invalid:ring-erro-texto';
const classeMarcacao = 'flex min-h-11 cursor-pointer items-start gap-3 py-2 text-[0.9375rem] leading-[1.5]';
const classeCaixa = 'mt-0.5 size-5 shrink-0 cursor-pointer accent-acao';
</script>

<template>
    <div class="mx-auto flex max-w-4xl flex-col gap-6 px-4 pt-8 lg:px-6">
        <Head :title="editando ? 'Editar edital · Retaguarda' : 'Novo edital · Retaguarda'" />

        <div>
            <Link :href="BASE" class="inline-flex min-h-11 items-center gap-1.5 text-[0.9375rem] font-semibold text-acao hover:underline">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M11 6l-6 6 6 6" />
                </svg>
                Editais
            </Link>
            <h1 class="mt-1 text-[1.75rem] leading-tight font-bold break-words">{{ salvo?.titulo ?? 'Novo edital' }}</h1>
        </div>

        <form id="formulario-edital" novalidate class="flex flex-col gap-6" @submit.prevent="salvar">
            <SecaoCadastro id="secao-edital-dados" :numero="1" titulo="Dados do edital">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6" />
                    </svg>
                </template>

                <div class="grid gap-x-6 gap-y-5 md:grid-cols-2">
                    <CampoCadastro id="titulo" class="md:col-span-2" rotulo="Título" obrigatorio :erro="form.errors.titulo" v-slot="{ descricao, invalido }">
                        <input
                            id="titulo"
                            v-model="form.titulo"
                            type="text"
                            maxlength="255"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('titulo')"
                        />
                    </CampoCadastro>

                    <CampoCadastro id="categoria" class="md:col-span-2" rotulo="Categoria" obrigatorio :erro="form.errors.categoria" v-slot="{ descricao, invalido }">
                        <input
                            id="categoria"
                            v-model="form.categoria"
                            type="text"
                            maxlength="255"
                            required
                            placeholder="Ex.: Fomento, Audiovisual"
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('categoria')"
                        />
                    </CampoCadastro>

                    <CampoCadastro id="resumo" class="md:col-span-2" rotulo="Resumo" obrigatorio :erro="form.errors.resumo" v-slot="{ descricao, invalido }">
                        <textarea
                            id="resumo"
                            v-model="form.resumo"
                            rows="5"
                            required
                            :class="[classeCampo, 'h-auto py-3 leading-[1.6]']"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('resumo')"
                        />
                    </CampoCadastro>

                    <CampoCadastro id="inscricoes_inicio" rotulo="Início das inscrições" obrigatorio :erro="form.errors.inscricoes_inicio" v-slot="{ descricao, invalido }">
                        <input
                            id="inscricoes_inicio"
                            v-model="form.inscricoes_inicio"
                            type="date"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('inscricoes_inicio')"
                        />
                    </CampoCadastro>

                    <CampoCadastro id="inscricoes_fim" rotulo="Fim das inscrições" obrigatorio :erro="form.errors.inscricoes_fim" v-slot="{ descricao, invalido }">
                        <input
                            id="inscricoes_fim"
                            v-model="form.inscricoes_fim"
                            type="date"
                            required
                            :min="form.inscricoes_inicio || undefined"
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('inscricoes_fim')"
                        />
                    </CampoCadastro>
                </div>
            </SecaoCadastro>

            <SecaoCadastro id="secao-edital-arquivos" :numero="2" titulo="Arquivos">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 16V4M7 9l5-5 5 5" />
                        <path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" />
                    </svg>
                </template>

                <div class="grid gap-x-6 gap-y-6 md:grid-cols-2">
                    <div class="flex min-w-0 flex-col gap-3">
                        <CampoCadastro id="pdf" rotulo="PDF do edital" :erro="form.errors.pdf" v-slot="{ descricao, invalido }">
                            <CampoArquivo
                                id="pdf"
                                accept="application/pdf"
                                dica="Só PDF, até 20 MB. Obrigatório para publicar"
                                :arquivo="form.pdf"
                                :enviado="Boolean(salvo?.pdfUrl)"
                                :descricao="descricao"
                                :invalido="invalido"
                                @escolher="escolherArquivo($event, 'pdf')"
                            />
                        </CampoCadastro>
                        <p v-if="salvo?.pdfUrl" class="text-[0.9375rem]">
                            PDF atual:
                            <a :href="salvo.pdfUrl" class="font-semibold break-all text-acao underline">{{ salvo.pdfNome }}</a>
                        </p>
                    </div>

                    <div class="flex min-w-0 flex-col gap-3">
                        <CampoCadastro id="imagem" rotulo="Imagem" :erro="form.errors.imagem" v-slot="{ descricao, invalido }">
                            <CampoArquivo
                                id="imagem"
                                accept="image/jpeg,image/png,image/webp"
                                dica="JPG, PNG ou WEBP, até 5 MB"
                                :arquivo="form.imagem"
                                :enviado="Boolean(salvo?.imagemUrl) && !form.remover_imagem"
                                :descricao="descricao"
                                :invalido="invalido"
                                @escolher="escolherArquivo($event, 'imagem')"
                            />
                        </CampoCadastro>
                        <div v-if="salvo?.imagemUrl && !form.imagem" class="flex items-start gap-4">
                            <img
                                :src="salvo.imagemUrl"
                                alt="Imagem atual do edital"
                                class="h-24 w-36 shrink-0 rounded-[12px] border border-hairline object-cover"
                                :class="{ 'opacity-40': form.remover_imagem }"
                            />
                            <label :class="classeMarcacao">
                                <input v-model="form.remover_imagem" type="checkbox" :class="classeCaixa" />
                                Remover imagem
                            </label>
                        </div>
                    </div>
                </div>
            </SecaoCadastro>

            <SecaoCadastro id="secao-edital-publicacao" :numero="3" titulo="Publicação">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
                    </svg>
                </template>

                <label :class="classeMarcacao">
                    <input v-model="form.publicado" type="checkbox" :class="classeCaixa" aria-describedby="publicado-ajuda" />
                    <span>
                        <span class="font-semibold">Publicar no portal</span>
                        <span id="publicado-ajuda" class="block text-[0.8125rem] text-tinta-suave">O edital só aparece no portal publicado e com PDF.</span>
                    </span>
                </label>
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
                        {{ textoBotao }}
                    </button>
                    <p v-if="form.hasErrors" role="alert" class="text-[0.9375rem] font-semibold text-erro-texto">Confira os campos destacados acima.</p>
                </div>
            </div>
        </form>
    </div>
</template>
