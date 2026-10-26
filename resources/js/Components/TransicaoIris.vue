<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

// Liga links com data-iris (Cadastre-se e Entrar da home) à página seguinte. No caminho até ela
// a página é recarregada inteira (redirect do gov.br), por isso o ponto do clique vai pelo sessionStorage.
const CHAVE = 'transicaoIris';
// Duração da íris cobrindo a tela no anexo, antes da troca de tela.
const COBRIR_MS = 1000;
// Fim da saída: opacidade (0,7 s) e subida do conteúdo (0,28 s + 0,9 s) do anexo.
const DESCOBRIR_MS = 1200;
// Chegada mais antiga que isso não é desta transição (ex.: volta do gov.br de verdade).
const VALIDADE_MS = 10000;

type Fase = 'parada' | 'entrando' | 'coberta' | 'saindo';
type Chegada = { x: number; y: number; de: string; em: number };

const iris = ref<HTMLElement | null>(null);
const fase = ref<Fase>('parada');
const x = ref<number>(0);
const y = ref<number>(0);

let relogio: number | undefined;

const lerChegada = (): Chegada | null => {
    try {
        const salva = sessionStorage.getItem(CHAVE);
        sessionStorage.removeItem(CHAVE);
        const chegada = salva ? (JSON.parse(salva) as Chegada) : null;
        // Na mesma página de onde saiu (voltar do gov.br), não há o que descobrir.
        return chegada && Date.now() - chegada.em < VALIDADE_MS && chegada.de !== location.pathname ? chegada : null;
    } catch {
        return null;
    }
};

// Na chegada a tela já nasce coberta, sem transição, e a íris se desfaz depois da primeira pintura.
const chegada = lerChegada();
if (chegada) {
    x.value = chegada.x;
    y.value = chegada.y;
    fase.value = 'coberta';
    document.documentElement.classList.add('chegada-iris');
}

const descobrir = (): void => {
    requestAnimationFrame(() =>
        requestAnimationFrame(() => {
            fase.value = 'saindo';
            relogio = window.setTimeout(() => {
                fase.value = 'parada';
                document.documentElement.classList.remove('chegada-iris');
            }, DESCOBRIR_MS);
        }),
    );
};

const aoClicar = async (evento: MouseEvent): Promise<void> => {
    const link = (evento.target as Element | null)?.closest<HTMLAnchorElement>('a[data-iris]');
    if (!link || evento.defaultPrevented || evento.button !== 0 || evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    evento.preventDefault();
    if (fase.value !== 'parada') return;

    // Pelo teclado o clique vem sem coordenadas: a íris abre do meio do botão. Dentro da janela,
    // o círculo de 150vmax cobre a tela toda de qualquer ponto.
    const caixa = link.getBoundingClientRect();
    const naJanela = (valor: number, limite: number): number => Math.min(Math.max(valor, 0), limite);
    x.value = naJanela(evento.detail ? evento.clientX : caixa.left + caixa.width / 2, window.innerWidth);
    y.value = naJanela(evento.detail ? evento.clientY : caixa.top + caixa.height / 2, window.innerHeight);

    // O círculo precisa estar no ponto novo, ainda fechado, antes de abrir.
    await nextTick();
    if (iris.value) void getComputedStyle(iris.value).clipPath;
    fase.value = 'entrando';

    relogio = window.setTimeout(() => {
        try {
            sessionStorage.setItem(CHAVE, JSON.stringify({ x: x.value, y: y.value, de: location.pathname, em: Date.now() } satisfies Chegada));
        } catch {}
        window.location.assign(link.href);
    }, COBRIR_MS);
};

// Voltar do gov.br pode restaurar esta página do cache como estava: coberta.
const aoMostrarPagina = (evento: PageTransitionEvent): void => {
    if (!evento.persisted) return;
    window.clearTimeout(relogio);
    fase.value = 'parada';
};

onMounted(() => {
    document.addEventListener('click', aoClicar);
    window.addEventListener('pageshow', aoMostrarPagina);
    if (fase.value === 'coberta') descobrir();
});

onBeforeUnmount(() => {
    window.clearTimeout(relogio);
    document.removeEventListener('click', aoClicar);
    window.removeEventListener('pageshow', aoMostrarPagina);
    document.documentElement.classList.remove('chegada-iris');
});
</script>

<template>
    <div ref="iris" class="iris" :data-fase="fase" :style="{ '--ix': `${x}px`, '--iy': `${y}px` }" aria-hidden="true">
        <span class="iris-anel"></span>
        <img src="/images/logo-fgm-prefeitura-branco.png" alt="" width="1395" height="210" class="iris-logo" />
    </div>
</template>
