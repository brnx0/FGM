<?php

namespace App\Enums;

/** Povos e comunidades tradicionais, como no formulário do SalCine II. */
enum PovoTradicional: string
{
    case NaoPertence = 'nao_pertence';
    case Andirobeiros = 'andirobeiros';
    case ApanhadoresDeFlores = 'apanhadores_flores_sempre_vivas';
    case Benzedeiros = 'benzedeiros';
    case Caatingueiros = 'caatingueiros';
    case Caboclos = 'caboclos';
    case Caicaras = 'caicaras';
    case CatadoresDeMangaba = 'catadores_mangaba';
    case Cipozeiros = 'cipozeiros';
    case FundosEFechosDePasto = 'fundos_fechos_pasto';
    case Quilombolas = 'quilombolas';
    case Extrativistas = 'extrativistas';
    case ExtrativistasCosteiros = 'extrativistas_costeiros_marinhos';
    case Faxinalenses = 'faxinalenses';
    case Geraizeiros = 'geraizeiros';
    case Ilheus = 'ilheus';
    case Juventude = 'juventude_povos_comunidades';
    case Morroquianos = 'morroquianos';
    case Pantaneiros = 'pantaneiros';
    case PescadoresArtesanais = 'pescadores_artesanais';
    case Pomerano = 'povo_pomerano';
    case Ciganos = 'povos_ciganos';
    case Terreiro = 'terreiro_matriz_africana';
    case Indigenas = 'povos_indigenas';
    case QuebradeirasDeCoco = 'quebradeiras_coco_babacu';
    case Raizeiros = 'raizeiros';
    case RetireirosDoAraguaia = 'retireiros_araguaia';
    case Ribeirinhos = 'ribeirinhos';
    case Vazanteiros = 'vazanteiros';
    case Veredeiros = 'veredeiros';

    public function rotulo(): string
    {
        return match ($this) {
            self::NaoPertence => 'Não pertence a povos ou comunidades tradicionais.',
            self::Andirobeiros => 'Andirobeiros',
            self::ApanhadoresDeFlores => 'Apanhadores de flores sempre vivas',
            self::Benzedeiros => 'Benzedeiros',
            self::Caatingueiros => 'Caatingueiros',
            self::Caboclos => 'Caboclos',
            self::Caicaras => 'Caiçaras',
            self::CatadoresDeMangaba => 'Catadores de mangaba',
            self::Cipozeiros => 'Cipozeiros',
            self::FundosEFechosDePasto => 'Comunidades de fundos e fechos de pasto',
            self::Quilombolas => 'Comunidades quilombolas',
            self::Extrativistas => 'Extrativistas',
            self::ExtrativistasCosteiros => 'Extrativistas costeiros e marinhos',
            self::Faxinalenses => 'Faxinalenses',
            self::Geraizeiros => 'Geraizeiros',
            self::Ilheus => 'Ilhéus',
            self::Juventude => 'Juventude de povos e comunidades tradicionais',
            self::Morroquianos => 'Morroquianos',
            self::Pantaneiros => 'Pantaneiros',
            self::PescadoresArtesanais => 'Pescadores artesanais',
            self::Pomerano => 'Povo pomerano',
            self::Ciganos => 'Povos ciganos',
            self::Terreiro => 'Povos e comunidades de terreiro/de matriz africana',
            self::Indigenas => 'Povos indígenas',
            self::QuebradeirasDeCoco => 'Quebradeiras de coco babaçu',
            self::Raizeiros => 'Raizeiros',
            self::RetireirosDoAraguaia => 'Retireiros do Araguaia',
            self::Ribeirinhos => 'Ribeirinhos',
            self::Vazanteiros => 'Vazanteiros',
            self::Veredeiros => 'Veredeiros',
        };
    }
}
