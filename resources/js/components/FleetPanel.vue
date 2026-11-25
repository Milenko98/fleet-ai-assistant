<script setup>
defineProps({ summary: Object });
const emit = defineEmits(['ask']);

const fmt = (n) => Math.round(n ?? 0).toLocaleString('hr');

const tipovi = { osobno: 'Osobno', dostavno: 'Dostavno', kamion: 'Kamion' };
</script>

<template>
    <div v-if="!summary" class="p-4 text-sm text-slate-400">Učitavanje…</div>

    <div v-else class="space-y-5 p-4">
        <section>
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Zadnjih 30 dana</h3>
            <div class="grid grid-cols-2 gap-2">
                <div v-for="s in [
                    { label: 'Vozila', value: fmt(summary.stats.vozila) },
                    { label: 'Prijeđeno km', value: fmt(summary.stats.km_30_dana) },
                    { label: 'Vožnji', value: fmt(summary.stats.voznji_30_dana) },
                    { label: 'Alarma', value: fmt(summary.stats.alarmi_30_dana) },
                ]" :key="s.label" class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                    <div class="text-lg font-semibold text-slate-900 dark:text-white">{{ s.value }}</div>
                    <div class="text-xs text-slate-500">{{ s.label }}</div>
                </div>
            </div>
        </section>

        <section>
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Vozila</h3>
            <p class="mb-2 text-xs text-slate-400">Klikni na vozilo za pitanje o njemu.</p>
            <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                <li v-for="v in summary.vehicles" :key="v.id">
                    <button
                        class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        @click="emit('ask', `Daj mi pregled vozila ${v.registracija} za zadnjih 7 dana: kilometri, potrošnja i alarmi.`)"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="font-mono text-sm font-medium text-slate-900 dark:text-white">{{ v.registracija }}</div>
                            <div class="truncate text-xs text-slate-500">{{ v.marka_model }} · {{ tipovi[v.tip] }} · {{ v.gorivo }}</div>
                        </div>
                        <div class="text-right text-xs">
                            <div class="text-slate-700 dark:text-slate-300">{{ fmt(v.km_30_dana) }} km</div>
                            <div :class="v.alarmi_30_dana > 5 ? 'font-medium text-red-600 dark:text-red-400' : 'text-slate-400'">
                                {{ v.alarmi_30_dana }} alarma
                            </div>
                        </div>
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
