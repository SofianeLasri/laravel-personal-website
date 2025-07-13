<script setup lang="ts">
import BlackLinkButton from '@/components/public/Ui/Button/BlackLinkButton.vue';
import { useTranslation } from '@/composables/useTranslation';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink } from '@/types';
import { Head } from '@inertiajs/vue3';
import WhiteLinkButton from '@/components/public/Ui/Button/WhiteLinkButton.vue';

const { t } = useTranslation();

defineProps<{
    socialMediaLinks: SocialMediaLink[];
}>();

const pageTitle = t('errors.404.page_name');
</script>

<template>
    <Head :title="pageTitle">
        <!-- SEO Meta Tags -->
        <meta name="description" :content="t('errors.404.page_description')" />
        <meta name="robots" content="noindex, follow" />
    </Head>

    <PublicAppLayout :social-media-links="socialMediaLinks">
        <div class="flex min-h-[80vh] items-center justify-center py-8">
            <div class="container flex flex-col items-center justify-center gap-12 lg:flex-row">
                <div>
                    <h1 class="text-primary text-9xl font-bold md:text-[16rem]">404</h1>
                </div>
                <div>
                    <div class="mb-6 flex flex-col">
                        <div class="text-design-system-paragraph">Erreur 404</div>
                        <h1 class="text-design-system-title text-5xl font-bold">Page non trouvée :’(</h1>
                    </div>

                    <div class="mb-6">
                        <p class="text-design-system-paragraph text-lg">
                            {{ t('errors.404.page_description') }}
                        </p>
                        <p class="text-design-system-paragraph text-lg">
                            {{ t('errors.404.page_description_2') }}
                        </p>
                    </div>

                    <p class="text-design-system-paragraph mb-6 text-lg">
                        {{ t('errors.404.page_description_3') }}
                    </p>

                    <div class="flex flex-col gap-4 sm:flex-row">
                        <BlackLinkButton href="/">
                            {{ t('errors.404.home_page_link') }}
                        </BlackLinkButton>

                        <WhiteLinkButton href="https://old.sl-projects.com" target="_blank" rel="noopener noreferrer">
                            {{ t('errors.404.old_website_link') }}
                        </WhiteLinkButton>
                    </div>
                </div>
            </div>
        </div>
    </PublicAppLayout>
</template>