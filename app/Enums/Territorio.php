<?php

namespace App\Enums;

/** As dez Prefeituras-Bairro de Salvador, mais quem mora fora da cidade. */
enum Territorio: string
{
    case CentroBrotas = 'centro_brotas';
    case SuburbioIlhas = 'suburbio_ilhas';
    case Cajazeiras = 'cajazeiras';
    case ItapuaIpitanga = 'itapua_ipitanga';
    case CidadeBaixa = 'cidade_baixa';
    case BarraPituba = 'barra_pituba';
    case LiberdadeSaoCaetano = 'liberdade_sao_caetano';
    case CabulaTancredoNeves = 'cabula_tancredo_neves';
    case PauDaLima = 'pau_da_lima';
    case Valeria = 'valeria';
    case ForaDeSalvador = 'fora_de_salvador';

    public function rotulo(): string
    {
        return match ($this) {
            self::CentroBrotas => 'Centro/Brotas',
            self::SuburbioIlhas => 'Subúrbio/Ilhas',
            self::Cajazeiras => 'Cajazeiras',
            self::ItapuaIpitanga => 'Itapuã/Ipitanga',
            self::CidadeBaixa => 'Cidade Baixa',
            self::BarraPituba => 'Barra/Pituba',
            self::LiberdadeSaoCaetano => 'Liberdade/São Caetano',
            self::CabulaTancredoNeves => 'Cabula/Tancredo Neves',
            self::PauDaLima => 'Pau da Lima',
            self::Valeria => 'Valéria',
            self::ForaDeSalvador => 'Fora de Salvador',
        };
    }
}
