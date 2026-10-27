<script setup lang="ts">
import { ref } from 'vue';
import CampoCadastro from '../CampoCadastro.vue';
import SecaoCadastro from '../SecaoCadastro.vue';
import { consultar, formatarCep } from './mascaras';
import type { EnderecoSede, OpcaoCadastro, RespostaConsultaCep } from '../../types';

// Bloco 2 do formulário da organização. Os campos são editados no próprio objeto do useForm (endereco),
// como nos cartões de representante.
const props = defineProps<{
    endereco: EnderecoSede;
    erros: Partial<Record<string, string>>;
    estados: OpcaoCadastro[];
}>();

const emit = defineEmits<{ limparErro: [chave: keyof EnderecoSede] }>();

const situacaoCep = ref<'buscando' | 'preenchido' | null>(null);

// Ao completar os 8 dígitos. Falha ou CEP desconhecido: nada muda e a pessoa preenche à mão.
const digitarCep = async (evento: Event): Promise<void> => {
    const entrada = evento.target as HTMLInputElement;
    entrada.value = formatarCep(entrada.value);
    props.endereco.cep = entrada.value;
    emit('limparErro', 'cep');
    situacaoCep.value = null;

    const cep = props.endereco.cep.replace(/\D/g, '');
    if (cep.length !== 8) return;

    situacaoCep.value = 'buscando';
    const resposta = await consultar<RespostaConsultaCep>(`/cadastro/consultas/cep/${cep}`);
    if (props.endereco.cep.replace(/\D/g, '') !== cep) return;

    situacaoCep.value = null;
    if (resposta?.encontrado) {
        for (const campo of ['logradouro', 'bairro', 'cidade', 'uf'] as const) {
            const valor = resposta.dados[campo];
            if (valor !== null) {
                props.endereco[campo] = valor;
                emit('limparErro', campo);
            }
        }
        situacaoCep.value = 'preenchido';
    }
};

const marcarSemNumero = (): void => {
    if (props.endereco.sem_numero) props.endereco.numero = '';
    emit('limparErro', 'numero');
};

const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave aria-invalid:border-erro-texto aria-invalid:ring-1 aria-invalid:ring-erro-texto disabled:cursor-not-allowed disabled:bg-nevoa disabled:opacity-70';
const classeMarcacao = 'flex min-h-11 cursor-pointer items-start gap-3 py-2 text-[0.9375rem] leading-[1.5]';
const classeCaixa = 'mt-0.5 size-5 shrink-0 cursor-pointer accent-acao';
</script>

<template>
    <SecaoCadastro id="secao-endereco" :numero="2" titulo="Endereço da sede">
        <template #icone>
            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z" />
                <circle cx="12" cy="9" r="2.5" />
            </svg>
        </template>

        <div class="grid gap-x-6 gap-y-5 md:grid-cols-6">
            <CampoCadastro
                id="cep"
                class="md:col-span-2"
                rotulo="CEP"
                ajuda="Informe o CEP para preencher automaticamente. Logradouro e bairro seguem editáveis."
                :erro="erros.cep"
                v-slot="{ descricao, invalido }"
            >
                <input
                    id="cep"
                    :value="endereco.cep"
                    type="text"
                    inputmode="numeric"
                    autocomplete="postal-code"
                    maxlength="9"
                    placeholder="00000-000"
                    :class="[classeCampo, 'tabular-nums']"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="digitarCep"
                />
                <p aria-live="polite" class="text-[0.8125rem] font-semibold text-acao empty:hidden">{{
                    situacaoCep === 'buscando' ? 'Buscando o endereço…' : situacaoCep === 'preenchido' ? 'Endereço preenchido pelo CEP. Confira.' : ''
                }}</p>
            </CampoCadastro>

            <CampoCadastro id="logradouro" class="md:col-span-4" rotulo="Logradouro" :erro="erros.logradouro" v-slot="{ descricao, invalido }">
                <input
                    id="logradouro"
                    v-model="endereco.logradouro"
                    type="text"
                    maxlength="255"
                    placeholder="Rua, avenida etc."
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                />
            </CampoCadastro>

            <CampoCadastro id="numero" class="md:col-span-2" rotulo="Número" :erro="erros.numero" v-slot="{ descricao, invalido }">
                <input
                    id="numero"
                    v-model="endereco.numero"
                    type="text"
                    maxlength="20"
                    :placeholder="endereco.sem_numero ? 'Sem número' : '123'"
                    :disabled="endereco.sem_numero"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                />
                <label :class="classeMarcacao">
                    <input id="sem_numero" v-model="endereco.sem_numero" type="checkbox" :class="classeCaixa" @change="marcarSemNumero" />
                    Sem número
                </label>
            </CampoCadastro>

            <CampoCadastro id="complemento" class="md:col-span-2" rotulo="Complemento" :erro="erros.complemento" v-slot="{ descricao, invalido }">
                <input
                    id="complemento"
                    v-model="endereco.complemento"
                    type="text"
                    maxlength="120"
                    placeholder="Sala, andar etc."
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                />
            </CampoCadastro>

            <CampoCadastro id="bairro" class="md:col-span-2" rotulo="Bairro" :erro="erros.bairro" v-slot="{ descricao, invalido }">
                <input
                    id="bairro"
                    v-model="endereco.bairro"
                    type="text"
                    maxlength="120"
                    placeholder="Nome do bairro"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                />
            </CampoCadastro>

            <CampoCadastro id="cidade" class="md:col-span-3" rotulo="Cidade" :erro="erros.cidade" v-slot="{ descricao, invalido }">
                <input
                    id="cidade"
                    v-model="endereco.cidade"
                    type="text"
                    maxlength="120"
                    placeholder="Nome da cidade"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                />
            </CampoCadastro>

            <CampoCadastro id="uf" class="md:col-span-3" rotulo="UF" :erro="erros.uf" v-slot="{ descricao, invalido }">
                <select id="uf" v-model="endereco.uf" :class="classeCampo" :aria-invalid="invalido || undefined" :aria-describedby="descricao" @change="emit('limparErro', 'uf')">
                    <option value="">Selecione o estado</option>
                    <option v-for="estado in estados" :key="estado.valor" :value="estado.valor">{{ estado.rotulo }}</option>
                </select>
            </CampoCadastro>
        </div>
    </SecaoCadastro>
</template>
