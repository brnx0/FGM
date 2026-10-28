<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import MapaLocal from './MapaLocal.vue';
import { carregarDetalhesDoEvento } from './detalhesEvento';
import type { Evento, EventoDetalhe } from '../types';

const props = defineProps<{
    /** Card clicado na página inicial; nulo com o modal fechado. */
    evento: Evento | null;
    /** Linha do card na página: o modal nasce dela e volta para ela ao fechar. */
    origem: HTMLElement | null;
}>();

const emit = defineEmits<{ fechado: [] }>();

// <dialog> nativo com showModal(): prende o foco, deixa o fundo inerte e devolve o foco ao card.
const dialogo = ref<HTMLDialogElement | null>(null);
const conteudo = ref<HTMLElement | null>(null);
const veu = ref<HTMLElement | null>(null);
const mapa = ref<InstanceType<typeof MapaLocal> | null>(null);
const fechando = ref<boolean>(false);

// Com o card de origem à vista (e sem "reduzir movimento"), o painel cresce a partir dele (.com-origem
// desliga a animação de CSS); sem ele, vale a entrada de reserva em app.css (.modal-evento).
const comOrigem = ref<boolean>(false);

// Curvas: a abertura desacelera longa (expande e assenta); a volta acelera para dentro do card.
const CURVA_ABRIR = 'cubic-bezier(0.16, 1, 0.3, 1)';
const CURVA_FECHAR = 'cubic-bezier(0.65, 0, 0.35, 1)';

const esperar = (ms: number): Promise<void> => new Promise((resolver) => window.setTimeout(resolver, ms));

/** Animações que terminam sozinhas, com um teto: em aba em segundo plano o navegador pode pausá-las. */
const aoTerminar = (animacoes: Animation[], teto: number): Promise<unknown> =>
    Promise.race([Promise.all(animacoes.map((animacao) => animacao.finished.catch(() => undefined))), esperar(teto)]);

/** Transform que leva o painel, na posição final, para cima do retângulo do card. */
const transformDoCard = (): string | null => {
    const painel = dialogo.value?.getBoundingClientRect();
    const card = props.origem?.getBoundingClientRect();
    if (!painel?.width || !card?.width) return null;

    return `translate(${card.left - painel.left}px, ${card.top - painel.top}px) scale(${card.width / painel.width}, ${card.height / painel.height})`;
};

/** Painel sai do card como uma lâmina navy, cresce até o lugar e o conteúdo surge em cascata. */
const animarEntrada = async (): Promise<void> => {
    const painel = dialogo.value;
    const inicio = transformDoCard();
    if (!painel || !inicio) return;

    const animacoes = [
        painel.animate([{ transform: inicio, borderRadius: '20px' }, { transform: 'none' }], { duration: 680, easing: CURVA_ABRIR }),
        veu.value!.animate([{ opacity: 1 }, { opacity: 1, offset: 0.4 }, { opacity: 0 }], { duration: 680, easing: 'ease-out' }),
        ...[...painel.querySelectorAll<HTMLElement>('[data-surge]')].map((parte, ordem) =>
            parte.animate([{ opacity: 0, transform: 'translateY(18px)' }, { opacity: 1, transform: 'none' }], {
                duration: 560,
                delay: 260 + ordem * 55,
                easing: CURVA_ABRIR,
                fill: 'backwards',
            }),
        ),
    ];

    await aoTerminar(animacoes, 1600);
};

/** O caminho de volta: o conteúdo some, a lâmina navy cobre o painel e ele encolhe para dentro do card. */
const animarSaida = async (): Promise<void> => {
    const painel = dialogo.value;
    const lamina = veu.value;
    if (!painel || !lamina) return;

    // Fechado com a entrada ainda rodando (X, clique fora ou Esc logo após abrir): a volta parte de onde o
    // painel está e a medida é a do lugar final dele; medido no meio da entrada, ele encolheria para outro canto.
    const { transform, borderRadius } = getComputedStyle(painel);
    const opacidadeDaLamina = getComputedStyle(lamina).opacity;
    [...painel.getAnimations(), ...lamina.getAnimations()].forEach((animacao) => animacao.cancel());

    const fim = transformDoCard();
    if (!fim) return;

    // fill: 'both' segura o ponto de partida durante o delay, senão o painel pularia para o tamanho cheio.
    const animacoes = [
        conteudo.value!.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 160, easing: 'ease-in', fill: 'forwards' }),
        lamina.animate([{ opacity: opacidadeDaLamina }, { opacity: 1 }], { duration: 200, delay: 60, easing: 'ease-in', fill: 'both' }),
        painel.animate(
            [{ transform, borderRadius }, { transform: fim, borderRadius: '20px', opacity: 1, offset: 0.85 }, { transform: fim, borderRadius: '20px', opacity: 0 }],
            { duration: 520, delay: 80, easing: CURVA_FECHAR, fill: 'both' },
        ),
    ];

    await aoTerminar(animacoes, 1200);
    // Sem isto, o fill deixaria o painel encolhido na próxima abertura.
    animacoes.forEach((animacao) => animacao.cancel());
};

