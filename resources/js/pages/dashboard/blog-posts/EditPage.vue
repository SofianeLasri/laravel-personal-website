<script setup lang="ts">
import BlogContentBuilder from '@/components/dashboard/BlogContentBuilder.vue';
import CategoryQuickCreate from '@/components/dashboard/CategoryQuickCreate.vue';
import GameReviewEditor from '@/components/dashboard/GameReviewEditor.vue';
import Heading from '@/components/dashboard/Heading.vue';
import HeadingSmall from '@/components/dashboard/HeadingSmall.vue';
import PictureInput from '@/components/dashboard/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BlogCategory, BlogPostDraftWithAllRelations, BlogPostType, BreadcrumbItem, Picture, Video } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import axios from 'axios';
import slugify from 'slugify';
import { useForm } from 'vee-validate';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

interface Props {
    blogPostDraft?: BlogPostDraftWithAllRelations;
    categories: BlogCategory[];
    pictures: Picture[];
    videos: Video[];
    blogPostTypes: { name: string; value: BlogPostType }[];
}

const props = defineProps<Props>();
const route = useRoute();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Articles',
        href: '#',
    },
    {
        title: 'Éditeur',
        href: route('dashboard.blog-posts.edit', undefined, false),
    },
];

const isSubmitting = ref(false);
const isPublishing = ref(false);
const currentBlogPostDraft = ref<BlogPostDraftWithAllRelations | null>(null);

if (props.blogPostDraft) {
    currentBlogPostDraft.value = props.blogPostDraft;
}

const page = usePage();
const locale = ref<'fr' | 'en'>('fr');

const getOriginalBlogPostId = (): number | null => {
    let url = new URL(page.props.ziggy.location);
    if (typeof window !== 'undefined' && window.location.href) {
        url = new URL(window.location.href);
    }

    const blogPostId = url.searchParams.get('blog-post-id');
    return blogPostId ? parseInt(blogPostId) : null;
};

const originalBlogPostId = ref(getOriginalBlogPostId());

const formSchema = toTypedSchema(
    z.object({
        slug: z.string().min(1, 'Le slug est requis'),
        cover_picture_id: z.coerce.number().nullable(),
        type: z.string(),
        category_id: z.coerce.number().min(1, 'Veuillez sélectionner une catégorie'),
        locale: z.enum(['fr', 'en']).default('fr'),
        title_content: z.string().min(1, 'Le titre est requis'),
    }),
);

const form = useForm({
    validationSchema: formSchema,
    initialValues: {
        slug: currentBlogPostDraft.value?.slug || '',
        cover_picture_id: currentBlogPostDraft.value?.cover_picture_id || null,
        type: currentBlogPostDraft.value?.type || 'article',
        category_id: currentBlogPostDraft.value?.category_id?.toString() || props.categories[0]?.id?.toString() || '1',
        locale: 'fr' as 'fr' | 'en',
        title_content: '',
    },
});

// Load initial content from existing draft
onMounted(() => {
    if (currentBlogPostDraft.value) {
        // Load title translation
        if (currentBlogPostDraft.value.title_translation_key) {
            const translation = currentBlogPostDraft.value.title_translation_key.translations.find((t) => t.locale === locale.value);
            if (translation) {
                form.setFieldValue('title_content', translation.text);
            }
        }

        // Load category
        if (currentBlogPostDraft.value.category_id) {
            form.setFieldValue('category_id', currentBlogPostDraft.value.category_id.toString());
        }

        // Load cover picture
        if (currentBlogPostDraft.value.cover_picture_id) {
            form.setFieldValue('cover_picture_id', currentBlogPostDraft.value.cover_picture_id);
        }

        // Load other fields that might not be in initialValues
        if (currentBlogPostDraft.value.slug) {
            form.setFieldValue('slug', currentBlogPostDraft.value.slug);
        }

        if (currentBlogPostDraft.value.type) {
            form.setFieldValue('type', currentBlogPostDraft.value.type);
        }
    }
});

// Watch locale changes to load appropriate translation
watch(locale, (newLocale) => {
    if (currentBlogPostDraft.value?.title_translation_key) {
        const translation = currentBlogPostDraft.value.title_translation_key.translations.find((t) => t.locale === newLocale);
        form.setFieldValue('title_content', translation?.text || '');
    }
});

