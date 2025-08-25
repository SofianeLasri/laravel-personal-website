<script setup lang="ts">
import hljs from 'highlight.js';
import 'highlight.js/styles/atom-one-light.css';
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

<style>
/* Override with dark theme when in dark mode */
.dark .hljs {
    /* Atom One Dark theme colors */
    background: #282c34;
    color: #abb2bf;
}

.dark .hljs-comment,
.dark .hljs-quote {
    color: #5c6370;
    font-style: italic;
}

.dark .hljs-doctag,
.dark .hljs-keyword,
.dark .hljs-formula {
    color: #c678dd;
}

.dark .hljs-section,
.dark .hljs-name,
.dark .hljs-selector-tag,
.dark .hljs-deletion,
.dark .hljs-subst {
    color: #e06c75;
}

.dark .hljs-literal {
    color: #56b6c2;
}

.dark .hljs-string,
.dark .hljs-regexp,
.dark .hljs-addition,
.dark .hljs-attribute,
.dark .hljs-meta .hljs-string {
    color: #98c379;
}

.dark .hljs-attr,
.dark .hljs-variable,
.dark .hljs-template-variable,
.dark .hljs-type,
.dark .hljs-selector-class,
.dark .hljs-selector-attr,
.dark .hljs-selector-pseudo,
.dark .hljs-number {
    color: #d19a66;
}

.dark .hljs-symbol,
.dark .hljs-bullet,
.dark .hljs-link,
.dark .hljs-meta,
.dark .hljs-selector-id,
.dark .hljs-title {
    color: #61aeee;
}

.dark .hljs-built_in,
.dark .hljs-title.class_,
.dark .hljs-class .hljs-title {
    color: #e6c07b;
}

.dark .hljs-emphasis {
    font-style: italic;
}

.dark .hljs-strong {
    font-weight: bold;
}

.dark .hljs-link {
    text-decoration: underline;
}

/* Ensure proper background for code blocks */
.markdown-view pre {
    background-color: #fafafa;
}

.dark .markdown-view pre {
    background-color: #282c34;
    border-color: #4b5563;
}

.dark .markdown-view code:not(pre code) {
    background-color: #374151;
    color: #e5e7eb;
}

/* Additional styles for markdown content */
.markdown-view {
    /* Base text color */
    color: var(--color-design-system-paragraph);
}

/* Code block container styles */
.markdown-view pre {
    position: relative;
    margin: 1rem 0;
    overflow-x: auto;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    max-width: 100%;
}

.markdown-view pre code {
    display: block;
    font-size: 0.875rem;
    font-family: monospace;
    padding: 1rem !important;
    background: transparent !important;
}

/* Ensure hljs code blocks are visible */
.markdown-view pre.hljs {
    padding: 0;
}

.markdown-view pre.hljs code {
    background: transparent !important;
}

/* Inline code styles */
.markdown-view code:not(pre code) {
    border-radius: 0.25rem;
    padding: 0.125rem 0.375rem;
    font-size: 0.875rem;
    font-family: monospace;
    background-color: #e5e7eb;
    color: #1f2937;
}

.markdown-view h1,
.markdown-view h2 {
    color: var(--color-design-system-title);
    margin-bottom: 2rem;
    font-size: 1.5rem;
    font-weight: bold;
}

.markdown-view h3 {
    color: var(--color-design-system-paragraph);
    margin-bottom: 1.5rem;
    font-size: 1.25rem;
    font-weight: bold;
}

.markdown-view h4 {
    color: var(--color-design-system-paragraph);
    margin-bottom: 0.75rem;
    font-size: 1.125rem;
    font-weight: bold;
}

.markdown-view h1:not(:first-child),
.markdown-view h2:not(:first-child) {
    margin-top: 2rem;
}

.markdown-view h3:not(:first-child),
.markdown-view h4:not(:first-child) {
    margin-top: 1.5rem;
}

.markdown-view a {
    color: var(--color-design-system-primary);
    text-decoration: underline;
}

.markdown-view p:not(:last-child) {
    margin-bottom: 1rem;
}

.markdown-view ul {
    margin-bottom: 1rem;
    list-style: disc inside;
    padding-top: 0.5rem;
    padding-left: 1rem;
}

.markdown-view ul li {
    margin-bottom: 0.5rem;
}

.markdown-view ol {
    margin-bottom: 1rem;
    list-style: decimal inside;
    padding-top: 0.5rem;
    padding-left: 1rem;
}

.markdown-view ol li {
    margin-bottom: 0.5rem;
}

.markdown-view blockquote {
    border-left: 4px solid #d1d5db;
    padding-left: 1rem;
    margin: 1rem 0;
    font-style: italic;
    color: #6b7280;
}

.markdown-view table {
    width: 100%;
    margin: 1rem 0;
    border-collapse: collapse;
}

.markdown-view th,
.markdown-view td {
    border: 1px solid #d1d5db;
    padding: 0.5rem 1rem;
}

.markdown-view th {
    background-color: #f3f4f6;
    font-weight: 600;
}

.markdown-view hr {
    margin: 2rem 0;
    border: none;
    border-top: 1px solid #d1d5db;
}

.markdown-view img {
    max-width: 100%;
    height: auto;
    border-radius: 0.5rem;
    margin: 1rem 0;
}

/* Dark mode overrides for markdown elements */
.dark .markdown-view blockquote {
    border-left-color: #4b5563;
    color: #9ca3af;
}

.dark .markdown-view th,
.dark .markdown-view td {
    border-color: #4b5563;
}

.dark .markdown-view th {
    background-color: #1f2937;
}

.dark .markdown-view hr {
    border-top-color: #4b5563;
}

/* Scrollbar styling for code blocks */
.markdown-view pre::-webkit-scrollbar {
    height: 8px;
}

.markdown-view pre::-webkit-scrollbar-track {
    background-color: #f3f4f6;
    border-radius: 0.25rem;
}

.markdown-view pre::-webkit-scrollbar-thumb {
    background-color: #9ca3af;
    border-radius: 0.25rem;
}

.markdown-view pre::-webkit-scrollbar-thumb:hover {
    background-color: #6b7280;
}

.dark .markdown-view pre::-webkit-scrollbar-track {
    background-color: #1f2937;
}

.dark .markdown-view pre::-webkit-scrollbar-thumb {
    background-color: #4b5563;
}

.dark .markdown-view pre::-webkit-scrollbar-thumb:hover {
    background-color: #6b7280;
}
</style>
