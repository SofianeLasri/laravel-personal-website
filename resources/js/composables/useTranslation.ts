import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useTranslation() {
    const page = usePage();

    function t(key: string, replacements?: Record<string, string | number>): string {
        const keys = key.split('.');
        let result: unknown = page.props.translations ?? {};

        for (const k of keys) {
            if (result && typeof result === 'object' && k in result) {
                result = (result as Record<string, unknown>)[k];
            } else {
                result = null;
                break;
            }

            if (result === null) {
                return key;
            }
        }

        let translatedString = result as string;

        // Handle variable replacements
        if (replacements) {
            for (const [placeholder, value] of Object.entries(replacements)) {
                // Replace Laravel-style placeholders (:variable) with actual values
                const pattern = new RegExp(`:${placeholder}\\b`, 'g');
                translatedString = translatedString.replace(pattern, String(value));
            }
        }

        return translatedString;
    }

    return {
        t,
        locale: computed(() => (page.props.locale as string) ?? 'fr'),
    };
}
