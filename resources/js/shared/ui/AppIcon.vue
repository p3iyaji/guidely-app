<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path
            v-for="path in iconPaths"
            :key="path"
            :d="path"
        />
    </svg>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: {
        type: String,
        default: 'default',
    },
});

const paths = {
    dashboard: ['M3 3h7v7H3z', 'M14 3h7v7h-7z', 'M3 14h7v7H3z', 'M14 14h7v7h-7z'],
    home: ['m3 11 9-8 9 8', 'M5 10v10h14V10', 'M9 20v-6h6v6'],
    pupils: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
    capture: ['M12 20h9', 'M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z'],
    drafts: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z', 'M14 2v6h6', 'M8 13h8', 'M8 17h5'],
    settings: ['M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z', 'M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.08A1.7 1.7 0 0 0 9 19.36a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.63 15 1.7 1.7 0 0 0 3.07 14H3v-4h.08A1.7 1.7 0 0 0 4.64 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.63h.01A1.7 1.7 0 0 0 10 3.08V3h4v.08a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.37 9v.01A1.7 1.7 0 0 0 20.92 10H21v4h-.08A1.7 1.7 0 0 0 19.4 15Z'],
    reviews: ['M8 2v4', 'M16 2v4', 'M3 10h18', 'M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z'],
    gaps: ['M12 9v4', 'M12 17h.01', 'M10.3 3.7 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z'],
    outputs: ['M14 2H6a2 2 0 0 0-2 2v16h14a2 2 0 0 0 2-2V8Z', 'M14 2v6h6', 'm9 15 2 2 4-4'],
    oversight: ['M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z'],
    alerts: ['M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9', 'M10 21h4'],
    import: ['M12 3v12', 'm7 10 5 5 5-5', 'M5 21h14'],
    access: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z', 'm9 12 2 2 4-4'],
    organisation: ['M3 21h18', 'M5 21V7l7-4 7 4v14', 'M9 9h.01', 'M15 9h.01', 'M9 13h.01', 'M15 13h.01', 'M9 17h6'],
    integrations: ['M8 12h8', 'M12 8v8', 'M4 4h5v5H4z', 'M15 15h5v5h-5z'],
    configuration: ['M4 6h16', 'M4 12h16', 'M4 18h16', 'M8 4v4', 'M16 10v4', 'M10 16v4'],
    trust: ['M12 3 3 7v5c0 5 3.8 8.7 9 10 5.2-1.3 9-5 9-10V7Z', 'm9 12 2 2 4-4'],
    schools: ['M3 22h18', 'M6 18v-8', 'M18 18v-8', 'M3 10 12 4 21 10', 'M10 22v-6h4v6'],
    users: ['M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2', 'M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'm17 11 2 2 4-4'],
    roles: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z'],
    permissions: ['M21 2 13.6 9.4', 'M15.5 6.5 18 9', 'M7 14a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z', 'm10 16 4-4'],
    profile: ['M20 21a8 8 0 0 0-16 0', 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8'],
    open: ['M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z'],
    edit: ['M12 20h9', 'M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z'],
    delete: ['M3 6h18', 'M8 6V4h8v2', 'M19 6l-1 15H6L5 6', 'M10 11v5', 'M14 11v5'],
    key: ['M21 2 13.6 9.4', 'M15.5 6.5 18 9', 'M7 14a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z'],
    deactivate: ['M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2', 'M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'm18 8 5 5', 'm23 8-5 5'],
    menu: ['M4 6h16', 'M4 12h16', 'M4 18h16'],
    more: ['M5 12h.01', 'M12 12h.01', 'M19 12h.01'],
    default: ['M4 4h16v16H4z'],
};

const aliases = {
    'review-cycles': 'reviews',
    'school-report': 'oversight',
    'safeguarding-context': 'access',
    'trust-dashboard': 'trust',
    'feature-flags': 'configuration',
    'provision-terms': 'configuration',
    'pilot-toolkit': 'configuration',
    connectors: 'integrations',
};

const iconPaths = computed(() => {
    const name = aliases[props.name] ?? props.name;

    return paths[name] ?? paths.default;
});
</script>
