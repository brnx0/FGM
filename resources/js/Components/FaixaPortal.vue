<template>
    <!-- Faixa azul-marinho que abre as páginas do portal do proponente. Os cartões sobem sobre a base dela:
         as páginas usam -mt-20 (md: -mt-24) logo depois desta faixa. Só essa base se dissolve no fundo claro,
         quase toda atrás dos cartões; acima deles o azul continua inteiro. -->
    <section class="home-escuro relative overflow-hidden bg-noite pt-8 pb-36 text-white md:pt-12 md:pb-40">
        <div
            aria-hidden="true"
            class="home-veu absolute inset-0 bg-[radial-gradient(70%_120%_at_88%_-10%,rgba(0,92,169,0.6)_0%,rgba(0,92,169,0)_70%)]"
        ></div>
        <!-- Degradê em curva suave (começa e termina devagar), para não marcar uma linha onde encontra o fundo.
             Passa 1px da borda de baixo para não sobrar um fio azul quando a altura da faixa não é inteira. -->
        <div
            aria-hidden="true"
            class="home-veu absolute inset-x-0 -bottom-px h-24 bg-[linear-gradient(to_bottom,transparent,color-mix(in_oklab,var(--color-parchment)_4%,transparent)_15%,color-mix(in_oklab,var(--color-parchment)_15%,transparent)_30%,color-mix(in_oklab,var(--color-parchment)_33%,transparent)_45%,color-mix(in_oklab,var(--color-parchment)_56%,transparent)_60%,color-mix(in_oklab,var(--color-parchment)_78%,transparent)_75%,color-mix(in_oklab,var(--color-parchment)_93%,transparent)_88%,var(--color-parchment))] md:h-28"
        ></div>

        <div class="relative mx-auto flex max-w-6xl flex-col gap-8 px-4">
            <slot />
        </div>
    </section>
</template>
