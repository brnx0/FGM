<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { LinkPaginacao } from '../../types';

const props = defineProps<{
    links: LinkPaginacao[];
    total: number;
    de: number | null;
    ate: number | null;
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
    <!-- Rodapé das listas paginadas: total à vista e links de página (TabelaPaginada e cards do portal). -->
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
</template>
