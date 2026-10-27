<script setup lang="ts">
import { computed, nextTick } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CampoArquivo from '../../../Components/CampoArquivo.vue';
import CampoCadastro from '../../../Components/CampoCadastro.vue';
import SecaoCadastro from '../../../Components/SecaoCadastro.vue';
import RetaguardaLayout from '../../../Layouts/RetaguardaLayout.vue';
import type { RetaguardaEventosFormProps } from '../../../types';

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaEventosFormProps>();

// Caminhos das rotas retaguarda.eventos.* (o front não tem helper de rotas nomeadas).
const BASE = '/retaguarda/eventos';

const salvo = props.evento;
const editando = salvo !== null;

// A imagem só vai quando escolhida; sem novo upload o servidor mantém a atual.
const form = useForm({
    titulo: salvo?.titulo ?? '',
    resumo: salvo?.resumo ?? '',
    data_realizacao: salvo?.data_realizacao ?? '',
    local: salvo?.local ?? '',
    publicado: salvo?.publicado ?? false,
    imagem: null as File | null,
    remover_imagem: false,
});

// Limite do servidor (max em KB na validação): o arquivo grande nem sai do navegador.
const LIMITE_IMAGEM = 5 * 1024 * 1024;

const escolherImagem = (arquivo: File | null): void => {
    form.clearErrors('imagem');

    if (arquivo && arquivo.size > LIMITE_IMAGEM) {
        form.imagem = null;
        form.setError('imagem', 'A imagem deve ter no máximo 5 MB.');
        return;
    }
    form.imagem = arquivo;
    if (arquivo) form.remover_imagem = false;
};

// Edição vai como POST com _method=put: PUT com multipart não chega ao PHP.
const salvar = (): void => {
    const opcoes = {
        forceFormData: true,
        preserveScroll: true,
        onError: () => nextTick(() => document.querySelector<HTMLElement>('#formulario-evento [aria-invalid="true"]')?.focus()),
    };

    if (editando) {
        form.transform((dados) => ({ ...dados, _method: 'put' })).post(`${BASE}/${salvo.slug}`, opcoes);
    } else {
        form.post(BASE, opcoes);
    }
};

const textoBotao = computed(() => {
    if (!form.processing) return 'Salvar evento';
    return form.progress?.percentage !== undefined ? `Enviando ${form.progress.percentage}%` : 'Salvando…';
});

const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave aria-invalid:border-erro-texto aria-invalid:ring-1 aria-invalid:ring-erro-texto';
const classeMarcacao = 'flex min-h-11 cursor-pointer items-start gap-3 py-2 text-[0.9375rem] leading-[1.5]';
const classeCaixa = 'mt-0.5 size-5 shrink-0 cursor-pointer accent-acao';
</script>

<template>
    <div class="mx-auto flex max-w-4xl flex-col gap-6 px-4 pt-8 lg:px-6">
        <Head :title="editando ? 'Editar evento · Retaguarda' : 'Novo evento · Retaguarda'" />

        <div>
            <Link :href="BASE" class="inline-flex min-h-11 items-center gap-1.5 text-[0.9375rem] font-semibold text-acao hover:underline">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M11 6l-6 6 6 6" />
                </svg>
                Eventos
            </Link>
            <h1 class="mt-1 text-[1.75rem] leading-tight font-bold break-words">{{ salvo?.titulo ?? 'Novo evento' }}</h1>
        </div>

        <form id="formulario-evento" novalidate class="flex flex-col gap-6" @submit.prevent="salvar">
            <SecaoCadastro id="secao-evento-dados" :numero="1" titulo="Dados do evento">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="16" rx="2" />
                        <path d="M3 10h18M8 3v4M16 3v4" />
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

                    <CampoCadastro id="data_realizacao" rotulo="Data de realização" obrigatorio :erro="form.errors.data_realizacao" v-slot="{ descricao, invalido }">
                        <input
                            id="data_realizacao"
                            v-model="form.data_realizacao"
                            type="date"
                            required
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('data_realizacao')"
                        />
                    </CampoCadastro>

                    <CampoCadastro id="local" rotulo="Local" :erro="form.errors.local" v-slot="{ descricao, invalido }">
                        <input
                            id="local"
                            v-model="form.local"
                            type="text"
                            maxlength="255"
                            placeholder="Ex.: Teatro Gregório de Mattos"
                            :class="classeCampo"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="form.clearErrors('local')"
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
                </div>
            </SecaoCadastro>

            <SecaoCadastro id="secao-evento-imagem" :numero="2" titulo="Imagem">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 16V4M7 9l5-5 5 5" />
                        <path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" />
                    </svg>
                </template>

                <div class="flex min-w-0 flex-col gap-3 md:max-w-md">
                    <CampoCadastro id="imagem" rotulo="Imagem do evento" :erro="form.errors.imagem" v-slot="{ descricao, invalido }">
                        <CampoArquivo
                            id="imagem"
                            accept="image/jpeg,image/png,image/webp"
                            dica="JPG, PNG ou WEBP, até 5 MB"
                            :arquivo="form.imagem"
                            :enviado="Boolean(salvo?.imagemUrl) && !form.remover_imagem"
                            :descricao="descricao"
                            :invalido="invalido"
                            @escolher="escolherImagem"
                        />
                    </CampoCadastro>
                    <div v-if="salvo?.imagemUrl && !form.imagem" class="flex items-start gap-4">
                        <img
                            :src="salvo.imagemUrl"
                            alt="Imagem atual do evento"
                            class="h-24 w-36 shrink-0 rounded-[12px] border border-hairline object-cover"
                            :class="{ 'opacity-40': form.remover_imagem }"
                        />
                        <label :class="classeMarcacao">
                            <input v-model="form.remover_imagem" type="checkbox" :class="classeCaixa" />
                            Remover imagem
                        </label>
                    </div>
                </div>
            </SecaoCadastro>

            <SecaoCadastro id="secao-evento-publicacao" :numero="3" titulo="Publicação">
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
                        <span id="publicado-ajuda" class="block text-[0.8125rem] text-tinta-suave">O evento aparece na página inicial depois da data de realização.</span>
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
