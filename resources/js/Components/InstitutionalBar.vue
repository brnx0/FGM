<script setup lang="ts">
import AccessibilityControls from './AccessibilityControls.vue';
</script>

<template>
    <div class="barra-institucional bg-profundo text-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4">
            <p class="flex items-center gap-2 py-2 text-[0.8125rem] leading-tight">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6z" />
                    <path d="m9 12 2 2 4-4" />
                </svg>
                <span>
                    <!-- No celular o começo fica só para leitores de tela, para caber em uma linha. -->
                    <span class="sr-only sm:not-sr-only">Site oficial da </span>Prefeitura de Salvador<span class="sr-only md:not-sr-only"> · Fundação Gregório de Mattos</span>
                </span>
            </p>
            <AccessibilityControls />
        </div>
    </div>
</template>
