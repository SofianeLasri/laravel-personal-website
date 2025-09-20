# Plan d'implémentation du système de blog

## Vue d'ensemble
Ajout d'un système de blog complet avec deux types d'articles (standard et test de jeu vidéo), système de contenu modulaire basé sur des composants, et intégration complète avec l'architecture existante du site.

## Structure des URLs
- `/blog` - Page d'accueil du blog (derniers articles)
- `/blog/articles` - Liste complète des articles avec filtres
- `/blog/{slug}` - Page de visualisation d'un article

## Architecture des données

### Tables principales

#### `blog_posts`
- `id`
- `slug` (unique)
- `type` (enum: 'standard', 'game_review')
- `status` (enum: 'draft', 'published')
- `category_id`
- `cover_picture_id` (relation avec `pictures`, format 16:9)
- `published_at`
- `timestamps`

#### `blog_post_drafts`
- Structure identique à `blog_posts` pour le workflow draft-first
- Relations séparées pour le contenu et les médias

#### `blog_categories`
- `id`
- `slug`
- `icon` (optionnel)
- `color` (optionnel)
- `order`
- `timestamps`

#### `blog_post_contents` (table pivot pour le contenu modulaire)
- `id`
- `blog_post_id`
- `content_type` (enum: 'markdown', 'gallery', 'video')
- `content_id` (polymorphique)
- `order`
- `timestamps`

#### `blog_post_draft_contents`
- Structure identique pour les brouillons

#### `blog_content_markdown`
- `id`
- `translation_key_id` (utilise le système de traduction existant)
- `timestamps`

#### `blog_content_galleries`
- `id`
- `layout` (enum: 'grid', 'carousel', 'masonry')
- `columns` (pour grid)
- `timestamps`

#### `blog_content_gallery_pictures` (pivot)
- `gallery_id`
- `picture_id`
- `order`
- `caption_translation_key_id` (optionnel)

#### `blog_content_videos`
- `id`
- `video_id` (relation avec `videos` existant)
- `caption_translation_key_id` (optionnel)
- `timestamps`

### Tables spécifiques aux tests de jeux

#### `game_reviews`
- `id`
- `blog_post_id`
- `game_title`
- `release_date`
- `genre`
- `developer`
- `publisher`
- `platforms` (JSON)
- `cover_picture_id`
- `pros_translation_key_id` (markdown)
- `cons_translation_key_id` (markdown)
- `score` (optionnel, 0-100)
- `timestamps`

#### `game_review_drafts`
- Structure identique pour les brouillons

#### `game_review_links`
- `id`
- `game_review_id`
- `type` (enum: 'steam', 'epic', 'playstation', 'xbox', 'nintendo', 'official', 'other')
- `url`
- `label_translation_key_id` (optionnel)
- `order`

## Modèles Eloquent

### Relations principales
- `BlogPost` → hasMany → `BlogPostContent` (ordonné)
- `BlogPost` → belongsTo → `BlogCategory`
- `BlogPost` → belongsTo → `Picture` (cover)
- `BlogPost` → hasOne → `GameReview` (si type = 'game_review')
- `BlogPostContent` → morphTo → contenable (markdown/gallery/video)
- `BlogContentGallery` → belongsToMany → `Picture`
- `BlogContentVideo` → belongsTo → `Video`
- `GameReview` → hasMany → `GameReviewLink`

## Services

### `BlogService`
- Gestion complète des articles (CRUD)
- Conversion draft → published
- Gestion du contenu modulaire
- Synchronisation des relations

### `BlogContentService`
- Création/modification des blocs de contenu
- Réorganisation des blocs
- Validation de la structure

### `GameReviewService`
- Gestion spécifique des tests de jeux
- Agrégation des données de jeu
- Gestion des liens et métadonnées

### `BlogPublicService`
- Transformation des données pour le frontend public
- Gestion de la pagination
- Filtrage par catégorie
- Optimisation des requêtes (eager loading)

## Frontend Dashboard

### Composants Vue

#### Éditeur d'article
- `BlogPostEditor.vue` - Composant principal
- `BlogContentBuilder.vue` - Gestion des blocs de contenu
- `BlogContentBlock.vue` - Bloc individuel (wrapper)
- `BlogMarkdownEditor.vue` - Éditeur markdown (basé sur TipTap)
- `BlogGalleryEditor.vue` - Configuration de galerie
- `BlogVideoSelector.vue` - Sélection de vidéo
- `GameReviewEditor.vue` - Section spécifique aux tests

#### Interface de gestion
- `BlogPostList.vue` - Liste des articles
- `BlogCategoryManager.vue` - Gestion des catégories
- `BlogPostPreview.vue` - Prévisualisation

### Flux de travail éditorial
1. Création/édition sur entité draft
2. Ajout/réorganisation des blocs de contenu
3. Upload/sélection des médias
4. Prévisualisation
5. Publication (conversion draft → published)

## Frontend Public

### Composants Vue

#### Pages
- `BlogIndex.vue` - Page d'accueil du blog
- `BlogArticleList.vue` - Liste filtrée des articles
- `BlogArticle.vue` - Affichage d'un article

