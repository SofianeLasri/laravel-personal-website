import EmojiSuggestionList, { type EmojiSuggestionItem } from '@/components/dashboard/EmojiSuggestionList.vue';
import { nativeEmojis } from '@/data/native-emojis';
import { Extension } from '@tiptap/core';
import { PluginKey } from '@tiptap/pm/state';
import Suggestion, { type SuggestionOptions } from '@tiptap/suggestion';
import { type VueRenderer } from '@tiptap/vue-3';
import axios from 'axios';
import tippy, { type Instance as TippyInstance } from 'tippy.js';

interface CustomEmoji {
    name: string;
    preview_url: string;
}

let customEmojisCache: CustomEmoji[] = [];
let customEmojisFetched = false;

const fetchCustomEmojis = async (): Promise<CustomEmoji[]> => {
    if (customEmojisFetched) {
        return customEmojisCache;
    }

    try {
        const response = await axios.get('/dashboard/api/custom-emojis-for-editor');
        customEmojisCache = response.data;
        customEmojisFetched = true;
        return customEmojisCache;
    } catch (error) {
        console.error('Failed to fetch custom emojis:', error);
        return [];
    }
};

const getSuggestionItems = async (query: string): Promise<EmojiSuggestionItem[]> => {
    const lowercaseQuery = query.toLowerCase();

    // Filter native emojis
    const matchingNativeEmojis = nativeEmojis
        .filter(
            (emoji) =>
                emoji.name.toLowerCase().includes(lowercaseQuery) || emoji.keywords.some((keyword) => keyword.toLowerCase().includes(lowercaseQuery)),
        )
        .slice(0, 10) // Limit to 10 native emojis
        .map((emoji) => ({
            type: 'native' as const,
            emoji: emoji.emoji,
            name: emoji.name,
        }));

    // Fetch and filter custom emojis
    const customEmojis = await fetchCustomEmojis();
    const matchingCustomEmojis = customEmojis
        .filter((emoji) => emoji.name.toLowerCase().includes(lowercaseQuery))
        .slice(0, 5) // Limit to 5 custom emojis
        .map((emoji) => ({
            type: 'custom' as const,
            name: emoji.name,
            preview_url: emoji.preview_url,
        }));

    // Combine and limit total results
    return [...matchingNativeEmojis, ...matchingCustomEmojis].slice(0, 15);
};

export const EmojiSuggestion = Extension.create({
    name: 'emojiSuggestion',

    addProseMirrorPlugins() {
        return [
            Suggestion({
                editor: this.editor,
                char: ':',
                pluginKey: new PluginKey('emojiSuggestion'),

                command: ({ editor, range, props }) => {
                    const item = props as EmojiSuggestionItem;

                    if (item.type === 'native') {
                        // Insert native emoji directly
                        editor
                            .chain()
                            .focus()
                            .deleteRange(range)
                            .insertContent(item.emoji ?? '')
                            .run();
                    } else {
                        // Insert custom emoji as :name:
                        editor.chain().focus().deleteRange(range).insertContent(`:${item.name}:`).run();
                    }
                },

                allow: ({ state, range }) => {
                    const $from = state.doc.resolve(range.from);
                    const type = state.schema.nodes.codeBlock;
                    const isInCodeBlock = Boolean($from.parent.type.spec.code) || $from.parent.type === type;

                    return !isInCodeBlock;
                },

                items: ({ query }) => {
                    return getSuggestionItems(query);
                },

                render: () => {
                    let component: VueRenderer;
                    let popup: TippyInstance[];

                    return {
                        onStart: (props) => {
                            component = new VueRenderer(EmojiSuggestionList, {
                                props,
                                editor: props.editor,
                            });

                            if (!props.clientRect) {
                                return;
                            }

                            popup = tippy('body', {
                                getReferenceClientRect: props.clientRect as () => DOMRect,
                                appendTo: () => document.body,
                                content: component.element,
                                showOnCreate: true,
                                interactive: true,
                                trigger: 'manual',
                                placement: 'bottom-start',
                            });
                        },

                        onUpdate(props) {
                            component.updateProps(props);

                            if (!props.clientRect) {
                                return;
                            }

                            popup[0].setProps({
                                getReferenceClientRect: props.clientRect as () => DOMRect,
                            });
                        },

                        onKeyDown(props) {
                            if (props.event.key === 'Escape') {
                                popup[0].hide();
                                return true;
                            }

                            // @ts-expect-error - ref is not typed properly
                            return component.ref?.onKeyDown(props.event);
                        },

                        onExit() {
                            popup[0].destroy();
                            component.destroy();
                        },
                    };
                },
            } as SuggestionOptions),
        ];
    },
});
