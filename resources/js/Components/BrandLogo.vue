<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <!--
        Marca provisória em texto. Quando chegarem os arquivos oficiais da FGM e da
        Prefeitura, troque o conteúdo do <Link> por <img src="/images/…" alt="…">.
    -->
    <Link href="/" class="inline-flex min-h-11 flex-col justify-center leading-none no-underline">
        <span class="text-[1.375rem] font-extrabold tracking-[-0.025em] text-acao">Mapa Cultura</span>
        <span class="mt-1 text-[0.75rem] font-semibold text-tinta-suave">Fundação Gregório de Mattos</span>
    </Link>
</template>
