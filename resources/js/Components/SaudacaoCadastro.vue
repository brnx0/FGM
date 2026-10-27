<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps<{
    /** id do h1, para o aria-labelledby da FaixaPortal. */
    idTitulo: string;
    /** "Olá, Maria" no lugar do título de edição: só no primeiro preenchimento dos dados pessoais. */
    saudacao?: boolean;
}>();

const primeiroNome = computed(() => usePage().props.auth.user?.nome.trim().split(/\s+/)[0] ?? '');
</script>

<template>
    <!-- Cabeçalho das etapas do cadastro. No modo edição (cadastro finalizado) é o mesmo em todas as abas,
         para nada mudar de lugar ao trocar de etapa; o texto de baixo pode ser trocado pelo slot. -->
    <div class="flex flex-col gap-3">
        <p class="text-[0.75rem] font-semibold tracking-[0.24em] text-white/75 uppercase">Portal do proponente</p>
        <h1 :id="idTitulo" class="font-barlow text-[2.6rem] leading-[0.95] font-extrabold tracking-[0.01em] break-words uppercase md:text-[3.5rem]">
            <template v-if="saudacao">Olá, {{ primeiroNome }}</template>
            <template v-else>Edição dos dados cadastrais</template>
        </h1>
        <p class="max-w-2xl text-base leading-[1.7] text-white/85 md:text-[1.0625rem]">
            <slot>Confira e atualize seus dados abaixo.</slot>
        </p>
    </div>
</template>
