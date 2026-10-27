/** Fotos da vitrine (anexo portal-fgm.html). Decorativas: vestem os cards de edital que não têm foto própria. */
export const fotosVitrine: { src: string; enquadramento: string }[] = [
    { src: '/images/vitrine-boca-de-brasa.jpg', enquadramento: 'center' },
    { src: '/images/vitrine-plateia-danca.jpg', enquadramento: 'center' },
    { src: '/images/vitrine-orixas.jpg', enquadramento: 'center 30%' },
    { src: '/images/vitrine-zelia-gattai.jpg', enquadramento: '58% center' },
    { src: '/images/vitrine-elenco-branco.jpg', enquadramento: 'center' },
];

/** Foto pela posição do card, em ciclo. */
export const fotoVitrine = (indice: number) => fotosVitrine[indice % fotosVitrine.length];