#### Blocs de contenu
- `BlogContentRenderer.vue` - Orchestrateur principal
- `BlogMarkdownContent.vue` - Rendu markdown
- `BlogGalleryContent.vue` - Affichage galerie (PhotoSwipe)
- `BlogVideoContent.vue` - Lecteur vidéo (Bunny Stream)
- `GameReviewSection.vue` - Section test de jeu

#### Composants UI
- `BlogCard.vue` - Carte d'article pour les listes
- `BlogCategoryFilter.vue` - Filtrage par catégorie
- `BlogPagination.vue` - Pagination

### Types TypeScript

```typescript
interface BlogPost {
  id: number
  slug: string
  type: 'standard' | 'game_review'
  category: BlogCategory
  coverImage: Picture
  title: string
  excerpt: string
  content: BlogContent[]
  gameReview?: GameReview
  publishedAt: string
}

interface BlogContent {
  id: number
  type: 'markdown' | 'gallery' | 'video'
  order: number
  data: MarkdownContent | GalleryContent | VideoContent
}

interface MarkdownContent {
  html: string
}

interface GalleryContent {
  layout: 'grid' | 'carousel' | 'masonry'
  columns?: number
  images: GalleryImage[]
}

interface VideoContent {
  video: Video
  caption?: string
}

interface GameReview {
  gameTitle: string
  releaseDate: string
  genre: string
  developer: string
  publisher: string
  platforms: string[]
  coverImage: Picture
  pros: string
  cons: string
  score?: number
  links: GameReviewLink[]
}
```

## Migrations

### Ordre de création
1. `blog_categories`
2. `blog_posts` et `blog_post_drafts`
3. Tables de contenu (`blog_content_*`)
4. Tables pivot (`blog_post_contents`, etc.)
5. `game_reviews` et tables associées

## Routes

### Public
```php
Route::get('/blog', [BlogController::class, 'index']);
Route::get('/blog/articles', [BlogController::class, 'list']);
Route::get('/blog/{slug}', [BlogController::class, 'show']);
```

### Dashboard
```php
Route::prefix('dashboard/blog')->group(function () {
    Route::resource('posts', BlogPostController::class);
    Route::post('posts/{id}/publish', [BlogPostController::class, 'publish']);
    Route::resource('categories', BlogCategoryController::class);
    Route::post('content/reorder', [BlogContentController::class, 'reorder']);
});
```

## Intégration avec l'existant

### Réutilisation
- Système de traduction (`TranslationKey`, `Translation`)
- Gestion des images (`Picture`, `ImageTranscodingService`)
- Gestion des vidéos (`Video`, `BunnyStreamService`)
- Workflow draft-first (pattern des `Creation`)
- Composants UI existants (Reka UI, PhotoSwipe)

### Nouveautés
- Système de contenu modulaire/composable
- Éditeur de contenu drag-and-drop
- Types de contenu spécialisés (game review)
- Rendu SSR des blocs de contenu

## Phases de développement

### Phase 1: Backend Core
1. Créer les migrations
2. Créer les modèles Eloquent avec relations
3. Implémenter `BlogService` et `BlogContentService`
4. Créer les contrôleurs API pour le dashboard
5. Tests unitaires et feature tests

### Phase 2: Dashboard Frontend
1. Créer l'éditeur de blocs de contenu
2. Implémenter le BlogPostEditor
3. Ajouter la gestion des catégories
4. Intégrer l'éditeur de tests de jeux
5. Tests E2E avec Dusk

### Phase 3: Public Frontend
1. Créer les pages publiques (index, liste, article)
2. Implémenter les renderers de contenu
3. Optimiser pour le SSR
4. Ajouter le support multilingue
5. Tests de performance et SEO

### Phase 4: Finalisation
1. Optimisation des requêtes (N+1)
2. Mise en cache
3. Documentation
4. Migration de contenu existant (si applicable)

## Considérations techniques

### Performance
- Eager loading systématique des relations
- Cache des articles publiés
- Lazy loading des images dans les galeries
- Pagination côté serveur

### SEO
- Métadonnées Open Graph pour chaque article
- Sitemap XML dynamique
- Schema.org pour les articles et reviews
- URLs canoniques

### Accessibilité
- Navigation au clavier dans l'éditeur
- Alt text pour toutes les images
- Structure HTML sémantique
- Support des lecteurs d'écran

## Points d'attention

1. **Gestion de la complexité**: Le système de contenu modulaire ajoute de la complexité, bien documenter les patterns
2. **Migration future**: Prévoir la migration du contenu des projets vers ce nouveau système
3. **Performance SSR**: Optimiser le rendu des blocs de contenu pour le SSR
4. **Cohérence UI**: Maintenir la cohérence avec le design existant
5. **Tests**: Coverage complet vu la complexité du système

## Estimations

- **Backend Core**: 3-4 jours
- **Dashboard Frontend**: 4-5 jours
- **Public Frontend**: 2-3 jours
- **Tests & Optimisation**: 2 jours
- **Total**: ~2 semaines

## Prochaines étapes

1. Valider le plan avec les maquettes
2. Commencer par les migrations de base de données
3. Implémenter les modèles et relations
4. Développer les services métier
5. Créer l'interface d'administration
6. Finaliser avec le frontend public