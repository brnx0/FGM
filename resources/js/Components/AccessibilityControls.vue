<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Mesmas chaves lidas pelo script de app.blade.php antes da primeira pintura.
const CHAVE_FONTE = 'a11yFonte';
const CHAVE_CONTRASTE = 'a11yContraste';
const CHAVE_TEMA = 'tema';

// Os rótulos começam pelo texto visível (WCAG 2.5.3, rótulo no nome).
const tamanhos: { valor: string; texto: string; rotulo: string }[] = [
    { valor: '90', texto: 'A-', rotulo: 'A-, diminuir tamanho do texto' },
    { valor: '100', texto: 'A', rotulo: 'A, tamanho padrão do texto' },
    { valor: '115', texto: 'A+', rotulo: 'A+, aumentar tamanho do texto' },
];

const fonte = ref<string>('100');
const contraste = ref<boolean>(false);
const escuro = ref<boolean>(false);

type Tema = 'claro' | 'escuro';
// Escolha feita nesta página quando o armazenamento não está disponível.
let temaEscolhido: Tema | null = null;
let consultaSistema: MediaQueryList | null = null;

const salvar = (chave: string, valor: string): void => {
    try {
        localStorage.setItem(chave, valor);
    } catch {
        // Armazenamento indisponível (modo privado, bloqueio): vale só nesta página.
    }
};

const lerTema = (): Tema | null => {
    try {
        const valor = localStorage.getItem(CHAVE_TEMA);
        if (valor === 'claro' || valor === 'escuro') return valor;
    } catch {
        // Sem armazenamento: vale a escolha em memória.
    }
    return temaEscolhido;
};

const aplicarEscuro = (ativo: boolean): void => {
    escuro.value = ativo;
    document.documentElement.classList.toggle('escuro', ativo);
};

const alternarEscuro = (): void => {
    const tema: Tema = escuro.value ? 'claro' : 'escuro';
    temaEscolhido = tema;
    aplicarEscuro(tema === 'escuro');
    salvar(CHAVE_TEMA, tema);
};

// Sem escolha salva, o portal acompanha o tema do sistema enquanto a página está aberta.
const aoMudarSistema = (evento: MediaQueryListEvent): void => {
    if (lerTema() === null) aplicarEscuro(evento.matches);
};

const aplicarFonte = (valor: string): void => {
    fonte.value = valor;
    document.documentElement.style.fontSize = valor === '100' ? '' : `${valor}%`;
    salvar(CHAVE_FONTE, valor);
};

const alternarContraste = (): void => {
    contraste.value = !contraste.value;
    document.documentElement.classList.toggle('alto-contraste', contraste.value);
    salvar(CHAVE_CONTRASTE, contraste.value ? '1' : '0');
};

onMounted(() => {
    // O script do <head> já aplicou as preferências; aqui só refletimos o estado.
    const atual = document.documentElement.style.fontSize.replace('%', '');
    fonte.value = ['90', '115'].includes(atual) ? atual : '100';
    contraste.value = document.documentElement.classList.contains('alto-contraste');
    escuro.value = document.documentElement.classList.contains('escuro');

    if (typeof window.matchMedia === 'function') {
        consultaSistema = window.matchMedia('(prefers-color-scheme: dark)');
        // Safari < 14 só tem addListener/removeListener.
        if (typeof consultaSistema.addEventListener === 'function') {
            consultaSistema.addEventListener('change', aoMudarSistema);
        } else {
            consultaSistema.addListener(aoMudarSistema);
        }
    }
});

onBeforeUnmount(() => {
    if (!consultaSistema) return;
    if (typeof consultaSistema.removeEventListener === 'function') {
        consultaSistema.removeEventListener('change', aoMudarSistema);
    } else {
        consultaSistema.removeListener(aoMudarSistema);
    }
});
</script>

<template>
    <div class="flex items-center" role="group" aria-label="Acessibilidade">
        <button
            v-for="t in tamanhos"
            :key="t.valor"
            type="button"
            class="pressionavel inline-flex min-h-11 min-w-11 items-center justify-center rounded-sm px-2 text-[0.875rem] font-bold text-white hover:bg-white/10 aria-pressed:underline aria-pressed:underline-offset-4"
            :aria-label="t.rotulo"
            :title="t.rotulo"
            :aria-pressed="fonte === t.valor ? 'true' : 'false'"
            @click="aplicarFonte(t.valor)"
        >
            {{ t.texto }}
        </button>
        <button
            type="button"
            class="pressionavel ml-1 inline-flex min-h-11 min-w-11 items-center justify-center gap-1.5 rounded-sm px-2 text-[0.875rem] font-semibold text-white hover:bg-white/10"
            :aria-pressed="contraste ? 'true' : 'false'"
            aria-label="Alto contraste"
            @click="alternarContraste"
        >
            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor" stroke="none" />
            </svg>
            <span class="hidden sm:inline">Alto contraste</span>
        </button>
        <button
            type="button"
            class="pressionavel inline-flex min-h-11 min-w-11 items-center justify-center gap-1.5 rounded-sm px-2 text-[0.875rem] font-semibold text-white hover:bg-white/10"
            :aria-pressed="escuro ? 'true' : 'false'"
            aria-label="Modo noturno"
            @click="alternarEscuro"
        >
            <!-- Lua com o modo desligado; sol quando ligado (indica a volta ao claro). -->
            <svg v-if="!escuro" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z" />
            </svg>
            <svg v-else aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle cx="12" cy="12" r="4" />
                <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
            </svg>
            <span class="hidden sm:inline">Modo noturno</span>
        </button>
    </div>
</template>
