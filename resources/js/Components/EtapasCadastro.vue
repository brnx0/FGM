<script setup lang="ts">
defineProps<{
    // Etapa em que a pessoa está, de 1 a 4; as anteriores aparecem como concluídas.
    atual: number;
}>();

const etapas = ['Conta gov.br', 'Seus dados', 'Pessoa Jurídica (opcional)', 'Inscrição nos editais'];
</script>

<template>
    <!-- Fica sobre a faixa azul-marinho do portal, em vidro. -->
    <nav aria-label="Etapas do cadastro">
        <ol class="vidro grid gap-1 rounded-[22px] p-1.5 sm:grid-cols-2 lg:grid-cols-4">
            <li
                v-for="(etapa, i) in etapas"
                :key="etapa"
                class="flex min-h-12 items-center gap-3 rounded-[16px] px-3 py-2"
                :class="i + 1 === atual ? 'botao-claro bg-white text-home-azul' : 'text-white/85'"
                :aria-current="i + 1 === atual ? 'step' : undefined"
            >
                <span
                    aria-hidden="true"
                    class="inline-flex size-7 shrink-0 items-center justify-center rounded-full text-[0.75rem] font-bold"
                    :class="{
                        'bg-situacao-aberto text-white': i + 1 < atual,
                        'bg-home-azul text-white': i + 1 === atual,
                        'border border-white/40': i + 1 > atual,
                    }"
                >
                    <svg v-if="i + 1 < atual" viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m5 12 5 5 9-10" />
                    </svg>
                    <template v-else>{{ i + 1 }}</template>
                </span>
                <span class="text-[0.8125rem] leading-tight font-semibold">
                    {{ etapa }}<span v-if="i + 1 < atual" class="sr-only"> (concluída)</span>
                </span>
            </li>
        </ol>
    </nav>
</template>