// O card já traz título, data, local e resumo; o resto (descrição, horário, endereço, pin) vem depois.
const detalhe = ref<EventoDetalhe | null>(null);
const situacao = ref<'carregando' | 'pronto' | 'erro'>('carregando');

const temMapa = computed<boolean>(() => detalhe.value?.latitude != null && detalhe.value?.longitude != null);
const comoChegar = computed<string | null>(() =>
    temMapa.value ? `https://www.google.com/maps/dir/?api=1&destination=${detalhe.value!.latitude},${detalhe.value!.longitude}` : null,
);

const carregar = async (slug: string): Promise<void> => {
    situacao.value = 'carregando';
    const dados = await carregarDetalhesDoEvento(slug);
    // Fechou ou trocou de evento enquanto carregava.
    if (props.evento?.slug !== slug) return;

    detalhe.value = dados;
    situacao.value = dados ? 'pronto' : 'erro';
};

let reserva: number | undefined;

watch(
    () => props.evento,
    async (evento) => {
        if (!evento) return;

        // Reaberto logo depois de fechar: a reserva do fechamento anterior não pode fechar este.
        window.clearTimeout(reserva);
        detalhe.value = null;
        fechando.value = false;
        comOrigem.value = props.origem !== null && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        void carregar(evento.slug);

        // A classe .com-origem precisa estar no <dialog> antes do showModal(), senão a animação de CSS começa.
        await nextTick();
        if (!dialogo.value?.open) dialogo.value?.showModal();
        if (comOrigem.value) {
            await animarEntrada();
            mapa.value?.ajustar();
        }
    },
);

const concluirFechamento = (): void => {
    window.clearTimeout(reserva);
    fechando.value = false;
    if (dialogo.value?.open) dialogo.value.close();
    emit('fechado');
};

// A saída espera o fim da animação; se ela não disparar (animações desligadas), fecha assim mesmo.
const fechar = async (): Promise<void> => {
    if (!dialogo.value?.open || fechando.value) return;
    fechando.value = true;

    if (comOrigem.value) {
        await animarSaida();
        concluirFechamento();
        return;
    }
    reserva = window.setTimeout(concluirFechamento, 400);
};

const aoTerminarAnimacao = (evento: AnimationEvent): void => {
    // Só a animação de CSS do próprio <dialog> (o esqueleto de carregamento também anima); vindo do card,
    // quem conduz é a Web Animations API, e o fim da animação do fundo não pode fechar nada.
    if (evento.target !== dialogo.value || comOrigem.value) return;
    if (fechando.value) concluirFechamento();
    else mapa.value?.ajustar();
};

// Clique fora do painel: o alvo é o próprio <dialog> (o conteúdo é um filho que o preenche).
const aoClicar = (evento: MouseEvent): void => {
    if (evento.target === dialogo.value) fechar();
};

// Fechado pelo navegador sem passar pela animação (ex.: Esc repetido): só avisa a página. O evento "close"
// chega depois do close(): se o modal já foi reaberto nesse meio-tempo, ele é de um fechamento antigo.
const aoFecharNativo = (): void => {
    if (dialogo.value?.open) return;
    if (props.evento !== null) concluirFechamento();
};

// "setembro" vira "SET", como nos cards.
const mesAbreviado = computed<string>(() => (props.evento ? props.evento.mes.slice(0, 3).toUpperCase() : ''));

const ICONES = {
    data: 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4',
    horario: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
    local: 'M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12zM12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
    endereco: 'M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6',
} as const;
</script>

