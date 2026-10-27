<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

type ItemMenu = { href: string; texto: string; icone: string };

// Caminhos das rotas retaguarda.editais.index, retaguarda.eventos.index e retaguarda.gestores.index
// (o front não tem helper de rotas nomeadas).
const itens: ItemMenu[] = [
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
        href: '/retaguarda/gestores',
        texto: 'Gestores',
        icone: 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21a7 7 0 0 1 14 0M17 11a3 3 0 1 0 0-6M22 21a6 6 0 0 0-4-5.6',
    },
];

const page = usePage();
const atual = (href: string): boolean => page.url === href || page.url.startsWith(`${href}/`) || page.url.startsWith(`${href}?`);
</script>

<template>
    <nav aria-label="Retaguarda">
        <ul class="flex flex-col gap-1">
            <li v-for="item in itens" :key="item.href">
                <Link
                    :href="item.href"
                    class="flex min-h-11 items-center gap-3 rounded-[12px] px-4 font-semibold text-home-tinta hover:bg-nevoa aria-[current=page]:bg-acao aria-[current=page]:text-sobre-acao"
                    :aria-current="atual(item.href) ? 'page' : undefined"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path :d="item.icone" />
                    </svg>
                    {{ item.texto }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
