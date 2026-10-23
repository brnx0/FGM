<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    titulo: string;
}>();
</script>

<template>
    <Head :title="titulo" />

    <article class="pagina-institucional mx-auto max-w-3xl px-4 py-12 sm:py-16">
        <p class="inline-flex rounded-pill bg-[#FEF3C7] px-3 py-1 text-[0.8125rem] font-semibold text-[#78350F]">
            Texto provisório — a validar
        </p>
        <h1 class="mt-4 text-[2rem] sm:text-[3rem]">{{ titulo }}</h1>
        <div class="mt-8 space-y-5">
            <slot />
        </div>
    </article>
</template>
