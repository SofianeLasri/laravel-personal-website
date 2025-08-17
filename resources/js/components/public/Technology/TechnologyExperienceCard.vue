<script setup lang="ts">
import ArrowUpRightRegular from '@/components/font-awesome/ArrowUpRightRegular.vue';
import BlackLinkButtonSm from '@/components/public/Ui/Button/BlackLinkButtonSm.vue';
import { SSRTechnologyExperience } from '@/types';
import { computed } from 'vue';
import VueMarkdown from 'vue-markdown-render';
import { useRoute } from '@/composables/useRoute';

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
        class="motion-translate-x-in-[0%] motion-translate-y-in-[5%] motion-blur-in-[.5rem] motion-duration-[0.16s]/blur flex flex-col items-center justify-start rounded-2xl bg-black shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] xl:aspect-video dark:bg-gray-100 dark:shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.5)]"
    >
        <div class="flex shrink-0 items-center justify-end gap-2.5 self-stretch px-4 py-1">
            <div class="dark:text-gray-990 justify-center text-sm text-white">
                {{ experience.typeLabel }}
            </div>
        </div>
        <div class="flex grow flex-col items-start justify-between gap-4 self-stretch rounded-2xl bg-white px-4 py-6 dark:bg-gray-950">
            <div class="flex flex-col items-start justify-start gap-4">
                <div class="flex items-center justify-start gap-4">
                    <div class="outline-border flex size-12 items-center justify-center gap-2.5 rounded-lg outline-1 dark:bg-gray-900">
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
                        <div class="text-design-system-title justify-center text-xl font-bold">
                            {{ experience.name }}
                        </div>
                        <div class="text-design-system-paragraph justify-center text-sm font-medium">
                            {{ projectsNumberText }}
                        </div>
                    </div>
                </div>
                <div class="text-design-system-paragraph">
                    <vue-markdown class="markdown-view" :source="experience.description" />
                </div>
            </div>
            <BlackLinkButtonSm :href="projectsUrl">
                <span>Voir les projets</span>
                <ArrowUpRightRegular class="dark:fill-gray-990 h-4 fill-white" />
            </BlackLinkButtonSm>
        </div>
    </div>
</template>

<style scoped></style>
