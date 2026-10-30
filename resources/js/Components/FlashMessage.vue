<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

type Tipo = 'sucesso' | 'aviso' | 'erro';
type Aviso = { tipo: Tipo; texto: string };

/** Só o sucesso some sozinho; erro e aviso costumam pedir uma ação e ficam até a pessoa fechar. */
const TEMPO_DO_SUCESSO_MS = 5000;
/** Distância da pílula até o cabeçalho (ou até o topo da tela, depois que ele sai de vista). */
const FOLGA_PX = 12;

const page = usePage();
const ancora = ref<HTMLElement | null>(null);
const atual = ref<Aviso | null>(null);
const topo = ref(16);

let temporizador: ReturnType<typeof setTimeout> | undefined;
let restante = TEMPO_DO_SUCESSO_MS;
let inicio = 0;
let quadro = 0;

// A mensagem nova (de outro salvamento, mesmo com o mesmo texto) substitui a anterior.
const daResposta = (): Aviso | null => {
    const flash = page.props.flash;
    if (flash?.erro) return { tipo: 'erro', texto: flash.erro };
    if (flash?.aviso) return { tipo: 'aviso', texto: flash.aviso };
    if (flash?.sucesso) return { tipo: 'sucesso', texto: flash.sucesso };
    return null;
};

const parar = (): void => {
    clearTimeout(temporizador);
    temporizador = undefined;
};

const contar = (): void => {
    parar();
    if (atual.value?.tipo !== 'sucesso') return;
    inicio = Date.now();
    temporizador = setTimeout(fechar, restante);
};

// Mouse ou foco do teclado na pílula seguram o sucesso na tela; ao sair, conta o que faltava.
const pausar = (): void => {
    if (temporizador === undefined) return;
    restante -= Date.now() - inicio;
    parar();
};

function fechar(): void {
    parar();
    atual.value = null;
}

// Logo abaixo do cabeçalho (o elemento onde o componente está, em geral o <main>) e, com a página rolada, no topo.
const posicionar = (): void => {
    cancelAnimationFrame(quadro);
    quadro = requestAnimationFrame(() => {
        const referencia = ancora.value?.parentElement;
        topo.value = Math.max(16, (referencia?.getBoundingClientRect().top ?? 0) + FOLGA_PX);
    });
};

// Entra depois de montada a região anunciada, para o leitor de tela perceber a mudança. Resposta sem mensagem (um
// recarregamento, um erro de validação) não apaga a pílula que já está na tela.
const mostrar = (aviso: Aviso | null): void => {
    if (aviso === null) return;
    parar();
    atual.value = null;
    void nextTick(() => {
        posicionar();
        atual.value = aviso;
        restante = TEMPO_DO_SUCESSO_MS;
        contar();
    });
};

watch(() => page.props.flash, () => mostrar(daResposta()));

onMounted(() => {
    window.addEventListener('scroll', posicionar, { passive: true });
    window.addEventListener('resize', posicionar, { passive: true });
    mostrar(daResposta());
});

onBeforeUnmount(() => {
    parar();
    cancelAnimationFrame(quadro);
    window.removeEventListener('scroll', posicionar);
    window.removeEventListener('resize', posicionar);
});

const icone = computed(() => {
    switch (atual.value?.tipo) {
        case 'erro':
            return { caminho: 'M8 8l8 8M16 8l-8 8', fundo: 'bg-pilula-erro' };
        case 'aviso':
            return { caminho: 'M12 7v6M12 17h.01', fundo: 'bg-pilula-aviso' };
        default:
            return { caminho: 'm7 12.5 3.5 3.5L17 9', fundo: 'bg-pilula-sucesso' };
    }
});
</script>

<template>
    <!-- Sem caixa própria: não ocupa espaço onde está (o <main> ou o topo da home), só marca a posição da pílula. -->
    <div ref="ancora" class="contents">
        <Teleport to="body">
            <div class="pointer-events-none fixed inset-x-0 z-[60] grid justify-items-center px-4" :style="{ top: `${topo}px` }">
                <!-- Regiões sempre presentes: o erro é anunciado na hora; sucesso e aviso, sem interromper. As duas ocupam
                     o mesmo lugar, para a pílula nova entrar por cima da que está saindo. -->
                <div role="alert" class="col-start-1 row-start-1 flex max-w-full justify-center">
                    <Transition
                        enter-from-class="-translate-y-3 opacity-0"
                        leave-to-class="-translate-y-3 opacity-0"
                        enter-active-class="transition duration-300 ease-out motion-reduce:transition-none"
                        leave-active-class="transition duration-200 ease-in motion-reduce:transition-none"
                    >
                        <div v-if="atual?.tipo === 'erro'" :key="atual.texto" class="pilula-aviso pointer-events-auto">
                            <span aria-hidden="true" class="pilula-aviso-icone" :class="icone.fundo">
                                <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <path :d="icone.caminho" />
                                </svg>
                            </span>
                            <p class="min-w-0">{{ atual.texto }}</p>
                            <button type="button" class="pilula-aviso-fechar" @click="fechar">
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                    <path d="M7 7l10 10M17 7 7 17" />
                                </svg>
                                <span class="sr-only">Fechar aviso</span>
                            </button>
                        </div>
                    </Transition>
                </div>
                <div role="status" class="col-start-1 row-start-1 flex max-w-full justify-center">
                    <Transition
                        enter-from-class="-translate-y-3 opacity-0"
                        leave-to-class="-translate-y-3 opacity-0"
                        enter-active-class="transition duration-300 ease-out motion-reduce:transition-none"
                        leave-active-class="transition duration-200 ease-in motion-reduce:transition-none"
                    >
                        <div
                            v-if="atual && atual.tipo !== 'erro'"
                            :key="atual.texto"
                            class="pilula-aviso pointer-events-auto"
                            @mouseenter="pausar"
                            @mouseleave="contar"
                            @focusin="pausar"
                            @focusout="contar"
                        >
                            <span aria-hidden="true" class="pilula-aviso-icone" :class="icone.fundo">
                                <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <path :d="icone.caminho" />
                                </svg>
                            </span>
                            <p class="min-w-0">{{ atual.texto }}</p>
                            <button v-if="atual.tipo === 'aviso'" type="button" class="pilula-aviso-fechar" @click="fechar">
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                    <path d="M7 7l10 10M17 7 7 17" />
                                </svg>
                                <span class="sr-only">Fechar aviso</span>
                            </button>
                        </div>
                    </Transition>
                </div>
            </div>
        </Teleport>
    </div>
</template>