// Auto-generate slug from title
watch(
    () => form.values.title_content,
    (newTitle) => {
        if (newTitle && !currentBlogPostDraft.value) {
            const generatedSlug = slugify(newTitle, {
                lower: true,
                strict: true,
                locale: locale.value,
            });
            form.setFieldValue('slug', generatedSlug);
        }
    },
);

const blogPostTypeLabels: Record<BlogPostType, string> = {
    article: 'Article',
    tutorial: 'Tutoriel',
    news: 'Actualité',
    review: 'Critique',
    guide: 'Guide',
    game_review: 'Critique de jeu',
};

const showGameReviewSection = computed(() => form.values.type === 'game_review');

const handleSubmit = form.handleSubmit(async (values) => {
    isSubmitting.value = true;

    try {
        const payload = {
            ...values,
            title_translation_key_id: currentBlogPostDraft.value?.title_translation_key_id,
            original_blog_post_id: currentBlogPostDraft.value?.original_blog_post_id || originalBlogPostId.value,
        };

        console.log('Submit payload:', payload); // Debug
        console.log('Cover picture ID in payload:', payload.cover_picture_id); // Debug

        if (currentBlogPostDraft.value) {
            // Update existing draft
            await axios.put(route('dashboard.api.blog-post-drafts.update', { blog_post_draft: currentBlogPostDraft.value.id }), payload);
            toast.success('Brouillon mis à jour avec succès');
        } else {
            // Create new draft
            const response = await axios.post(route('dashboard.api.blog-post-drafts.store'), payload);
            currentBlogPostDraft.value = response.data;

            // Update URL to reflect the draft ID
            const url = new URL(window.location.href);
            url.searchParams.set('draft-id', response.data.id.toString());
            url.searchParams.delete('blog-post-id');
            window.history.replaceState({}, '', url.toString());

            toast.success('Brouillon créé avec succès');
        }

        router.reload({ only: ['blogPostDraft'] });
    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error);
        toast.error('Une erreur est survenue lors de la sauvegarde');
    } finally {
        isSubmitting.value = false;
    }
});

const handlePublish = async () => {
    if (!currentBlogPostDraft.value) {
        toast.error("Veuillez d'abord sauvegarder le brouillon");
        return;
    }

    isPublishing.value = true;

    try {
        await axios.post(route('dashboard.api.blog-posts.store'), {
            draft_id: currentBlogPostDraft.value.id,
        });

        toast.success('Article publié avec succès');
        router.visit(route('dashboard.blog-posts.index'));
    } catch (error) {
        console.error('Erreur lors de la publication:', error);
        toast.error('Une erreur est survenue lors de la publication');
    } finally {
        isPublishing.value = false;
    }
};

const handleCoverPictureChange = (pictureId: number | null) => {
    console.log('Cover picture changed:', pictureId); // Debug
    form.setFieldValue('cover_picture_id', pictureId);
    console.log('Form value after change:', form.values.cover_picture_id); // Debug
};

const categories = ref([...props.categories]);

