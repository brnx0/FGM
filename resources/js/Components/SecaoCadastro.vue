<script setup lang="ts">
defineProps<{
    id: string;
    numero: number;
    titulo: string;
    /** O título recebe o foco por script (ex.: ao trocar de passo). */
    focavel?: boolean;
}>();
</script>

<template>
    <!-- Um bloco do formulário de cadastro: ícone (slot "icone"), título numerado e os campos. -->
    <section :aria-labelledby="id" class="rounded-[24px] border border-hairline bg-superficie p-6 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:p-8">
        <div class="flex items-center gap-4">
            <span aria-hidden="true" class="inline-flex size-12 shrink-0 items-center justify-center rounded-2xl bg-nevoa text-acao">
                <slot name="icone" />
            </span>
            <h2 :id="id" :tabindex="focavel ? -1 : undefined" class="focus:outline-none font-barlow text-[1.5rem] leading-none font-extrabold tracking-[0.01em] uppercase md:text-[1.75rem]">
                {{ numero }}. {{ titulo }}
            </h2>
        </div>
        <div class="mt-6 border-t border-hairline pt-6">
            <slot />
        </div>
    </section>
</template>
