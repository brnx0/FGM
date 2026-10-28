<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import CabecalhoHome from '../Components/CabecalhoHome.vue';
import EditaisCarrossel from '../Components/EditaisCarrossel.vue';
import FlashMessage from '../Components/FlashMessage.vue';
import ModalEvento from '../Components/ModalEvento.vue';
import RodapeHome from '../Components/RodapeHome.vue';
import { carregarDetalhesDoEvento } from '../Components/detalhesEvento';
import HomeLayout from '../Layouts/HomeLayout.vue';
import type { Evento, HomeProps } from '../types';

defineOptions({ layout: HomeLayout });

const props = defineProps<HomeProps>();

const usuario = computed(() => usePage().props.auth?.user);

const textoAbertos = computed(() => {
    if (props.totalAbertos === 0) return 'Nenhum edital com inscrições abertas';
    return `${props.totalAbertos} ${props.totalAbertos === 1 ? 'edital' : 'editais'} com inscrições abertas`;
});

// "setembro" vira "SET", como no anexo.
const mesAbreviado = (mes: string): string => mes.slice(0, 3).toUpperCase();

// O card do evento abre o modal, que nasce da linha do card; Ctrl/Cmd/Shift/meio e o navegador sem
// JavaScript seguem para a página.
const eventoAberto = ref<Evento | null>(null);
const origemEvento = ref<HTMLElement | null>(null);

const abrirEvento = (evento: Evento, clique: MouseEvent): void => {
    if (clique.button !== 0 || clique.ctrlKey || clique.metaKey || clique.shiftKey || clique.altKey) return;
    clique.preventDefault();
    origemEvento.value = (clique.currentTarget as HTMLElement).closest('li');
    eventoAberto.value = evento;
};

// Mouse ou foco no card já buscam os detalhes: o modal abre com eles quase sempre prontos.
const adiantarEvento = (evento: Evento): void => void carregarDetalhesDoEvento(evento.slug);

// A agenda mostra 6 eventos por vez; com mais, as setas dos lados passam para os demais (sem dar a volta:
// a lista é cronológica, então nas pontas a seta fica esmaecida).
const EVENTOS_POR_PAGINA = 6;
const paginaEventos = ref<number>(0);
const totalPaginasEventos = computed(() => Math.ceil(props.eventos.length / EVENTOS_POR_PAGINA));
const inicioDaPagina = computed(() => paginaEventos.value * EVENTOS_POR_PAGINA);
const eventosDaPagina = computed(() => props.eventos.slice(inicioDaPagina.value, inicioDaPagina.value + EVENTOS_POR_PAGINA));

// A lista nova entra pelo lado da seta clicada.
const sentidoEventos = ref<1 | -1>(1);
// As setas continuam visíveis, então a troca é anunciada por este texto (como no carrossel de editais).
const anuncioEventos = ref<string>('');

// O aria-disabled não bloqueia o Enter do teclado: a página fora da lista é descartada aqui.
const irParaPaginaDeEventos = (pagina: number): void => {
    if (pagina < 0 || pagina >= totalPaginasEventos.value) return;
    sentidoEventos.value = pagina > paginaEventos.value ? 1 : -1;
    paginaEventos.value = pagina;
    const fim = Math.min(inicioDaPagina.value + EVENTOS_POR_PAGINA, props.eventos.length);
    anuncioEventos.value = `Eventos ${inicioDaPagina.value + 1} a ${fim} de ${props.eventos.length}`;
};

// Numa nova visita a lista pode ter encolhido: a página atual não pode ficar vazia.
watch(totalPaginasEventos, (total) => {
    if (paginaEventos.value >= total) paginaEventos.value = Math.max(total - 1, 0);
});

// A refração do vidro usa o filtro SVG #lente-vidro no backdrop-filter, que só o Chromium aplica.
const lente = ref<boolean>(false);
onMounted(() => {
    const agente = (navigator as Navigator & { userAgentData?: { brands: { brand: string }[] } }).userAgentData;
    lente.value = agente?.brands.some((marca) => marca.brand === 'Chromium') ?? false;
});

