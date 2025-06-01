# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Start Development Environment
```bash
# Start all development services (server, queue, logs, vite)
composer dev

# With SSR support
composer dev:ssr
```

### Testing
```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run browser tests (requires Chrome/Chromium)
php artisan dusk

# Run single test file
php artisan test tests/Feature/Services/PublicControllersServiceTest.php

# Run with coverage
php artisan test --coverage

# Docker commands (recommended for local development)
docker exec laravel.test php artisan test
docker exec laravel.test php artisan test tests/Feature/Services/PublicControllersServiceTest.php
```

### Code Quality
```bash
# Run static analysis
./vendor/bin/phpstan analyse

# Format PHP code
./vendor/bin/pint

# Format/lint frontend code
npm run format
npm run lint

# Docker commands (recommended for local development)
docker exec laravel.test ./vendor/bin/phpstan analyse
docker exec laravel.test ./vendor/bin/pint
```

### Build Commands
```bash
# Development build
npm run dev

# Production build
npm run build

# Production build with SSR
npm run build:ssr
```

## Architecture Overview

This is a **Laravel-Vue.js personal portfolio website** with a sophisticated dual-application architecture:

### Dual Frontend Applications
- **Public App** (`resources/js/public-app.ts`): Portfolio frontend using Inertia.js + Vue 3
- **Dashboard App** (`resources/js/dashboard-app.ts`): Admin panel for content management 

Both applications are served from the same Laravel backend but have completely separate Vue entry points, layouts, and component hierarchies.

### Core Domain Models
- **Creation/CreationDraft**: Portfolio projects with draft-first editing workflow
- **Technology**: Tech stack with SVG icons, types (Frontend/Backend/Database), and experience levels
- **Experience**: Professional work history with types (Work/Education/Project/Certification)
- **Person**: Collaborators/team members linked to projects
- **Picture**: Advanced image system with automatic AVIF/WebP optimization and 5 size variants
- **Video**: Video content with Bunny CDN streaming integration, cover images, and creation relationships

### Key Services
- **PublicControllersService**: Transforms and aggregates data for public pages (complex business logic)
- **ImageTranscodingService**: Handles automatic image optimization with multiple formats
- **CreationConversionService**: Converts drafts to published creations
- **UploadedFilesService**: Manages file uploads and storage
- **BunnyStreamService**: Video streaming service with Bunny CDN integration for upload, transcoding, and playback

### Translation System
**Dual Translation Architecture**:
1. **Custom Translation System**: Uses `TranslationKey` and `Translation` models for dynamic content managed through the admin interface (creation descriptions, feature titles, etc.)
2. **Static Translation System**: Traditional Laravel i18n files in `/lang` directory for static UI text, used by Vue components via `useTranslation()` composable

**Translation Fallback**: The `PublicControllersService` implements automatic fallback from current locale to fallback locale, ensuring translations are always available.

### Media Processing Pipelines

#### Image Optimization
Sophisticated image handling with automatic transcoding to modern formats:
- **Formats**: Original, AVIF, WebP
- **Sizes**: thumbnail, small, medium, large, full
- **Processing**: Queue-based using Intervention Image

#### Video Streaming
Professional video handling through Bunny CDN integration:
- **Upload**: Direct upload to Bunny Stream with automatic transcoding
- **Playback**: Iframe embedding with `https://iframe.mediadelivery.net/embed/{libraryId}/{videoId}`
- **Thumbnails**: Dynamic thumbnail generation with custom dimensions
- **Management**: Full CRUD operations with status tracking

## Frontend Structure

### Component Organization
```
resources/js/components/
├── dashboard/     # Admin interface components
├── public/        # Public portfolio components  
├── ui/           # Shared design system (Reka UI + Tailwind)
└── font-awesome/ # Icon components
```

