<script setup lang="ts">
import CampoCadastro from '../../CampoCadastro.vue';
import { OPCOES_SIM_NAO, classeCampo, classeDica, decimal } from '../listaEditavel';
import type { CotasEditavel, OpcaoFormulario } from '../../../types';

defineProps<{
    /** Erros do useForm: "cotas.<modalidade>.campo", "cotas.base" etc. */
    erros: Partial<Record<string, string>>;
    modalidadesCota: OpcaoFormulario[];
    basesCota: OpcaoFormulario[];
    alcancesCota: OpcaoFormulario[];
}>();

const emit = defineEmits<{ limparErros: [campos: string[]] }>();

/** Uma entrada em `modalidades` para cada opção de modalidadesCota (o formulário monta todas). */
const cotas = defineModel<CotasEditavel>({ required: true });

const limpar = (campo: string): void => emit('limparErros', [`cotas.${campo}`]);
</script>

<template>
    <div class="flex flex-col gap-6">
        <fieldset class="min-w-0">
            <legend class="text-[0.9375rem] font-semibold">Modalidades oferecidas</legend>
            <p :class="[classeDica, 'mt-1']">Percentual e quantidade (de vagas ou de recursos, conforme a base) só quando o edital informar.</p>

            <ul class="mt-3 flex flex-col gap-3">
                <li v-for="modalidade in modalidadesCota" :key="modalidade.valor" class="@container rounded-[16px] border border-hairline p-4">
                    <template v-if="cotas.modalidades[modalidade.valor]">
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 text-[0.9375rem] font-semibold">
                            <input
                                v-model="cotas.modalidades[modalidade.valor]!.ativa"
                                type="checkbox"
                                class="size-5 shrink-0 cursor-pointer accent-acao"
                                @change="limpar(`${modalidade.valor}.ativa`)"
                            />
                            {{ modalidade.rotulo }}
                        </label>

                        <div v-show="cotas.modalidades[modalidade.valor]!.ativa" class="mt-3 grid gap-x-6 gap-y-5 @lg:grid-cols-2">
                            <CampoCadastro
                                :id="`cota-${modalidade.valor}-percentual`"
                                rotulo="Percentual (%)"
                                :erro="erros[`cotas.${modalidade.valor}.percentual`]"
                                v-slot="{ descricao, invalido }"
                            >
                                <input
                                    :id="`cota-${modalidade.valor}-percentual`"
                                    :value="cotas.modalidades[modalidade.valor]!.percentual"
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    :class="[classeCampo, 'tabular-nums']"
                                    :aria-invalid="invalido || undefined"
                                    :aria-describedby="descricao"
                                    @input="
                                        cotas.modalidades[modalidade.valor]!.percentual = decimal($event, 3);
                                        limpar(`${modalidade.valor}.percentual`);
                                    "
                                />
                            </CampoCadastro>

                            <CampoCadastro
                                :id="`cota-${modalidade.valor}-quantidade`"
                                rotulo="Quantidade"
                                :erro="erros[`cotas.${modalidade.valor}.quantidade`]"
                                v-slot="{ descricao, invalido }"
                            >
                                <input
                                    :id="`cota-${modalidade.valor}-quantidade`"
                                    :value="cotas.modalidades[modalidade.valor]!.quantidade"
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    :class="[classeCampo, 'tabular-nums']"
                                    :aria-invalid="invalido || undefined"
                                    :aria-describedby="descricao"
                                    @input="
                                        cotas.modalidades[modalidade.valor]!.quantidade = decimal($event, 11);
                                        limpar(`${modalidade.valor}.quantidade`);
                                    "
                                />
                            </CampoCadastro>
                        </div>
                    </template>
                </li>
            </ul>
        </fieldset>

        <div class="grid gap-x-6 gap-y-5 md:grid-cols-2">
            <CampoCadastro id="cotas_base" rotulo="Base da reserva" :erro="erros['cotas.base']" v-slot="{ descricao, invalido }">
                <select id="cotas_base" v-model="cotas.base" :class="classeCampo" :aria-invalid="invalido || undefined" :aria-describedby="descricao" @change="limpar('base')">
                    <option value="">Não informada</option>
                    <option v-for="base in basesCota" :key="base.valor" :value="base.valor">{{ base.rotulo }}</option>
                </select>
            </CampoCadastro>

            <CampoCadastro id="cotas_alcance" rotulo="Alcance" :erro="erros['cotas.alcance']" v-slot="{ descricao, invalido }">
                <select
                    id="cotas_alcance"
                    v-model="cotas.alcance"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @change="limpar('alcance')"
                >
                    <option value="">Não informado</option>
                    <option v-for="alcance in alcancesCota" :key="alcance.valor" :value="alcance.valor">{{ alcance.rotulo }}</option>
                </select>
            </CampoCadastro>

            <CampoCadastro
                id="cotas_veda_negro_indigena"
                rotulo="Proíbe concorrer a negros e indígenas ao mesmo tempo"
                :erro="erros['cotas.veda_negro_indigena']"
                v-slot="{ descricao, invalido }"
            >
                <select
                    id="cotas_veda_negro_indigena"
                    v-model="cotas.veda_negro_indigena"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @change="limpar('veda_negro_indigena')"
                >
                    <option v-for="opcao in OPCOES_SIM_NAO" :key="String(opcao.valor)" :value="opcao.valor">{{ opcao.rotulo }}</option>
                </select>
            </CampoCadastro>

            <CampoCadastro id="cotas_heteroidentificacao" rotulo="Exige heteroidentificação" :erro="erros['cotas.heteroidentificacao']" v-slot="{ descricao, invalido }">
                <select
                    id="cotas_heteroidentificacao"
                    v-model="cotas.heteroidentificacao"
                    :class="classeCampo"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @change="limpar('heteroidentificacao')"
                >
                    <option v-for="opcao in OPCOES_SIM_NAO" :key="String(opcao.valor)" :value="opcao.valor">{{ opcao.rotulo }}</option>
                </select>
            </CampoCadastro>
        </div>
    </div>
</template>
