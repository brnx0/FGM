<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

type ItemMenu = { href: string; texto: string; icone: string };
type GrupoMenu = { id: string; texto: string; icone: string; itens: ItemMenu[] };

// Caminhos das rotas retaguarda.editais.index, retaguarda.eventos.index, retaguarda.vlibras.edit e
// retaguarda.usuarios.index (o front não tem helper de rotas nomeadas).
const portal: GrupoMenu = {
    id: 'menu-grupo-portal',
    texto: 'Portal',
    icone: 'M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z',
    itens: [
        {
            href: '/retaguarda/editais',
            texto: 'Editais',
            icone: 'M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6',
        },
        {
            href: '/retaguarda/eventos',
            texto: 'Eventos',
            icone: 'M4 6h16v14H4zM4 10h16M9 3v5M15 3v5',
        },
        {
            href: '/retaguarda/vlibras',
            texto: 'VLibras',
            icone: 'M18 11V6a2 2 0 0 0-4 0M14 10V4a2 2 0 0 0-4 0v2M10 10.5V6a2 2 0 0 0-4 0v8M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15',
        },
    ],
};

const usuarios: ItemMenu = {
    href: '/retaguarda/usuarios',
    texto: 'Usuários',
    icone: 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21a7 7 0 0 1 14 0M17 11a3 3 0 1 0 0-6M22 21a6 6 0 0 0-4-5.6',
};

const page = usePage();
const atual = (href: string): boolean => page.url === href || page.url.startsWith(`${href}/`) || page.url.startsWith(`${href}?`);

// O grupo "Portal" abre sozinho quando a página atual é um dos seus itens.
const portalAtivo = computed<boolean>(() => portal.itens.some((item) => atual(item.href)));
const portalAberto = ref<boolean>(portalAtivo.value);
watch(portalAtivo, (ativo) => {
    if (ativo) portalAberto.value = true;
});

const classeLink =
    'flex min-h-11 items-center gap-3 rounded-[12px] px-4 font-semibold text-home-tinta hover:bg-nevoa aria-[current=page]:bg-acao aria-[current=page]:text-sobre-acao';
</script>

<template>
    <nav aria-label="Retaguarda">
        <ul class="flex flex-col gap-1">
            <li>
                <button
                    type="button"
                    class="flex min-h-11 w-full items-center gap-3 rounded-[12px] px-4 text-left font-semibold text-home-tinta hover:bg-nevoa"
                    :aria-expanded="portalAberto ? 'true' : 'false'"
                    :aria-controls="portal.id"
                    @click="portalAberto = !portalAberto"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path :d="portal.icone" />
                    </svg>
                    <span class="flex-1">{{ portal.texto }}</span>
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        class="size-4 shrink-0 transition-transform motion-reduce:transition-none"
                        :class="portalAberto ? 'rotate-180' : ''"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>

                <ul v-show="portalAberto" :id="portal.id" class="mt-1 flex flex-col gap-1 pl-4">
                    <li v-for="item in portal.itens" :key="item.href">
                        <Link :href="item.href" :class="classeLink" :aria-current="atual(item.href) ? 'page' : undefined">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path :d="item.icone" />
                            </svg>
                            {{ item.texto }}
                        </Link>
                    </li>
                </ul>
            </li>

            <li>
                <Link :href="usuarios.href" :class="classeLink" :aria-current="atual(usuarios.href) ? 'page' : undefined">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path :d="usuarios.icone" />
                    </svg>
                    {{ usuarios.texto }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
