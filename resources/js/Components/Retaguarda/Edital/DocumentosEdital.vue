<script setup lang="ts">
import CampoArquivo from '../../CampoArquivo.vue';
import CampoCadastro from '../../CampoCadastro.vue';
import ListaEditavel from './ListaEditavel.vue';
import { classeCaixa, classeCampo, classeDica, classeMarcacao, classeTexto, novaChave } from '../listaEditavel';
import type { CategoriaEditavel, DocumentoEditavel, OpcaoFormulario } from '../../../types';

const props = defineProps<{
    /** Erros do useForm; os dos documentos vêm como "documentos.N.campo". */
    erros: Partial<Record<string, string>>;
    naturezas: OpcaoFormulario[];
    etapas: OpcaoFormulario[];
    modalidadesCota: OpcaoFormulario[];
    checagensDocumento: OpcaoFormulario[];
    /** Categorias atuais da aba Categorias, na ordem em que estão. */
    categorias: CategoriaEditavel[];
}>();

const emit = defineEmits<{
    limparErros: [campos: string[]];
    /** Erro do navegador num campo (arquivo grande demais). */
    definirErro: [campo: string, mensagem: string];
}>();

const documentos = defineModel<DocumentoEditavel[]>({ required: true });

/** Mesmo limite do SalvarEditalRequest (max:10240 KB): o arquivo grande nem sai do navegador. */
const LIMITE_MODELO = 10 * 1024 * 1024;
const ACEITOS_MODELO =
    '.pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.oasis.opendocument.text,application/vnd.oasis.opendocument.spreadsheet';

/** Campos que aparecem no cartão: só os erros deles contam no selo (id e remover_modelo não mostram mensagem). */
const CAMPOS_VISIVEIS = /^(nome|orientacao|etapa|obrigatorio|naturezas|todas_categorias|categorias|modalidade_cota|anexo_referencia|checagem|instrucao_ia|modelo)(\.|$)/;

const rotulo = (opcoes: OpcaoFormulario[], valor: string): string | undefined => opcoes.find((opcao) => opcao.valor === valor)?.rotulo;

const rotuloDaCategoria = (categoria: CategoriaEditavel, posicao: number): string => categoria.nome.trim() || `Categoria ${posicao + 1}`;

const resumo = (documento: DocumentoEditavel): string =>
    [rotulo(props.etapas, documento.etapa) ?? 'Etapa a escolher', documento.obrigatorio ? 'Obrigatório' : 'Opcional', documento.modeloNome || documento.modelo ? 'Com arquivo-modelo' : null]
        .filter(Boolean)
        .join(' · ');

const escolherModelo = (indice: number, documento: DocumentoEditavel, arquivo: File | null): void => {
    emit('limparErros', [`documentos.${indice}.modelo`]);

    if (arquivo && arquivo.size > LIMITE_MODELO) {
        documento.modelo = null;
        emit('definirErro', `documentos.${indice}.modelo`, 'O arquivo-modelo deve ter no máximo 10 MB.');
        return;
    }
    documento.modelo = arquivo;
    if (arquivo) documento.remover_modelo = false;
};

const escolherTodas = (documento: DocumentoEditavel, todas: boolean, limparLista: (campo: string) => void): void => {
    documento.todas_categorias = todas;
    limparLista('categorias');
};

const novo = (): DocumentoEditavel => ({
    chave: novaChave(),
    id: null,
    nome: '',
    orientacao: '',
    etapa: props.etapas[0]?.valor ?? '',
    obrigatorio: true,
    naturezas: [],
    todas_categorias: true,
    categorias: [],
    modalidade_cota: '',
    anexo_referencia: '',
    checagem: 'aceita_sem_validar',
    instrucao_ia: '',
    modelo: null,
    remover_modelo: false,
    modeloUrl: null,
    modeloNome: null,
});
</script>

