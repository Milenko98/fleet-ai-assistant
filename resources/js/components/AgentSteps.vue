<script setup>
import { ref } from 'vue';

defineProps({ steps: { type: Array, required: true } });

const open = ref(false);
const expanded = ref(new Set());

const labels = {
    popis_vozila: 'Popis vozila',
    pretrazi_voznje: 'Pretraga vožnji',
    potrosnja_goriva: 'Potrošnja goriva',
    alarmi_vozila: 'Alarmi vozila',
    pretrazi_dokumente: 'Pretraga dokumenata (RAG)',
};

function toggle(i) {
    expanded.value.has(i) ? expanded.value.delete(i) : expanded.value.add(i);
}

const json = (v) => JSON.stringify(v, null, 2);
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-slate-600 dark:text-slate-300" @click="open = !open">
            <svg viewBox="0 0 24 24" class="size-4 transition" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6" /></svg>
            Koraci agenta
            <span class="rounded-full bg-slate-100 px-2 text-xs dark:bg-slate-800">{{ steps.length }}</span>
            <span class="ml-auto truncate text-xs font-normal text-slate-400">
                {{ [...new Set(steps.map((s) => labels[s.alat] ?? s.alat))].join(' → ') }}
            </span>
        </button>

        <ol v-if="open" class="border-t border-slate-100 dark:border-slate-800">
            <li v-for="(s, i) in steps" :key="i" class="border-b border-slate-100 last:border-0 dark:border-slate-800">
                <button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800/50" @click="toggle(i)">
                    <span class="rounded bg-slate-100 px-1.5 text-[11px] font-medium text-slate-500 dark:bg-slate-800">korak {{ s.korak }}</span>
                    <code class="font-mono text-[13px] text-indigo-700 dark:text-indigo-300">{{ s.alat }}</code>
                    <span v-if="s.greska" class="rounded bg-red-100 px-1.5 text-[11px] font-medium text-red-700 dark:bg-red-950 dark:text-red-300">greška</span>
                    <span v-if="s.izlaz?.nacin" class="rounded bg-slate-100 px-1.5 text-[11px] text-slate-500 dark:bg-slate-800">{{ s.izlaz.nacin }}</span>
                    <span class="ml-auto text-xs text-slate-400">{{ s.trajanje_ms }} ms</span>
                </button>

                <div v-if="expanded.has(i)" class="grid gap-2 px-3 pb-3 text-xs">
                    <div>
                        <div class="mb-1 font-medium text-slate-500">Ulaz (generirao model)</div>
                        <pre class="max-h-48 overflow-auto rounded-lg bg-slate-50 p-2 font-mono dark:bg-slate-950">{{ json(s.ulaz) }}</pre>
                    </div>
                    <div>
                        <div class="mb-1 font-medium text-slate-500">Izlaz (vraćeno modelu)</div>
                        <pre class="max-h-72 overflow-auto rounded-lg bg-slate-50 p-2 font-mono dark:bg-slate-950">{{ json(s.izlaz) }}</pre>
                    </div>
                </div>
            </li>
        </ol>
    </div>
</template>
