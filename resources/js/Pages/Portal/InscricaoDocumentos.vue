<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AvisoCamposObrigatorios from '../../Components/AvisoCamposObrigatorios.vue';
import FaixaPortal from '../../Components/FaixaPortal.vue';
import { focarPrimeiroErro } from '../../Components/focarPrimeiroErro';
import DocumentoExigido from '../../Components/Inscricao/DocumentoExigido.vue';
import EscolhasDaInscricao from '../../Components/Inscricao/EscolhasDaInscricao.vue';
import SecaoCadastro from '../../Components/SecaoCadastro.vue';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';
import type { DocumentoDaInscricao, PortalInscricaoDocumentosProps, SituacaoDaInscricao, SituacaoDoDocumento } from '../../types';

defineOptions({ layout: PortalProponenteLayout });

const props = defineProps<PortalInscricaoDocumentosProps>();

// Caminho das rotas portal.inscricoes.* (o front não tem helper de rotas nomeadas).
const BASE = `/portal/inscricoes/${props.inscricao.id}`;
const INTERVALO_CONSULTA_MS = 5_000;
const ORDEM_DAS_ETAPAS = ['inscricao', 'validacao_cotas', 'habilitacao'];

const rotulosDasCotas = computed(() => props.cotas.filter((cota) => props.inscricao.cotas.includes(cota.valor)).map((cota) => cota.rotulo));

// Data e hora do envio, separadas para a frase ("12/11/2026 15:30").
const envio = computed(() => {
    const [data, hora] = (props.inscricao.finalizadaEm ?? '').split(' ');
    return { data, hora };
});

// O Inertia faz uma requisição comum por vez: a nova cancela a que ainda está em andamento (um envio de arquivo, por exemplo)
// sem avisar. Enquanto houver uma, os controles que disparam outra ficam desabilitados. A consulta periódica usa
// fetch e as requisições assíncronas não interrompem ninguém, então nenhuma das duas conta.
const requisicoesEmAndamento = ref<number>(0);
const ocupado = computed<boolean>(() => requisicoesEmAndamento.value > 0);
const pararDeOuvirInicio = router.on('start', (evento) => {
    if (!evento.detail.visit.async) {
        requisicoesEmAndamento.value += 1;
    }
});
const pararDeOuvirFim = router.on('finish', (evento) => {
    if (!evento.detail.visit.async) {
        requisicoesEmAndamento.value = Math.max(0, requisicoesEmAndamento.value - 1);
    }
});
onBeforeUnmount(() => {
    pararDeOuvirInicio();
    pararDeOuvirFim();
});

// Escolhas do rascunho.
const formulario = useForm<{ cadastro: string; categoria: number | null; cotas: string[] }>({
    cadastro: props.inscricao.cadastroChave,
    categoria: props.inscricao.categoriaId,
    cotas: [...props.inscricao.cotas],
});

const salvarEscolhas = (): void => {
    formulario.put(BASE, {
        preserveScroll: true,
        onSuccess: () => formulario.defaults(),
        onError: () => focarPrimeiroErro('#formulario-escolhas'),
    });
};

// Situação de cada documento enviado e o que falta para finalizar: cópia local, que a consulta periódica atualiza
// sem recarregar a página. Cada recarga das props (enviar, remover, trocar escolhas) recomeça a cópia.
const situacoes = ref<Record<number, SituacaoDoDocumento | null>>({});
const pendencias = ref<string[]>([]);
const podeFinalizar = ref<boolean>(false);
let geracao = 0;

const sincronizar = (): void => {
    geracao += 1;
    situacoes.value = Object.fromEntries(
        props.documentos.map((documento): [number, SituacaoDoDocumento | null] => [
            documento.id,
            documento.enviado ? { situacao: documento.enviado.situacao, motivo: documento.enviado.motivo } : null,
        ]),
    );
    pendencias.value = [...props.pendencias];
    podeFinalizar.value = props.pendencias.length === 0;
};
sincronizar();
watch(() => [props.documentos, props.pendencias], sincronizar);

const temConferindo = computed<boolean>(() => Object.values(situacoes.value).some((situacao) => situacao?.situacao === 'conferindo'));