// O brilho do vidro acompanha o ponteiro (variáveis --mx e --my lidas em app.css).
const iluminarVidro = (evento: PointerEvent): void => {
    const vidro = (evento.target as Element | null)?.closest<HTMLElement>('.vidro');
    if (!vidro) return;
    const caixa = vidro.getBoundingClientRect();
    if (!caixa.width) return;
    vidro.style.setProperty('--mx', `${(((evento.clientX - caixa.left) / caixa.width) * 100).toFixed(1)}%`);
    vidro.style.setProperty('--my', `${(((evento.clientY - caixa.top) / caixa.height) * 100).toFixed(1)}%`);
};
</script>

<template>
    <!-- Página inicial conforme o anexo portal-fgm.html. As faixas se sobrepõem em curva:
         cada uma sobe 130px (64px no celular) sobre a anterior; a primeira, sobre a abertura,
         encolhe até 88px em tela baixa (notebook) para sobrar espaço sob o cabeçalho. -->
    <div class="home font-montserrat overflow-x-clip" :class="{ lente }" @pointermove="iluminarVidro">
        <Head title="Início" />

        <svg aria-hidden="true" focusable="false" class="absolute size-0">
            <filter id="lente-vidro" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
                <feTurbulence type="fractalNoise" baseFrequency="0.006 0.01" numOctaves="2" seed="11" result="ruido" />
                <feGaussianBlur in="ruido" stdDeviation="3" result="suave" />
                <feDisplacementMap in="SourceGraphic" in2="suave" scale="34" xChannelSelector="R" yChannelSelector="G" />
            </filter>
        </svg>

        <div class="relative">
            <CabecalhoHome />

            <main id="conteudo" tabindex="-1" class="focus:outline-none">
                <section
                    id="topo"
                    class="home-escuro relative isolate flex min-h-svh flex-col overflow-hidden bg-home-azul px-6 pt-[75px] pb-24 text-white md:pt-[103px] md:pb-[clamp(110px,min(20vh,31vh_-_100px),230px)]"
                    aria-labelledby="topo-titulo"
                >
                    <div aria-hidden="true" class="absolute inset-0 -z-10 overflow-hidden bg-[#0d1117]">
                        <img
                            src="/images/abertura-capoeira-pelourinho.jpg"
                            alt=""
                            class="home-foto size-full origin-[70%_60%] animate-zoom-lento object-cover object-[62%_center] motion-reduce:animate-none"
                            fetchpriority="high"
                        />
                        <div
                            class="home-veu absolute inset-0 bg-[linear-gradient(90deg,rgba(4,26,56,0.82)_0%,rgba(4,26,56,0.62)_32%,rgba(4,26,56,0.18)_62%,rgba(4,26,56,0)_100%),linear-gradient(180deg,rgba(4,26,56,0.45)_0%,rgba(4,26,56,0)_22%,rgba(4,26,56,0)_75%,rgba(4,26,56,0.35)_100%)]"
                        ></div>
                    </div>

                    <!-- Centralizado na altura livre pelos pseudo-elementos, mas em tela baixa (notebook) o de cima
                         garante uma folga sob o cabeçalho. -->
                    <div class="flex w-full flex-1 flex-col before:flex-1 after:flex-1 md:px-[clamp(0px,4vw,80px)] md:before:min-h-9">
                        <div class="flex flex-col items-start gap-[clamp(14px,3vh,28px)]">
                            <FlashMessage />
                            <h1
                                id="topo-titulo"
                                class="font-barlow text-[2.5rem] leading-[0.95] font-extrabold tracking-[0.01em] uppercase md:text-[clamp(2.8rem,6vw,5.4rem)]"
                            >
                                Cultura que<br />move Salvador
                            </h1>
                            <div aria-hidden="true" class="h-1 w-20 bg-white"></div>
                            <p class="max-w-[520px] text-base leading-[1.7] md:text-[1.1875rem]">
                                Editais de fomento, inscrições e a agenda cultural da cidade reunidos em um só lugar.
                            </p>
                            <div class="mt-2 flex flex-wrap gap-4">
                                <Link
                                    v-if="usuario"
                                    href="/cadastro"
                                    class="botao-claro pressionavel inline-flex min-h-12 items-center gap-3 rounded-pill bg-white px-[22px] text-[0.75rem] font-bold tracking-[0.12em] text-home-azul uppercase no-underline shadow-[0_20px_40px_-16px_rgba(0,0,0,0.45)] md:min-h-[58px] md:px-9 md:text-[0.875rem]"
                                >
                                    Continuar meu cadastro
                                </Link>
                                <!-- Link comum (não Inertia): a conta é criada no primeiro login gov.br. -->
                                <a
                                    v-else
                                    href="/auth/govbr/redirect"
                                    data-iris
                                    class="botao-claro pressionavel inline-flex min-h-12 items-center gap-3 rounded-pill bg-white px-[22px] text-[0.75rem] font-bold tracking-[0.12em] text-home-azul uppercase no-underline shadow-[0_20px_40px_-16px_rgba(0,0,0,0.45)] md:min-h-[58px] md:px-9 md:text-[0.875rem]"
                                >
                                    Cadastre-se<span class="sr-only"> com a conta gov.br</span>
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14M13 6l6 6-6 6" />
                                    </svg>
                                </a>
                                <Link
                                    v-if="usuario"
                                    href="/organizacoes"
                                    class="vidro pressionavel inline-flex min-h-12 items-center rounded-pill px-[22px] text-[0.75rem] font-bold tracking-[0.12em] text-white uppercase no-underline md:min-h-[58px] md:px-9 md:text-[0.875rem]"
                                >
                                    Minhas organizações
                                </Link>
                                <a
                                    href="#editais"
                                    class="vidro pressionavel inline-flex min-h-12 items-center rounded-pill px-[22px] text-[0.75rem] font-bold tracking-[0.12em] text-white uppercase no-underline md:min-h-[58px] md:px-9 md:text-[0.875rem]"
                                >
                                    Ver editais abertos
                                </a>
                            </div>
                            <div class="mt-[clamp(8px,3vh,40px)] hidden flex-wrap items-center gap-x-12 gap-y-6 md:flex">
                                <p class="flex items-center gap-4">
                                    <span class="font-barlow text-[4.75rem] leading-none font-extrabold">{{ totalAbertos }}</span>
                                    <span class="text-[0.8125rem] leading-normal font-semibold tracking-[0.12em] uppercase">
                                        {{ totalAbertos === 1 ? 'Edital com' : 'Editais com' }}<br />inscrições abertas
                                    </span>
                                </p>
                                <span aria-hidden="true" class="h-[52px] w-px bg-white/40"></span>
                                <p class="flex items-center gap-4">
                                    <span class="font-barlow text-[4.75rem] leading-none font-extrabold">{{ eventos.length }}</span>
                                    <span class="text-[0.8125rem] leading-normal font-semibold tracking-[0.12em] uppercase">
                                        {{ eventos.length === 1 ? 'Evento na' : 'Eventos na' }}<br />agenda cultural
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    id="editais"
                    class="home-curva relative z-[2] -mt-16 rounded-[50%/64px] bg-superficie px-6 pt-24 pb-[220px] text-home-tinta md:-mt-[clamp(88px,14vh,130px)] md:rounded-[50%/clamp(88px,14vh,130px)] md:pt-[150px]"
                    aria-labelledby="editais-titulo"
                >
                    <div class="mx-auto flex max-w-[1200px] flex-col gap-16">
                        <div class="flex flex-wrap items-end justify-between gap-x-12 gap-y-6 border-b border-hairline pb-9">
                            <div class="flex flex-col gap-[18px]">
                                <p class="text-[0.8125rem] font-semibold tracking-[0.3em] text-tinta-suave uppercase">Fomento à cultura</p>
                                <h2 id="editais-titulo" class="font-barlow text-[clamp(3rem,6vw,5.2rem)] leading-[0.92] font-extrabold tracking-[0.01em] uppercase">
                                    Editais
                                </h2>
                                <div aria-hidden="true" class="h-1 w-[72px] bg-home-tinta"></div>
                            </div>
                            <div class="flex max-w-[460px] flex-col items-start gap-[18px]">
                                <p class="text-[1.125rem] leading-[1.7] text-tinta-suave">
                                    Acompanhe os editais de fomento à cultura e inscreva seu projeto dentro do período de cada chamada.
                                </p>
                                <p class="inline-flex items-center gap-2.5 text-[0.8125rem] font-bold tracking-[0.12em] uppercase">
                                    <span
                                        aria-hidden="true"
                                        class="size-2 rounded-full"
                                        :class="totalAbertos ? 'bg-situacao-aberto shadow-[0_0_0_4px_rgba(31,157,87,0.15)]' : 'bg-situacao-encerrado'"
                                    ></span>
                                    {{ textoAbertos }}
                                </p>
                            </div>
                        </div>

                        <EditaisCarrossel v-if="editais.length" :editais="editais" />
                        <p v-else class="text-center text-[1.1875rem] text-tinta-suave">
                            Nenhum edital com inscrições abertas no momento. Volte em breve.
                        </p>
                    </div>
                </section>

                <section
                    id="eventos"
                    class="home-escuro home-curva-baixo relative z-[1] -mt-[130px] overflow-hidden rounded-b-[50%_130px] bg-noite px-6 py-[250px] text-white"
                    aria-labelledby="eventos-titulo"
                >
                    <img src="/images/vitrine-elenco-branco.jpg" alt="" class="home-foto absolute inset-0 size-full object-cover opacity-40" loading="lazy" decoding="async" />
                    <div
                        aria-hidden="true"
                        class="home-veu absolute inset-0 bg-[linear-gradient(90deg,rgba(6,36,74,0.97)_0%,rgba(6,36,74,0.9)_42%,rgba(6,36,74,0.78)_100%)]"
                    ></div>

                    <div class="relative mx-auto flex max-w-[1200px] flex-col gap-14">
                        <div class="flex flex-wrap items-end justify-between gap-x-12 gap-y-6">
                            <div class="flex flex-col gap-[18px]">
                                <p class="text-[0.8125rem] font-semibold tracking-[0.3em] text-white/80 uppercase">Agenda cultural</p>
                                <h2 id="eventos-titulo" class="font-barlow text-[clamp(3rem,6vw,5.2rem)] leading-[0.92] font-extrabold tracking-[0.01em] uppercase">
                                    Eventos
                                </h2>
                                <div aria-hidden="true" class="h-1 w-[72px] bg-white"></div>
                            </div>
                            <p class="max-w-[460px] text-[1.125rem] leading-[1.7] text-white/85">
                                Encontros, cortejos, oficinas e festivais que fazem a agenda cultural da cidade.
                            </p>
                        </div>

                        <!-- Com mais de 6 eventos, uma seta antes e outra depois da lista; no celular as duas descem para
                             baixo dela (order-first na lista), mas a ordem de leitura continua seta, lista, seta. Com folga
                             dos lados (1480px+, sem encostar no botão do VLibras na borda), as setas (64px + 24px de vão)
                             saem para a margem e a lista fica com os 1200px inteiros. -->
                        <div
                            v-if="eventos.length"
                            class="flex flex-wrap items-center justify-center gap-4 md:flex-nowrap md:gap-6"
                            :class="{ 'min-[1480px]:-mx-[88px]': totalPaginasEventos > 1 }"
                        >
                            <button
                                v-if="totalPaginasEventos > 1"
                                type="button"
                                class="vidro vidro-escuro pressionavel inline-flex size-12 shrink-0 items-center justify-center rounded-full text-white aria-disabled:pointer-events-none aria-disabled:opacity-40 md:size-16"
                                aria-label="Página anterior de eventos"
                                aria-controls="eventos-lista"
                                :aria-disabled="paginaEventos === 0 ? 'true' : undefined"
                                @click="irParaPaginaDeEventos(paginaEventos - 1)"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 12H5M11 18l-6-6 6-6" />
                                </svg>
                            </button>

                            <!-- Cada página é uma lista nova (key), que entra pelo lado da seta. auto-fill: na última página,
                                 com menos eventos, as colunas mantêm a largura das outras. Colunas de 300px no mínimo: com
                                 as setas ao lado, ainda cabem três no notebook. -->
                            <Transition
                                mode="out-in"
                                enter-active-class="transition duration-300 ease-out motion-reduce:transition-none"
                                :enter-from-class="sentidoEventos > 0 ? 'translate-x-6 opacity-0' : '-translate-x-6 opacity-0'"
                                leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
                                :leave-to-class="sentidoEventos > 0 ? '-translate-x-6 opacity-0' : 'translate-x-6 opacity-0'"
                            >
                                <ul
                                    id="eventos-lista"
                                    :key="paginaEventos"
                                    class="order-first grid w-full grid-cols-[repeat(auto-fill,minmax(min(300px,100%),1fr))] gap-x-10 border-b border-white/15 md:order-none md:w-auto md:min-w-0 md:flex-1"
                                >
                                    <li
                                        v-for="(evento, i) in eventosDaPagina"
                                        :key="`${inicioDaPagina + i}-${evento.slug}`"
                                        :class="{ 'linha-evento-aberta': eventoAberto === evento }"
                                        class="linha-evento relative grid grid-cols-[72px_minmax(0,1fr)] items-start gap-x-[18px] gap-y-1.5 border-t border-white/15 px-1 py-6 md:grid-cols-[76px_minmax(0,1fr)] md:gap-x-5 md:px-3"
                                    >
                                        <!-- O link cobre a linha inteira (::after); o foco aparece na linha (.linha-evento:has).
                                             O clique abre o modal com os detalhes; o endereço continua sendo a página do evento. -->
                                        <h3 class="col-start-2 font-barlow text-[1.5rem] leading-[1.1] font-bold tracking-[0.01em] uppercase md:text-[1.375rem]">
                                            <a
                                                :href="`/eventos/${evento.slug}`"
                                                aria-haspopup="dialog"
                                                class="text-white no-underline outline-none after:absolute after:inset-0 after:content-['']"
                                                @click="abrirEvento(evento, $event)"
                                                @pointerenter="adiantarEvento(evento)"
                                                @focus="adiantarEvento(evento)"
                                            >
                                                {{ evento.titulo }}
                                            </a>
                                        </h3>
                                        <p class="col-start-1 row-span-3 row-start-1 flex flex-col gap-1.5">
                                            <span aria-hidden="true" class="font-barlow text-[2.5rem] leading-[0.85] font-extrabold tracking-[0.02em]">
                                                {{ mesAbreviado(evento.mes) }}
                                            </span>
                                            <span aria-hidden="true" class="text-[0.75rem] font-semibold tracking-[0.24em] text-white/80">{{ evento.ano }}</span>
                                            <span class="sr-only">{{ evento.mes }} de {{ evento.ano }}</span>
                                        </p>
                                        <p class="col-start-2 line-clamp-2 text-[0.875rem] leading-[1.6] text-white/80">{{ evento.resumo }}</p>
                                        <p v-if="evento.local" class="col-start-2 mt-1 flex items-start gap-2 text-[0.8125rem] leading-[1.5] font-semibold text-white/80">
                                            <svg aria-hidden="true" viewBox="0 0 24 24" class="mt-0.5 size-[15px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z" />
                                                <circle cx="12" cy="10" r="2.5" />
                                            </svg>
                                            <span>{{ evento.local }}</span>
                                        </p>
                                    </li>
                                </ul>
                            </Transition>

                            <button
                                v-if="totalPaginasEventos > 1"
                                type="button"
                                class="vidro vidro-escuro pressionavel inline-flex size-12 shrink-0 items-center justify-center rounded-full text-white aria-disabled:pointer-events-none aria-disabled:opacity-40 md:size-16"
                                aria-label="Próxima página de eventos"
                                aria-controls="eventos-lista"
                                :aria-disabled="paginaEventos === totalPaginasEventos - 1 ? 'true' : undefined"
                                @click="irParaPaginaDeEventos(paginaEventos + 1)"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </button>
                        </div>
                        <p v-else class="text-[1.1875rem]">Nenhum evento programado no momento.</p>
                        <p class="sr-only" aria-live="polite">{{ anuncioEventos }}</p>
                    </div>
                </section>

                <ModalEvento :evento="eventoAberto" :origem="origemEvento" @fechado="eventoAberto = null" />
            </main>
        </div>

        <RodapeHome />
    </div>
</template>
