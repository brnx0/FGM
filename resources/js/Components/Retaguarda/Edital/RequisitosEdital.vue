<script setup lang="ts">
import CampoCadastro from '../../CampoCadastro.vue';
import ListaEditavel from './ListaEditavel.vue';
import { classeCaixa, classeDica, classeMarcacao, classeTexto, novaChave } from '../listaEditavel';
import type { DocumentoEditavel, RequisitoEditavel } from '../../../types';

defineProps<{
    /** Erros do useForm; os dos requisitos vêm como "requisitos.N.campo". */
    erros: Partial<Record<string, string>>;
    /** Documentos atuais da aba Documentos, na ordem em que estão. */
    documentos: DocumentoEditavel[];
}>();

const emit = defineEmits<{ limparErros: [campos: string[]] }>();

const requisitos = defineModel<RequisitoEditavel[]>({ required: true });

const rotuloDoDocumento = (documento: DocumentoEditavel, posicao: number): string => documento.nome.trim() || `Documento ${posicao + 1}`;

const resumo = (requisito: RequisitoEditavel): string => {
    const quantidade = requisito.documentos.length;

    return quantidade === 0 ? 'Sem documento que comprove' : `${quantidade} ${quantidade === 1 ? 'documento comprova' : 'documentos comprovam'}`;
};

const novo = (): RequisitoEditavel => ({ chave: novaChave(), id: null, descricao: '', documentos: [] });
</script>

<template>
    <ListaEditavel
        v-model="requisitos"
        id="requisito"
        prefixo="requisitos"
        :erros="erros"
        singular="requisito"
        plural="requisitos"
        vazio="Nenhum requisito de homologação cadastrado."
        :novo="novo"
        campo-inicial="descricao"
        :nome="(requisito) => requisito.descricao"
        :resumo="resumo"
        @limpar-erros="emit('limparErros', $event)"
    >
        <template #default="{ item: requisito, idCampo, erro, erroDaLista, limpar, limparLista }">
            <CampoCadastro :id="idCampo('descricao')" rotulo="Descrição" obrigatorio :erro="erro('descricao')" v-slot="{ descricao, invalido }">
                <textarea
                    :id="idCampo('descricao')"
                    v-model="requisito.descricao"
                    rows="2"
                    maxlength="1000"
                    required
                    placeholder="Ex.: Sede ou domicílio em Salvador há pelo menos 2 anos"
                    :class="classeTexto"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="limpar('descricao')"
                />
            </CampoCadastro>

            <fieldset class="min-w-0" :aria-describedby="erroDaLista('documentos') ? idCampo('documentos-erro') : undefined">
                <legend class="text-[0.9375rem] font-semibold">Documentos que comprovam</legend>
                <p v-if="documentos.length === 0" :class="[classeDica, 'mt-1']">Cadastre os documentos na aba Documentos.</p>
                <div v-else class="mt-1.5 grid gap-x-6 @lg:grid-cols-2">
                    <label v-for="(documento, posicao) in documentos" :key="documento.chave" :class="classeMarcacao">
                        <input
                            v-model="requisito.documentos"
                            type="checkbox"
                            :value="documento.chave"
                            :class="classeCaixa"
                            :aria-invalid="erroDaLista('documentos') ? true : undefined"
                            @change="limparLista('documentos')"
                        />
                        {{ rotuloDoDocumento(documento, posicao) }}
                    </label>
                </div>
                <p v-if="erroDaLista('documentos')" :id="idCampo('documentos-erro')" class="mt-1 text-[0.875rem] font-semibold text-invalido-texto">{{ erroDaLista('documentos') }}</p>
            </fieldset>
        </template>
    </ListaEditavel>
</template>
