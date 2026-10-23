<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import type { ContaFake } from '../../types';

withDefaults(
    defineProps<{
        contas?: ContaFake[];
    }>(),
    { contas: () => [] },
);

const form = useForm({ cpf: '', nome: '', email: '' });

function entrarComo(conta: ContaFake): void {
    form.cpf = conta.cpf;
    form.nome = conta.nome;
    form.email = conta.email ?? '';
    form.post('/auth/govbr/fake');
}

function enviar(): void {
    form.post('/auth/govbr/fake');
}
</script>

<template>
    <Head title="gov.br (simulado)" />

    <section class="mx-auto max-w-xl px-4 py-12 sm:py-16">
        <h1 class="text-[2rem] sm:text-[3rem]">gov.br (simulado)</h1>
        <p class="mt-4 border-l-4 border-current pl-3 text-[1.0625rem]" role="note">
            Login simulado para testes — nenhum dado é enviado ao gov.br.
        </p>

        <div
            v-if="form.hasErrors"
            class="mt-6 border-2 border-current p-3"
            role="alert"
        >
            <p class="font-bold">Corrija os campos abaixo:</p>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="(mensagem, campo) in form.errors" :key="campo">{{ mensagem }}</li>
            </ul>
        </div>

        <h2 class="mt-8 text-[1.5rem]">Entrar com uma conta de teste</h2>
        <ul class="mt-3 space-y-3">
            <li v-for="conta in contas" :key="conta.cpf">
                <button
                    type="button"
                    class="w-full border-2 border-current px-4 py-3 text-left"
                    :disabled="form.processing"
                    @click="entrarComo(conta)"
                >
                    <span class="block font-bold">{{ conta.nome }}</span>
                    <span class="block text-[0.9375rem]">CPF {{ conta.cpf }}</span>
                </button>
            </li>
        </ul>

        <h2 class="mt-10 text-[1.5rem]">Ou informe os dados</h2>
        <form class="mt-3 space-y-4" novalidate @submit.prevent="enviar">
            <div>
                <label for="cpf" class="block font-bold">CPF</label>
                <input
                    id="cpf"
                    v-model="form.cpf"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    class="mt-1 w-full border-2 border-current px-3 py-2"
                    :aria-invalid="form.errors.cpf ? 'true' : undefined"
                />
            </div>
            <div>
                <label for="nome" class="block font-bold">Nome</label>
                <input
                    id="nome"
                    v-model="form.nome"
                    type="text"
                    maxlength="120"
                    autocomplete="off"
                    class="mt-1 w-full border-2 border-current px-3 py-2"
                    :aria-invalid="form.errors.nome ? 'true' : undefined"
                />
            </div>
            <div>
                <label for="email" class="block font-bold">E-mail (opcional)</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="off"
                    class="mt-1 w-full border-2 border-current px-3 py-2"
                    :aria-invalid="form.errors.email ? 'true' : undefined"
                />
            </div>
            <button type="submit" class="border-2 border-current px-5 py-3 font-bold" :disabled="form.processing">
                Entrar
            </button>
        </form>
    </section>
</template>
