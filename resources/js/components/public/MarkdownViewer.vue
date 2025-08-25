<script setup lang="ts">
import hljs from 'highlight.js';
import '../../../css/public.css';
import VueMarkdown from 'vue-markdown-render';

defineProps<{
    source: string;
}>();

// Configure markdown-it with highlight.js
const markdownOptions = {
    html: true,
    breaks: true,
    linkify: true,
    typographer: true,
    highlight: function (str: string, lang: string) {
        if (lang && hljs.getLanguage(lang)) {
            try {
                return hljs.highlight(str, { language: lang }).value;
            } catch (error) {
                console.error('Highlight.js error:', error);
            }
        }
        // Try auto-detection
        try {
            return hljs.highlightAuto(str).value;
        } catch (error) {
            console.error('Highlight.js auto-detection error:', error);
        }
        // Return empty string to use default escaping
        return '';
    },
};
</script>

<template>
    <vue-markdown class="markdown-view" :source="source" :options="markdownOptions" />
</template>
