<script setup lang="ts">
import MenuPortal from './MenuPortal.vue';
</script>

<template>
    <!-- Faixa azul-marinho que abre as páginas do portal do proponente. Os cartões sobem sobre a base dela:
         as páginas usam -mt-20 (md: -mt-24) logo depois desta faixa. O azul termina numa linha reta atrás
         dos cartões, e dali para baixo começa o fundo claro.
         O menu do portal fica no alto, à direita do título (no celular, acima dele). Slots: o padrão é o título;
         "acoes" fica no canto de baixo à direita do título; "abaixo" ocupa a largura toda (as etapas do cadastro). -->
    <section class="home-escuro relative overflow-hidden bg-noite pt-8 pb-36 text-white md:pt-12 md:pb-40">
        <div
            aria-hidden="true"
            class="home-veu absolute inset-0 bg-[radial-gradient(70%_120%_at_88%_-10%,rgba(0,92,169,0.6)_0%,rgba(0,92,169,0)_70%)]"
        ></div>

        <div class="relative mx-auto flex max-w-6xl flex-col gap-8 px-4">
            <!-- O menu vem antes do título no código, para chegar cedo pelo Tab. -->
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:gap-x-10">
                <MenuPortal class="lg:col-start-2 lg:row-start-1 lg:self-start lg:justify-self-end" />
                <div class="min-w-0 lg:col-start-1 lg:row-span-2 lg:row-start-1">
                    <slot />
                </div>
                <div v-if="$slots.acoes" class="flex justify-end lg:col-start-2 lg:row-start-2 lg:self-end">
                    <slot name="acoes" />
                </div>
            </div>
            <slot name="abaixo" />
        </div>
    </section>
</template>
