<script setup lang="ts">
import { computed } from 'vue';
import CampoCadastro from '../CampoCadastro.vue';
import { formatarCpf, formatarTelefone } from './mascaras';
import type { OpcaoCadastro, RepresentanteEdicao } from '../../types';

// Um cartão "Representante N de M". Os campos são do objeto da lista do useForm (v-model), e os erros
// chegam com as chaves do Laravel: representantes.N.campo.
const props = defineProps<{
    indice: number;
    total: number;
    erros: Partial<Record<string, string>>;
    tiposDocumento: OpcaoCadastro[];
    origens: OpcaoCadastro[];
    podeRemover: boolean;
}>();

const emit = defineEmits<{ remover: []; limparErro: [chave: string] }>();

const representante = defineModel<RepresentanteEdicao>({ required: true });

type Campo = keyof RepresentanteEdicao;

const prefixo = computed(() => `representante-${props.indice}`);
const idCampo = (campo: Campo): string => `${prefixo.value}-${campo}`;
const erro = (campo: Campo): string | undefined => props.erros[`representantes.${props.indice}.${campo}`];
const limpar = (campo: Campo): void => emit('limparErro', `representantes.${props.indice}.${campo}`);

const mascarar = (evento: Event, campo: 'cpf' | 'telefone', formatar: (valor: string) => string): void => {
    const entrada = evento.target as HTMLInputElement;
    entrada.value = formatar(entrada.value);
    representante.value[campo] = entrada.value;
    limpar(campo);
};

// Sem "Outro", a descrição não faz sentido e não vai para o servidor.
const trocarOrigem = (): void => {
    if (representante.value.origem !== 'outro') representante.value.origem_descricao = '';
    limpar('origem');
    limpar('origem_descricao');
};

const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave aria-invalid:border-erro-texto aria-invalid:ring-1 aria-invalid:ring-erro-texto';
</script>

<template>
    <!-- tabindex="-1": recebe o foco quando o cartão seguinte é removido. -->
    <section
        :id="prefixo"
        tabindex="-1"
        :aria-labelledby="`${prefixo}-titulo`"
        class="rounded-[18px] border border-hairline bg-parchment p-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acao md:p-6"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 :id="`${prefixo}-titulo`" class="font-barlow text-[1.25rem] leading-none font-extrabold tracking-[0.01em] uppercase">
                Representante {{ indice + 1 }} de {{ total }}
            </h3>
            <button
                type="button"
                :disabled="!podeRemover"
                class="pressionavel inline-flex min-h-11 items-center gap-1.5 rounded-pill border border-tinta-suave/70 bg-superficie px-4 text-[0.75rem] font-bold tracking-[0.1em] text-tinta-suave uppercase hover:border-erro-texto hover:text-erro-texto disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-tinta-suave/70 disabled:hover:text-tinta-suave"
                @click="emit('remover')"
            >
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
                </svg>
                Remover<span class="sr-only"> representante {{ indice + 1 }}</span>
            </button>
        </div>

        <div class="mt-5 grid gap-x-6 gap-y-5 md:grid-cols-2">
            <CampoCadastro :id="idCampo('cpf')" rotulo="CPF" obrigatorio :erro="erro('cpf')" v-slot="{ descricao, invalido }">
                <input
                    :id="idCampo('cpf')"
                    :value="representante.cpf"
                    type="text"
                    inputmode="numeric"
                    maxlength="14"
                    required
                    placeholder="000.000.000-00"
                    :class="[classeCampo, 'tabular-nums']"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="mascarar($event, 'cpf', formatarCpf)"
                />
            </CampoCadastro>

            <CampoCadastro :id="idCampo('nome')" rotulo="Nome" obrigatorio :erro="erro('nome')" v-slot="{ descricao, invalido }">
                <input
                    :id="idCampo('nome')"
                    v-model="representante.nome"
                    type="text"
                    maxlength="255"
                    required
                    placeholder="Nome completo"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="limpar('nome')"
                />
            </CampoCadastro>

            <CampoCadastro :id="idCampo('tipo_documento')" rotulo="Tipo de documento de identidade" :erro="erro('tipo_documento')" v-slot="{ descricao, invalido }">
                <select
                    :id="idCampo('tipo_documento')"
                    v-model="representante.tipo_documento"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @change="limpar('tipo_documento')"
                >
                    <option value="">Selecione…</option>
                    <option v-for="opcao in tiposDocumento" :key="opcao.valor" :value="opcao.valor">{{ opcao.rotulo }}</option>
                </select>
            </CampoCadastro>

            <CampoCadastro :id="idCampo('numero_documento')" rotulo="Número do documento" :erro="erro('numero_documento')" v-slot="{ descricao, invalido }">
                <input
                    :id="idCampo('numero_documento')"
                    v-model="representante.numero_documento"
                    type="text"
                    maxlength="30"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="limpar('numero_documento')"
                />
            </CampoCadastro>

            <CampoCadastro :id="idCampo('cargo')" rotulo="Cargo" :erro="erro('cargo')" v-slot="{ descricao, invalido }">
                <input
                    :id="idCampo('cargo')"
                    v-model="representante.cargo"
                    type="text"
                    maxlength="120"
                    placeholder="Ex.: Presidente, sócio-administrador"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="limpar('cargo')"
                />
            </CampoCadastro>

            <CampoCadastro :id="idCampo('telefone')" rotulo="Telefone" :erro="erro('telefone')" v-slot="{ descricao, invalido }">
                <input
                    :id="idCampo('telefone')"
                    :value="representante.telefone"
                    type="tel"
                    inputmode="numeric"
                    maxlength="15"
                    placeholder="(00) 00000-0000"
                    :class="[classeCampo, 'tabular-nums']"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="mascarar($event, 'telefone', formatarTelefone)"
                />
            </CampoCadastro>

            <CampoCadastro :id="idCampo('email')" rotulo="E-mail" :erro="erro('email')" v-slot="{ descricao, invalido }">
                <input
                    :id="idCampo('email')"
                    v-model="representante.email"
                    type="email"
                    maxlength="255"
                    placeholder="nome@exemplo.com"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="limpar('email')"
                />
            </CampoCadastro>

            <CampoCadastro :id="idCampo('origem')" rotulo="Origem da representação" :erro="erro('origem')" v-slot="{ descricao, invalido }">
                <select
                    :id="idCampo('origem')"
                    v-model="representante.origem"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @change="trocarOrigem"
                >
                    <option value="">Selecione…</option>
                    <option v-for="opcao in origens" :key="opcao.valor" :value="opcao.valor">{{ opcao.rotulo }}</option>
                </select>
            </CampoCadastro>

            <CampoCadastro
                v-if="representante.origem === 'outro'"
                :id="idCampo('origem_descricao')"
                class="md:col-span-2"
                rotulo="Descreva a origem"
                obrigatorio
                :erro="erro('origem_descricao')"
                v-slot="{ descricao, invalido }"
            >
                <input
                    :id="idCampo('origem_descricao')"
                    v-model="representante.origem_descricao"
                    type="text"
                    maxlength="255"
                    required
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @input="limpar('origem_descricao')"
                />
            </CampoCadastro>
        </div>
    </section>
</template>