const handleCategoryCreated = (newCategory: BlogCategory) => {
    categories.value.push(newCategory);
    form.setFieldValue('category_id', newCategory.id);
    toast.success('Catégorie sélectionnée automatiquement');
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="currentBlogPostDraft ? 'Modifier l\'article' : 'Nouvel article'" />

        <div class="px-5 py-6">
            <Heading
                :title="currentBlogPostDraft ? 'Modifier l\'article' : 'Nouvel article'"
                :description="currentBlogPostDraft?.original_blog_post_id ? 'Modification d\'un article publié' : 'Création d\'un nouvel article'"
            />

            <form class="space-y-8" data-testid="blog-form" @submit="handleSubmit">
                <!-- Language selector -->
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium">Langue :</label>
                    <Button type="button" :variant="locale === 'fr' ? 'default' : 'outline'" size="sm" @click="locale = 'fr'"> Français </Button>
                    <Button type="button" :variant="locale === 'en' ? 'default' : 'outline'" size="sm" @click="locale = 'en'"> English </Button>
                </div>

                <!-- Basic Information -->
                <div class="space-y-4">
                    <HeadingSmall title="Informations générales" />

                    <FormField v-slot="{ componentField }" name="title_content">
                        <FormItem>
                            <FormLabel>Titre</FormLabel>
                            <FormControl>
                                <Input type="text" placeholder="Titre de l'article" v-bind="componentField" data-testid="blog-title-input" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="slug">
                        <FormItem>
                            <FormLabel>Slug</FormLabel>
                            <FormControl>
                                <Input type="text" placeholder="slug-de-l-article" v-bind="componentField" />
                            </FormControl>
                            <FormDescription> L'URL de l'article : /blog/{{ form.values.slug || 'slug-de-l-article' }} </FormDescription>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField v-slot="{ componentField }" name="type">
                            <FormItem>
                                <FormLabel>Type d'article</FormLabel>
                                <Select v-bind="componentField">
                                    <FormControl>
                                        <SelectTrigger data-testid="blog-type-select">
                                            <SelectValue placeholder="Sélectionner un type" />
                                        </SelectTrigger>
                                    </FormControl>
                                    <SelectContent>
                                        <SelectItem v-for="blogType in blogPostTypes" :key="blogType.value" :value="blogType.value">
                                            {{ blogPostTypeLabels[blogType.value as BlogPostType] }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="category_id">
                            <FormItem>
                                <div class="flex items-center justify-between">
                                    <FormLabel>Catégorie</FormLabel>
                                    <CategoryQuickCreate :locale="locale" @category-created="handleCategoryCreated" />
                                </div>
                                <Select v-bind="componentField">
                                    <FormControl>
                                        <SelectTrigger data-testid="blog-category-select">
                                            <SelectValue placeholder="Sélectionner une catégorie" />
                                        </SelectTrigger>
                                    </FormControl>
                                    <SelectContent>
                                        <SelectItem v-for="category in categories" :key="category.id" :value="category.id.toString()">
                                            {{ category.name_translation_key.translations.find((t) => t.locale === 'fr')?.text }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <FormDescription v-if="categories.length === 0">
                                    Aucune catégorie disponible. Créez-en une avec le bouton ci-dessus.
                                </FormDescription>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <FormField name="cover_picture_id">
                        <FormItem>
                            <FormLabel>Image de couverture</FormLabel>
                            <FormControl>
                                <PictureInput
                                    :model-value="form.values.cover_picture_id"
                                    label="Image de couverture (16:9 recommandé)"
                                    @update:model-value="handleCoverPictureChange"
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <!-- Game Review Section (if type is game_review) -->
                <div v-if="showGameReviewSection">
                    <div
                        v-if="!currentBlogPostDraft"
                        class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20"
                    >
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            <strong>Info :</strong> Veuillez d'abord sauvegarder le brouillon pour pouvoir ajouter les informations de critique de
                            jeu.
                        </p>
                    </div>
                    <GameReviewEditor
                        v-else
                        :draft-id="currentBlogPostDraft?.id"
                        :game-review-draft="currentBlogPostDraft?.game_review_draft"
                        :locale="locale"
                    />
                </div>

                <!-- Content Builder -->
                <div class="space-y-4">
                    <HeadingSmall title="Contenu de l'article" />
                    <div
                        v-if="!currentBlogPostDraft"
                        class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
                    >
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            <strong>Info :</strong> Veuillez d'abord sauvegarder le brouillon pour pouvoir ajouter du contenu à l'article.
                        </p>
                        <p class="mt-2 text-xs text-blue-600 dark:text-blue-300">
                            Cette mesure évite la création de contenus orphelins dans la base de données.
                        </p>
                    </div>
                    <BlogContentBuilder
                        v-else
                        :draft-id="currentBlogPostDraft?.id"
                        :contents="currentBlogPostDraft?.contents || []"
                        :pictures="pictures"
                        :videos="videos"
                        :locale="locale"
                        data-testid="content-builder"
                    />
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" @click="router.visit(route('dashboard.blog-posts.drafts.index'))"> Annuler </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Sauvegarde...' : 'Sauvegarder le brouillon' }}
                    </Button>
                    <Button v-if="currentBlogPostDraft" type="button" :disabled="isPublishing" @click="handlePublish">
                        {{ isPublishing ? 'Publication...' : 'Publier' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>