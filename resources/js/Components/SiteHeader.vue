<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import BrandLogo from './BrandLogo.vue';
import GovBrButton from './GovBrButton.vue';
import InstitutionalBar from './InstitutionalBar.vue';

const page = usePage();
const usuario = computed(() => page.props.auth?.user);
// Token do <meta> de app.blade.php (a página é recarregada inteira após o login).
const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const links: { href: string; texto: string }[] = [
    { href: '/#editais', texto: 'Editais' },
    { href: '/#eventos', texto: 'Eventos' },
    { href: '/a-fgm', texto: 'A FGM' },
    { href: '/duvidas', texto: 'Dúvidas' },
];

const menuAberto = ref<boolean>(false);
const pararDeOuvir = router.on('navigate', () => {
    menuAberto.value = false;
});
onUnmounted(pararDeOuvir);
</script>

<template>
    <InstitutionalBar />

    <header
        class="sticky z-50 border-b border-hairline bg-fundo/80 backdrop-blur-[20px]"
        style="top: var(--altura-faixa-teste, 0px)"
        @keydown.esc="menuAberto = false"
    >
        <div class="mx-auto flex min-h-16 max-w-6xl items-center justify-between gap-4 px-4">
            <BrandLogo />

            <nav aria-label="Principal" class="hidden nav:block">
                <ul class="flex items-center gap-1">
                    <li v-for="l in links" :key="l.href">
                        <Link :href="l.href" class="inline-flex min-h-11 items-center rounded-pill px-4 text-[0.9375rem] text-tinta no-underline hover:text-acao">
                            {{ l.texto }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="hidden items-center gap-3 nav:flex">
                <template v-if="usuario">
                    <span class="max-w-48 truncate text-[0.9375rem] font-semibold">{{ usuario.nome }}</span>
                    <form method="post" action="/logout" data-iris>
                        <input type="hidden" name="_token" :value="csrf" />
                        <button type="submit" class="pressionavel inline-flex min-h-11 items-center rounded-pill border border-acao px-4 text-[0.9375rem] font-semibold text-acao hover:bg-nevoa">
                            Sair
                        </button>
                    </form>
                </template>
                <GovBrButton v-else size="sm" />
            </div>

            <button
                type="button"
                class="pressionavel inline-flex size-11 items-center justify-center rounded-pill text-tinta hover:bg-parchment nav:hidden"
                :aria-expanded="menuAberto ? 'true' : 'false'"
                aria-controls="menu-movel"
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

        <div v-show="menuAberto" id="menu-movel" class="border-t border-hairline px-4 pt-2 pb-5 nav:hidden">
            <nav aria-label="Principal (celular)">
                <ul>
                    <li v-for="l in links" :key="l.href">
                        <Link
                            :href="l.href"
                            class="flex min-h-12 items-center border-b border-hairline text-corpo text-tinta no-underline"
                            @click="menuAberto = false"
                        >
                            {{ l.texto }}
                        </Link>
                    </li>
                </ul>
            </nav>
            <div class="mt-4">
                <div v-if="usuario" class="flex items-center justify-between gap-3">
                    <span class="truncate font-semibold">{{ usuario.nome }}</span>
                    <form method="post" action="/logout" data-iris>
                        <input type="hidden" name="_token" :value="csrf" />
                        <button type="submit" class="pressionavel inline-flex min-h-11 items-center rounded-pill border border-acao px-5 font-semibold text-acao">
                            Sair
                        </button>
                    </form>
                </div>
                <GovBrButton v-else size="lg" class="w-full" />
            </div>
        </div>
    </header>
</template>
