<script setup lang="ts">
import type { TipoCadastro } from '../types';

defineProps<{
    tipo: TipoCadastro;
}>();
</script>

<template>
    <svg v-if="tipo === 'pessoa-fisica'" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="8" r="4" />
        <path d="M4 21a8 8 0 0 1 16 0" />
    </svg>
    <svg v-else aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="4" y="3" width="11" height="18" rx="1" />
        <path d="M15 9h4a1 1 0 0 1 1 1v11M2 21h20M8 7h3M8 11h3M8 15h3" />
    </svg>
</template>
