<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { LeafletMouseEvent, Map as MapaLeaflet, Marker } from 'leaflet';

const props = withDefaults(
    defineProps<{
        latitude: number | null;
        longitude: number | null;
        /** Nome do mapa para o leitor de tela. */
        rotulo: string;
        /** Clicar no mapa ou arrastar o pin muda a posição (formulário da retaguarda). */
        editavel?: boolean;
    }>(),
    { editavel: false },
);

const emit = defineEmits<{
    'update:posicao': [posicao: { latitude: number; longitude: number }];
}>();

// Sem pin ainda, o mapa abre em Salvador.
const CENTRO_DE_SALVADOR: [number, number] = [-12.9714, -38.5014];
const ZOOM_DO_PIN = 16;
const ZOOM_DA_CIDADE = 12;

// O mesmo pin dos cards de evento da página inicial; cores em .pino-mapa (app.css).
const PINO =
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z" /><circle cx="12" cy="10" r="2.5" /></svg>';

const elemento = ref<HTMLElement | null>(null);
const carregando = ref<boolean>(true);

let leaflet: typeof import('leaflet') | null = null;
let mapa: MapaLeaflet | null = null;
let pino: Marker | null = null;

const avisar = (latitude: number, longitude: number): void => emit('update:posicao', { latitude, longitude });

const posicionar = (latitude: number, longitude: number): void => {
    if (!leaflet || !mapa) return;

    if (pino) {
        pino.setLatLng([latitude, longitude]);
        return;
    }

    pino = leaflet
        .marker([latitude, longitude], {
            icon: leaflet.divIcon({ className: 'pino-mapa', html: PINO, iconSize: [40, 48], iconAnchor: [20, 46] }),
            draggable: props.editavel,
            keyboard: props.editavel,
            alt: 'Local do evento',
        })
        .addTo(mapa);

    if (props.editavel) {
        pino.on('dragend', () => {
            const ponto = pino!.getLatLng();
            avisar(ponto.lat, ponto.lng);
        });
    }
};

onMounted(async () => {
    // O Leaflet só baixa quando um mapa aparece: fica fora do pacote da página inicial.
    const [modulo] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
    if (!elemento.value) return;

    leaflet = modulo;
    const reduzMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const temPino = props.latitude !== null && props.longitude !== null;

    // Sem zoom pela rodinha: a rolagem da página (ou do modal) não fica presa no mapa.
    mapa = leaflet.map(elemento.value, {
        scrollWheelZoom: false,
        zoomAnimation: !reduzMovimento,
        fadeAnimation: !reduzMovimento,
        markerZoomAnimation: !reduzMovimento,
    });
    mapa.setView(temPino ? [props.latitude!, props.longitude!] : CENTRO_DE_SALVADOR, temPino ? ZOOM_DO_PIN : ZOOM_DA_CIDADE);

    leaflet
        .tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        })
        .addTo(mapa);

    if (temPino) posicionar(props.latitude!, props.longitude!);

    if (props.editavel) {
        mapa.on('click', (evento: LeafletMouseEvent) => {
            posicionar(evento.latlng.lat, evento.latlng.lng);
            avisar(evento.latlng.lat, evento.latlng.lng);
        });
    }

    carregando.value = false;
});

// Posição vinda de fora (busca pelo endereço, "Remover marcação"): o mapa vai até o pin se ele estiver longe.
watch(
    () => [props.latitude, props.longitude] as const,
    ([latitude, longitude]) => {
        if (!mapa) return;

        if (latitude === null || longitude === null) {
            pino?.remove();
            pino = null;
            return;
        }

        posicionar(latitude, longitude);
        if (mapa.getZoom() < ZOOM_DO_PIN || !mapa.getBounds().contains([latitude, longitude])) {
            mapa.setView([latitude, longitude], ZOOM_DO_PIN);
        }
    },
);

/** Recalcula o tamanho e recentra no pin: o modal abre com animação de escala. */
const ajustar = (): void => {
    if (!mapa) return;
    mapa.invalidateSize();
    if (pino) mapa.setView(pino.getLatLng(), mapa.getZoom(), { animate: false });
};

defineExpose({ ajustar });

onBeforeUnmount(() => {
    mapa?.remove();
    mapa = null;
    pino = null;
});
</script>

<template>
    <!-- isolate: as camadas do Leaflet (z-index 400+) ficam abaixo do cabeçalho e dos menus da página. -->
    <div class="relative isolate overflow-hidden rounded-[20px] border border-hairline bg-nevoa">
        <div ref="elemento" role="region" :aria-label="rotulo" class="size-full min-h-56"></div>
        <div v-if="carregando" aria-hidden="true" class="absolute inset-0 animate-pulse bg-nevoa motion-reduce:animate-none"></div>
    </div>
</template>