// Uma falha de rede fica para a próxima rodada; a resposta de antes de uma recarga das props é descartada.
const consultar = async (): Promise<void> => {
    const daGeracao = geracao;
    try {
        const resposta = await fetch(`${BASE}/situacao`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!resposta.ok || daGeracao !== geracao) {
            return;
        }

        const atual = (await resposta.json()) as SituacaoDaInscricao;
        if (daGeracao !== geracao) {
            return;
        }

        for (const documento of atual.documentos) {
            situacoes.value[documento.id] = { situacao: documento.situacao, motivo: documento.motivo };
        }
        pendencias.value = atual.pendencias;
        podeFinalizar.value = atual.podeFinalizar;
    } catch {
        // Sem conexão: tenta de novo na próxima rodada.
    }
};

let temporizador: ReturnType<typeof setInterval> | undefined;
const pararConsulta = (): void => {
    clearInterval(temporizador);
    temporizador = undefined;
};
watch(
    temConferindo,
    (conferindo) => {
        if (conferindo && temporizador === undefined) {
            temporizador = setInterval(() => void consultar(), INTERVALO_CONSULTA_MS);
        } else if (!conferindo) {
            pararConsulta();
        }
    },
    { immediate: true },
);
onBeforeUnmount(pararConsulta);

// Documentos agrupados por etapa, na ordem do edital dentro de cada uma.
const posicaoDaEtapa = (etapa: string): number => {
    const posicao = ORDEM_DAS_ETAPAS.indexOf(etapa);
    return posicao === -1 ? ORDEM_DAS_ETAPAS.length : posicao;
};

interface GrupoDeEtapa {
    etapa: string;
    rotulo: string;
    documentos: DocumentoDaInscricao[];
}

const etapas = computed<GrupoDeEtapa[]>(() => {
    const grupos = new Map<string, GrupoDeEtapa>();
    for (const documento of props.documentos) {
        const grupo = grupos.get(documento.etapa) ?? { etapa: documento.etapa, rotulo: documento.etapaRotulo, documentos: [] };
        grupo.documentos.push(documento);
        grupos.set(documento.etapa, grupo);
    }
    return [...grupos.values()].sort((a, b) => posicaoDaEtapa(a.etapa) - posicaoDaEtapa(b.etapa));
});

// 1 é "Suas escolhas"; depois, uma seção por etapa (ou uma só, "Documentos", quando nada é exigido) e a de finalizar.
const numeroDaSecaoFinal = computed<number>(() => Math.max(etapas.value.length, 1) + 2);

// Depois de finalizar ou reabrir, o botão que a pessoa usou some: o foco vai para o conteúdo, onde a mensagem aparece.
const focarConteudo = (): void => {
    void nextTick(() => document.getElementById('conteudo')?.focus());
};

const finalizando = ref<boolean>(false);
const finalizar = (): void => {
    router.post(
        `${BASE}/finalizar`,
        {},
        {
            onStart: () => {
                finalizando.value = true;
            },
            onSuccess: focarConteudo,
            onFinish: () => {
                finalizando.value = false;
            },
        },
    );
};

// <dialog> nativo com showModal(): prende o foco, fecha com Esc e devolve o foco ao botão que abriu.
const dialogoReabrir = ref<HTMLDialogElement | null>(null);
const reabrindo = ref<boolean>(false);
const abrirReabrir = (): void => dialogoReabrir.value?.showModal();
const fecharReabrir = (): void => dialogoReabrir.value?.close();

const reabrir = (): void => {
    router.post(
        `${BASE}/reabrir`,
        {},
        {
            onStart: () => {
                reabrindo.value = true;
            },
            // Fechar antes de focar: ao fechar, o diálogo devolve o foco ao botão que abriu, que já saiu da tela.
            onSuccess: () => {
                fecharReabrir();
                focarConteudo();
            },
            onFinish: () => {
                reabrindo.value = false;
                fecharReabrir();
            },
        },
    );
};

const classeBotao =
    'pressionavel inline-flex min-h-12 items-center justify-center rounded-pill px-8 text-center text-[0.8125rem] font-bold tracking-[0.12em] uppercase disabled:cursor-not-allowed disabled:opacity-70';
const classePrimario = `${classeBotao} bg-acao text-sobre-acao hover:bg-acao-escuro`;
const classeSecundario = `${classeBotao} border border-tinta-suave/70 bg-superficie hover:border-acao hover:text-acao`;
</script>

