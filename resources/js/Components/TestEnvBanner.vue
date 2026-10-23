<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Publica a altura da faixa para o topo fixo se posicionar logo abaixo dela.
const faixa = ref<HTMLElement | null>(null);
let observer: ResizeObserver | undefined;

const publicarAltura = (): void => {
    if (!faixa.value) return;
    document.documentElement.style.setProperty('--altura-faixa-teste', `${faixa.value.offsetHeight}px`);
};

onMounted(() => {
    publicarAltura();
    observer = new ResizeObserver(publicarAltura);
    if (faixa.value) observer.observe(faixa.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    document.documentElement.style.removeProperty('--altura-faixa-teste');
});
</script>

<template>
    <div
        ref="faixa"
        role="status"
        class="sticky top-0 z-[60] bg-aviso-fundo px-4 py-2 text-center text-[0.875rem] font-semibold text-aviso-texto"
    >
        Ambiente de teste — login simulado, não use dados reais
    </div>
</template>