### Key Frontend Libraries
- **Inertia.js**: SPA-like behavior without API complexity
- **Vue 3** with Composition API and TypeScript
- **Shadcn Vue UI**: Headless UI components for Vue
- **Reka UI**: Component library (headless UI components)
- **TipTap**: Rich text editing for markdown content
- **Tailwind CSS 4**: Styling with motion and animation plugins
- **Vee-validate + Zod**: Form validation
- **PhotoSwipe**: Image gallery functionality
- **Video Components**: Custom video gallery and modal components for Bunny Stream integration

## Development Patterns

### Draft-First Workflow
All content editing happens on draft entities (`CreationDraft`, `CreationDraftFeature`, `CreationDraftVideo`, etc.) before being converted to published versions. This allows safe editing without affecting the live site. Videos and media assets follow this same pattern with separate pivot tables for drafts vs. published content.

### Service-Layer Architecture
Complex business logic is extracted into services rather than controllers or models. Services handle data transformation, aggregation, and complex operations.

### Type Safety
- **PHP**: Comprehensive PHPDoc with typed arrays and return types
- **TypeScript**: Strict typing throughout frontend
- **Ziggy**: Type-safe route generation from PHP to TypeScript

### Testing Strategy
- **Feature Tests**: Test HTTP routes and business logic
- **Unit Tests**: Test individual classes and methods
- **Browser Tests**: End-to-end testing with Laravel Dusk
- **Service Tests**: Dedicated tests for service classes

## Production Features

### Performance
- **Laravel Octane**: High-performance application server
- **SSR Support**: Server-side rendering for better SEO
- **Image Optimization**: Automatic format conversion and sizing
- **Queue Processing**: Background jobs for intensive operations

### Development Tools
- **Laravel Pail**: Real-time log monitoring
- **Parallel Testing**: ParaTest for faster test execution
- **Static Analysis**: PHPStan with Laravel-specific rules
- **Code Coverage**: Integrated with Codecov

## Important Notes

### Route Structure
- Public routes: `/`, `/projects`, `/projects/{slug}`
- Dashboard routes: `/dashboard/*` (requires authentication)
- API routes: `/dashboard/api/*` (for AJAX requests from admin)

### Model Relationships
Most models use pivot tables for many-to-many relationships. Draft entities mirror published entity relationships but with separate pivot tables.

### File Storage
- **Images**: Stored in `storage/app/public/uploads/` with automatic optimization generating multiple variants
- **Videos**: Uploaded directly to Bunny Stream CDN with iframe embedding for playback
- **CDN Integration**: BunnyCDN filesystem support for production deployments

## Local Development
Usage of docker for local development is strongly recommended. All the required dependencies are included in the `docker-compose.yml` file. Run tests and build commands inside the container.

## Translation Implementation Details

### Static UI Translations
- Files located in `/lang/{locale}/` (e.g., `projects.php`, `navigation.php`, `search.php`)
- Passed to Vue components via Laravel controllers in the `translations` property
- Used in Vue with `useTranslation()` composable: `t('navigation.home')`
- Automatic fallback: current locale → fallback locale → empty string

### Dynamic Content Translations
- Managed through `TranslationKey` and `Translation` models
- Admin interface allows managing translations for different locales
- `PublicControllersService` handles retrieval with automatic fallback logic
- Used for creation descriptions, feature titles, experience details, etc.

### Adding New Static Translations
1. Add translation keys to appropriate files in `/lang/en/` and `/lang/fr/`
2. Include translation group in controller's `translations` array
3. Use `t('group.key')` in Vue components with `useTranslation()` composable

## Development Guidelines

### Code Creation Policy
- **ALWAYS prefer editing existing files** over creating new ones
- **NEVER create files unless absolutely necessary** for achieving the goal
- **NEVER proactively create documentation files** (*.md) or README files unless explicitly requested

### Code Standards
- Follow existing code patterns and conventions in the codebase
- Use the established service-layer architecture for complex business logic
- Maintain type safety across PHP (PHPDoc) and TypeScript implementations
- Follow the draft-first workflow for all content management features