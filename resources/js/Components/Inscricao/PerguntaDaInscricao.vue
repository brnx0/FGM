<script setup lang="ts">
import { computed } from 'vue';
import type { PerguntaDaInscricao } from '../../types';
import CampoCadastro from '../CampoCadastro.vue';
import Interruptor from '../Interruptor.vue';
import { CHAVE_DECLARACAO, CHAVE_NAO, CHAVE_SIM, caracteres, escolheuOutro } from './questionario';
import type { RespostaEditavel } from './questionario';

const props = defineProps<{
    pergunta: PerguntaDaInscricao;
    /** Erro de respostas.<id> (e das chaves dentro dela, menos a do "Qual?"). */
    erro?: string;
    /** Erro de respostas.<id>.outro. */
    erroOutro?: string;
}>();

const resposta = defineModel<RespostaEditavel>({ required: true });

const emit = defineEmits<{ limparErro: [] }>();

const id = computed<string>(() => `pergunta-${props.pergunta.id}`);
const idContador = computed<string>(() => `${id.value}-contador`);
const ajuda = computed<string | undefined>(() => props.pergunta.ajuda ?? undefined);

const alterar = (mudanca: Partial<RespostaEditavel>): void => {
    resposta.value = { ...resposta.value, ...mudanca };
    emit('limparErro');
};

// Textos: o contador mostra o que passa do limite, sem cortar o que se digita (o servidor recusa).
const usados = computed<number>(() => caracteres(resposta.value.texto));
const passouDoLimite = computed<boolean>(() => props.pergunta.limite !== null && usados.value > props.pergunta.limite);
const numero = (valor: number): string => valor.toLocaleString('pt-BR');
const descricaoDoTexto = (descricao: string | undefined): string | undefined =>
    [descricao, props.pergunta.limite !== null ? idContador.value : null].filter(Boolean).join(' ') || undefined;

const temOutro = computed<boolean>(() => escolheuOutro(props.pergunta, resposta.value.opcoes));

const escolher = (chave: string): void => alterar({ opcoes: [chave] });

const alternar = (chave: string, ligado: boolean): void => {
    const opcoes = resposta.value.opcoes.filter((opcao) => opcao !== chave);
    alterar({ opcoes: ligado ? [...opcoes, chave] : opcoes });
};

// Sim/não: desligado é "Não"; a tela sempre manda uma das duas chaves.
const respostaSim = computed<boolean>({
    get: () => resposta.value.opcoes.includes(CHAVE_SIM),
    set: (ligado) => alterar({ opcoes: [ligado ? CHAVE_SIM : CHAVE_NAO] }),
});
const ajudaSimNao = computed<string>(() => [props.pergunta.ajuda, 'Ligado é "Sim"; desligado é "Não".'].filter(Boolean).join(' '));

const declarado = computed<boolean>({
    get: () => resposta.value.opcoes.includes(CHAVE_DECLARACAO),
    set: (ligado) => alterar({ opcoes: ligado ? [CHAVE_DECLARACAO] : [] }),
});

const classeCampo = 'w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave';
const classeGrupo = 'flex flex-col gap-1 rounded-[12px] border border-hairline p-2';
</script>

