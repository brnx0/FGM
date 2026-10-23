<script setup>
// Provisória: a home real (abertura, vitrine de editais e eventos) chega na Task 5.
import { Head } from '@inertiajs/vue3';
import GovBrButton from '../Components/GovBrButton.vue';
</script>

<template>
    <Head title="Início" />

    <section class="px-4 py-20 text-center">
        <h1 class="text-[2.5rem] sm:text-[3.5rem]">Sua cultura começa aqui.</h1>
        <p class="mx-auto mt-4 max-w-xl text-tinta-suave">
            Inscreva-se nos editais da Fundação Gregório de Mattos com sua conta gov.br.
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <GovBrButton size="lg" />
            <a href="#editais" class="pressionavel inline-flex min-h-12 items-center rounded-pill border border-acao px-6 font-semibold text-acao hover:bg-nevoa">
                Ver editais
            </a>
        </div>
    </section>

    <section id="editais" class="bg-acao px-4 py-16 text-center text-sobre-acao">
        <h2 class="text-[1.75rem]">Editais em breve.</h2>
    </section>

    <section id="eventos" class="bg-nevoa px-4 py-16 text-center">
        <h2 class="text-[1.75rem]">Já aconteceu.</h2>
    </section>
</template>
