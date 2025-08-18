<script setup lang="ts">
import ArrowUpRightRegular from '@/components/font-awesome/ArrowUpRightRegular.vue';
import BaseButton from '@/components/public/Ui/Button/BaseButton.vue';
import { useRoute } from '@/composables/useRoute';
import { SSRTechnologyExperience } from '@/types';
import { computed } from 'vue';
import VueMarkdown from 'vue-markdown-render';

const props = defineProps<{
    experience: SSRTechnologyExperience;
}>();
const route = useRoute();

let projectsNumberText: string;
if (props.experience.creationCount > 1) {
    projectsNumberText = `${props.experience.creationCount} projets`;
} else {
    projectsNumberText = `${props.experience.creationCount} projet`;
}

const projectsUrl = computed(() => {
    const params: Record<string, string> = {
        tab: 'development',
    };

    switch (props.experience.type) {
        case 'framework':
            params.frameworks = props.experience.technologyId.toString();
            break;
        case 'library':
            params.libraries = props.experience.technologyId.toString();
            break;
        case 'language':
            params.languages = props.experience.technologyId.toString();
            break;
        case 'game_engine':
            params.tab = 'games';
            params.gameEngines = props.experience.technologyId.toString();
            break;
    }

    return route('public.projects', params);
});
</script>

<template>
    <div
        class="group motion-translate-x-in-[0%] motion-translate-y-in-[5%] motion-blur-in-[.5rem] motion-duration-[0.16s]/blur dark:from-atomic-tangerine-900/40 dark:border-atomic-tangerine-500/30 flex flex-col items-center justify-start rounded-2xl bg-black shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] xl:aspect-video dark:relative dark:border dark:bg-gradient-to-br dark:to-gray-900/90 dark:shadow-[0_0_30px_rgba(247,142,87,0.2)] dark:backdrop-blur-sm dark:transition-all dark:hover:shadow-[0_0_40px_rgba(247,142,87,0.35)]"
    >
        <div class="flex shrink-0 items-center justify-end gap-2.5 self-stretch px-4 py-1">
            <div class="dark:text-atomic-tangerine-200 justify-center text-sm text-white">
                {{ experience.typeLabel }}
            </div>
        </div>
        <div class="flex grow flex-col items-start justify-between gap-4 self-stretch rounded-2xl bg-white px-4 py-6 dark:bg-gray-950">
            <div class="flex flex-col items-start justify-start gap-4">
                <div class="flex items-center justify-start gap-4">
                    <div
                        class="outline-border dark:from-atomic-tangerine-600/20 dark:border-atomic-tangerine-500/20 dark:group-hover:border-atomic-tangerine-500/30 flex size-12 items-center justify-center gap-2.5 rounded-lg outline-1 dark:bg-gradient-to-br dark:to-gray-800/60 dark:shadow-[inset_0_1px_0_0_rgba(247,142,87,0.1)]"
                    >
                        <div class="flex size-7 items-center justify-center">
                            <img
                                v-if="experience.iconPicture"
                                :src="experience.iconPicture.webp.small"
                                :alt="experience.name"
                                class="h-full w-full object-contain"
                            />
                        </div>
                    </div>
                    <div class="size- flex flex-col items-start justify-start">
                        <div
                            class="text-design-system-title dark:text-atomic-tangerine-100 dark:group-hover:text-atomic-tangerine-50 justify-center text-xl font-bold"
                        >
                            {{ experience.name }}
                        </div>
                        <div class="text-design-system-paragraph dark:text-atomic-tangerine-300/80 justify-center text-sm font-medium">
                            {{ projectsNumberText }}
                        </div>
                    </div>
                </div>
                <div class="text-design-system-paragraph dark:text-gray-300">
                    <vue-markdown class="markdown-view" :source="experience.description" />
                </div>
            </div>
            <BaseButton variant="black" size="sm" as="link" :href="projectsUrl">
                <span>Voir les projets</span>
                <ArrowUpRightRegular class="dark:fill-gray-990 h-4 fill-white" />
            </BaseButton>
        </div>
    </div>
</template>

<style scoped></style>
