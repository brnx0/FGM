<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PaginaInstitucional from '../../Components/PaginaInstitucional.vue';
</script>

<template>
    <PaginaInstitucional titulo="Termos de uso">
        <p class="text-[1.1875rem]">
            O Mapa Cultura é um serviço da Fundação Gregório de Mattos, da Prefeitura Municipal de Salvador, para
            divulgação de editais culturais e inscrição de proponentes.
        </p>
        <h2>Acesso</h2>
        <p>
            O acesso é feito com a conta gov.br do próprio proponente. A conta é pessoal: não a compartilhe e não
            faça inscrições em nome de outra pessoa sem autorização.
        </p>
        <h2>Informações prestadas</h2>
        <p>
            Você é responsável pela veracidade das informações e dos documentos enviados. Informações falsas podem
            levar à desclassificação e às medidas previstas em lei.
        </p>
        <h2>Regras dos editais</h2>
        <p>
            Cada edital tem regras próprias, que prevalecem sobre estes termos no que for específico. Leia o edital
            completo antes de se inscrever.
        </p>
        <h2>Privacidade</h2>
        <p>
            O tratamento dos seus dados segue o <Link href="/privacidade">Aviso de privacidade</Link>.
        </p>
    </PaginaInstitucional>
</template>
