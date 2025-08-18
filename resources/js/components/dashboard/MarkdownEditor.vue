<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Bold, Code, FileCode, Italic, Link as LucideLink, Underline as LucideUnderline, Strikethrough } from 'lucide-vue-next';
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
const rawMode = ref(false);
const rawContent = ref(props.modelValue || '');

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
        rawContent.value = markdown;
        emit('update:modelValue', markdown);
    },
});

watch(
    () => props.modelValue,
    (newValue) => {
        newValue = newValue || '';

        if (newValue !== content.value) {
            content.value = newValue;
            rawContent.value = newValue;

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

const handleRawContentChange = (event: Event) => {
    const newContent = (event.target as HTMLTextAreaElement).value;
    rawContent.value = newContent;
    content.value = newContent;
    emit('update:modelValue', newContent);

    if (!rawMode.value && editor.value) {
        editor.value.commands.setContent(newContent, false);
    }
};

const toggleRawMode = () => {
    rawMode.value = !rawMode.value;

    if (!rawMode.value && editor.value) {
        editor.value.commands.setContent(rawContent.value, false);
    } else if (rawMode.value && editor.value) {
        rawContent.value = editor.value.storage.markdown.getMarkdown();
    }
};

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
    if (typeof window === 'undefined') return;

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
                v-if="!rawMode"
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
                v-if="!rawMode"
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
                v-if="!rawMode"
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
                v-if="!rawMode"
                size="sm"
                variant="outline"
                type="button"
                @click="toggleUnderline"
                :class="{ 'is-active': isActive('underline') }"
                title="Souligné"
            >
                <LucideUnderline />
            </Button>

            <div v-if="!rawMode" class="dropdown">
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

            <Button
                v-if="!rawMode"
                size="sm"
                variant="outline"
                type="button"
                @click="setLink"
                :class="{ 'is-active': isActive('link') }"
                title="Lien ([texte](url))"
            >
                <LucideLink />
            </Button>

            <Button
                v-if="!rawMode"
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
                v-if="!rawMode"
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

            <Button
                size="sm"
                :variant="rawMode ? 'default' : 'outline'"
                type="button"
                @click="toggleRawMode"
                :title="rawMode ? 'Mode visuel' : 'Mode Markdown brut'"
                class="ml-auto"
            >
                <FileCode />
                <span class="ml-1">{{ rawMode ? 'Visuel' : 'Markdown' }}</span>
            </Button>
        </div>

        <EditorContent v-if="!rawMode" :editor="editor" class="editor-content" />

        <textarea
            v-else
            v-model="rawContent"
            class="raw-markdown-editor"
            :placeholder="placeholder"
            :disabled="disabled"
            @input="handleRawContentChange"
        ></textarea>
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

.raw-markdown-editor {
    width: 100%;
    min-height: 150px;
    padding: 1rem;
    font-family: monospace;
    border: none;
    outline: none;
    resize: vertical;
    background-color: inherit;
    color: inherit;
}
</style>
