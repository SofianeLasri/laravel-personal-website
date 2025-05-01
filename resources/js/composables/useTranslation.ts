import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useTranslation() {
    const page = usePage();

    const translations = computed(() => page.props.translations || {});
    const locale = computed(() => page.props.locale || 'fr');

    function t(key: string): string {
        const keys = key.split('.');
        let result = translations.value;

        for (const k of keys) {
            if (result && typeof result === 'object' && k in result) {
                result = result[k];
            } else {
                return key;
            }
        }

        return result as string;
    }

    return { t, locale };
}
