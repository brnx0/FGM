<script setup lang="ts">
import { computed, defineAsyncComponent, nextTick, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CampoArquivo from '../../../Components/CampoArquivo.vue';
import CampoCadastro from '../../../Components/CampoCadastro.vue';
import AbasFormulario, { camposComErro } from '../../../Components/Retaguarda/AbasFormulario.vue';
import CategoriasEdital from '../../../Components/Retaguarda/CategoriasEdital.vue';
import SecaoCadastro from '../../../Components/SecaoCadastro.vue';
import RetaguardaLayout from '../../../Layouts/RetaguardaLayout.vue';
import type { AbaFormulario, CategoriaEditavel, RetaguardaEditaisFormProps } from '../../../types';

// O editor (Tiptap) é pesado: vai num pedaço separado, carregado só nesta tela.
const EditorTextoRico = defineAsyncComponent(() => import('../../../Components/EditorTextoRico.vue'));

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaEditaisFormProps>();

// Caminhos das rotas retaguarda.editais.* (o front não tem helper de rotas nomeadas).
const BASE = '/retaguarda/editais';
// Imagens do texto rico usam o mesmo envio dos eventos (retaguarda.eventos.conteudo.imagem).
const URL_IMAGEM_CONTEUDO = '/retaguarda/eventos/conteudo/imagem';

const salvo = props.edital;
const editando = salvo !== null;

// Categorias salvas viram cartões editáveis; a chave do cartão segue o id (não a posição).
const categoriasSalvas: CategoriaEditavel[] = (salvo?.categorias ?? []).map((categoria) => ({
    chave: `salva-${categoria.id}`,
    id: categoria.id,
    nome: categoria.nome,
    valor: categoria.valor,
    vagas: categoria.vagas,
    naturezas: [...categoria.naturezas],
    cotas: {
        negros: { valor: categoria.cotas.negros.valor, unidade: categoria.cotas.negros.unidade ?? 'vagas' },
        indigenas: { valor: categoria.cotas.indigenas.valor, unidade: categoria.cotas.indigenas.unidade ?? 'vagas' },
        pcd: { valor: categoria.cotas.pcd.valor, unidade: categoria.cotas.pcd.unidade ?? 'vagas' },
    },
}));

// Arquivos só vão quando escolhidos; sem novo upload o servidor mantém os atuais.
const form = useForm({
    titulo: salvo?.titulo ?? '',
    categoria: salvo?.categoria ?? '',
    tipo_fomento: salvo?.tipo_fomento ?? '',
    vigencia_inicio: salvo?.vigencia_inicio ?? '',
    vigencia_fim: salvo?.vigencia_fim ?? '',
    divulgacao: salvo?.divulgacao ?? '',
    inscricoes_inicio: salvo?.inscricoes_inicio ?? '',
    inscricoes_fim: salvo?.inscricoes_fim ?? '',
    publicado: salvo?.publicado ?? false,
    pdf: null as File | null,
    imagem: null as File | null,
    remover_imagem: false,
    categorias: categoriasSalvas,
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

// O edital vai ganhar muitos campos: cada grupo numa aba, todas no mesmo formulário.
const abas: AbaFormulario[] = [
    { id: 'dados', rotulo: 'Dados gerais', campos: ['titulo', 'categoria', 'tipo_fomento', 'vigencia_inicio', 'vigencia_fim', 'divulgacao'] },
    { id: 'inscricoes', rotulo: 'Inscrições', campos: ['inscricoes_inicio', 'inscricoes_fim'] },
    { id: 'categorias', rotulo: 'Categorias', campos: ['categorias', 'categorias.*'] },
    { id: 'arquivos', rotulo: 'Arquivos', campos: ['pdf', 'imagem'] },
    { id: 'publicacao', rotulo: 'Publicação', campos: ['publicado'] },
];
const abaAtiva = ref<string>('dados');

// Com erro, abre a primeira aba que tem algum e leva o foco ao campo inválido dela.
const mostrarPrimeiroErro = async (): Promise<void> => {
    const comErro = abas.find((aba) => camposComErro(aba, form.errors as Partial<Record<string, string>>).length > 0);
    if (comErro) abaAtiva.value = comErro.id;
    await nextTick();
    document.querySelector<HTMLElement>(`#edital-painel-${abaAtiva.value} [aria-invalid="true"]`)?.focus();
};

// Os erros das categorias chegam como "categorias.N.campo": chaves que o tipo do useForm não prevê.
const limparErros = (campos: string[]): void => {
    form.clearErrors(...(campos as Parameters<typeof form.clearErrors>));
};

// A lista de categorias vai sempre inteira: o servidor apaga as que não vierem (lista vazia = nenhuma).
// Sem a chave do cartão, que é só da tela.
const paraEnvio = (dados: ReturnType<typeof form.data>) => ({
    ...dados,
    categorias: dados.categorias.map(({ chave: _chave, ...categoria }) => categoria),
});

// Edição vai como POST com _method=put: PUT com multipart não chega ao PHP.
// "indices" monta categorias[0][nome]; o padrão ("brackets") viraria categorias[][nome] e quebraria os itens.
const salvar = (): void => {
    const opcoes = {
        forceFormData: true,
        queryStringArrayFormat: 'indices' as const,
        preserveScroll: true,
        onError: () => void mostrarPrimeiroErro(),
    };

    if (editando) {
        form.transform((dados) => ({ ...paraEnvio(dados), _method: 'put' })).post(`${BASE}/${salvo.slug}`, opcoes);
    } else {
        form.transform(paraEnvio).post(BASE, opcoes);
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
    <!-- Mais largo que os outros formulários: as abas laterais ocupam uma coluna. -->
    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 pt-8 lg:px-6">
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
            <AbasFormulario id="edital" v-model="abaAtiva" variante="lateral" :abas="abas" :erros="form.errors">
                <template #dados>
                    <SecaoCadastro id="secao-edital-dados" :numero="1" titulo="Dados gerais">
                        <template #icone>
                            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6" />
                            </svg>
                        </template>

                        <div class="grid gap-x-6 gap-y-5">
                            <CampoCadastro id="titulo" rotulo="Título do edital" obrigatorio :erro="form.errors.titulo" v-slot="{ descricao, invalido }">
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

                            <CampoCadastro id="categoria" rotulo="Categoria" obrigatorio :erro="form.errors.categoria" v-slot="{ descricao, invalido }">
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

                            <CampoCadastro id="tipo_fomento" rotulo="Tipo de fomento" obrigatorio :erro="form.errors.tipo_fomento" v-slot="{ descricao, invalido }">
                                <select
                                    id="tipo_fomento"
                                    v-model="form.tipo_fomento"
                                    required
                                    :class="classeCampo"
                                    :aria-invalid="invalido || undefined"
                                    :aria-describedby="descricao"
                                    @change="form.clearErrors('tipo_fomento')"
                                >
                                    <option value="">Selecione…</option>
                                    <option v-for="tipo in tiposFomento" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                                </select>
                            </CampoCadastro>

                            <div class="grid gap-x-6 gap-y-5 md:grid-cols-2">
                                <CampoCadastro
                                    id="vigencia_inicio"
                                    rotulo="Início da vigência"
                                    obrigatorio
                                    ajuda="Validade administrativa, não a janela de inscrição."
                                    :erro="form.errors.vigencia_inicio"
                                    v-slot="{ descricao, invalido }"
                                >
                                    <input
                                        id="vigencia_inicio"
                                        v-model="form.vigencia_inicio"
                                        type="date"
                                        required
                                        :class="classeCampo"
                                        :aria-invalid="invalido || undefined"
                                        :aria-describedby="descricao"
                                        @input="form.clearErrors('vigencia_inicio')"
                                    />
                                </CampoCadastro>

                                <CampoCadastro
                                    id="vigencia_fim"
                                    rotulo="Fim da vigência"
                                    ajuda="Opcional. Sem data, o edital fica vigente por prazo indeterminado."
                                    :erro="form.errors.vigencia_fim"
                                    v-slot="{ descricao, invalido }"
                                >
                                    <input
                                        id="vigencia_fim"
                                        v-model="form.vigencia_fim"
                                        type="date"
                                        :min="form.vigencia_inicio || undefined"
                                        :class="classeCampo"
                                        :aria-invalid="invalido || undefined"
                                        :aria-describedby="descricao"
                                        @input="form.clearErrors('vigencia_fim')"
                                    />
                                </CampoCadastro>
                            </div>

                            <CampoCadastro
                                id="divulgacao"
                                rotulo="Divulgação"
                                obrigatorio
                                ajuda="Texto informativo do edital, exibido na página dele no portal."
                                :erro="form.errors.divulgacao"
                                v-slot="{ descricao, invalido }"
                            >
                                <EditorTextoRico
                                    id="divulgacao"
                                    v-model="form.divulgacao"
                                    rotulo="Divulgação"
                                    :descricao="descricao"
                                    :invalido="invalido"
                                    :url-imagem="URL_IMAGEM_CONTEUDO"
                                    @update:model-value="form.clearErrors('divulgacao')"
                                />
                            </CampoCadastro>
                        </div>
                    </SecaoCadastro>
                </template>

                <template #inscricoes>
                    <SecaoCadastro id="secao-edital-inscricoes" :numero="2" titulo="Inscrições">
                        <template #icone>
                            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="5" width="16" height="16" rx="2" />
                                <path d="M4 10h16M9 3v4M15 3v4" />
                            </svg>
                        </template>

                        <div class="grid gap-x-6 gap-y-5 md:grid-cols-2">
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
                </template>

                <template #categorias>
                    <SecaoCadastro id="secao-edital-categorias" :numero="3" titulo="Categorias de inscrição">
                        <template #icone>
                            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="4" width="16" height="6" rx="1.5" />
                                <rect x="4" y="14" width="16" height="6" rx="1.5" />
                            </svg>
                        </template>

                        <p class="mb-6 text-[0.9375rem] leading-[1.6] text-tinta-suave">
                            As opções que o proponente escolhe ao se inscrever. Cada categoria tem valor, vagas, quem pode concorrer e reserva de cotas.
                        </p>

                        <CategoriasEdital
                            v-model="form.categorias"
                            :erros="form.errors as Partial<Record<string, string>>"
                            :naturezas="naturezas"
                            @limpar-erros="limparErros"
                        />
                    </SecaoCadastro>
                </template>

                <template #arquivos>
                    <SecaoCadastro id="secao-edital-arquivos" :numero="4" titulo="Arquivos">
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
                </template>

                <template #publicacao>
                    <SecaoCadastro id="secao-edital-publicacao" :numero="5" titulo="Publicação">
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
                </template>
            </AbasFormulario>

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
                    <p v-if="form.hasErrors" role="alert" class="text-[0.9375rem] font-semibold text-erro-texto">Confira os campos destacados nas abas marcadas.</p>
                </div>
            </div>
        </form>
    </div>
</template>
