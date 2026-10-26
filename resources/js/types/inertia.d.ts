import type { Component } from 'vue';
import type { SharedProps } from './index';

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}

// Layout próprio de uma página, via defineOptions({ layout }); sem ele, app.ts usa o PortalLayout.
declare module 'vue' {
    interface ComponentCustomOptions {
        layout?: Component;
    }
}

export {};
