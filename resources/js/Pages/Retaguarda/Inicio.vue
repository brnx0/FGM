<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RetaguardaLayout from '../../Layouts/RetaguardaLayout.vue';

// Provisória: a gestão de editais (Task 5) passa a ser a entrada da retaguarda.
defineOptions({ layout: RetaguardaLayout });
</script>

<template>
    <div class="mx-auto max-w-5xl px-4 pt-8">
        <Head title="Retaguarda" />
        <h1 class="text-[1.75rem] font-bold">Retaguarda</h1>
        <p class="mt-2 text-tinta-suave">Escolha no menu o conteúdo do portal que deseja gerenciar.</p>
    </div>
</template>
