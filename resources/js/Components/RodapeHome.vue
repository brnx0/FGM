<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const usuario = computed(() => usePage().props.auth?.user);
const ano = new Date().getFullYear();

// Seções da home (âncoras) e as páginas institucionais do rodapé das outras páginas.
const navegacao: { href: string; texto: string; pagina?: boolean }[] = [
    { href: '#topo', texto: 'Início' },
    { href: '#editais', texto: 'Editais' },
    { href: '#eventos', texto: 'Eventos' },
    { href: '/a-fgm', texto: 'A FGM', pagina: true },
    { href: '/duvidas', texto: 'Dúvidas', pagina: true },
];
</script>

<template>
    <!-- Começa por baixo da curva da faixa de eventos (margem negativa e padding maior). -->
    <footer class="relative z-0 -mt-[130px] bg-superficie px-6 pt-[210px] text-tinta">
        <div class="mx-auto flex max-w-[1200px] flex-col gap-16">
            <div class="flex flex-wrap items-end justify-between gap-x-12 gap-y-7 border-b border-hairline pb-14">
                <div class="flex max-w-[620px] flex-col gap-[18px]">
                    <h2 class="font-barlow text-[clamp(2.6rem,5vw,4.4rem)] leading-[0.92] font-extrabold tracking-[0.01em] uppercase">
                        Cultura que<br />move Salvador
                    </h2>
                    <div aria-hidden="true" class="h-1 w-[72px] bg-tinta"></div>
                </div>
                <div class="flex flex-wrap gap-3.5">
                    <Link
                        v-if="usuario"
                        href="/cadastro"
                        class="pressionavel inline-flex min-h-14 items-center gap-3 rounded-pill bg-tinta px-8 text-[0.8125rem] font-bold tracking-[0.12em] text-fundo uppercase no-underline shadow-[0_18px_36px_-16px_rgba(11,17,28,0.45)]"
                    >
                        Continuar meu cadastro
                    </Link>
                    <a
                        v-else
                        href="/auth/govbr/redirect"
                        class="pressionavel inline-flex min-h-14 items-center gap-3 rounded-pill bg-tinta px-8 text-[0.8125rem] font-bold tracking-[0.12em] text-fundo uppercase no-underline shadow-[0_18px_36px_-16px_rgba(11,17,28,0.45)]"
                    >
                        Cadastre-se<span class="sr-only"> com a conta gov.br</span>
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </a>
                    <a
                        href="#editais"
                        class="pressionavel inline-flex min-h-14 items-center rounded-pill border-2 border-tinta px-8 text-[0.8125rem] font-bold tracking-[0.12em] uppercase no-underline"
                    >
                        Ver editais abertos
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-x-12 gap-y-10">
                <div class="flex flex-col gap-[18px]">
                    <div class="self-start rounded-[14px] bg-noite px-5 py-3.5">
                        <img
                            src="/images/logo-fgm-prefeitura-branco.png"
                            alt="FGM – Fundação Gregório de Mattos e Prefeitura de Salvador"
                            width="1395"
                            height="210"
                            class="block h-9 w-auto max-w-full"
                            loading="lazy"
                        />
                    </div>
                    <p class="text-[0.9375rem] leading-[1.7] text-tinta-suave">
                        Editais de fomento, inscrições e a agenda cultural da cidade reunidos em um só lugar.
                    </p>
                </div>

                <nav aria-label="Rodapé" class="flex flex-col gap-2.5">
                    <h3 class="text-[0.75rem] font-bold tracking-[0.22em] uppercase">Navegação</h3>
                    <ul>
                        <li v-for="l in navegacao" :key="l.href">
                            <component :is="l.pagina ? Link : 'a'" :href="l.href" class="inline-flex min-h-11 items-center text-[0.9375rem] font-medium no-underline hover:underline">
                                {{ l.texto }}
                            </component>
                        </li>
                    </ul>
                </nav>

                <div class="flex flex-col gap-2.5">
                    <h3 class="text-[0.75rem] font-bold tracking-[0.22em] uppercase">Participe</h3>
                    <ul>
                        <li v-if="usuario">
                            <Link href="/cadastro" class="inline-flex min-h-11 items-center text-[0.9375rem] font-medium no-underline hover:underline">Meu cadastro</Link>
                        </li>
                        <template v-else>
                            <li>
                                <a href="/auth/govbr/redirect" class="inline-flex min-h-11 items-center text-[0.9375rem] font-medium no-underline hover:underline">
                                    Cadastre-se<span class="sr-only"> com a conta gov.br</span>
                                </a>
                            </li>
                            <li>
                                <a href="/auth/govbr/redirect" class="inline-flex min-h-11 items-center text-[0.9375rem] font-medium no-underline hover:underline">
                                    Entrar<span class="sr-only"> com gov.br</span>
                                </a>
                            </li>
                        </template>
                        <li>
                            <a href="#editais" class="inline-flex min-h-11 items-center text-[0.9375rem] font-medium no-underline hover:underline">Inscrever um projeto</a>
                        </li>
                    </ul>
                </div>

                <div class="flex flex-col gap-2.5">
                    <h3 class="text-[0.75rem] font-bold tracking-[0.22em] uppercase">Acessibilidade</h3>
                    <p class="text-[0.9375rem] leading-[1.7] text-tinta-suave">
                        Fonte ajustável, alto contraste e modo noturno disponíveis no topo da página.
                    </p>
                </div>
            </div>
        </div>

        <div
            class="mx-auto mt-16 flex max-w-[1200px] flex-wrap items-center justify-between gap-x-6 gap-y-3 border-t border-hairline pt-[22px] pb-7 text-[0.8125rem] text-tinta-suave"
        >
            <p>© {{ ano }} FGM – Fundação Gregório de Mattos. Todos os direitos reservados.</p>
            <nav aria-label="Informações legais" class="flex flex-wrap gap-x-5">
                <Link href="/privacidade" class="inline-flex min-h-11 items-center hover:text-tinta hover:underline">Aviso de privacidade</Link>
                <Link href="/termos" class="inline-flex min-h-11 items-center hover:text-tinta hover:underline">Termos de uso</Link>
            </nav>
            <a
                href="#topo"
                class="pressionavel inline-flex min-h-11 items-center gap-2.5 rounded-pill border-2 border-tinta px-5 text-[0.75rem] font-bold tracking-[0.12em] text-tinta uppercase no-underline"
            >
                Voltar ao topo
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 19V5M6 11l6-6 6 6" />
                </svg>
            </a>
        </div>
    </footer>
</template>
