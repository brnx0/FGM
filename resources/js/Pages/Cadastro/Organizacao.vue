<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EtapasCadastro from '../../Components/EtapasCadastro.vue';
import FaixaPortal from '../../Components/FaixaPortal.vue';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';

defineOptions({ layout: PortalProponenteLayout });
</script>

<template>
    <div>
        <Head title="Organização" />

        <FaixaPortal aria-labelledby="organizacao-titulo">
            <div class="flex flex-col gap-3">
                <p class="text-[0.75rem] font-semibold tracking-[0.24em] text-white/75 uppercase">Etapa 3 de 4 · Organização</p>
                <h1 id="organizacao-titulo" class="font-barlow text-[2.6rem] leading-[0.95] font-extrabold tracking-[0.01em] break-words uppercase md:text-[3.5rem]">
                    Você representa uma organização?
                </h1>
            </div>
            <EtapasCadastro :atual="3" />
        </FaixaPortal>

        <div class="relative mx-auto -mt-20 max-w-6xl px-4 pb-16 md:-mt-24">
            <section
                aria-labelledby="organizacao-opcoes-titulo"
                class="rounded-[24px] border border-hairline bg-superficie p-6 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:p-10"
            >
                <h2 id="organizacao-opcoes-titulo" class="sr-only">Escolha como seguir</h2>
                <p class="max-w-2xl leading-[1.6] text-tinta-suave">
                    Cadastre a organização com CNPJ que você representa no setor cultural. Assim, ela também pode se inscrever nos editais.
                </p>
                <p id="organizacao-tipos" class="mt-6 font-semibold text-tinta">Pode ser:</p>
                <ul aria-labelledby="organizacao-tipos" class="mt-2 flex list-disc flex-col gap-1 pl-5 leading-[1.6] text-tinta-suave marker:text-acao">
                    <li>MEI (Microempreendedor Individual)</li>
                    <li>Empresa com fins lucrativos</li>
                    <li>Associação, coletivo ou outra entidade sem fins lucrativos</li>
                </ul>
                <p class="mt-6 max-w-2xl leading-[1.6] text-tinta-suave">Não é obrigatório: se preferir, cadastre depois em Minhas organizações.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Link
                        href="/cadastro/pessoa-juridica"
                        class="pressionavel inline-flex min-h-12 items-center justify-center rounded-pill bg-acao px-8 text-center text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase no-underline hover:bg-acao-escuro"
                    >
                        Cadastrar organização
                    </Link>
                    <Link
                        href="/"
                        class="pressionavel inline-flex min-h-12 items-center justify-center rounded-pill border border-hairline px-8 text-center text-[0.8125rem] font-bold tracking-[0.12em] text-tinta uppercase no-underline"
                    >
                        Não represento uma organização
                    </Link>
                </div>
            </section>
        </div>
    </div>
</template>
