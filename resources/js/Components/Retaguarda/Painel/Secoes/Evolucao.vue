<script setup lang="ts">
import type { PainelEvolucao } from '../../../../types';
import CalendarioDeCalor from '../CalendarioDeCalor.vue';
import EvolucaoMensal from '../EvolucaoMensal.vue';
import { dataCurta, numero } from '../formatos';
import QuadroPainel from '../QuadroPainel.vue';
import SecaoPainel from '../SecaoPainel.vue';
import TabelaDeCalor from '../TabelaDeCalor.vue';

defineProps<{
    dados: PainelEvolucao;
    /** Mínimo de pessoas para um território mostrar percentuais. */
    baseMinima: number;
    atualizando?: boolean;
}>();
</script>

<template>
    <SecaoPainel
        id="evolucao"
        titulo="Evolução e território"
        descricao="Como os cadastros crescem, o ritmo das inscrições e o perfil de cada Prefeitura-Bairro"
        icone="M4 6h16v14H4zM4 10h16M9 3v5M15 3v5"
        :atualizando="atualizando"
    >
        <QuadroPainel titulo="Novos cadastros por mês" descricao="Pelo mês em que a pessoa enviou o perfil, separado pelo tipo de cadastro escolhido">
            <EvolucaoMensal :meses="dados.meses" legenda="Novos cadastros por mês e tipo" />
        </QuadroPainel>

        <QuadroPainel
            titulo="Ritmo das inscrições"
            :descricao="`Inscrições enviadas por dia, de ${dataCurta(dados.ritmo.inicio)} a ${dataCurta(dados.ritmo.fim)}: ${numero(dados.ritmo.total)} no total; o dia mais cheio teve ${numero(dados.ritmo.maximo)}`"
        >
            <CalendarioDeCalor
                :inicio="dados.ritmo.inicio"
                :fim="dados.ritmo.fim"
                :dias="dados.ritmo.dias"
                :maximo="dados.ritmo.maximo"
                legenda="Inscrições enviadas por mês"
            />
        </QuadroPainel>

        <QuadroPainel
            titulo="Perfil por Prefeitura-Bairro"
            :descricao="`Percentual das pessoas de cada território em cada grupo; o tom mais forte é o maior da coluna. Com menos de ${baseMinima} pessoas, o percentual não aparece.`"
        >
            <TabelaDeCalor
                :colunas="dados.territorios.colunas"
                :linhas="dados.territorios.linhas"
                :base-minima="baseMinima"
                legenda="Perfil das pessoas por Prefeitura-Bairro"
            />
        </QuadroPainel>
    </SecaoPainel>
</template>
