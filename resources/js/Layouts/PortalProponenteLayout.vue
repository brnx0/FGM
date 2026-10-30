<script lang="ts">
// Uma vez por documento: o layout volta a montar ao sair e voltar por outra tela, e a entrada extra do Voltar não se repete.
let entradaDoVoltarCriada = false;
</script>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AccessibilityControls from '../Components/AccessibilityControls.vue';
import CookieNotice from '../Components/CookieNotice.vue';
import FlashMessage from '../Components/FlashMessage.vue';
import SkipLink from '../Components/SkipLink.vue';
import TestEnvBanner from '../Components/TestEnvBanner.vue';
import TransicaoIris from '../Components/TransicaoIris.vue';
import VidroLiquido from '../Components/VidroLiquido.vue';

const page = usePage();
const ambienteTeste = computed(() => page.props.ambienteTeste);
const usuario = computed(() => page.props.auth?.user);

// Token do <meta> de app.blade.php, como no CabecalhoHome.
const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const ano = new Date().getFullYear();

const linksRodape: { href: string; texto: string }[] = [
    { href: '/', texto: 'Voltar ao site' },
    { href: '/duvidas', texto: 'Dúvidas' },
    { href: '/privacidade', texto: 'Aviso de privacidade' },
    { href: '/termos', texto: 'Termos de uso' },
];

// Painel de acessibilidade do botão ao lado de "Sair": fecha com Esc, com o ×, com clique fora,
// quando o foco sai dele pelo teclado e ao trocar de página.
const acessibilidadeAberta = ref<boolean>(false);
const botaoAcessibilidade = ref<HTMLButtonElement | null>(null);
const painelAcessibilidade = ref<HTMLElement | null>(null);

const dentroDaAcessibilidade = (alvo: Node | null): boolean =>
    alvo !== null && (!!botaoAcessibilidade.value?.contains(alvo) || !!painelAcessibilidade.value?.contains(alvo));

const fecharAoClicarFora = (evento: PointerEvent): void => {
    if (!dentroDaAcessibilidade(evento.target as Node)) acessibilidadeAberta.value = false;
};

// Sem relatedTarget (clique numa área sem foco) quem decide é o clique fora.
const fecharAoSairDoFoco = (evento: FocusEvent): void => {
    const destino = evento.relatedTarget as Node | null;
    if (destino && !dentroDaAcessibilidade(destino)) acessibilidadeAberta.value = false;
};

const fecharEVoltarAoBotao = (): void => {
    if (!acessibilidadeAberta.value) return;
    acessibilidadeAberta.value = false;
    botaoAcessibilidade.value?.focus();
};

watch(acessibilidadeAberta, (aberto) => {
    if (aberto) document.addEventListener('pointerdown', fecharAoClicarFora);
    else document.removeEventListener('pointerdown', fecharAoClicarFora);
});

// Voltar do navegador encerra a sessão pelo mesmo caminho do botão Sair, e a pessoa volta ao portal deslogada.
const formularioSair = ref<HTMLFormElement | null>(null);
let saindo = false;

const sairAoVoltar = (evento: PopStateEvent): void => {
    // Estado nulo é clique num link de âncora (ex.: "Pular para o conteúdo"), não um Voltar.
    if (saindo || evento.state === null) return;
    saindo = true;
    formularioSair.value?.submit();
};

window.addEventListener('popstate', sairAoVoltar);

const pararDeOuvir = router.on('navigate', () => {
    acessibilidadeAberta.value = false;

    // A primeira tela ganha uma cópia no histórico: assim o Voltar cai no popstate daqui mesmo quando a tela
    // anterior é outro documento (gov.br, ou depois de um F5). No navigate o Inertia já gravou a tela no
    // histórico (a gravação cifrada é assíncrona), então a cópia leva o estado dela.
    if (!entradaDoVoltarCriada) {
        entradaDoVoltarCriada = true;
        window.history.pushState(window.history.state, '', location.href);
    }
});

onBeforeUnmount(() => {
    pararDeOuvir();
    window.removeEventListener('popstate', sairAoVoltar);
    document.removeEventListener('pointerdown', fecharAoClicarFora);
});
</script>

