<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const erro = computed(() => usePage().props.flash?.erro);
</script>

<template>
    <div v-if="erro" role="alert" class="mx-auto mt-6 max-w-5xl px-4">
        <p class="rounded-card border border-erro-borda bg-erro-fundo px-5 py-4 text-erro-texto">
            {{ erro }}
        </p>
    </div>
</template>
