<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import type { PerguntaDaInscricao, RespostaDaInscricao, SecaoDaInscricao } from '../../types';
import AvisoCamposObrigatorios from '../AvisoCamposObrigatorios.vue';
import { focarPrimeiroErro } from '../focarPrimeiroErro';
import PerguntaDaInscricaoCampo from './PerguntaDaInscricao.vue';
import { CHAVE_NAO, CHAVE_SIM, chavesDaPergunta, escolheuOutro, perguntasVisiveis } from './questionario';
import type { RespostaEditavel } from './questionario';

const props = defineProps<{
    secao: SecaoDaInscricao;
    /** Todas as seções que valem: a condição pode depender de pergunta de outra seção. */
    secoes: SecaoDaInscricao[];
    /** Respostas gravadas, pelo id da pergunta. */
    respostas: Record<number, RespostaDaInscricao>;
    inscricaoId: number;
    /** Endereço do passo anterior; nulo no primeiro. */
    voltarPara: string | null;
    /** Outra requisição da tela em andamento (ela seria cancelada). */
    ocupado: boolean;
}>();

const idFormulario = `formulario-secao-${props.secao.id}`;

// Sim/não sem resposta começa em "Não" (o interruptor desligado), e é isso que vale para as condições na tela.
const respostaInicial = (pergunta: PerguntaDaInscricao): RespostaEditavel => {
    const salva = props.respostas[pergunta.id];
    return {
        texto: salva?.texto ?? '',
        opcoes: salva?.opcoes.length ? [...salva.opcoes] : pergunta.tipo === 'sim_nao' ? [CHAVE_NAO] : [],
        outro: salva?.outro ?? '',
    };
};

const formulario = useForm<{ respostas: Record<string, RespostaEditavel> }>({
    respostas: Object.fromEntries(props.secao.perguntas.map((pergunta) => [String(pergunta.id), respostaInicial(pergunta)])),
});

// A visibilidade vem das respostas gravadas das outras seções e do que está na tela nesta, recalculada a cada mudança.
const visiveis = computed<Set<number>>(() => {
    const atuais: Record<number, RespostaDaInscricao> = { ...props.respostas };
    for (const pergunta of props.secao.perguntas) {
        const editada = formulario.respostas[String(pergunta.id)];
        atuais[pergunta.id] = { texto: editada.texto || null, opcoes: editada.opcoes, outro: editada.outro || null };
    }
    return perguntasVisiveis(props.secoes, atuais);
});

const perguntasNaTela = computed<PerguntaDaInscricao[]>(() => props.secao.perguntas.filter((pergunta) => visiveis.value.has(pergunta.id)));
const temObrigatoria = computed<boolean>(() => perguntasNaTela.value.some((pergunta) => pergunta.obrigatoria));

// "12,5" vira "12.5", que o servidor entende; o resto vai como foi digitado.
const numeroParaEnvio = (texto: string): string => (/^-?\d+,\d+$/.test(texto.trim()) ? texto.trim().replace(',', '.') : texto);

// O que vai para o servidor de cada pergunta, no formato que SalvarRespostasRequest valida.
const paraEnvio = (pergunta: PerguntaDaInscricao, resposta: RespostaEditavel): Record<string, unknown> => {
    const chaves = chavesDaPergunta(pergunta);
    const opcoes = resposta.opcoes.filter((opcao) => chaves.includes(opcao));

    switch (pergunta.tipo) {
        case 'sim_nao':
            return { opcoes: [opcoes.includes(CHAVE_SIM) ? CHAVE_SIM : CHAVE_NAO] };
        case 'declaracao':
            return { opcoes };
        case 'escolha_unica':
        case 'multipla_escolha':
            return escolheuOutro(pergunta, opcoes) ? { opcoes, outro: resposta.outro } : { opcoes };
        case 'numero':
            return { texto: numeroParaEnvio(resposta.texto) };
        default:
            return { texto: resposta.texto };
    }
};

// Os erros chegam como respostas.<id>, respostas.<id>.texto, respostas.<id>.opcoes.<n>...: todos vão para a pergunta,
// menos o do "Qual?", que fica no campo dele.
const erros = computed<Record<string, string>>(() => formulario.errors as Record<string, string>);
const chavesDeErro = (pergunta: PerguntaDaInscricao): string[] =>
    Object.keys(erros.value).filter((chave) => chave === `respostas.${pergunta.id}` || chave.startsWith(`respostas.${pergunta.id}.`));
const erroDaPergunta = (pergunta: PerguntaDaInscricao): string | undefined => {
    const chave = chavesDeErro(pergunta).find((candidata) => candidata !== `respostas.${pergunta.id}.outro`);
    return chave === undefined ? undefined : erros.value[chave];
};
const erroDoOutro = (pergunta: PerguntaDaInscricao): string | undefined => erros.value[`respostas.${pergunta.id}.outro`];
const limparErros = (pergunta: PerguntaDaInscricao): void => {
    const chaves = chavesDeErro(pergunta);
    if (chaves.length > 0) {
        formulario.clearErrors(...(chaves as never[]));
    }
};

// Só as perguntas visíveis desta seção; as escondidas o servidor descarta. Com sucesso, o servidor leva ao passo seguinte.
const avancar = (): void => {
    formulario
        .transform((dados) => ({
            respostas: Object.fromEntries(perguntasNaTela.value.map((pergunta) => [String(pergunta.id), paraEnvio(pergunta, dados.respostas[String(pergunta.id)])])),
        }))
        // Caminho da rota portal.inscricoes.secoes.update (o front não tem helper de rotas nomeadas).
        .put(`/portal/inscricoes/${props.inscricaoId}/secoes/${props.secao.id}`, {
            preserveScroll: true,
            onError: () => focarPrimeiroErro(`#${idFormulario}`),
        });
};

const classeBotao =
    'pressionavel inline-flex min-h-12 items-center justify-center rounded-pill px-8 text-center text-[0.8125rem] font-bold tracking-[0.12em] uppercase no-underline disabled:cursor-not-allowed disabled:opacity-70';
</script>

<template>
    <form :id="idFormulario" novalidate class="flex flex-col gap-6" @submit.prevent="avancar">
        <p v-if="secao.descricao" class="max-w-3xl leading-[1.6] whitespace-pre-line text-tinta-suave">{{ secao.descricao }}</p>
        <AvisoCamposObrigatorios v-if="temObrigatoria" />

        <PerguntaDaInscricaoCampo
            v-for="pergunta in perguntasNaTela"
            :key="pergunta.id"
            v-model="formulario.respostas[String(pergunta.id)]"
            :pergunta="pergunta"
            :erro="erroDaPergunta(pergunta)"
            :erro-outro="erroDoOutro(pergunta)"
            @limpar-erro="limparErros(pergunta)"
        />

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-hairline pt-6">
            <Link v-if="voltarPara" :href="voltarPara" preserve-state :class="[classeBotao, 'border border-tinta-suave/70 bg-superficie text-home-tinta hover:border-acao hover:text-acao']">
                Voltar
            </Link>
            <button type="submit" :disabled="formulario.processing || ocupado" :class="[classeBotao, 'ml-auto bg-acao text-sobre-acao hover:bg-acao-escuro']">
                {{ formulario.processing ? 'Salvando…' : 'Avançar' }}
            </button>
        </div>
    </form>
</template>
