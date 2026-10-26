<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AccessibilityControls from './AccessibilityControls.vue';

const page = usePage();
const usuario = computed(() => page.props.auth?.user);
// Token do <meta> de app.blade.php (a página é recarregada inteira após o login).
const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

// Seções desta página (âncoras) e as páginas institucionais que o SiteHeader também lista.
const links: { href: string; texto: string; pagina?: boolean }[] = [
    { href: '#topo', texto: 'Início' },
    { href: '#editais', texto: 'Editais' },
    { href: '#eventos', texto: 'Eventos' },
    { href: '/a-fgm', texto: 'A FGM', pagina: true },
    { href: '/duvidas', texto: 'Dúvidas', pagina: true },
];

const menuAberto = ref<boolean>(false);
const pararDeOuvir = router.on('navigate', () => {
    menuAberto.value = false;
});
onUnmounted(pararDeOuvir);
</script>

<template>
    <!-- Fica sobre a foto da abertura e rola com a página (não é fixo, como no anexo). -->
    <header
        class="home-escuro absolute inset-x-0 top-0 z-20 border-b border-white/15 bg-[linear-gradient(180deg,rgba(8,10,16,0.42)_0%,rgba(8,10,16,0.18)_100%)] px-6 text-white backdrop-blur-md"
        :class="{ 'bg-noite/95': menuAberto }"
        @keydown.esc="menuAberto = false"
    >
        <div class="flex items-center justify-between gap-4 py-3.5 md:gap-8 md:px-[clamp(0px,4vw,80px)] md:py-5">
            <a href="#topo" class="block min-w-0 py-2">
                <img
                    src="/images/logo-fgm-prefeitura-branco.png"
                    alt="FGM – Fundação Gregório de Mattos e Prefeitura de Salvador"
                    width="1395"
                    height="210"
                    class="block h-[30px] w-auto max-w-full object-contain object-left drop-shadow-[0_1px_8px_rgba(0,0,0,0.35)] md:h-[46px]"
                />
            </a>

            <div class="flex shrink-0 items-center gap-2 md:gap-6">
                <nav aria-label="Principal" class="hidden xl:block">
                    <ul class="flex gap-[26px] text-[0.8125rem] font-semibold tracking-[0.12em] uppercase">
                        <li v-for="l in links" :key="l.href">
                            <component
                                :is="l.pagina ? Link : 'a'"
                                :href="l.href"
                                :aria-current="l.href === '#topo' ? 'page' : undefined"
                                class="inline-flex min-h-11 items-center border-b-2 no-underline"
                                :class="l.href === '#topo' ? 'border-white' : 'border-transparent hover:border-white/50'"
                            >
                                {{ l.texto }}
                            </component>
                        </li>
                    </ul>
                </nav>

                <div class="hidden lg:block">
                    <AccessibilityControls variante="pilula" />
                </div>

                <template v-if="usuario">
                    <!-- Abaixo de 2xl a linha não comporta o nome: ele aparece no menu. -->
                    <span class="hidden max-w-48 truncate text-[0.9375rem] font-semibold 2xl:inline">{{ usuario.nome }}</span>
                    <form method="post" action="/logout">
                        <input type="hidden" name="_token" :value="csrf" />
                        <button
                            type="submit"
                            class="botao-claro pressionavel inline-flex min-h-11 items-center rounded-pill bg-white px-4 text-[0.8125rem] font-bold tracking-[0.12em] text-home-azul uppercase md:px-[26px]"
                        >
                            Sair
                        </button>
                    </form>
                </template>
                <!-- Link comum (não Inertia): a rota redireciona para o gov.br. -->
                <a
                    v-else
                    href="/auth/govbr/redirect"
                    class="botao-claro pressionavel inline-flex min-h-11 items-center rounded-pill bg-white px-4 text-[0.8125rem] font-bold tracking-[0.12em] whitespace-nowrap text-home-azul uppercase no-underline md:px-[26px]"
                >
                    Entrar<span class="sr-only"> com gov.br</span>
                </a>

                <button
                    type="button"
                    class="vidro vidro-escuro pressionavel inline-flex size-11 shrink-0 items-center justify-center rounded-pill text-white xl:hidden"
                    :aria-expanded="menuAberto ? 'true' : 'false'"
                    aria-controls="menu-home"
                    :aria-label="menuAberto ? 'Fechar menu' : 'Abrir menu'"
                    @click="menuAberto = !menuAberto"
                >
                    <svg v-if="!menuAberto" aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                    <svg v-else aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>
        </div>

        <div v-show="menuAberto" id="menu-home" class="pb-6 md:px-[clamp(0px,4vw,80px)] xl:hidden">
            <nav aria-label="Principal (menu)">
                <ul class="border-t border-white/15">
                    <li v-for="l in links" :key="l.href">
                        <component
                            :is="l.pagina ? Link : 'a'"
                            :href="l.href"
                            :aria-current="l.href === '#topo' ? 'page' : undefined"
                            class="flex min-h-12 items-center border-b border-white/15 text-[0.8125rem] font-semibold tracking-[0.12em] uppercase no-underline"
                            @click="menuAberto = false"
                        >
                            {{ l.texto }}
                        </component>
                    </li>
                </ul>
            </nav>
            <p v-if="usuario" class="mt-4 truncate font-semibold">{{ usuario.nome }}</p>
            <div class="mt-5 flex lg:hidden">
                <AccessibilityControls variante="pilula" />
            </div>
        </div>
    </header>
</template>
