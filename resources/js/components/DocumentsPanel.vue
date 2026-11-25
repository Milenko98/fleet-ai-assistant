<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';

const emit = defineEmits(['changed']);

const documents = ref([]);
const ragMode = ref(null);
const openDoc = ref(null);        // { id, naslov, chunks }
const showForm = ref(false);
const form = ref({ naslov: '', sadrzaj: '' });
const saving = ref(false);
const error = ref('');

// Testiranje pretrage bez agenta
const query = ref('');
const searchResult = ref(null);
const searching = ref(false);

async function load() {
    const res = await api.documents();
    documents.value = res.documents;
    ragMode.value = res.rag_mode;
}

async function toggleDoc(doc) {
    openDoc.value = openDoc.value?.id === doc.id ? null : await api.document(doc.id);
}

async function save() {
    saving.value = true;
    error.value = '';
    try {
        await api.addDocument(form.value.naslov, form.value.sadrzaj);
        form.value = { naslov: '', sadrzaj: '' };
        showForm.value = false;
        await load();
        emit('changed');
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function remove(doc) {
    if (!confirm(`Obrisati dokument "${doc.naslov}"?`)) return;
    await api.deleteDocument(doc.id);
    if (openDoc.value?.id === doc.id) openDoc.value = null;
    await load();
    emit('changed');
}

async function search() {
    if (!query.value.trim()) return;
    searching.value = true;
    try {
        searchResult.value = await api.searchDocuments(query.value);
    } catch (e) {
        searchResult.value = { error: e.message };
    } finally {
        searching.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-5 p-4">
        <!-- Testiranje retrieval koraka -->
        <section>
            <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Testiraj pretragu</h3>
            <p class="mb-2 text-xs text-slate-500">
                Ovo je korak koji agent radi kad pozove <code class="font-mono">pretrazi_dokumente</code>.
                Način: <strong>{{ ragMode === 'semantic' ? 'semantički (embeddingsi)' : 'full-text (po riječima)' }}</strong>.
            </p>
            <form class="flex gap-2" @submit.prevent="search">
                <input
                    v-model="query"
                    placeholder="npr. kad moram stati s vožnjom"
                    class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm outline-none focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950"
                />
                <button class="rounded-lg bg-slate-800 px-3 text-sm text-white hover:bg-slate-700 disabled:opacity-50 dark:bg-slate-700" :disabled="searching">Traži</button>
            </form>

            <div v-if="searchResult" class="mt-2 space-y-2">
                <p v-if="searchResult.error" class="text-xs text-red-600">{{ searchResult.error }}</p>
                <p v-else-if="!searchResult.rezultati.length" class="text-xs text-slate-500">
                    Nema pogodaka.
                    <span v-if="searchResult.nacin === 'fulltext'">Full-text traži iste riječi; semantička pretraga bi našla i odlomke sličnog značenja.</span>
                </p>
                <div v-for="(r, i) in searchResult.rezultati" :key="i" class="rounded-lg bg-slate-50 p-2.5 text-xs dark:bg-slate-800/60">
                    <div class="mb-1 flex justify-between gap-2 font-medium text-slate-700 dark:text-slate-300">
                        <span class="truncate">{{ r.dokument }}</span>
                        <span class="shrink-0 text-slate-400">score {{ r.score }}</span>
                    </div>
                    <p class="line-clamp-4 whitespace-pre-line text-slate-600 dark:text-slate-400">{{ r.odlomak }}</p>
                </div>
            </div>
        </section>

        <!-- Dokumenti -->
        <section>
            <div class="mb-2 flex items-center justify-between">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Indeksirani dokumenti</h3>
                <button class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400" @click="showForm = !showForm">
                    {{ showForm ? 'Odustani' : '+ Dodaj dokument' }}
                </button>
            </div>

            <form v-if="showForm" class="mb-3 space-y-2 rounded-lg border border-slate-200 p-3 dark:border-slate-800" @submit.prevent="save">
                <input
                    v-model="form.naslov"
                    placeholder="Naslov dokumenta"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm outline-none focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950"
                />
                <textarea
                    v-model="form.sadrzaj"
                    rows="8"
                    placeholder="Zalijepi tekst dokumenta. Odlomke odvoji praznim redom; naslove sekcija označi s ##."
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950"
                />
                <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
                <button
                    class="w-full rounded-lg bg-indigo-600 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="saving || !form.naslov || !form.sadrzaj"
                >
                    {{ saving ? 'Indeksiram…' : 'Indeksiraj dokument' }}
                </button>
            </form>

            <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                <li v-for="d in documents" :key="d.id">
                    <div class="flex items-center gap-2 px-3 py-2.5">
                        <button class="min-w-0 flex-1 text-left" @click="toggleDoc(d)">
                            <div class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ d.naslov }}</div>
                            <div class="text-xs text-slate-400">{{ d.chunks_count }} odlomaka</div>
                        </button>
                        <button class="text-xs text-slate-400 hover:text-red-600" title="Obriši" @click="remove(d)">✕</button>
                    </div>

                    <ol v-if="openDoc?.id === d.id" class="space-y-1.5 px-3 pb-3">
                        <li v-for="c in openDoc.chunks" :key="c.id" class="rounded bg-slate-50 p-2 text-xs dark:bg-slate-800/60">
                            <span class="font-medium text-slate-400">#{{ c.redni_broj }}</span>
                            <p class="mt-0.5 line-clamp-3 whitespace-pre-line text-slate-600 dark:text-slate-400">{{ c.sadrzaj }}</p>
                        </li>
                    </ol>
                </li>
                <li v-if="!documents.length" class="px-3 py-4 text-center text-xs text-slate-400">Nema dokumenata.</li>
            </ul>
        </section>
    </div>
</template>
