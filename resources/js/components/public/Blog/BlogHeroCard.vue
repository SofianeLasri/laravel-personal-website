<script setup lang="ts">
import { SSRBlogPost } from '@/types';
import BlogCategoryBadge from './BlogCategoryBadge.vue';
import BaseButton from '@/components/public/Ui/Button/BaseButton.vue';

interface Props {
    post: SSRBlogPost;
}

defineProps<Props>();
</script>

<template>
    <div class="flex cursor-pointer flex-col gap-6 transition-transform hover:scale-[1.01] lg:flex-row lg:items-center lg:gap-10">
        <!-- Hero Image -->
        <div class="flex aspect-video h-96 shrink-0 overflow-hidden rounded-2xl shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] lg:h-96">
            <picture>
                <source :srcset="post.coverImage.avif.large" type="image/avif" />
                <source :srcset="post.coverImage.webp.large" type="image/webp" />
                <img
                    :src="post.coverImage.webp.large"
                    :alt="`Image de couverture - ${post.title}`"
                    class="h-full w-full object-cover"
                    loading="eager"
                />
            </picture>
        </div>

        <!-- Hero Content -->
        <div class="flex grow flex-col gap-4">
            <div class="flex flex-col gap-3">
                <!-- Title -->
                <h4 class="text-design-system-title hover:text-primary text-2xl font-bold transition-colors duration-200 lg:text-4xl">
                    {{ post.title }}
                </h4>

                <!-- Category and Date -->
                <div class="flex gap-4">
                    <BlogCategoryBadge :category="post.category" />
                    <div class="text-design-system-paragraph flex items-center">{{ post.publishedAtFormatted }}</div>
                </div>

                <!-- Excerpt -->
                <p class="text-design-system-paragraph">
                    {{ post.excerpt }}
                </p>

                <!-- Read Button -->
                <div>
                    <BaseButton variant="black">Lire l'article</BaseButton>
                </div>
            </div>
        </div>
    </div>
</template>