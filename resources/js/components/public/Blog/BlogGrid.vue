<script setup lang="ts">
import { SSRBlogPost } from '@/types';
import BlogArticleCard from './BlogArticleCard.vue';

interface Props {
    posts: SSRBlogPost[];
    loading?: boolean;
}

defineProps<Props>();
</script>

<template>
    <div>
        <!-- Posts Grid -->
        <div v-if="posts.length > 0" class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <BlogArticleCard v-for="post in posts" :key="post.id" :post="post" />
        </div>

        <!-- No Results Message -->
        <div v-else class="py-16 text-center">
            <p class="text-design-system-paragraph text-lg">Aucun article trouvé.</p>
            <p class="text-design-system-paragraph mt-2 text-sm">Essayez de modifier vos filtres pour trouver ce que vous cherchez.</p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center py-16">
            <div class="border-primary h-8 w-8 animate-spin rounded-full border-b-2"></div>
        </div>
    </div>
</template>