<template>
    <div class="flex flex-col gap-3">
        <CampoCadastro
            v-if="pergunta.tipo === 'texto_curto' || pergunta.tipo === 'texto_longo'"
            :id="id"
            :rotulo="pergunta.enunciado"
            :obrigatorio="pergunta.obrigatoria"
            :ajuda="ajuda"
            :erro="erro"
            v-slot="{ descricao, invalido }"
        >
            <input
                v-if="pergunta.tipo === 'texto_curto'"
                :id="id"
                type="text"
                :value="resposta.texto"
                :required="pergunta.obrigatoria"
                :class="[classeCampo, 'h-12']"
                :aria-invalid="invalido || undefined"
                :aria-describedby="descricaoDoTexto(descricao)"
                @input="alterar({ texto: ($event.target as HTMLInputElement).value })"
            />
            <textarea
                v-else
                :id="id"
                rows="6"
                :value="resposta.texto"
                :required="pergunta.obrigatoria"
                :class="[classeCampo, 'py-3 leading-[1.6]']"
                :aria-invalid="invalido || undefined"
                :aria-describedby="descricaoDoTexto(descricao)"
                @input="alterar({ texto: ($event.target as HTMLTextAreaElement).value })"
            ></textarea>
            <p
                v-if="pergunta.limite !== null"
                :id="idContador"
                class="text-[0.8125rem] tabular-nums"
                :class="passouDoLimite ? 'font-semibold text-invalido-texto' : 'text-tinta-suave'"
            >
                {{ numero(usados) }} de {{ numero(pergunta.limite) }} caracteres<template v-if="passouDoLimite"> (passou do limite)</template>
            </p>
        </CampoCadastro>

        <CampoCadastro
            v-else-if="pergunta.tipo === 'data' || pergunta.tipo === 'numero'"
            :id="id"
            :rotulo="pergunta.enunciado"
            :obrigatorio="pergunta.obrigatoria"
            :ajuda="ajuda"
            :erro="erro"
            v-slot="{ descricao, invalido }"
        >
            <input
                :id="id"
                :type="pergunta.tipo === 'data' ? 'date' : 'text'"
                :inputmode="pergunta.tipo === 'numero' ? 'decimal' : undefined"
                :value="resposta.texto"
                :required="pergunta.obrigatoria"
                :class="[classeCampo, 'h-12 md:max-w-xs']"
                :aria-invalid="invalido || undefined"
                :aria-describedby="descricao"
                @input="alterar({ texto: ($event.target as HTMLInputElement).value })"
            />
        </CampoCadastro>

        <CampoCadastro
            v-else-if="pergunta.tipo === 'escolha_unica'"
            :id="id"
            grupo
            :rotulo="pergunta.enunciado"
            :obrigatorio="pergunta.obrigatoria"
            :ajuda="ajuda"
            :erro="erro"
            v-slot="{ invalido }"
        >
            <div :class="[classeGrupo, { 'campo-invalido': invalido }]">
                <label
                    v-for="opcao in pergunta.opcoes"
                    :key="opcao.chave"
                    class="flex min-h-11 cursor-pointer items-center gap-3 rounded-[8px] px-2 py-1.5 hover:bg-nevoa"
                >
                    <input
                        type="radio"
                        :name="id"
                        :value="opcao.chave"
                        :checked="resposta.opcoes.includes(opcao.chave)"
                        :required="pergunta.obrigatoria"
                        :aria-invalid="invalido || undefined"
                        class="size-5 shrink-0 cursor-pointer accent-acao"
                        @change="escolher(opcao.chave)"
                    />
                    <span class="min-w-0 break-words">{{ opcao.texto }}</span>
                </label>
            </div>
            <!-- Opcional: dá para voltar a ficar sem resposta (o rádio sozinho não desmarca). -->
            <button
                v-if="!pergunta.obrigatoria && resposta.opcoes.length > 0"
                type="button"
                class="inline-flex min-h-11 items-center self-start text-[0.875rem] font-semibold text-acao underline hover:text-acao-escuro"
                @click="alterar({ opcoes: [], outro: '' })"
            >
                Limpar resposta
            </button>
        </CampoCadastro>

        <CampoCadastro
            v-else-if="pergunta.tipo === 'multipla_escolha'"
            :id="id"
            grupo
            :rotulo="pergunta.enunciado"
            :obrigatorio="pergunta.obrigatoria"
            :ajuda="ajuda ?? 'Marque todas as que se aplicam.'"
            :erro="erro"
            v-slot="{ descricao, invalido }"
        >
            <div :class="[classeGrupo, 'px-4', { 'campo-invalido': invalido }]">
                <Interruptor
                    v-for="opcao in pergunta.opcoes"
                    :id="`${id}-${opcao.chave}`"
                    :key="opcao.chave"
                    :model-value="resposta.opcoes.includes(opcao.chave)"
                    :rotulo="opcao.texto"
                    variante="linha"
                    :invalido="invalido"
                    :descricao="invalido ? descricao : undefined"
                    @update:model-value="(ligado: boolean) => alternar(opcao.chave, ligado)"
                />
            </div>
        </CampoCadastro>

        <Interruptor
            v-else-if="pergunta.tipo === 'sim_nao'"
            :id="id"
            v-model="respostaSim"
            :rotulo="pergunta.enunciado"
            :ajuda="ajudaSimNao"
            :obrigatorio="pergunta.obrigatoria"
            :erro="erro"
        />

        <Interruptor
            v-else-if="pergunta.tipo === 'declaracao'"
            :id="id"
            v-model="declarado"
            :rotulo="pergunta.enunciado"
            :ajuda="ajuda"
            :obrigatorio="pergunta.obrigatoria"
            :erro="erro"
        >
            <span class="font-normal whitespace-pre-line">{{ pergunta.enunciado }}</span>
        </Interruptor>

        <CampoCadastro
            v-if="temOutro"
            :id="`${id}-outro`"
            rotulo="Qual?"
            :obrigatorio="pergunta.obrigatoria"
            :erro="erroOutro"
            v-slot="{ descricao, invalido }"
        >
            <input
                :id="`${id}-outro`"
                type="text"
                :value="resposta.outro"
                :required="pergunta.obrigatoria"
                :class="[classeCampo, 'h-12']"
                :aria-invalid="invalido || undefined"
                :aria-describedby="descricao"
                @input="alterar({ outro: ($event.target as HTMLInputElement).value })"
            />
        </CampoCadastro>
    </div>
</template>
