<script setup lang="ts">
import hljs from 'highlight.js';
import { nextTick, onMounted, ref, watch } from 'vue';
import VueMarkdown from 'vue-markdown-render';
import '../../../css/public.css';

const props = defineProps<{
    source: string;
}>();

const markdownContainer = ref<HTMLElement | null>(null);

const markdownOptions = {
    html: true,
    breaks: true,
    linkify: true,
    typographer: true,
    highlight(str: string, lang: string) {
        if (lang && hljs.getLanguage(lang)) {
            try {
                return hljs.highlight(str, { language: lang }).value;
            } catch (error) {
                console.error('Highlight.js error:', error);
            }
        }

        try {
            return hljs.highlightAuto(str).value;
        } catch (error) {
            console.error('Highlight.js auto-detection error:', error);
        }
        return '';
    },
};

const wrapTables = () => {
    if (!markdownContainer.value) return;

    const tables = markdownContainer.value.querySelectorAll('table');
    tables.forEach((table) => {
        if (table.parentElement?.classList.contains('table-wrapper')) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'table-wrapper relative my-4 min-w-0 w-full overflow-x-auto';

        table.parentNode?.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });
};

onMounted(() => {
    void nextTick(() => {
        wrapTables();
    });
});

watch(
    () => props.source,
    () => {
        void nextTick(() => {
            wrapTables();
        });
    },
);
</script>

<template>
    <div ref="markdownContainer">
        <VueMarkdown class="markdown-view" :source="source" :options="markdownOptions" />
    </div>
</template>
