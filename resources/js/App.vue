<script setup>
import { onMounted, ref } from 'vue';
import { api } from './api';
import ChatPanel from './components/ChatPanel.vue';
import FleetPanel from './components/FleetPanel.vue';
import DocumentsPanel from './components/DocumentsPanel.vue';

const summary = ref(null);
const tab = ref('fleet');
const chat = ref(null);

async function loadSummary() {
    try {
        summary.value = await api.summary();
    } catch (e) {
        console.error(e);
    }
}

function askAbout(text) {
    chat.value?.prefill(text);
}

onMounted(loadSummary);
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Zaglavlje -->
        <header class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-white px-5 py-3 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center gap-3">
                <div class="grid size-9 place-items-center rounded-lg bg-indigo-600 text-white">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 13h2l2-5h9l3 5h2v4h-2" /><circle cx="7.5" cy="17.5" r="1.8" /><circle cx="16.5" cy="17.5" r="1.8" /><path d="M9.3 17.5h5.4" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-[15px] font-semibold text-slate-900 dark:text-white">Fleet AI Assistant</h1>
                    <p class="text-xs text-slate-500">AI agent za vozni park · tool calling + RAG</p>
                </div>
            </div>

            <div v-if="summary" class="ml-auto flex flex-wrap gap-2 text-xs">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium"
                    :class="summary.status.claude ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'"
                    :title="summary.status.claude ? 'ANTHROPIC_API_KEY je postavljen' : 'Dodaj ANTHROPIC_API_KEY u .env'"
                >
                    <span class="size-1.5 rounded-full" :class="summary.status.claude ? 'bg-emerald-500' : 'bg-amber-500'" />
                    {{ summary.status.claude ? summary.status.model : 'Claude: nema API ključa' }}
                </span>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium"
                    :class="summary.status.rag_mode === 'semantic' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                    :title="summary.status.rag_mode === 'semantic' ? 'Dokumenti se pretražuju po značenju (Voyage embeddingsi + pgvector)' : 'Dokumenti se pretražuju po riječima (PostgreSQL full-text). Dodaj VOYAGE_API_KEY za semantičku pretragu.'"
                >
                    RAG: {{ summary.status.rag_mode === 'semantic' ? 'semantička pretraga' : 'full-text pretraga' }}
                </span>
            </div>
        </header>

        <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
            <!-- Chat -->
            <main class="flex min-h-0 flex-1 flex-col">
                <ChatPanel ref="chat" :claude-ready="summary?.status.claude ?? true" />
            </main>

            <!-- Bočna traka -->
            <aside class="flex max-h-[45vh] min-h-0 flex-col border-t border-slate-200 bg-white lg:max-h-none lg:w-[380px] lg:border-l lg:border-t-0 dark:border-slate-800 dark:bg-slate-900">
                <nav class="flex gap-1 border-b border-slate-200 p-2 dark:border-slate-800">
                    <button
                        v-for="t in [{ id: 'fleet', label: 'Vozni park' }, { id: 'docs', label: 'Dokumenti (RAG)' }]"
                        :key="t.id"
                        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition"
                        :class="tab === t.id ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                        @click="tab = t.id"
                    >
                        {{ t.label }}
                    </button>
                </nav>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <FleetPanel v-if="tab === 'fleet'" :summary="summary" @ask="askAbout" />
                    <DocumentsPanel v-else @changed="loadSummary" />
                </div>
            </aside>
        </div>
    </div>
</template>
