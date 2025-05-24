<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Bold, Code, Italic, Link as LucideLink, Strikethrough, Underline as LucideUnderline } from 'lucide-vue-next';
import { Markdown } from 'tiptap-markdown';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    modelValue?: string;
    placeholder?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const content = ref(props.modelValue || '');

const editor = useEditor({
    content: content.value,
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3, 4, 5, 6],
            },
        }),
        Underline,
        Link.configure({
            openOnClick: false,
        }),
        Markdown.configure({
            transformPastedText: true,
            transformCopiedText: true,
        }),
    ],
    editable: !props.disabled,
    onUpdate: ({ editor }) => {
        const markdown = editor.storage.markdown.getMarkdown();
        content.value = markdown;
        emit('update:modelValue', markdown);
    },
});

watch(
    () => props.modelValue,
    (newValue) => {
        newValue = newValue || '';

        if (newValue !== content.value) {
            content.value = newValue;

            if (editor.value && editor.value.storage.markdown.getMarkdown() !== newValue) {
                editor.value.commands.setContent(newValue, false);
            }
        }
    },
);

watch(
    () => props.disabled,
    (newValue) => {
        if (editor.value) {
            editor.value.setEditable(!newValue);
        }
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

const toggleBold = () => editor.value?.chain().focus().toggleBold().run();
const toggleItalic = () => editor.value?.chain().focus().toggleItalic().run();
const toggleStrike = () => editor.value?.chain().focus().toggleStrike().run();
const toggleUnderline = () => editor.value?.chain().focus().toggleUnderline().run();
const toggleCode = () => editor.value?.chain().focus().toggleCode().run();
const toggleCodeBlock = () => editor.value?.chain().focus().toggleCodeBlock().run();

const toggleHeading = (level: 1 | 2 | 3 | 4 | 5 | 6) => {
    editor.value?.chain().focus().toggleHeading({ level }).run();
};

const setLink = () => {
    const url = window.prompt('URL');

    if (url === null) {
        return;
    }

    if (url === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};

const isActive = (type: string, options = {}) => {
    return editor.value?.isActive(type, options) ?? false;
};
</script>

<template>
    <Card class="markdown-editor gap-0 py-0">
        <div class="editor-toolbar flex flex-wrap gap-2 border-b p-1">
            <Button
                size="sm"
                variant="outline"
                type="button"
                @click="toggleBold"
                :class="{ 'is-active': isActive('bold') }"
                title="Gras (** texte **)"
            >
                <Bold />
            </Button>

            <Button
                size="sm"
                variant="outline"
                type="button"
                @click="toggleItalic"
                :class="{ 'is-active': isActive('italic') }"
                title="Italique (* texte *)"
            >
                <Italic />
            </Button>

            <Button
                size="sm"
                variant="outline"
                type="button"
                @click="toggleStrike"
                :class="{ 'is-active': isActive('strike') }"
                title="Barré (~~ texte ~~)"
            >
                <Strikethrough />
            </Button>

            <Button
                size="sm"
                variant="outline"
                type="button"
                @click="toggleUnderline"
                :class="{ 'is-active': isActive('underline') }"
                title="Souligné"
            >
                <LucideUnderline />
            </Button>

            <div class="dropdown">
                <Button size="sm" variant="outline" type="button" title="Titres (# Titre)">Titres</Button>
                <Card class="dropdown-content">
                    <Button
                        v-for="level in [1, 2, 3, 4, 5, 6]"
                        :key="level"
                        size="sm"
                        variant="ghost"
                        type="button"
                        @click="toggleHeading(level as 1 | 2 | 3 | 4 | 5 | 6)"
                        :class="{ 'is-active': isActive('heading', { level }) }"
                    >
                        H{{ level }}
                    </Button>
                </Card>
            </div>

            <Button size="sm" variant="outline" type="button" @click="setLink" :class="{ 'is-active': isActive('link') }" title="Lien ([texte](url))">
                <LucideLink />
            </Button>

            <Button
                size="sm"
                variant="outline"
                type="button"
                @click="toggleCode"
                :class="{ 'is-active': isActive('code') }"
                title="Code inline (`code`)"
            >
                <Code />
            </Button>

            <Button
                size="sm"
                variant="outline"
                type="button"
                @click="toggleCodeBlock"
                :class="{ 'is-active': isActive('codeBlock') }"
                title="Bloc de code (```code```)"
            >
                <span class="font-mono">```</span>
                <Code />
            </Button>
        </div>

        <EditorContent :editor="editor" class="editor-content" />
    </Card>
</template>

<style>
.editor-toolbar button.is-active {
    background-color: var(--muted);
}

.dropdown-content {
    display: none;
    position: absolute;
    min-width: 120px;
    z-index: 10;
}

.dropdown:hover .dropdown-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.editor-content {
    padding: 1rem;
}

.ProseMirror {
    outline: none;
    min-height: 150px;
}

.ProseMirror p {
    margin-bottom: 0.75rem;
}

.ProseMirror h1,
.ProseMirror h2,
.ProseMirror h3,
.ProseMirror h4,
.ProseMirror h5,
.ProseMirror h6 {
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    font-weight: bold;
}

.ProseMirror h1 {
    font-size: 2em;
}

.ProseMirror h2 {
    font-size: 1.5em;
}

.ProseMirror h3 {
    font-size: 1.17em;
}

.ProseMirror pre {
    background-color: var(--muted);
    padding: 0.75rem;
    border-radius: 0.25rem;
    font-family: monospace;
    margin: 1rem 0;
}

.ProseMirror code {
    background-color: var(--muted);
    padding: 0.15rem 0.25rem;
    border-radius: 0.25rem;
    font-family: monospace;
}

.ProseMirror a {
    color: rgb(247, 142, 87);
    text-decoration: underline;
}

.ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: var(--muted-foreground);
    pointer-events: none;
    height: 0;
}
</style>
