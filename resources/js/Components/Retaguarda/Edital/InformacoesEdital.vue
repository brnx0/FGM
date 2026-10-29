<script setup lang="ts">
import CampoCadastro from '../../CampoCadastro.vue';
import { classeTexto } from '../listaEditavel';
import type { OpcaoFormulario } from '../../../types';

defineProps<{
    /** Erros do useForm: "informacoes.<seção>". */
    erros: Partial<Record<string, string>>;
    /** Seções informativas, na ordem de exibição no portal. */
    secoes: OpcaoFormulario[];
}>();

const emit = defineEmits<{ limparErros: [campos: string[]] }>();

/** Texto de cada seção, pela chave (vazio = a seção não aparece no portal). */
const informacoes = defineModel<Record<string, string>>({ required: true });

/** Mesmo limite do SalvarEditalRequest. */
const MAXIMO = 20000;
</script>

<template>
    <div class="flex flex-col gap-5">
        <p class="text-[0.9375rem] leading-[1.6] text-tinta-suave">Textos mostrados na página do edital no portal. Seção vazia não aparece.</p>

        <CampoCadastro
            v-for="secao in secoes"
            :id="`informacoes-${secao.valor}`"
            :key="secao.valor"
            :rotulo="secao.rotulo"
            :erro="erros[`informacoes.${secao.valor}`]"
            v-slot="{ descricao, invalido }"
        >
            <textarea
                :id="`informacoes-${secao.valor}`"
                v-model="informacoes[secao.valor]"
                rows="4"
                :maxlength="MAXIMO"
                :class="classeTexto"
                :aria-invalid="invalido || undefined"
                :aria-describedby="descricao"
                @input="emit('limparErros', [`informacoes.${secao.valor}`])"
            />
        </CampoCadastro>
    </div>
</template>
