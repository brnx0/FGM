<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

withDefaults(
    defineProps<{
        size?: 'sm' | 'lg';
        /** Início do login: o do portal do proponente, ou o da retaguarda. */
        href?: string;
    }>(),
    { size: 'lg', href: '/auth/govbr/redirect' },
);

/** Depois do clique, até a página do gov.br abrir: mostra que está indo e evita o segundo clique. */
const abrindo = ref(false);

const aoClicar = (evento: MouseEvent): void => {
    if (abrindo.value) {
        evento.preventDefault();
        return;
    }
    // Abrir em outra aba (Ctrl/Cmd/Shift ou botão do meio) não sai desta página.
    if (evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.button !== 0) {
        return;
    }
    abrindo.value = true;
};

// Voltar do gov.br pelo navegador pode restaurar a página como estava (cache): o botão volta ao normal.
const aoMostrarPagina = (): void => {
    abrindo.value = false;
};

onMounted(() => window.addEventListener('pageshow', aoMostrarPagina));
onBeforeUnmount(() => window.removeEventListener('pageshow', aoMostrarPagina));
</script>

<template>
    <!-- Link comum (não Inertia): a rota redireciona para fora do portal. -->
    <a
        :href="href"
        :aria-busy="abrindo || undefined"
        :aria-disabled="abrindo || undefined"
        class="pressionavel inline-flex min-h-11 items-center justify-center gap-2 rounded-pill bg-govbr font-semibold whitespace-nowrap text-sobre-govbr no-underline hover:brightness-110 aria-busy:cursor-wait aria-busy:opacity-70"
        :class="size === 'sm' ? 'px-4 text-[0.9375rem]' : 'min-h-12 px-6 text-corpo'"
        @click="aoClicar"
    >
        <svg
            aria-hidden="true"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            :class="size === 'sm' ? 'size-4' : 'size-5'"
        >
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21a8 8 0 0 1 16 0" />
        </svg>
        {{ abrindo ? 'Abrindo o gov.br…' : 'Entrar com gov.br' }}
    </a>
</template>
