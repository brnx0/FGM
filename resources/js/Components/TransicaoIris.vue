<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

// Liga links com data-iris (Cadastre-se e Entrar da home) e formulários com data-iris (Sair) à página seguinte.
// No caminho até ela a página é recarregada inteira (redirect do gov.br, ou o POST do logout), por isso o ponto
// do clique vai pelo sessionStorage.
const CHAVE = 'transicaoIris';
// Duração da íris cobrindo a tela no anexo, antes da troca de tela.
const COBRIR_MS = 1000;
// Na saída a tela fica coberta um pouco mais, para dar tempo de ler a despedida.
const DESPEDIDA_MS = 1800;
// Fim da saída: opacidade (0,7 s) e subida do conteúdo (0,28 s + 0,9 s) do anexo.
const DESCOBRIR_MS = 1200;
// Chegada mais antiga que isso não é desta transição (ex.: volta do gov.br de verdade).
const VALIDADE_MS = 10000;

type Fase = 'parada' | 'entrando' | 'coberta' | 'saindo';
type Chegada = { x: number; y: number; de: string; em: number; despedida?: boolean };

const iris = ref<HTMLElement | null>(null);
const fase = ref<Fase>('parada');
const x = ref<number>(0);
const y = ref<number>(0);
const despedida = ref<boolean>(false);

let relogio: number | undefined;

const lerChegada = (): Chegada | null => {
    try {
        const salva = sessionStorage.getItem(CHAVE);
        sessionStorage.removeItem(CHAVE);
        const chegada = salva ? (JSON.parse(salva) as Chegada) : null;
        // Na mesma página de onde saiu (voltar do gov.br), não há o que descobrir; a saída volta à home de propósito.
        return chegada && Date.now() - chegada.em < VALIDADE_MS && (chegada.despedida || chegada.de !== location.pathname) ? chegada : null;
    } catch {
        return null;
    }
};

// Na chegada a tela já nasce coberta, sem transição, e a íris se desfaz depois da primeira pintura.
const chegada = lerChegada();
if (chegada) {
    x.value = chegada.x;
    y.value = chegada.y;
    despedida.value = Boolean(chegada.despedida);
    fase.value = 'coberta';
    document.documentElement.classList.add('chegada-iris');
}

const descobrir = (): void => {
    requestAnimationFrame(() =>
        requestAnimationFrame(() => {
            fase.value = 'saindo';
            relogio = window.setTimeout(() => {
                fase.value = 'parada';
                despedida.value = false;
                document.documentElement.classList.remove('chegada-iris');
            }, DESCOBRIR_MS);
        }),
    );
};

// Abre a íris a partir do ponto (dentro da janela, o círculo de 150vmax cobre a tela toda de qualquer ponto)
// e, com a tela coberta, guarda o ponto para a página seguinte e segue.
const cobrir = async (pontoX: number, pontoY: number, comDespedida: boolean, seguir: () => void): Promise<void> => {
    const naJanela = (valor: number, limite: number): number => Math.min(Math.max(valor, 0), limite);
    x.value = naJanela(pontoX, window.innerWidth);
    y.value = naJanela(pontoY, window.innerHeight);
    despedida.value = comDespedida;

    // O círculo precisa estar no ponto novo, ainda fechado, antes de abrir.
    await nextTick();
    if (iris.value) void getComputedStyle(iris.value).clipPath;
    fase.value = 'entrando';

    relogio = window.setTimeout(
        () => {
            try {
                const chegada: Chegada = { x: x.value, y: y.value, de: location.pathname, em: Date.now(), despedida: comDespedida };
                sessionStorage.setItem(CHAVE, JSON.stringify(chegada));
            } catch {}
            seguir();
        },
        comDespedida ? DESPEDIDA_MS : COBRIR_MS,
    );
};

const semMovimento = (): boolean => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const aoClicar = (evento: MouseEvent): void => {
    const link = (evento.target as Element | null)?.closest<HTMLAnchorElement>('a[data-iris]');
    if (!link || evento.defaultPrevented || evento.button !== 0 || evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) return;
    if (semMovimento()) return;

    evento.preventDefault();
    if (fase.value !== 'parada') return;

    // Pelo teclado o clique vem sem coordenadas: a íris abre do meio do botão.
    const caixa = link.getBoundingClientRect();
    void cobrir(
        evento.detail ? evento.clientX : caixa.left + caixa.width / 2,
        evento.detail ? evento.clientY : caixa.top + caixa.height / 2,
        false,
        () => window.location.assign(link.href),
    );
};

// O Sair é um formulário (POST com o token): a íris abre do botão, com a despedida, e só então ele é enviado.
// O submit() de código não dispara este evento, então o Voltar da área do proponente continua saindo direto.
const aoEnviar = (evento: SubmitEvent): void => {
    const formulario = evento.target;
    if (!(formulario instanceof HTMLFormElement) || !formulario.hasAttribute('data-iris') || evento.defaultPrevented) return;
    if (semMovimento()) return;

    evento.preventDefault();
    if (fase.value !== 'parada') return;

    const caixa = (evento.submitter ?? formulario).getBoundingClientRect();
    void cobrir(caixa.left + caixa.width / 2, caixa.top + caixa.height / 2, true, () => formulario.submit());
};

// Voltar do gov.br pode restaurar esta página do cache como estava: coberta.
const aoMostrarPagina = (evento: PageTransitionEvent): void => {
    if (!evento.persisted) return;
    window.clearTimeout(relogio);
    fase.value = 'parada';
    despedida.value = false;
};

onMounted(() => {
    document.addEventListener('click', aoClicar);
    document.addEventListener('submit', aoEnviar);
    window.addEventListener('pageshow', aoMostrarPagina);
    if (fase.value === 'coberta') descobrir();
});

onBeforeUnmount(() => {
    window.clearTimeout(relogio);
    document.removeEventListener('click', aoClicar);
    document.removeEventListener('submit', aoEnviar);
    window.removeEventListener('pageshow', aoMostrarPagina);
    document.documentElement.classList.remove('chegada-iris');
});
</script>

<template>
    <div ref="iris" class="iris" :data-fase="fase" :style="{ '--ix': `${x}px`, '--iy': `${y}px` }" aria-hidden="true">
        <span class="iris-anel"></span>
        <img src="/images/logo-fgm-prefeitura-branco.png" alt="" width="1395" height="210" class="iris-logo" />
        <p v-if="despedida" class="iris-despedida">
            <span class="iris-despedida-titulo">Até a próxima!</span>
            <span class="iris-despedida-texto">A Fundação Gregório de Mattos agradece a sua visita.</span>
        </p>
    </div>
</template>
