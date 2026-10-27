<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { LinkPaginacao } from '../../types';

const props = defineProps<{
    /** Título da tabela para o leitor de tela. */
    legenda: string;
    links: LinkPaginacao[];
    total: number;
    de: number | null;
    ate: number | null;
    /** Texto quando a lista (já filtrada) está vazia. */
    vazio: string;
}>();

// O primeiro e o último link do Laravel são "anterior" e "próxima"; o texto deles vem em inglês, então é trocado aqui.
const paginas = computed(() => props.links.slice(1, -1));
const anterior = computed(() => props.links[0]);
const proxima = computed(() => props.links.at(-1));
const temPaginas = computed(() => paginas.value.length > 1);

const classeSeta =
    'pressionavel inline-flex min-h-11 items-center gap-1.5 rounded-pill border border-tinta-suave/70 bg-superficie px-4 text-[0.8125rem] font-bold tracking-[0.08em] uppercase hover:border-acao hover:text-acao';
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

        <div v-if="total > 0" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[0.875rem] text-tinta-suave">
                Mostrando {{ de }} a {{ ate }} de {{ total }}
            </p>

            <nav v-if="temPaginas" aria-label="Paginação" class="flex flex-wrap items-center gap-2">
                <Link v-if="anterior?.url" :href="anterior.url" preserve-state :class="classeSeta">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m15 6-6 6 6 6" />
                    </svg>
                    Anterior
                </Link>

                <template v-for="(pagina, indice) in paginas" :key="indice">
                    <span v-if="!pagina.url" aria-hidden="true" class="px-1 text-tinta-suave">…</span>
                    <Link
                        v-else
                        :href="pagina.url"
                        preserve-state
                        class="pressionavel inline-flex min-h-11 min-w-11 items-center justify-center rounded-pill px-3 font-semibold tabular-nums hover:bg-nevoa aria-[current=page]:bg-acao aria-[current=page]:text-sobre-acao"
                        :aria-current="pagina.active ? 'page' : undefined"
                    >
                        <span class="sr-only">Página </span>{{ pagina.label }}
                    </Link>
                </template>

                <Link v-if="proxima?.url" :href="proxima.url" preserve-state :class="classeSeta">
                    Próxima
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m9 6 6 6-6 6" />
                    </svg>
                </Link>
            </nav>
        </div>
    </div>
</template>
