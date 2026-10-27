<script setup lang="ts">
import Paginacao from './Paginacao.vue';
import type { LinkPaginacao } from '../../types';

defineProps<{
    /** Título da tabela para o leitor de tela. */
    legenda: string;
    links: LinkPaginacao[];
    total: number;
    de: number | null;
    ate: number | null;
    /** Texto quando a lista (já filtrada) está vazia. */
    vazio: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- relative: os textos sr-only (absolutos) ficam presos à rolagem e não alargam a página. -->
        <div class="relative overflow-x-auto rounded-card border border-hairline bg-superficie">
            <table class="w-full min-w-[50rem] border-collapse text-left text-[0.9375rem]">
                <caption class="sr-only">{{ legenda }}</caption>
                <thead class="border-b border-hairline bg-nevoa text-[0.75rem] font-bold tracking-[0.08em] text-tinta-suave uppercase">
                    <slot name="cabecalho" />
                </thead>
                <tbody class="divide-y divide-hairline">
                    <slot />
                    <tr v-if="total === 0">
                        <td colspan="100" class="px-4 py-10 text-center text-tinta-suave">{{ vazio }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Paginacao :links="links" :total="total" :de="de" :ate="ate" />
    </div>
</template>