<template>
    <!-- Portal do proponente: identidade da home (Montserrat, Barlow, azul-marinho e vidro) num layout de aplicativo.
         Cada página abre com a FaixaPortal (saudação e etapas), e os cartões sobem sobre ela. -->
    <div class="flex min-h-screen flex-col">
        <SkipLink />
        <TestEnvBanner v-if="ambienteTeste" />
        <VidroLiquido class="flex flex-1 flex-col bg-parchment text-home-tinta">
            <!-- Sem home-escuro: o anel de foco branco fica só nos controles do topo, e não no painel claro de acessibilidade. -->
            <header class="border-b border-white/10 bg-noite text-white [html.alto-contraste_&]:bg-fundo">
                <!-- Logo, nome e controles na mesma linha, com o nome centralizado no espaço entre os dois. O menu do
                     portal não fica aqui: fica na FaixaPortal de cada página, à direita do título. -->
                <div class="mx-auto grid max-w-6xl grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-4 px-4 py-3.5">
                    <Link href="/" class="block min-w-0 justify-self-start py-1 focus-visible:outline-white">
                        <img
                            src="/images/logo-fgm-prefeitura-branco.png"
                            alt="FGM – Fundação Gregório de Mattos e Prefeitura de Salvador (voltar ao site)"
                            width="1395"
                            height="210"
                            class="block h-7 w-auto max-w-full md:h-9"
                        />
                    </Link>

                    <!-- No celular não há espaço: o nome fica só para o leitor de tela. -->
                    <p v-if="usuario" class="sr-only md:not-sr-only md:truncate md:text-center md:text-[1.1875rem] md:font-semibold">{{ usuario.nome }}</p>

                    <!-- Coluna fixa: no celular o nome sai do fluxo (sr-only), e os controles não podem cair no meio. -->
                    <div class="relative col-start-3 flex w-full min-w-0 items-center justify-end gap-2 md:gap-4" @keydown.esc="fecharEVoltarAoBotao">

                        <!-- Ícone e seta, sem o texto (fica para o leitor de tela). No celular, só o ícone: com a seta
                             o botão espreme a logo. -->
                        <button
                            ref="botaoAcessibilidade"
                            type="button"
                            class="pressionavel group inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center gap-1 rounded-pill border border-white/15 bg-white/10 px-2.5 text-white sm:pr-3 hover:bg-white/20 focus-visible:outline-white aria-expanded:bg-white/25 [html.alto-contraste_&]:border-hairline aria-expanded:ring-2 aria-expanded:ring-white/60"
                            :aria-expanded="acessibilidadeAberta ? 'true' : 'false'"
                            aria-controls="painel-acessibilidade"
                            title="Acessibilidade"
                            @click="acessibilidadeAberta = !acessibilidadeAberta"
                            @focusout="fecharAoSairDoFoco"
                        >
                            <!-- Figura do acesso universal: pessoa de braços abertos. -->
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="4.5" r="2.2" fill="currentColor" stroke="none" />
                                <path d="M4.5 8.6c2.5.9 5 1.3 7.5 1.3s5-.4 7.5-1.3M12 9.9v4.6m0 0-3.2 6.5m3.2-6.5 3.2 6.5" />
                            </svg>
                            <span class="sr-only">Acessibilidade</span>
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 24 24"
                                class="hidden size-4 transition-transform group-aria-expanded:rotate-180 motion-reduce:transition-none sm:block"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>

                        <!-- Logo depois do botão: pelo teclado, o Tab entra no painel antes de chegar ao "Sair". -->
                        <Transition
                            enter-active-class="transition duration-150 ease-out motion-reduce:transition-none"
                            enter-from-class="-translate-y-1 scale-[0.98] opacity-0"
                            leave-active-class="transition duration-100 ease-in motion-reduce:transition-none"
                            leave-to-class="-translate-y-1 scale-[0.98] opacity-0"
                        >
                            <div
                                v-show="acessibilidadeAberta"
                                id="painel-acessibilidade"
                                ref="painelAcessibilidade"
                                class="absolute top-full right-0 z-50 mt-3 w-[min(20rem,calc(100vw-2rem))] origin-top-right rounded-[20px] border border-hairline bg-superficie p-5 text-home-tinta shadow-[0_24px_60px_-24px_rgba(6,36,74,0.5)]"
                                @focusout="fecharAoSairDoFoco"
                            >
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <p class="font-bold">Acessibilidade</p>
                                    <button
                                        type="button"
                                        class="pressionavel -mr-2.5 inline-flex size-11 items-center justify-center rounded-full text-tinta-suave hover:bg-parchment hover:text-home-tinta"
                                        aria-label="Fechar acessibilidade"
                                        @click="fecharEVoltarAoBotao"
                                    >
                                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                            <path d="M6 6l12 12M18 6 6 18" />
                                        </svg>
                                    </button>
                                </div>
                                <AccessibilityControls variante="painel" />
                            </div>
                        </Transition>

                        <form ref="formularioSair" method="post" action="/logout" data-iris>
                            <input type="hidden" name="_token" :value="csrf" />
                            <button
                                type="submit"
                                class="botao-claro pressionavel inline-flex min-h-11 items-center rounded-pill bg-white px-5 text-[0.75rem] font-bold tracking-[0.12em] text-home-azul uppercase focus-visible:outline-white"
                            >
                                Sair
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main id="conteudo" tabindex="-1" class="flex-1 focus:outline-none">
                <FlashMessage />
                <slot />
            </main>

            <footer class="border-t border-hairline bg-superficie">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-8 gap-y-1 px-4 py-5 text-[0.8125rem] text-tinta-suave">
                    <p class="py-3">© {{ ano }} FGM – Fundação Gregório de Mattos · Prefeitura de Salvador</p>
                    <nav aria-label="Rodapé">
                        <ul class="flex flex-wrap gap-x-5">
                            <li v-for="link in linksRodape" :key="link.href">
                                <Link :href="link.href" class="inline-flex min-h-11 items-center hover:text-home-tinta hover:underline">{{ link.texto }}</Link>
                            </li>
                        </ul>
                    </nav>
                </div>
            </footer>
        </VidroLiquido>
        <CookieNotice />
        <TransicaoIris />
    </div>
</template>
