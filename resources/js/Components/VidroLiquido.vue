<script setup lang="ts">
import { onMounted, ref } from 'vue';

// A refração do vidro usa o filtro SVG #lente-vidro no backdrop-filter, que só o Chromium aplica.
const lente = ref<boolean>(false);
onMounted(() => {
    const agente = (navigator as Navigator & { userAgentData?: { brands: { brand: string }[] } }).userAgentData;
    lente.value = agente?.brands.some((marca) => marca.brand === 'Chromium') ?? false;
});

// O brilho do vidro acompanha o ponteiro (variáveis --mx e --my lidas em app.css).
const iluminarVidro = (evento: PointerEvent): void => {
    const vidro = (evento.target as Element | null)?.closest<HTMLElement>('.vidro');
    if (!vidro) return;
    const caixa = vidro.getBoundingClientRect();
    if (!caixa.width) return;
    vidro.style.setProperty('--mx', `${(((evento.clientX - caixa.left) / caixa.width) * 100).toFixed(1)}%`);
    vidro.style.setProperty('--my', `${(((evento.clientY - caixa.top) / caixa.height) * 100).toFixed(1)}%`);
};
</script>

<template>
    <!-- Vidro líquido da página inicial (.vidro em app.css), com refração e brilho, para outras páginas que usam o vidro. -->
    <div class="home font-montserrat overflow-x-clip" :class="{ lente }" @pointermove="iluminarVidro">
        <svg aria-hidden="true" focusable="false" class="absolute size-0">
            <filter id="lente-vidro" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
                <feTurbulence type="fractalNoise" baseFrequency="0.006 0.01" numOctaves="2" seed="11" result="ruido" />
                <feGaussianBlur in="ruido" stdDeviation="3" result="suave" />
                <feDisplacementMap in="SourceGraphic" in2="suave" scale="34" xChannelSelector="R" yChannelSelector="G" />
            </filter>
        </svg>
        <slot />
    </div>
</template>
