<script setup>
import { computed } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import AgentSteps from './AgentSteps.vue';

const props = defineProps({ message: { type: Object, required: true } });

// Odgovor modela je Markdown; prije prikaza ga čistimo (DOMPurify) radi sigurnosti
const html = computed(() => DOMPurify.sanitize(marked.parse(props.message.content ?? '')));
</script>

<template>
    <!-- Korisnik -->
    <div v-if="message.role === 'user'" class="flex justify-end">
        <div
            class="max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-br-md bg-indigo-600 px-4 py-2.5 text-[15px] text-white"
            :class="{ 'opacity-60': message.failed }"
        >
            {{ message.content }}
        </div>
    </div>

    <!-- Greška -->
    <div v-else-if="message.error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
        {{ message.content }}
    </div>

    <!-- Agent -->
    <div v-else class="flex gap-3">
        <div class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">AI</div>
        <div class="min-w-0 flex-1">
            <div class="md" v-html="html" />

            <AgentSteps v-if="message.steps?.length" :steps="message.steps" class="mt-3" />

            <p v-if="message.usage" class="mt-2 text-xs text-slate-400">
                {{ message.steps?.length || 0 }} poziva alata · {{ message.usage.input_tokens.toLocaleString('hr') }} ulaznih /
                {{ message.usage.output_tokens.toLocaleString('hr') }} izlaznih tokena
            </p>
        </div>
    </div>
</template>
