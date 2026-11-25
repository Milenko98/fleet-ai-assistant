<script setup>
import { nextTick, onMounted, ref } from 'vue';
import { api } from '../api';
import ChatMessage from './ChatMessage.vue';

defineProps({ claudeReady: Boolean });

const messages = ref([]);   // { role, content, steps?, usage?, error? }
const input = ref('');
const loading = ref(false);
const scroller = ref(null);
const textarea = ref(null);

const examples = [
    'Koja su vozila jučer prešla više od 200 km?',
    'Usporedi potrošnju goriva vozila ST-3307-DE u zadnja 2 tjedna i 4 tjedna prije toga. Je li odstupanje u skladu s pravilima?',
    'Je li vozač kamiona ZG-5521-KM u zadnjih 14 dana vozio dulje od dopuštenog bez stanke?',
    'Koje vozilo ima najviše alarma za prekoračenje brzine ovaj mjesec i što pravila kažu o tome?',
    'Kojim vozilima uskoro treba servis prema uputama za održavanje?',
    'Što vozač mora napraviti ako ošteti službeno vozilo?',
];

async function scrollToBottom() {
    await nextTick();
    scroller.value?.scrollTo({ top: scroller.value.scrollHeight, behavior: 'smooth' });
}

async function send(text = input.value) {
    const question = text.trim();
    if (!question || loading.value) return;

    messages.value.push({ role: 'user', content: question });
    input.value = '';
    loading.value = true;
    scrollToBottom();

    // Backendu šaljemo samo tekst razgovora (bez koraka i grešaka) — on je stateless
    const history = messages.value
        .filter((m) => !m.error && !m.failed)
        .map((m) => ({ role: m.role, content: m.content }));

    try {
        const res = await api.chat(history);
        messages.value.push({ role: 'assistant', content: res.answer, steps: res.steps, usage: res.usage });
    } catch (e) {
        // Pitanje bez odgovora označimo da ne ide u povijest sljedećeg zahtjeva
        messages.value[messages.value.length - 1].failed = true;
        messages.value.push({ role: 'assistant', content: e.message, error: true });
        input.value = question;
    } finally {
        loading.value = false;
        scrollToBottom();
        textarea.value?.focus();
    }
}

function onKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}

function reset() {
    messages.value = [];
    input.value = '';
}

function prefill(text) {
    input.value = text;
    nextTick(() => textarea.value?.focus());
}

// Link oblika /?q=pitanje odmah postavlja pitanje (korisno za dijeljenje primjera)
onMounted(() => {
    const q = new URLSearchParams(window.location.search).get('q');
    if (q) send(q);
});

defineExpose({ prefill });
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div ref="scroller" class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto flex max-w-3xl flex-col gap-5 px-4 py-6">
                <!-- Prazno stanje -->
                <div v-if="!messages.length" class="py-6">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Što želiš znati o voznom parku?</h2>
                    <p class="mt-1.5 text-sm text-slate-500">
                        Agent sam bira alate: upite nad vožnjama, potrošnjom i alarmima te pretragu internih pravilnika.
                        Ispod svakog odgovora vidiš korake koje je napravio.
                    </p>

                    <div v-if="!claudeReady" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        Nije postavljen <code class="font-mono">ANTHROPIC_API_KEY</code>. Dodaj ga u <code class="font-mono">.env</code> i ponovno učitaj stranicu.
                    </div>

                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="q in examples"
                            :key="q"
                            class="rounded-xl border border-slate-200 bg-white p-3 text-left text-sm text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-indigo-800 dark:hover:bg-indigo-950/40"
                            @click="send(q)"
                        >
                            {{ q }}
                        </button>
                    </div>
                </div>

                <ChatMessage v-for="(m, i) in messages" :key="i" :message="m" />

                <!-- Agent radi -->
                <div v-if="loading" class="flex items-center gap-3 text-sm text-slate-500">
                    <span class="flex gap-1">
                        <span class="size-2 animate-bounce rounded-full bg-indigo-500 [animation-delay:-0.3s]" />
                        <span class="size-2 animate-bounce rounded-full bg-indigo-500 [animation-delay:-0.15s]" />
                        <span class="size-2 animate-bounce rounded-full bg-indigo-500" />
                    </span>
                    Agent analizira pitanje i poziva alate…
                </div>
            </div>
        </div>

        <!-- Unos -->
        <div class="border-t border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
            <form class="mx-auto flex max-w-3xl items-end gap-2" @submit.prevent="send()">
                <button
                    v-if="messages.length"
                    type="button"
                    title="Novi razgovor"
                    class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                    @click="reset"
                >
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" /></svg>
                </button>
                <textarea
                    ref="textarea"
                    v-model="input"
                    rows="1"
                    placeholder="Postavi pitanje… (Enter šalje, Shift+Enter novi red)"
                    class="field-sizing-content max-h-40 min-h-10 flex-1 resize-none rounded-lg border border-slate-200 bg-white px-3 py-2 text-[15px] outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-950 dark:focus:ring-indigo-950"
                    @keydown="onKeydown"
                />
                <button
                    type="submit"
                    :disabled="loading || !input.trim()"
                    class="h-10 shrink-0 rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Pošalji
                </button>
            </form>
        </div>
    </div>
</template>
