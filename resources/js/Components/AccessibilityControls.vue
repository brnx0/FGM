<script lang="ts">
import { ref } from 'vue';

type Tema = 'claro' | 'escuro';

// Estado do módulo, compartilhado entre instâncias: a home tem uma no topo e outra no menu.
const fonte = ref<string>('100');
const contraste = ref<boolean>(false);
const escuro = ref<boolean>(false);
// Escolha feita nesta página quando o armazenamento não está disponível.
let temaEscolhido: Tema | null = null;
</script>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue';

withDefaults(
    defineProps<{
        // 'barra': faixa institucional (A- / A / A+ e rótulos); 'pilula': topo da home, só ícones;
        // 'painel': lista com rótulos sobre fundo claro (botão de acessibilidade do portal do proponente).
        variante?: 'barra' | 'pilula' | 'painel';
    }>(),
    { variante: 'barra' },
);

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

// A pílula só tem A− e A+: cada toque anda um passo na mesma escala de tamanhos.
const escala = tamanhos.map((t) => t.valor);
const noMinimo = computed<boolean>(() => fonte.value === escala[0]);
const noMaximo = computed<boolean>(() => fonte.value === escala[escala.length - 1]);
const passoFonte = (direcao: 1 | -1): void => {
    const destino = escala[escala.indexOf(fonte.value) + direcao];
    if (destino) aplicarFonte(destino);
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
    <div
        v-if="variante === 'pilula'"
        class="flex items-center gap-0.5 rounded-pill border border-white/15 bg-white/10 px-1 text-white"
        role="group"
        aria-label="Acessibilidade"
    >
        <button
            type="button"
            class="pressionavel inline-flex size-11 items-center justify-center rounded-pill text-[0.8125rem] font-semibold hover:bg-white/10 aria-disabled:cursor-default aria-disabled:opacity-50 aria-disabled:hover:bg-transparent"
            aria-label="A−, diminuir tamanho do texto"
            title="A−, diminuir tamanho do texto"
            :aria-disabled="noMinimo ? 'true' : 'false'"
            @click="passoFonte(-1)"
        >
            A−
        </button>
        <button
            type="button"
            class="pressionavel inline-flex size-11 items-center justify-center rounded-pill text-[0.9375rem] font-semibold hover:bg-white/10 aria-disabled:cursor-default aria-disabled:opacity-50 aria-disabled:hover:bg-transparent"
            aria-label="A+, aumentar tamanho do texto"
            title="A+, aumentar tamanho do texto"
            :aria-disabled="noMaximo ? 'true' : 'false'"
            @click="passoFonte(1)"
        >
            A+
        </button>
        <button
            type="button"
            class="pressionavel inline-flex size-11 items-center justify-center rounded-pill hover:bg-white/10"
            :aria-pressed="contraste ? 'true' : 'false'"
            aria-label="Alto contraste"
            title="Alto contraste"
            @click="alternarContraste"
        >
            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="1.8">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor" stroke="none" />
            </svg>
        </button>
        <button
            type="button"
            class="pressionavel inline-flex size-11 items-center justify-center rounded-pill hover:bg-white/10"
            :aria-pressed="escuro ? 'true' : 'false'"
            aria-label="Modo noturno"
            title="Modo noturno"
            @click="alternarEscuro"
        >
            <svg v-if="!escuro" aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
            </svg>
            <svg v-else aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <circle cx="12" cy="12" r="4" />
                <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
            </svg>
        </button>
    </div>

    <div v-else-if="variante === 'painel'" class="flex flex-col gap-4" role="group" aria-label="Acessibilidade">
        <div class="flex flex-col gap-2">
            <p class="text-[0.8125rem] font-semibold text-tinta-suave">Tamanho do texto</p>
            <div class="grid grid-cols-3 gap-1 rounded-pill bg-parchment p-1" role="group" aria-label="Tamanho do texto">
                <button
                    v-for="t in tamanhos"
                    :key="t.valor"
                    type="button"
                    class="pressionavel inline-flex min-h-11 items-center justify-center rounded-pill text-[0.9375rem] font-bold"
                    :class="fonte === t.valor ? 'bg-superficie text-acao ring-1 ring-hairline' : 'text-tinta-suave hover:text-home-tinta'"
                    :aria-label="t.rotulo"
                    :title="t.rotulo"
                    :aria-pressed="fonte === t.valor ? 'true' : 'false'"
                    @click="aplicarFonte(t.valor)"
                >
                    {{ t.texto }}
                </button>
            </div>
        </div>

        <div class="flex flex-col divide-y divide-hairline border-t border-hairline">
            <button
                type="button"
                class="flex min-h-12 items-center justify-between gap-4 py-3 text-left font-semibold"
                :aria-pressed="contraste ? 'true' : 'false'"
                @click="alternarContraste"
            >
                <span class="flex items-start gap-3">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0 text-acao" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor" stroke="none" />
                    </svg>
                    <span class="flex flex-col">
                        Alto contraste
                        <span class="text-[0.8125rem] font-normal text-tinta-suave">Texto claro sobre fundo preto</span>
                    </span>
                </span>
                <span aria-hidden="true" class="relative inline-flex h-6 w-10 shrink-0 rounded-pill transition-colors motion-reduce:transition-none" :class="contraste ? 'bg-acao' : 'bg-hairline'">
                    <span
                        class="absolute top-0.5 left-0.5 size-5 rounded-full border border-hairline bg-superficie transition-transform motion-reduce:transition-none"
                        :class="{ 'translate-x-4': contraste }"
                    ></span>
                </span>
            </button>
            <button
                type="button"
                class="flex min-h-12 items-center justify-between gap-4 py-3 text-left font-semibold"
                :aria-pressed="escuro ? 'true' : 'false'"
                @click="alternarEscuro"
            >
                <span class="flex items-start gap-3">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0 text-acao" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z" />
                    </svg>
                    <span class="flex flex-col">
                        Modo noturno
                        <span class="text-[0.8125rem] font-normal text-tinta-suave">Cores escuras, para pouca luz</span>
                    </span>
                </span>
                <span aria-hidden="true" class="relative inline-flex h-6 w-10 shrink-0 rounded-pill transition-colors motion-reduce:transition-none" :class="escuro ? 'bg-acao' : 'bg-hairline'">
                    <span
                        class="absolute top-0.5 left-0.5 size-5 rounded-full border border-hairline bg-superficie transition-transform motion-reduce:transition-none"
                        :class="{ 'translate-x-4': escuro }"
                    ></span>
                </span>
            </button>
        </div>
    </div>

    <div v-else class="flex items-center" role="group" aria-label="Acessibilidade">
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
