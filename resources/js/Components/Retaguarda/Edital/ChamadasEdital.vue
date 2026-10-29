<script setup lang="ts">
import CampoCadastro from '../../CampoCadastro.vue';
import ListaEditavel from './ListaEditavel.vue';
import { classeCampo, novaChave } from '../listaEditavel';
import type { ChamadaEditavel } from '../../../types';

defineProps<{
    /** Erros do useForm; os das chamadas vêm como "chamadas.N.campo". */
    erros: Partial<Record<string, string>>;
}>();

const emit = defineEmits<{ limparErros: [campos: string[]] }>();

const chamadas = defineModel<ChamadaEditavel[]>({ required: true });

const data = (texto: string): string => (texto ? texto.split('-').reverse().join('/') : '');

const resumo = (chamada: ChamadaEditavel): string =>
    chamada.inicio || chamada.fim ? `${data(chamada.inicio) || '…'} a ${data(chamada.fim) || '…'}` : 'Datas a preencher';

const nova = (): ChamadaEditavel => ({ chave: novaChave(), id: null, nome: `Chamada ${chamadas.value.length + 1}`, inicio: '', fim: '' });
</script>

<template>
    <ListaEditavel
        v-model="chamadas"
        id="chamada"
        prefixo="chamadas"
        :erros="erros"
        singular="chamada"
        plural="chamadas"
        feminino
        vazio="Nenhuma chamada cadastrada. Para publicar, cadastre ao menos uma com início e fim das inscrições."
        :novo="nova"
        campo-inicial="nome"
        :nome="(chamada) => chamada.nome"
        :resumo="resumo"
        @limpar-erros="emit('limparErros', $event)"
    >
        <template #default="{ item: chamada, idCampo, erro, limpar }">
            <CampoCadastro :id="idCampo('nome')" rotulo="Nome" obrigatorio :erro="erro('nome')" v-slot="{ descricao, invalido }">
                <input
                    :id="idCampo('nome')"
                    v-model="chamada.nome"
                    type="text"
                    maxlength="100"
                    required
                    placeholder="Ex.: Chamada 1"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="limpar('nome')"
                />
            </CampoCadastro>

            <div class="grid gap-x-6 gap-y-5 @lg:grid-cols-2">
                <CampoCadastro :id="idCampo('inicio')" rotulo="Início das inscrições" obrigatorio :erro="erro('inicio')" v-slot="{ descricao, invalido }">
                    <input
                        :id="idCampo('inicio')"
                        v-model="chamada.inicio"
                        type="date"
                        required
                        :class="classeCampo"
                        :aria-invalid="invalido || undefined"
                        :aria-describedby="descricao"
                        @input="limpar('inicio', 'fim')"
                    />
                </CampoCadastro>

                <CampoCadastro :id="idCampo('fim')" rotulo="Fim das inscrições" obrigatorio :erro="erro('fim')" v-slot="{ descricao, invalido }">
                    <input
                        :id="idCampo('fim')"
                        v-model="chamada.fim"
                        type="date"
                        required
                        :min="chamada.inicio || undefined"
                        :class="classeCampo"
                        :aria-invalid="invalido || undefined"
                        :aria-describedby="descricao"
                        @input="limpar('fim')"
                    />
                </CampoCadastro>
            </div>
        </template>
    </ListaEditavel>
</template>
