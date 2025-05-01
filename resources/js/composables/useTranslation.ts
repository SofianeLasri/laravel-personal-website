import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useTranslation() {
    const page = usePage();

    function t(key: string): string {
        const keys = key.split('.');
        let result: any = page.props.translations || {};

        for (const k of keys) {
            result = result[k as keyof typeof result] || null;

            if (result === null) {
                return key;
            }
        }

        return result as string;
    }

    return {
        t,
        locale: computed(() => (page.props.locale as string) || 'fr'),
    };
}