<template>
    <ListaEditavel
        v-model="documentos"
        id="documento"
        prefixo="documentos"
        :erros="erros"
        singular="documento"
        plural="documentos"
        vazio="Nenhum documento exigido."
        :novo="novo"
        campo-inicial="nome"
        :nome="(documento) => documento.nome"
        :resumo="resumo"
        :conta-no-selo="(campo) => CAMPOS_VISIVEIS.test(campo)"
        @limpar-erros="emit('limparErros', $event)"
    >
        <template #default="{ item: documento, indice, idCampo, erro, erroDaLista, limpar, limparLista }">
            <CampoCadastro :id="idCampo('nome')" rotulo="Nome" obrigatorio :erro="erro('nome')" v-slot="{ descricao, invalido }">
                <input
                    :id="idCampo('nome')"
                    v-model="documento.nome"
                    type="text"
                    maxlength="255"
                    required
                    placeholder="Ex.: Comprovante de residência"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="limpar('nome')"
                />
            </CampoCadastro>

            <CampoCadastro :id="idCampo('orientacao')" rotulo="Orientação" ajuda="Texto curto mostrado ao proponente." :erro="erro('orientacao')" v-slot="{ descricao, invalido }">
                <textarea
                    :id="idCampo('orientacao')"
                    v-model="documento.orientacao"
                    rows="2"
                    maxlength="1000"
                    :class="classeTexto"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="limpar('orientacao')"
                />
            </CampoCadastro>

            <div class="grid gap-x-6 gap-y-5 @lg:grid-cols-2">
                <CampoCadastro :id="idCampo('etapa')" rotulo="Etapa" obrigatorio :erro="erro('etapa')" v-slot="{ descricao, invalido }">
                    <select
                        :id="idCampo('etapa')"
                        v-model="documento.etapa"
                        required
                        :class="classeCampo"
                        :aria-invalid="invalido || undefined"
                        :aria-describedby="descricao"
                        @change="limpar('etapa')"
                    >
                        <option v-for="etapa in etapas" :key="etapa.valor" :value="etapa.valor">{{ etapa.rotulo }}</option>
                    </select>
                </CampoCadastro>

                <CampoCadastro
                    :id="idCampo('modalidade_cota')"
                    rotulo="Modalidade de cota"
                    ajuda="Para os documentos da validação de cotas."
                    :erro="erro('modalidade_cota')"
                    v-slot="{ descricao, invalido }"
                >
                    <select
                        :id="idCampo('modalidade_cota')"
                        v-model="documento.modalidade_cota"
                        :class="classeCampo"
                        :aria-invalid="invalido || undefined"
                        :aria-describedby="descricao"
                        @change="limpar('modalidade_cota')"
                    >
                        <option value="">Nenhuma</option>
                        <option v-for="modalidade in modalidadesCota" :key="modalidade.valor" :value="modalidade.valor">{{ modalidade.rotulo }}</option>
                    </select>
                </CampoCadastro>
            </div>

            <label :class="classeMarcacao">
                <input v-model="documento.obrigatorio" type="checkbox" :class="classeCaixa" @change="limpar('obrigatorio')" />
                Obrigatório
            </label>

            <fieldset class="min-w-0" :aria-describedby="[idCampo('naturezas-ajuda'), erroDaLista('naturezas') && idCampo('naturezas-erro')].filter(Boolean).join(' ')">
                <legend class="text-[0.9375rem] font-semibold">Para quem</legend>
                <p :id="idCampo('naturezas-ajuda')" :class="[classeDica, 'mt-1']">Nenhuma marcada = todas as naturezas.</p>
                <div class="mt-1.5 grid gap-x-6 @lg:grid-cols-2">
                    <label v-for="natureza in naturezas" :key="natureza.valor" :class="classeMarcacao">
                        <input
                            v-model="documento.naturezas"
                            type="checkbox"
                            :value="natureza.valor"
                            :class="classeCaixa"
                            :aria-invalid="erroDaLista('naturezas') ? true : undefined"
                            @change="limparLista('naturezas')"
                        />
                        {{ natureza.rotulo }}
                    </label>
                </div>
                <p v-if="erroDaLista('naturezas')" :id="idCampo('naturezas-erro')" class="mt-1 text-[0.875rem] font-semibold text-erro-texto">{{ erroDaLista('naturezas') }}</p>
            </fieldset>

            <fieldset class="min-w-0" :aria-describedby="erroDaLista('categorias') ? idCampo('categorias-erro') : undefined">
                <legend class="text-[0.9375rem] font-semibold">Categorias</legend>
                <div class="mt-1.5 flex flex-wrap gap-x-6">
                    <label :class="classeMarcacao">
                        <input
                            type="radio"
                            :name="idCampo('todas')"
                            :checked="documento.todas_categorias"
                            :class="classeCaixa"
                            @change="escolherTodas(documento, true, limparLista)"
                        />
                        Todas as categorias
                    </label>
                    <label :class="classeMarcacao">
                        <input
                            type="radio"
                            :name="idCampo('todas')"
                            :checked="!documento.todas_categorias"
                            :class="classeCaixa"
                            @change="escolherTodas(documento, false, limparLista)"
                        />
                        Só algumas
                    </label>
                </div>
                <div v-if="!documento.todas_categorias" class="mt-1 grid gap-x-6 border-l-2 border-hairline pl-4 @lg:grid-cols-2">
                    <p v-if="categorias.length === 0" :class="[classeDica, 'py-2']">Cadastre as categorias na aba Categorias.</p>
                    <label v-for="(categoria, posicao) in categorias" :key="categoria.chave" :class="classeMarcacao">
                        <input
                            v-model="documento.categorias"
                            type="checkbox"
                            :value="categoria.chave"
                            :class="classeCaixa"
                            :aria-invalid="erroDaLista('categorias') ? true : undefined"
                            @change="limparLista('categorias')"
                        />
                        {{ rotuloDaCategoria(categoria, posicao) }}
                    </label>
                </div>
                <p v-if="erroDaLista('categorias')" :id="idCampo('categorias-erro')" class="mt-1 text-[0.875rem] font-semibold text-erro-texto">{{ erroDaLista('categorias') }}</p>
            </fieldset>

            <CampoCadastro
                :id="idCampo('anexo_referencia')"
                rotulo="Anexo do edital que serve de modelo"
                :erro="erro('anexo_referencia')"
                v-slot="{ descricao, invalido }"
            >
                <input
                    :id="idCampo('anexo_referencia')"
                    v-model="documento.anexo_referencia"
                    type="text"
                    maxlength="255"
                    placeholder="Ex.: Anexo IV — Declaração de endereço"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="limpar('anexo_referencia')"
                />
            </CampoCadastro>

            <CampoCadastro :id="idCampo('checagem')" rotulo="Checagem pela IA" obrigatorio :erro="erro('checagem')" v-slot="{ descricao, invalido }">
                <select
                    :id="idCampo('checagem')"
                    v-model="documento.checagem"
                    required
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @change="limpar('checagem')"
                >
                    <option v-for="checagem in checagensDocumento" :key="checagem.valor" :value="checagem.valor">{{ checagem.rotulo }}</option>
                </select>
            </CampoCadastro>

            <CampoCadastro
                :id="idCampo('instrucao_ia')"
                rotulo="Instrução para a IA"
                ajuda="O que a IA deve conferir neste documento. Não aparece para o proponente."
                :erro="erro('instrucao_ia')"
                v-slot="{ descricao, invalido }"
            >
                <textarea
                    :id="idCampo('instrucao_ia')"
                    v-model="documento.instrucao_ia"
                    rows="3"
                    maxlength="2000"
                    :class="classeTexto"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="limpar('instrucao_ia')"
                />
            </CampoCadastro>

            <div class="flex min-w-0 flex-col gap-3">
                <CampoCadastro :id="idCampo('modelo')" rotulo="Arquivo-modelo" :erro="erro('modelo')" v-slot="{ descricao, invalido }">
                    <CampoArquivo
                        :id="idCampo('modelo')"
                        :accept="ACEITOS_MODELO"
                        dica="PDF, DOC, DOCX, XLS, XLSX, ODT ou ODS, até 10 MB. O proponente baixa, preenche e envia"
                        :arquivo="documento.modelo"
                        :enviado="Boolean(documento.modeloUrl) && !documento.remover_modelo"
                        :descricao="descricao"
                        :invalido="invalido"
                        @escolher="escolherModelo(indice, documento, $event)"
                    />
                </CampoCadastro>
                <div v-if="documento.modeloUrl && !documento.modelo" class="flex flex-wrap items-center gap-x-6 gap-y-1">
                    <p class="min-w-0 text-[0.9375rem]" :class="{ 'line-through opacity-60': documento.remover_modelo }">
                        Arquivo atual:
                        <a :href="documento.modeloUrl" class="font-semibold break-all text-acao underline">{{ documento.modeloNome ?? 'baixar' }}</a>
                    </p>
                    <label :class="classeMarcacao">
                        <input v-model="documento.remover_modelo" type="checkbox" :class="classeCaixa" />
                        Remover arquivo
                    </label>
                </div>
            </div>
        </template>
    </ListaEditavel>
</template>
