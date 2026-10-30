<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import type { DocumentoDaInscricao, SituacaoDoDocumento } from '../../types';
import CampoArquivo from '../CampoArquivo.vue';
import CampoCadastro from '../CampoCadastro.vue';
import ConfirmarExclusao from '../Retaguarda/ConfirmarExclusao.vue';

const props = defineProps<{
    documento: DocumentoDaInscricao;
    inscricaoId: number;
    /** Situação do arquivo enviado, que a consulta periódica da tela atualiza; nulo = nada enviado. */
    situacao: SituacaoDoDocumento | null;
    /** Rascunho no período: só então dá para enviar, trocar, remover e conferir de novo. */
    podeEditar: boolean;
    /** Outra requisição da tela em andamento: enquanto durar, nada aqui dispara uma nova (ela cancelaria a primeira). */
    ocupado: boolean;
}>();

// Mesmos tipos, limite e mensagem do servidor (Inscricao::TIPOS_DE_ARQUIVO, InscricaoDocumentoController::ERRO_ARQUIVO).
const TAMANHO_MAXIMO_MB = 10;
const ERRO_ARQUIVO = `Envie um PDF ou uma imagem (JPG ou PNG) de até ${TAMANHO_MAXIMO_MB} MB.`;
const TIPOS_ACEITOS = ['application/pdf', 'image/jpeg', 'image/png'];
const EXTENSOES_ACEITAS = ['.pdf', '.jpg', '.jpeg', '.png'];
// Mesma mensagem do servidor (InscricaoDocumentoController::NAO_CONFERIDO), caso o motivo venha vazio.
const NAO_CONFERIDO = 'Não foi possível conferir o documento agora.';
const ERRO_ENVIO_INTERROMPIDO = 'O envio foi interrompido; envie de novo.';

type Tom = 'andamento' | 'ok' | 'erro' | 'aviso';

const idCampo = `documento-${props.documento.id}`;
// Caminho das rotas portal.inscricoes.documentos.* (o front não tem helper de rotas nomeadas).
const urlDoDocumento = `/portal/inscricoes/${props.inscricaoId}/documentos/${props.documento.id}`;

const formulario = useForm<{ arquivo: File | null }>({ arquivo: null });
const reconferindo = ref<boolean>(false);

const limparCampo = (): void => {
    const entrada = document.getElementById(idCampo) as HTMLInputElement | null;
    if (entrada) {
        entrada.value = '';
    }
};

const focarCampo = (): void => {
    void nextTick(() => document.getElementById(idCampo)?.focus());
};

// O arquivo que não é PDF nem imagem JPG ou PNG, ou passa de 10 MB, nem sai do navegador; o servidor confere de novo.
const escolher = (arquivo: File | null): void => {
    formulario.clearErrors();
    if (arquivo === null) {
        return;
    }

    const nome = arquivo.name.toLowerCase();
    const aceito = TIPOS_ACEITOS.includes(arquivo.type) || EXTENSOES_ACEITAS.some((extensao) => nome.endsWith(extensao));
    if (!aceito || arquivo.size > TAMANHO_MAXIMO_MB * 1024 * 1024) {
        formulario.setError('arquivo', ERRO_ARQUIVO);
        limparCampo();
        focarCampo();
        return;
    }

    formulario.arquivo = arquivo;
    formulario.post(urlDoDocumento, {
        forceFormData: true,
        preserveScroll: true,
        // Outra visita (sair da tela, por exemplo) cancela o envio em andamento; a pessoa precisa saber que o arquivo não foi.
        onCancel: () => formulario.setError('arquivo', ERRO_ENVIO_INTERROMPIDO),
        // O foco só volta ao campo no fim: durante a requisição ele fica desabilitado.
        onFinish: () => {
            formulario.reset('arquivo');
            limparCampo();
            if (formulario.hasErrors) {
                focarCampo();
            }
        },
    });
};

const conferirDeNovo = (): void => {
    router.post(
        `${urlDoDocumento}/conferir`,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                reconferindo.value = true;
            },
            onFinish: () => {
                reconferindo.value = false;
            },
        },
    );
};

// O motivo da IA vem com ponto final; a frase em volta já traz o seu.
const semPontoFinal = (texto: string): string => texto.replace(/[.\s]+$/u, '');

interface Estado {
    tom: Tom;
    texto: string;
    /** Oferece "Conferir de novo". */
    reconferivel: boolean;
}

// O que a pessoa lê sobre o documento: depende da situação e do modo de checagem do edital.
const estado = computed<Estado | null>(() => {
    if (formulario.processing) {
        const progresso = formulario.progress?.percentage;
        return { tom: 'andamento', texto: progresso === undefined ? 'Enviando…' : `Enviando… ${progresso}%`, reconferivel: false };
    }

    const atual = props.situacao;
    if (atual === null) {
        return null;
    }

    const motivo = atual.motivo ?? '';
    const checagem = props.documento.checagem;
    const falhou = atual.situacao === 'reprovado' || atual.situacao === 'nao_conferido';

    if (falhou && checagem === 'rigorosa') {
        return {
            tom: 'erro',
            texto: atual.situacao === 'reprovado' ? `Documento recusado: ${semPontoFinal(motivo)}` : motivo,
            reconferivel: props.podeEditar,
        };
    }
    if (falhou && checagem === 'aceita_sem_validar') {
        return {
            tom: 'aviso',
            texto:
                atual.situacao === 'reprovado'
                    ? `Atenção: ${semPontoFinal(motivo)}. Você pode seguir, e a equipe vai conferir.`
                    : `${semPontoFinal(motivo || NAO_CONFERIDO)}. A equipe vai conferir.`,
            // Aceito mesmo assim, mas a pessoa pode pedir outra conferência (a IA pode ter voltado, o arquivo pode ter sido trocado).
            reconferivel: props.podeEditar,
        };
    }

    switch (atual.situacao) {
        case 'conferindo':
            return { tom: 'andamento', texto: 'Conferindo…', reconferivel: false };
        case 'aprovado':
            return { tom: 'ok', texto: 'Conferido', reconferivel: false };
        default:
            return { tom: 'ok', texto: 'Recebido', reconferivel: false };
    }
});

