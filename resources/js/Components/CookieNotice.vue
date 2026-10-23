<script setup>
import { onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const CHAVE = 'cookieAviso';
const visivel = ref(false);

onMounted(() => {
    try {
        visivel.value = localStorage.getItem(CHAVE) !== '1';
    } catch (e) {
        visivel.value = true;
    }
});

const dispensar = () => {
    visivel.value = false;
    try {
        localStorage.setItem(CHAVE, '1');
    } catch (e) {
        // Sem armazenamento: o aviso volta na próxima visita.
    }
};
</script>

<template>
    <section
        v-if="visivel"
        aria-label="Aviso de cookies"
        class="fixed inset-x-4 bottom-4 z-40 mx-auto max-w-lg rounded-card border border-hairline bg-fundo p-5 pr-14 sm:inset-x-auto sm:right-6 sm:bottom-6"
    >
        <button
            type="button"
            class="pressionavel absolute top-2 right-2 inline-flex size-11 items-center justify-center rounded-pill text-tinta-suave hover:bg-parchment"
            aria-label="Fechar aviso de cookies"
            @click="dispensar"
        >
            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M6 6l12 12M18 6 6 18" />
            </svg>
        </button>
        <p class="text-[0.9375rem]">
            Este portal usa apenas cookies necessários para manter você conectado. Não usamos cookies de publicidade nem de rastreamento.
        </p>
        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2">
            <button
                type="button"
                class="pressionavel inline-flex min-h-11 items-center rounded-pill bg-acao px-5 font-semibold text-sobre-acao hover:bg-acao-escuro"
                @click="dispensar"
            >
                Entendi
            </button>
            <Link href="/privacidade" class="font-semibold text-acao hover:underline">Aviso de privacidade</Link>
        </div>
    </section>
</template>
