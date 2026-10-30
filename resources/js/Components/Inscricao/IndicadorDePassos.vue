<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { PassoDaInscricao } from '../../types';

const props = defineProps<{
    passos: PassoDaInscricao[];
    /** Id do passo aberto. */
    atual: string;
    /** Endereço da inscrição; cada passo abre em ?passo=<id>. */
    base: string;
}>();

interface PassoNoIndicador extends PassoDaInscricao {
    ehAtual: boolean;
    /** Completo e com todos os anteriores completos: o servidor abre, então vira link. */
    href: string | null;
}

const itens = computed<PassoNoIndicador[]>(() => {
    let anterioresCompletos = true;
    return props.passos.map((passo) => {
        const ehAtual = passo.id === props.atual;
        const href = !ehAtual && passo.completo && anterioresCompletos ? `${props.base}?passo=${encodeURIComponent(passo.id)}` : null;
        anterioresCompletos = anterioresCompletos && passo.completo;
        return { ...passo, ehAtual, href };
    });
});
</script>

<template>
    <!-- Fica sobre a faixa azul-marinho do portal, em vidro, como as etapas do cadastro. -->
    <nav aria-label="Passos da inscrição">
        <ol class="vidro grid gap-1 rounded-[22px] p-1.5 sm:grid-cols-2 lg:grid-cols-[repeat(auto-fit,minmax(11rem,1fr))]">
            <li v-for="(passo, indice) in itens" :key="passo.id">
                <component
                    :is="passo.href ? Link : 'div'"
                    :href="passo.href ?? undefined"
                    :preserve-state="passo.href ? true : undefined"
                    class="flex min-h-12 items-center gap-3 rounded-[16px] px-3 py-2 no-underline"
                    :class="[
                        passo.ehAtual ? 'botao-claro bg-white text-home-azul' : 'text-white/85',
                        passo.href ? 'pressionavel hover:bg-white/15 hover:text-white' : '',
                    ]"
                    :aria-current="passo.ehAtual ? 'step' : undefined"
                >
                    <span
                        aria-hidden="true"
                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-full text-[0.75rem] font-bold"
                        :class="{
                            'bg-situacao-aberto text-white': passo.completo && !passo.ehAtual,
                            'bg-home-azul text-white': passo.ehAtual,
                            'border border-white/40': !passo.ehAtual && !passo.completo,
                        }"
                    >
                        <svg v-if="passo.completo && !passo.ehAtual" viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m5 12 5 5 9-10" />
                        </svg>
                        <template v-else>{{ indice + 1 }}</template>
                    </span>
                    <span class="min-w-0 text-[0.8125rem] leading-tight font-semibold break-words">
                        <span class="sr-only">Passo {{ indice + 1 }} de {{ itens.length }}: </span>{{ passo.rotulo
                        }}<span v-if="passo.completo" class="sr-only"> (concluído)</span>
                    </span>
                </component>
            </li>
        </ol>
    </nav>
</template>