const classeDoEstado: Record<Tom, string> = {
    andamento: 'text-acao',
    ok: 'text-texto-verde',
    erro: 'rounded-[12px] border border-erro-borda bg-erro-fundo px-4 py-3 text-erro-texto',
    aviso: 'rounded-[12px] border border-aviso-texto/40 bg-aviso-fundo px-4 py-3 text-aviso-texto',
};

// O campo que está enviando já mostra "Enviando…"; nos outros, a dica explica por que estão travados.
const aguardando = computed<boolean>(() => props.ocupado && !formulario.processing);

const classeAcao = 'pressionavel inline-flex min-h-11 items-center gap-1.5 rounded-pill px-3 text-[0.8125rem] font-bold text-acao hover:bg-nevoa disabled:cursor-wait disabled:opacity-70';
</script>

<template>
    <article :aria-labelledby="`${idCampo}-nome`" class="flex flex-col gap-3 rounded-[16px] border border-hairline p-4 md:p-5">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <h3 :id="`${idCampo}-nome`" class="min-w-0 font-semibold break-words">{{ documento.nome }}</h3>
            <span
                class="inline-flex shrink-0 items-center rounded-pill px-2 py-0.5 text-[0.6875rem] font-bold tracking-[0.08em] uppercase"
                :class="documento.obrigatorio ? 'bg-nevoa text-acao' : 'border border-hairline text-tinta-suave'"
            >
                {{ documento.obrigatorio ? 'Obrigatório' : 'Opcional' }}
            </span>
        </div>

        <p v-if="documento.orientacao" class="text-[0.875rem] leading-[1.5] text-tinta-suave">{{ documento.orientacao }}</p>
        <p v-if="documento.modeloUrl">
            <a :href="documento.modeloUrl" class="inline-flex min-h-11 items-center text-[0.875rem] font-semibold text-acao">
                Baixar modelo<span v-if="documento.modeloNome" class="sr-only"> ({{ documento.modeloNome }})</span>
            </a>
        </p>

        <CampoCadastro
            v-if="podeEditar"
            :id="idCampo"
            :rotulo="documento.enviado ? `Trocar o arquivo de ${documento.nome}` : `Arquivo de ${documento.nome}`"
            :erro="formulario.errors.arquivo"
            v-slot="{ descricao, invalido }"
        >
            <CampoArquivo
                :id="idCampo"
                :accept="[...TIPOS_ACEITOS, ...EXTENSOES_ACEITAS].join(',')"
                :dica="aguardando ? 'Aguarde a ação em andamento terminar para enviar um arquivo' : `PDF ou imagem (JPG ou PNG), até ${TAMANHO_MAXIMO_MB} MB`"
                :arquivo="null"
                :enviado="documento.enviado !== null"
                :descricao="descricao"
                :invalido="invalido"
                :desabilitado="ocupado"
                @escolher="escolher"
            />
        </CampoCadastro>

        <div v-if="documento.enviado" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[0.875rem]">
            <span class="text-tinta-suave">Arquivo enviado:</span>
            <a :href="documento.enviado.url" class="inline-flex min-h-11 items-center font-semibold break-all text-acao">
                {{ documento.enviado.nome }}<span class="sr-only"> (baixar)</span>
            </a>
            <ConfirmarExclusao
                v-if="podeEditar && !documento.obrigatorio"
                :url="urlDoDocumento"
                :nome="`o arquivo de ${documento.nome}`"
                acao="Remover"
                :pergunta="`Remover o arquivo de “${documento.nome}”?`"
                aviso="O arquivo será apagado da sua inscrição."
                acao-em-andamento="Removendo…"
                :desabilitado="ocupado"
            />
        </div>
        <p v-else class="text-[0.875rem] text-tinta-suave">Nenhum arquivo enviado.</p>

        <!-- A região fica sempre na página: o leitor de tela anuncia o que entra nela (conferindo, conferido, recusado). -->
        <div aria-live="polite" aria-atomic="true">
            <p v-if="estado" class="flex items-start gap-2 text-[0.9375rem] font-semibold" :class="classeDoEstado[estado.tom]">
                <svg
                    v-if="estado.tom === 'andamento'"
                    aria-hidden="true"
                    viewBox="0 0 24 24"
                    class="mt-0.5 size-5 shrink-0 animate-spin motion-reduce:animate-none"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                >
                    <path d="M12 3a9 9 0 1 0 9 9" />
                </svg>
                <svg v-else-if="estado.tom === 'ok'" aria-hidden="true" viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m5 12 5 5 9-10" />
                </svg>
                <svg v-else-if="estado.tom === 'erro'" aria-hidden="true" viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="12" cy="12" r="9" />
                    <path d="m15 9-6 6M9 9l6 6" />
                </svg>
                <svg v-else aria-hidden="true" viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 4 2.5 20h19z" />
                    <path d="M12 10v4M12 17.5h.01" />
                </svg>
                <span class="min-w-0 break-words">{{ estado.texto }}</span>
            </p>
        </div>
        <div v-if="estado?.reconferivel" class="flex">
            <button type="button" :disabled="reconferindo || ocupado" :class="classeAcao" @click="conferirDeNovo">
                {{ reconferindo ? 'Pedindo…' : 'Conferir de novo' }}<span class="sr-only"> {{ documento.nome }}</span>
            </button>
        </div>
    </article>
</template>