<template>
    <dialog
        ref="dialogo"
        class="modal-evento m-0 mt-auto max-h-none w-full max-w-none overflow-hidden rounded-t-[28px] bg-superficie p-0 text-home-tinta shadow-[0_40px_90px_-30px_rgba(6,36,74,0.65)] sm:m-auto sm:w-[min(64rem,calc(100vw-3rem))] sm:rounded-[28px]"
        :class="{ fechando, 'com-origem': comOrigem }"
        aria-labelledby="modal-evento-titulo"
        @cancel.prevent="fechar"
        @click="aoClicar"
        @close="aoFecharNativo"
        @animationend="aoTerminarAnimacao"
    >
        <!-- Em telas grandes, duas colunas (texto rolando e o mapa ao lado); nas menores, tudo numa coluna só. -->
        <div
            v-if="evento"
            ref="conteudo"
            class="max-h-[92dvh] overflow-y-auto overscroll-contain sm:max-h-[min(90dvh,52rem)] lg:grid lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:overflow-hidden"
        >
            <div class="flex min-h-0 flex-col lg:overflow-y-auto lg:overscroll-contain">
                <header class="home-escuro relative isolate overflow-hidden bg-noite px-6 pt-4 pb-7 text-white sm:px-8 sm:pt-8">
                    <img v-if="evento.imagem" :src="evento.imagem" alt="" class="home-foto absolute inset-0 -z-10 size-full object-cover opacity-40" />
                    <div
                        aria-hidden="true"
                        class="home-veu absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(6,36,74,0.97)_0%,rgba(6,36,74,0.88)_55%,rgba(6,36,74,0.72)_100%)]"
                    ></div>

                    <span aria-hidden="true" class="mx-auto mb-5 block h-1.5 w-12 rounded-pill bg-white/30 sm:hidden"></span>

                    <button
                        type="button"
                        class="vidro vidro-escuro pressionavel absolute top-4 right-4 inline-flex size-11 items-center justify-center rounded-full text-white sm:top-6 sm:right-6"
                        aria-label="Fechar"
                        @click="fechar"
                    >
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                            <path d="M6 6l12 12M18 6 6 18" />
                        </svg>
                    </button>

                    <div class="flex items-start gap-5 pr-14">
                        <p data-surge class="flex shrink-0 flex-col items-center gap-1 rounded-[18px] bg-white/10 px-4 py-3 text-center ring-1 ring-white/15">
                            <span aria-hidden="true" class="font-barlow text-[2.75rem] leading-[0.85] font-extrabold">{{ evento.dia }}</span>
                            <span aria-hidden="true" class="font-barlow text-[1.25rem] leading-none font-bold tracking-[0.04em]">{{ mesAbreviado }}</span>
                            <span aria-hidden="true" class="text-[0.6875rem] font-semibold tracking-[0.24em] text-white/80">{{ evento.ano }}</span>
                            <span class="sr-only">{{ evento.dia }} de {{ evento.mes }} de {{ evento.ano }}</span>
                        </p>
                        <div data-surge class="min-w-0">
                            <p class="text-[0.6875rem] font-semibold tracking-[0.2em] text-white/80 uppercase sm:text-[0.75rem] sm:tracking-[0.3em]">Agenda cultural</p>
                            <h2
                                id="modal-evento-titulo"
                                class="mt-2 font-barlow text-[clamp(1.75rem,4vw,2.5rem)] leading-none font-extrabold tracking-[0.01em] break-words uppercase"
                            >
                                {{ evento.titulo }}
                            </h2>
                            <div aria-hidden="true" class="mt-4 h-1 w-14 bg-white"></div>
                        </div>
                    </div>
                </header>

                <div class="flex flex-col gap-6 px-6 py-7 sm:px-8">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div data-surge class="flex items-start gap-3">
                            <span aria-hidden="true" class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-nevoa text-acao">
                                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path :d="ICONES.data" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <dt class="text-[0.8125rem] text-tinta-suave">Data</dt>
                                <dd class="font-semibold first-letter:uppercase">
                                    {{ detalhe ? `${detalhe.diaSemana}, ${detalhe.dataRealizacao}` : `${evento.dia} de ${evento.mes} de ${evento.ano}` }}
                                </dd>
                            </div>
                        </div>
                        <div v-if="detalhe?.horario" class="surge-detalhe flex items-start gap-3">
                            <span aria-hidden="true" class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-nevoa text-acao">
                                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path :d="ICONES.horario" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <dt class="text-[0.8125rem] text-tinta-suave">Horário</dt>
                                <dd class="font-semibold">{{ detalhe.horario }}</dd>
                            </div>
                        </div>
                        <div v-if="evento.local" data-surge class="flex items-start gap-3">
                            <span aria-hidden="true" class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-nevoa text-acao">
                                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path :d="ICONES.local" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <dt class="text-[0.8125rem] text-tinta-suave">Local</dt>
                                <dd class="font-semibold break-words">{{ evento.local }}</dd>
                            </div>
                        </div>
                        <div v-if="detalhe?.endereco" class="surge-detalhe flex items-start gap-3">
                            <span aria-hidden="true" class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-nevoa text-acao">
                                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path :d="ICONES.endereco" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <dt class="text-[0.8125rem] text-tinta-suave">Endereço</dt>
                                <dd class="font-semibold break-words">{{ detalhe.endereco }}</dd>
                            </div>
                        </div>
                    </dl>

                    <p data-surge class="text-[1.0625rem] leading-[1.7] whitespace-pre-line">{{ evento.resumo }}</p>

                    <div v-if="situacao === 'carregando'" aria-hidden="true" class="flex animate-pulse flex-col gap-3 border-t border-hairline pt-6 motion-reduce:animate-none">
                        <span class="h-3.5 w-full rounded-pill bg-nevoa"></span>
                        <span class="h-3.5 w-11/12 rounded-pill bg-nevoa"></span>
                        <span class="h-3.5 w-4/5 rounded-pill bg-nevoa"></span>
                    </div>
                    <p v-else-if="situacao === 'erro'" role="alert" class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-hairline pt-6 text-[0.9375rem]">
                        Não foi possível carregar os detalhes do evento.
                        <button type="button" class="inline-flex min-h-11 items-center font-semibold text-acao hover:underline" @click="carregar(evento.slug)">
                            Tentar de novo
                        </button>
                    </p>
                    <!-- HTML limpo ao salvar pela allowlist de App\Support\HtmlSeguro. -->
                    <div v-else-if="detalhe?.descricao" class="surge-detalhe conteudo-rico border-t border-hairline pt-6" v-html="detalhe.descricao"></div>

                    <div v-if="comoChegar" class="surge-detalhe flex flex-wrap gap-3">
                        <a
                            :href="comoChegar"
                            target="_blank"
                            rel="noopener"
                            class="pressionavel inline-flex min-h-12 items-center justify-center gap-2 rounded-pill bg-acao px-6 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase no-underline hover:bg-acao-escuro"
                        >
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 11 21 3l-8 18-2-8z" />
                            </svg>
                            Como chegar<span class="sr-only"> (abre o Google Maps em nova aba)</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mapa: ao lado em telas grandes (com um aviso quando não há pin); embaixo do texto nas menores. -->
            <div v-if="temMapa" class="surge-detalhe px-6 pb-7 sm:px-8 lg:p-6 lg:pl-0">
                <MapaLocal
                    ref="mapa"
                    :latitude="detalhe!.latitude"
                    :longitude="detalhe!.longitude"
                    :rotulo="`Mapa com o local de ${evento.titulo}`"
                    class="h-64 lg:h-full"
                />
            </div>
            <div v-else-if="situacao === 'carregando'" data-surge aria-hidden="true" class="hidden p-6 pl-0 lg:block">
                <div class="size-full animate-pulse rounded-[20px] bg-nevoa motion-reduce:animate-none"></div>
            </div>
            <div v-else class="surge-detalhe hidden p-6 pl-0 lg:block">
                <div class="flex size-full flex-col items-center justify-center gap-3 rounded-[20px] border border-hairline bg-nevoa p-8 text-center text-tinta-suave">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-10 text-acao" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path :d="ICONES.local" />
                    </svg>
                    <p class="max-w-[16rem] text-[0.9375rem]">O mapa deste evento ainda não foi marcado.</p>
                </div>
            </div>
        </div>

        <!-- Lâmina navy da transição: cobre o painel enquanto ele sai do card (e volta para ele); parada, fica invisível. -->
        <div
            ref="veu"
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 z-10 bg-[linear-gradient(135deg,#0b3366_0%,#06244a_70%)] opacity-0 shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
        ></div>
    </dialog>
</template>
