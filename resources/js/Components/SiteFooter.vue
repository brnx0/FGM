<script setup>
import { Link } from '@inertiajs/vue3';

const portal = [
    { href: '/#editais', texto: 'Editais' },
    { href: '/#eventos', texto: 'Eventos' },
    { href: '/a-fgm', texto: 'A FGM' },
    { href: '/duvidas', texto: 'Dúvidas' },
];
</script>

<template>
    <footer class="border-t border-hairline bg-parchment text-[0.875rem] text-tinta-suave">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-[2fr_1fr_1fr]">
            <div>
                <p class="font-semibold text-tinta">Fundação Gregório de Mattos</p>
                <p class="mt-1">Ladeira da Barroquinha, 02 — Salvador/BA</p>
                <p class="mt-1">Prefeitura Municipal de Salvador</p>
            </div>
            <nav aria-label="Portal">
                <p class="font-semibold text-tinta">Portal</p>
                <ul class="mt-1">
                    <li v-for="l in portal" :key="l.href">
                        <Link :href="l.href" class="inline-flex min-h-11 items-center hover:text-acao hover:underline">{{ l.texto }}</Link>
                    </li>
                </ul>
            </nav>
            <nav aria-label="Informações legais">
                <p class="font-semibold text-tinta">Informações</p>
                <ul class="mt-1">
                    <li><Link href="/privacidade" class="inline-flex min-h-11 items-center hover:text-acao hover:underline">Aviso de privacidade</Link></li>
                    <li><Link href="/termos" class="inline-flex min-h-11 items-center hover:text-acao hover:underline">Termos de uso</Link></li>
                </ul>
            </nav>
        </div>
    </footer>
</template>
