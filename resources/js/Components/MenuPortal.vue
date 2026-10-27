<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

type ItemMenu = { href: string; texto: string; ativoEm: string[] };

// Caminhos das rotas cadastro, portal.editais e portal.projetos (o front não tem helper de rotas nomeadas).
const cadastro: ItemMenu = { href: '/cadastro', texto: 'Cadastro', ativoEm: ['/cadastro', '/organizacoes'] };
const editais: ItemMenu = { href: '/portal/editais', texto: 'Editais', ativoEm: ['/portal/editais'] };
const projetos: ItemMenu = { href: '/portal/projetos', texto: 'Projetos', ativoEm: ['/portal/projetos'] };

const page = usePage();
const tipoCadastro = computed(() => page.props.auth.user?.tipoCadastro ?? null);

// Cadastro é comum aos dois tipos; Editais é do proponente e Projetos, do patrocinador.
const itens = computed<ItemMenu[]>(() => {
    if (tipoCadastro.value === 'proponente') return [cadastro, editais];
    if (tipoCadastro.value === 'patrocinador') return [cadastro, projetos];
    return [];
});

const atual = (item: ItemMenu): boolean =>
    item.ativoEm.some((caminho) => page.url === caminho || page.url.startsWith(`${caminho}/`) || page.url.startsWith(`${caminho}?`));
</script>

<template>
    <!-- Só depois do cadastro finalizado (tipo escolhido na etapa 4). -->
    <nav v-if="itens.length" aria-label="Portal">
        <ul class="flex gap-1 text-[0.8125rem] font-semibold tracking-[0.12em] uppercase md:gap-2">
            <li v-for="item in itens" :key="item.href">
                <Link
                    :href="item.href"
                    class="inline-flex min-h-11 items-center rounded-pill px-4 text-white/85 no-underline hover:bg-white/10 hover:text-white focus-visible:outline-white aria-[current=page]:bg-white aria-[current=page]:text-home-azul"
                    :aria-current="atual(item) ? 'page' : undefined"
                >
                    {{ item.texto }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
