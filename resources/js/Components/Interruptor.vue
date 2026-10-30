<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        id: string;
        rotulo: string;
        ajuda?: string;
        erro?: string;
        obrigatorio?: boolean;
        desabilitado?: boolean;
        /** 'caixa' traz a borda; 'linha' é sem borda, para listas de opções. */
        variante?: 'caixa' | 'linha';
    }>(),
    { variante: 'caixa' },
);

const ligado = defineModel<boolean>({ required: true });

defineEmits<{ change: [] }>();

const idRotulo = computed(() => `${props.id}-rotulo`);
const idAjuda = computed(() => `${props.id}-ajuda`);
const idErro = computed(() => `${props.id}-erro`);

const descricao = computed(() => [props.ajuda && idAjuda.value, props.erro && idErro.value].filter(Boolean).join(' ') || undefined);
</script>

<template>
    <div class="flex min-w-0 flex-col gap-1.5">
        <label
            :for="id"
            class="flex min-h-12 items-center justify-between gap-4"
            :class="[
                variante === 'caixa' ? 'rounded-[12px] border border-hairline px-4 py-3' : 'py-1.5',
                desabilitado ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                { 'campo-invalido': erro && variante === 'caixa' },
            ]"
        >
            <span class="flex flex-col">
                <span :id="idRotulo" class="font-semibold">
                    <slot>{{ rotulo }}</slot><span v-if="obrigatorio" aria-hidden="true" class="ml-0.5 text-invalido-texto">*</span>
                </span>
                <span v-if="ajuda" :id="idAjuda" class="text-[0.8125rem] text-tinta-suave">{{ ajuda }}</span>
            </span>
            <input
                :id="id"
                v-model="ligado"
                type="checkbox"
                role="switch"
                class="peer sr-only"
                :disabled="desabilitado"
                :aria-labelledby="idRotulo"
                :aria-invalid="erro ? true : undefined"
                :aria-describedby="descricao"
                @change="$emit('change')"
            />
            <span
                aria-hidden="true"
                class="relative inline-flex h-6 w-10 shrink-0 rounded-pill transition-colors peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-acao motion-reduce:transition-none"
                :class="ligado ? 'bg-acao' : 'bg-hairline'"
            >
                <span
                    class="absolute top-0.5 left-0.5 size-5 rounded-full border border-hairline bg-superficie transition-transform motion-reduce:transition-none"
                    :class="{ 'translate-x-4': ligado }"
                ></span>
            </span>
        </label>
        <p v-if="erro" :id="idErro" class="text-[0.875rem] font-semibold text-invalido-texto">{{ erro }}</p>
    </div>
</template>
