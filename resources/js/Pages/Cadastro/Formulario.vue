<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import EtapasCadastro from '../../Components/EtapasCadastro.vue';
import FaixaPortal from '../../Components/FaixaPortal.vue';
import IconeTipoCadastro from '../../Components/IconeTipoCadastro.vue';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';
import type { CadastroFormularioProps, TipoCadastro } from '../../types';

defineOptions({ layout: PortalProponenteLayout });

const props = defineProps<CadastroFormularioProps>();

const usuario = computed(() => usePage().props.auth?.user);

const tipos: Record<TipoCadastro, { titulo: string; texto: string }> = {
    'pessoa-juridica': {
        titulo: 'Pessoa Jurídica',
        texto: 'Aqui você vai cadastrar a organização, pelo CNPJ, e atuar como representante dela nas inscrições.',
    },
};

const tipo = computed(() => tipos[props.tipo]);

// Como em Cadastro/Completar: o ícone volta para o cartão (view transition) e o foco vai para o conteúdo novo.
const animar = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const focarConteudo = (): void => document.getElementById('conteudo')?.focus({ preventScroll: true });
</script>

<template>
    <div>
        <Head :title="`Cadastro de ${tipo.titulo}`" />

        <FaixaPortal aria-labelledby="formulario-titulo">
            <div class="flex flex-col items-start gap-4">
                <Link
                    href="/cadastro"
                    :view-transition="animar"
                    class="vidro pressionavel inline-flex min-h-11 items-center gap-2 rounded-pill px-5 text-[0.75rem] font-bold tracking-[0.12em] text-white uppercase no-underline"
                    @success="focarConteudo"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M11 6l-6 6 6 6" />
                    </svg>
                    Voltar<span class="sr-only"> para o tipo de cadastro</span>
                </Link>

                <p class="mt-2 text-[0.75rem] font-semibold tracking-[0.24em] text-white/75 uppercase">Etapa 3 de 4 · Organização</p>
                <div class="flex items-center gap-4">
                    <span class="vidro inline-flex size-14 shrink-0 items-center justify-center rounded-2xl" :style="{ viewTransitionName: `icone-${props.tipo}` }">
                        <IconeTipoCadastro :tipo="props.tipo" class="size-7" />
                    </span>
                    <h1 id="formulario-titulo" class="font-barlow text-[2.6rem] leading-[0.95] font-extrabold tracking-[0.01em] uppercase md:text-[3.5rem]">
                        {{ tipo.titulo }}
                    </h1>
                </div>
                <p class="text-white/85">
                    {{ usuario?.nome }} · CPF <span class="tabular-nums">{{ usuario?.cpfMascarado }}</span>
                </p>
            </div>
            <EtapasCadastro :atual="3" />
        </FaixaPortal>

        <div class="relative mx-auto -mt-20 max-w-6xl px-4 pb-16 md:-mt-24">
            <section
                aria-labelledby="em-breve-titulo"
                class="rounded-[24px] border border-hairline bg-superficie p-6 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:p-10"
            >
                <h2 id="em-breve-titulo" class="font-barlow text-[2rem] leading-[0.95] font-extrabold tracking-[0.01em] uppercase md:text-[2.5rem]">
                    Formulário em breve
                </h2>
                <p class="mt-3 max-w-2xl leading-[1.6] text-tinta-suave">{{ tipo.texto }}</p>
            </section>
        </div>
    </div>
</template>
