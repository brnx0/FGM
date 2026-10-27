<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    // Etapa em que a pessoa está, de 1 a 4; as anteriores aparecem como concluídas.
    atual: number;
    // Cadastro já finalizado: todas as etapas aparecem concluídas e as de 2 a 4 viram links,
    // para revisar os dados ou trocar o tipo de cadastro.
    navegavel?: boolean;
}>();

const etapas: { texto: string; href: string | null }[] = [
    { texto: 'Conta gov.br', href: null },
    { texto: 'Seus dados', href: '/cadastro' },
    { texto: 'Pessoa Jurídica (opcional)', href: '/cadastro/organizacao' },
    { texto: 'Tipo de Cadastro', href: '/cadastro/tipo' },
];

const concluida = (i: number): boolean => (props.navegavel ? i + 1 !== props.atual : i + 1 < props.atual);
const linkDaEtapa = (i: number): string | null => (props.navegavel && i + 1 !== props.atual ? etapas[i].href : null);
</script>

<template>
    <!-- Fica sobre a faixa azul-marinho do portal, em vidro. -->
    <nav aria-label="Etapas do cadastro">
        <ol class="vidro grid gap-1 rounded-[22px] p-1.5 sm:grid-cols-2 lg:grid-cols-4">
            <li v-for="(etapa, i) in etapas" :key="etapa.texto">
                <component
                    :is="linkDaEtapa(i) ? Link : 'div'"
                    :href="linkDaEtapa(i) ?? undefined"
                    class="flex min-h-12 items-center gap-3 rounded-[16px] px-3 py-2 no-underline"
                    :class="[
                        i + 1 === atual ? 'botao-claro bg-white text-home-azul' : 'text-white/85',
                        linkDaEtapa(i) ? 'pressionavel hover:bg-white/15 hover:text-white' : '',
                    ]"
                    :aria-current="i + 1 === atual ? 'step' : undefined"
                >
                    <span
                        aria-hidden="true"
                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-full text-[0.75rem] font-bold"
                        :class="{
                            'bg-situacao-aberto text-white': concluida(i),
                            'bg-home-azul text-white': i + 1 === atual,
                            'border border-white/40': i + 1 !== atual && !concluida(i),
                        }"
                    >
                        <svg v-if="concluida(i)" viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m5 12 5 5 9-10" />
                        </svg>
                        <template v-else>{{ i + 1 }}</template>
                    </span>
                    <span class="text-[0.8125rem] leading-tight font-semibold">
                        {{ etapa.texto }}<span v-if="concluida(i)" class="sr-only"> (concluída)</span>
                    </span>
                </component>
            </li>
        </ol>
    </nav>
</template>
