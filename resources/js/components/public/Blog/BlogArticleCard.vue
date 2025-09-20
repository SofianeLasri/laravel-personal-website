<script setup lang="ts">
import ArrowRightRegular from '@/components/font-awesome/ArrowRightRegular.vue';
import { SSRBlogPost } from '@/types';
import { Link } from '@inertiajs/vue3';
import BlogCategoryBadge from './BlogCategoryBadge.vue';

interface Props {
    post: SSRBlogPost;
}

defineProps<Props>();
</script>

<template>
    <Link
        :href="route('public.blog.post', { slug: post.slug })"
        class="group flex cursor-pointer flex-col gap-4 transition-transform hover:scale-[1.02]"
    >
        <!-- Article Card -->
        <div class="relative aspect-video w-full overflow-hidden rounded-2xl shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)]">
            <!-- Category Badge Overlay -->
            <div class="absolute z-10 flex h-full w-full p-2">
                <div>
                    <BlogCategoryBadge :category="post.category" size="sm" />
                </div>
            </div>

            <!-- Cover Image -->
            <picture>
                <source :srcset="post.coverImage.avif.medium" type="image/avif" />
                <source :srcset="post.coverImage.webp.medium" type="image/webp" />
                <img
                    :src="post.coverImage.webp.medium"
                    :alt="`Image de couverture - ${post.title}`"
                    class="h-full w-full object-cover"
                    loading="lazy"
                />
            </picture>
        </div>

        <!-- Article Info -->
        <div class="flex flex-col">
            <h4 class="text-design-system-title group-hover:text-primary text-xl font-bold transition-colors duration-200">{{ post.title }}</h4>
            <div class="text-design-system-paragraph text-sm">{{ post.publishedAtFormatted }}</div>
        </div>

        <!-- Excerpt -->
        <p class="text-design-system-paragraph">{{ post.excerpt }}</p>

        <!-- Read More Button -->
        <div class="flex w-full justify-end">
            <div class="group/button flex w-fit flex-col">
                <div class="flex items-center gap-1">
                    <div class="font-bold">Lire l'article</div>
                    <ArrowRightRegular class="size-3 fill-black dark:fill-white" />
                </div>
                <!-- Underline -->
                <div class="group-hover/button:bg-primary h-1 bg-transparent transition-colors duration-200"></div>
            </div>
        </div>
    </Link>
</template>
