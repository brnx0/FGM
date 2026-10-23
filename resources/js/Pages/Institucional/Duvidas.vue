<script setup lang="ts">
import PaginaInstitucional from '../../Components/PaginaInstitucional.vue';

const perguntas = [
    {
        pergunta: 'Preciso criar uma senha no Mapa Cultura?',
        resposta: 'Não. O acesso é feito só com a sua conta gov.br, a mesma usada em outros serviços públicos. O portal não guarda senhas.',
    },
    {
        pergunta: 'Ainda não tenho conta gov.br. Como faço?',
        resposta: 'Ao clicar em "Entrar com gov.br" você vai para a página oficial do gov.br, onde é possível criar a conta gratuitamente usando o seu CPF.',
    },
    {
        pergunta: 'Esqueci a senha do gov.br.',
        resposta: 'A senha é do gov.br, não do Mapa Cultura. Recupere-a na própria página de login do gov.br.',
    },
    {
        pergunta: 'Quem pode se inscrever nos editais?',
        resposta: 'Cada edital define quem pode participar. Leia o edital completo antes de se inscrever; os requisitos aparecem na página de cada edital.',
    },
    {
        pergunta: 'Como sei se um edital está com inscrições abertas?',
        resposta: 'Na página inicial, cada edital mostra a situação ("Aberto", "Em breve" ou "Encerrado") e a data final das inscrições.',
    },
];
</script>

<template>
    <PaginaInstitucional titulo="Dúvidas frequentes">
        <div class="divide-y divide-hairline rounded-card border border-hairline">
            <details v-for="item in perguntas" :key="item.pergunta" class="group px-5">
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-3 font-semibold">
                    {{ item.pergunta }}
                    <span aria-hidden="true" class="text-acao transition-transform group-open:rotate-90">›</span>
                </summary>
                <p class="pb-5 text-tinta-suave">{{ item.resposta }}</p>
            </details>
        </div>
        <p>
            Não encontrou o que procurava? Fale com a Fundação Gregório de Mattos na Ladeira da Barroquinha, 02 —
            Centro, Salvador/BA.
        </p>
    </PaginaInstitucional>
</template>
