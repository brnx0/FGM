<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const erro = computed(() => usePage().props.flash?.erro);
const aviso = computed(() => usePage().props.flash?.aviso);
const sucesso = computed(() => usePage().props.flash?.sucesso);
</script>

<template>
    <div v-if="erro" role="alert" class="mx-auto mt-6 max-w-5xl px-4">
        <p class="rounded-card border border-erro-borda bg-erro-fundo px-5 py-4 text-erro-texto">
            {{ erro }}
        </p>
    </div>
    <div v-else-if="aviso" role="status" class="mx-auto mt-6 max-w-5xl px-4">
        <p class="rounded-card border border-aviso-texto/40 bg-aviso-fundo px-5 py-4 text-aviso-texto">
            {{ aviso }}
        </p>
    </div>
    <div v-else-if="sucesso" role="status" class="mx-auto mt-6 max-w-5xl px-4">
        <p class="flex items-start gap-3 rounded-card border border-situacao-aberto bg-superficie px-5 py-4 text-tinta">
            <svg aria-hidden="true" viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0 text-situacao-aberto" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="m5 12 5 5 9-10" />
            </svg>
            {{ sucesso }}
        </p>
    </div>
</template>
