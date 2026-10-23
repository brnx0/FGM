<script setup>
import { onMounted, ref } from 'vue';

// Mesmas chaves lidas pelo script de app.blade.php antes da primeira pintura.
const CHAVE_FONTE = 'a11yFonte';
const CHAVE_CONTRASTE = 'a11yContraste';

// Os rótulos começam pelo texto visível (WCAG 2.5.3, rótulo no nome).
const tamanhos = [
    { valor: '90', texto: 'A-', rotulo: 'A-, diminuir tamanho do texto' },
    { valor: '100', texto: 'A', rotulo: 'A, tamanho padrão do texto' },
    { valor: '115', texto: 'A+', rotulo: 'A+, aumentar tamanho do texto' },
];

const fonte = ref('100');
const contraste = ref(false);

const salvar = (chave, valor) => {
    try {
        localStorage.setItem(chave, valor);
    } catch (e) {
        // Armazenamento indisponível (modo privado, bloqueio): vale só nesta página.
    }
};

const aplicarFonte = (valor) => {
    fonte.value = valor;
    document.documentElement.style.fontSize = valor === '100' ? '' : `${valor}%`;
    salvar(CHAVE_FONTE, valor);
};

const alternarContraste = () => {
    contraste.value = !contraste.value;
    document.documentElement.classList.toggle('alto-contraste', contraste.value);
    salvar(CHAVE_CONTRASTE, contraste.value ? '1' : '0');
};

onMounted(() => {
    // O script do <head> já aplicou as preferências; aqui só refletimos o estado.
    const atual = document.documentElement.style.fontSize.replace('%', '');
    fonte.value = ['90', '115'].includes(atual) ? atual : '100';
    contraste.value = document.documentElement.classList.contains('alto-contraste');
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
    </div>
</template>
