<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

const usuario = computed(() => usePage().props.auth?.user);
</script>

<template>
    <Head title="Complete seu cadastro" />

    <section class="mx-auto max-w-3xl px-4 py-12 sm:py-16">
        <p class="text-[1.1875rem]">Olá, {{ usuario?.nome }}</p>
        <p class="mt-1 text-[0.9375rem]">CPF {{ usuario?.cpfMascarado }}</p>
        <h1 class="mt-4 text-[2rem] sm:text-[3rem]">Complete seu cadastro</h1>
        <p class="mt-6 text-[1.1875rem]">
            Em breve você poderá completar seus dados de proponente ou preposto aqui.
        </p>
    </section>
</template>
