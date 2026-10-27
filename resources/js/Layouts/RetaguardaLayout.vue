<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AccessibilityControls from '../Components/AccessibilityControls.vue';
import FlashMessage from '../Components/FlashMessage.vue';
import MenuRetaguarda from '../Components/Retaguarda/MenuRetaguarda.vue';
import SkipLink from '../Components/SkipLink.vue';
import TestEnvBanner from '../Components/TestEnvBanner.vue';
import VidroLiquido from '../Components/VidroLiquido.vue';

const page = usePage();
const ambienteTeste = computed(() => page.props.ambienteTeste);
const usuario = computed(() => page.props.auth?.user);

// "Maria Gestora Teste" → "MT", para o avatar.
const iniciais = computed(() => {
    const nomes = usuario.value?.nome.trim().split(/\s+/) ?? [];
    return ((nomes[0]?.[0] ?? '') + (nomes.length > 1 ? (nomes.at(-1)?.[0] ?? '') : '')).toUpperCase();
});

// Token do <meta> de app.blade.php, como no PortalProponenteLayout.
const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

// Abaixo de 1024px o menu lateral fica recolhido atrás do botão "Menu"; fecha ao trocar de página e com Esc.
const menuAberto = ref<boolean>(false);
const botaoMenu = ref<HTMLButtonElement | null>(null);

const fecharMenu = (): void => {
    if (!menuAberto.value) return;
    menuAberto.value = false;
    botaoMenu.value?.focus();
};

const pararDeOuvir = router.on('navigate', () => {
    menuAberto.value = false;
});

onBeforeUnmount(pararDeOuvir);
</script>

<template>
    <!-- Retaguarda: identidade do portal do proponente (Montserrat, azul-marinho), com menu lateral. -->
    <div class="flex min-h-screen flex-col">
        <SkipLink />
        <TestEnvBanner v-if="ambienteTeste" />
        <VidroLiquido class="flex flex-1 flex-col bg-parchment text-home-tinta">
            <!-- Topo compacto, em uma linha: menu (celular), logo, acessibilidade, usuário, trocar senha e Sair. -->
            <header class="barra-institucional border-b border-white/10 bg-noite text-white [html.alto-contraste_&]:bg-fundo">
                <div class="flex flex-nowrap items-center justify-between gap-3 px-4 py-1.5 lg:px-6">
                    <div class="flex min-w-0 items-center gap-2 md:gap-3">
                        <button
                            ref="botaoMenu"
                            type="button"
                            class="pressionavel inline-flex min-h-9 min-w-9 shrink-0 items-center justify-center rounded-pill text-white hover:bg-white/15 focus-visible:outline-white lg:hidden"
                            :aria-expanded="menuAberto ? 'true' : 'false'"
                            aria-controls="menu-retaguarda"
                            @click="menuAberto = !menuAberto"
                        >
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <path v-if="menuAberto" d="M6 6l12 12M18 6 6 18" />
                                <path v-else d="M4 7h16M4 12h16M4 17h16" />
                            </svg>
                            <span class="sr-only">Menu</span>
                        </button>

                        <Link href="/retaguarda" class="flex min-w-0 items-center focus-visible:outline-white">
                            <img
                                src="/images/logo-fgm-prefeitura-branco.png"
                                alt="FGM – Fundação Gregório de Mattos e Prefeitura de Salvador"
                                width="1395"
                                height="210"
                                class="h-6 w-auto max-w-[45vw] object-contain object-left md:h-7"
                            />
                        </Link>
                    </div>

                    <div class="flex shrink-0 items-center gap-2 md:gap-3">
                        <AccessibilityControls variante="pilula" class="hidden sm:flex" />

                        <p v-if="usuario" class="flex items-center gap-2">
                            <span aria-hidden="true" class="hidden size-8 items-center justify-center sm:inline-flex rounded-full bg-white/15 text-[0.75rem] font-bold">
                                {{ iniciais }}
                            </span>
                            <span class="sr-only lg:not-sr-only lg:max-w-48 lg:truncate lg:text-[0.875rem] lg:font-semibold">{{ usuario.nome }}</span>
                        </p>

                        <Link
                            v-if="usuario"
                            href="/retaguarda/minha-senha"
                            :aria-current="page.url === '/retaguarda/minha-senha' ? 'page' : undefined"
                            class="pressionavel inline-flex min-h-9 min-w-9 items-center justify-center gap-1.5 rounded-pill text-white hover:bg-white/15 focus-visible:outline-white lg:px-3"
                        >
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15.5 7.5a4 4 0 1 1-3.4 6.1L4 21.5V19h2.5v-2H9v-2.1l1.9-1.9A4 4 0 0 1 15.5 7.5z" />
                            </svg>
                            <span class="sr-only lg:not-sr-only lg:text-[0.8125rem] lg:font-semibold">Trocar senha</span>
                        </Link>

                        <form method="post" action="/retaguarda/sair">
                            <input type="hidden" name="_token" :value="csrf" />
                            <button
                                type="submit"
                                class="botao-claro pressionavel inline-flex min-h-9 items-center rounded-pill bg-white px-4 text-[0.6875rem] font-bold tracking-[0.12em] text-home-azul uppercase focus-visible:outline-white"
                            >
                                Sair
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <div class="flex flex-1 flex-col lg:flex-row">
                <aside
                    id="menu-retaguarda"
                    class="border-b border-hairline bg-superficie p-4 lg:block lg:w-64 lg:shrink-0 lg:border-r lg:border-b-0"
                    :class="menuAberto ? 'block' : 'hidden'"
                    @keydown.esc="fecharMenu"
                >
                    <MenuRetaguarda />
                    <!-- No celular o topo só mostra o ícone; aqui o item aparece por extenso. -->
                    <Link
                        href="/retaguarda/minha-senha"
                        class="mt-1 flex min-h-11 items-center gap-3 rounded-[12px] px-4 font-semibold text-home-tinta hover:bg-nevoa aria-[current=page]:bg-acao aria-[current=page]:text-sobre-acao lg:hidden"
                        :aria-current="page.url === '/retaguarda/minha-senha' ? 'page' : undefined"
                    >
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15.5 7.5a4 4 0 1 1-3.4 6.1L4 21.5V19h2.5v-2H9v-2.1l1.9-1.9A4 4 0 0 1 15.5 7.5z" />
                        </svg>
                        Trocar senha
                    </Link>
                    <!-- No celular os controles de acessibilidade saem do topo e ficam aqui. -->
                    <AccessibilityControls variante="painel" class="mt-4 sm:hidden" />
                </aside>

                <main id="conteudo" tabindex="-1" class="min-w-0 flex-1 pb-12 focus:outline-none">
                    <FlashMessage />
                    <slot />
                </main>
            </div>
        </VidroLiquido>
    </div>
</template>