<template>
    <div>
        <Head :title="`Inscrição — ${edital.titulo}`" />

        <FaixaPortal aria-labelledby="inscricao-titulo">
            <div class="flex flex-col gap-3">
                <!-- Caminho da rota portal.editais.inscricao (o front não tem helper de rotas nomeadas). -->
                <Link
                    :href="`/portal/editais/${edital.slug}/inscricao`"
                    class="inline-flex min-h-11 items-center gap-2 self-start text-[0.8125rem] font-semibold tracking-[0.12em] text-white/85 uppercase no-underline hover:text-white"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M11 6l-6 6 6 6" />
                    </svg>
                    Voltar às inscrições do edital
                </Link>
                <h1 id="inscricao-titulo" class="font-barlow text-[2.6rem] leading-[0.95] font-extrabold tracking-[0.01em] break-words uppercase md:text-[3.5rem]">
                    Inscrição
                </h1>
                <p class="max-w-2xl text-base leading-[1.7] text-white/85 md:text-[1.0625rem]">
                    <strong class="font-semibold text-white">{{ edital.titulo }}</strong> · {{ edital.categoria }}<br />
                    Inscrições de {{ edital.periodo }}.<br />
                    {{ inscricao.situacaoRotulo }}<template v-if="inscricao.numero"> · nº {{ inscricao.numero }}</template>
                </p>
            </div>
        </FaixaPortal>

        <div class="relative mx-auto -mt-20 flex max-w-6xl flex-col gap-6 px-4 pb-16 md:-mt-24">
            <SecaoCadastro id="escolhas-titulo" :numero="1" titulo="Suas escolhas">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12" />
                        <circle cx="16" cy="6" r="2" />
                        <circle cx="10" cy="12" r="2" />
                        <circle cx="18" cy="18" r="2" />
                    </svg>
                </template>

                <form v-if="podeEditar" id="formulario-escolhas" novalidate class="flex flex-col gap-6" @submit.prevent="salvarEscolhas">
                    <AvisoCamposObrigatorios />
                    <p class="max-w-2xl text-[0.875rem] leading-[1.5] text-tinta-suave">
                        Trocar o cadastro, a categoria ou as cotas pode mudar os documentos exigidos. Os arquivos dos documentos que deixarem de ser exigidos são apagados.
                    </p>
                    <EscolhasDaInscricao
                        v-model:cadastro="formulario.cadastro"
                        v-model:categoria="formulario.categoria"
                        v-model:cotas-escolhidas="formulario.cotas"
                        :cadastros="cadastros"
                        :categorias="categorias"
                        :cotas="cotas"
                        :veda-negro-indigena="vedaNegroIndigena"
                        :erros="formulario.errors"
                        :cadastro-atual="inscricao.cadastroChave"
                        @limpar-erro="(campo) => formulario.clearErrors(campo)"
                    />
                    <div class="flex">
                        <button type="submit" :disabled="formulario.processing || !formulario.isDirty || ocupado" :class="classePrimario">
                            {{ formulario.processing ? 'Salvando…' : 'Salvar escolhas' }}
                        </button>
                    </div>
                </form>

                <dl v-else class="grid gap-x-8 gap-y-3 md:grid-cols-3">
                    <div>
                        <dt class="text-[0.8125rem] font-semibold text-tinta-suave">Cadastro</dt>
                        <dd class="break-words">{{ inscricao.cadastro }}</dd>
                    </div>
                    <div>
                        <dt class="text-[0.8125rem] font-semibold text-tinta-suave">Categoria</dt>
                        <dd class="break-words">{{ inscricao.categoria ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[0.8125rem] font-semibold text-tinta-suave">Cotas</dt>
                        <dd>{{ rotulosDasCotas.length > 0 ? rotulosDasCotas.join(', ') : 'Sem cota' }}</dd>
                    </div>
                </dl>
            </SecaoCadastro>

            <SecaoCadastro v-for="(grupo, indice) in etapas" :id="`etapa-${grupo.etapa}-titulo`" :key="grupo.etapa" :numero="indice + 2" :titulo="grupo.rotulo">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
                        <path d="M14 3v5h5M9 13h6M9 17h6" />
                    </svg>
                </template>
                <ul :aria-label="`Documentos de ${grupo.rotulo}`" class="flex flex-col gap-4">
                    <li v-for="documento in grupo.documentos" :key="documento.id">
                        <DocumentoExigido
                            :documento="documento"
                            :inscricao-id="inscricao.id"
                            :situacao="situacoes[documento.id] ?? null"
                            :pode-editar="podeEditar"
                            :ocupado="ocupado"
                        />
                    </li>
                </ul>
            </SecaoCadastro>

            <SecaoCadastro v-if="etapas.length === 0" id="documentos-titulo" :numero="2" titulo="Documentos">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
                        <path d="M14 3v5h5" />
                    </svg>
                </template>
                <p class="leading-[1.6] text-tinta-suave">Nenhum documento é exigido para as escolhas desta inscrição.</p>
            </SecaoCadastro>

            <SecaoCadastro id="finalizar-titulo" :numero="numeroDaSecaoFinal" :titulo="inscricao.situacao === 'finalizada' ? 'Inscrição finalizada' : 'Finalizar inscrição'">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 21V4M5 4h11l-2 4 2 4H5" />
                    </svg>
                </template>

                <div v-if="inscricao.situacao === 'finalizada'" class="flex flex-col gap-4">
                    <p class="leading-[1.6]">
                        Número da inscrição <strong class="font-semibold">{{ inscricao.numero }}</strong>.
                        <template v-if="inscricao.finalizadaEm">Enviada em {{ envio.data }} às {{ envio.hora }}.</template>
                    </p>
                    <div v-if="podeReabrir" class="flex">
                        <button type="button" :class="classeSecundario" @click="abrirReabrir">Reabrir para editar</button>
                    </div>
                    <p v-else class="leading-[1.6] text-tinta-suave">Fora do período de inscrição, a inscrição finalizada continua válida e não pode mais ser alterada.</p>
                </div>

                <div v-else-if="podeEditar" class="flex flex-col gap-4">
                    <p class="leading-[1.6] text-tinta-suave">
                        Finalize para enviar a inscrição. Depois de finalizada, você ainda pode reabri-la para editar até o fim do período, mas ela só conta se você finalizar de novo.
                    </p>
                    <!-- A região fica sempre na página: a lista de pendências muda enquanto as conferências terminam. -->
                    <div id="pendencias-finalizar" aria-live="polite">
                        <template v-if="pendencias.length > 0">
                            <p class="font-semibold">Para finalizar, resolva:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-6 text-invalido-texto">
                                <li v-for="pendencia in pendencias" :key="pendencia">{{ pendencia }}</li>
                            </ul>
                        </template>
                    </div>
                    <div class="flex">
                        <button
                            type="button"
                            :disabled="!podeFinalizar || finalizando || ocupado"
                            :aria-describedby="pendencias.length > 0 ? 'pendencias-finalizar' : undefined"
                            :class="classePrimario"
                            @click="finalizar"
                        >
                            {{ finalizando ? 'Finalizando…' : 'Finalizar inscrição' }}
                        </button>
                    </div>
                </div>

                <p v-else class="leading-[1.6] text-tinta-suave">
                    Fora do período de inscrição, esta inscrição não pode mais ser alterada. Como não foi finalizada, ela não conta.
                </p>
            </SecaoCadastro>
        </div>

        <dialog
            ref="dialogoReabrir"
            aria-labelledby="reabrir-titulo"
            aria-describedby="reabrir-aviso"
            class="m-auto w-[min(28rem,calc(100vw-2rem))] rounded-card border border-hairline bg-superficie p-6 text-home-tinta backdrop:bg-noite/60"
        >
            <h2 id="reabrir-titulo" class="text-[1.25rem] leading-tight font-bold">Reabrir a inscrição?</h2>
            <p id="reabrir-aviso" class="mt-2 text-[0.9375rem] text-tinta-suave">
                Ao reabrir, sua inscrição volta a ser rascunho e só conta se você finalizar de novo até {{ inscricao.fimDoPeriodo }}.
            </p>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    autofocus
                    class="pressionavel inline-flex min-h-11 items-center justify-center rounded-pill border border-tinta-suave/70 bg-superficie px-5 text-[0.8125rem] font-bold tracking-[0.12em] uppercase hover:border-acao hover:text-acao"
                    @click="fecharReabrir"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="reabrindo || ocupado"
                    class="pressionavel inline-flex min-h-11 items-center justify-center rounded-pill bg-acao px-5 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro disabled:cursor-wait disabled:opacity-70"
                    @click="reabrir"
                >
                    {{ reabrindo ? 'Reabrindo…' : 'Reabrir inscrição' }}
                </button>
            </div>
        </dialog>
    </div>
</template>
