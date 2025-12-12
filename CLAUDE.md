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
# Run all tests with parallel execution (faster)
php artisan test --parallel

# Run specific test suites
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run browser tests (requires Chrome/Chromium)
php artisan dusk

# Run single test file
php artisan test tests/Feature/Services/PublicControllersServiceTest.php

# Run single test method
php artisan test --filter testMethodName

# Run with coverage
php artisan test --coverage

# Docker commands (recommended for local development)
docker exec laravel.test php artisan test --parallel
docker exec laravel.test php artisan test tests/Feature/Services/PublicControllersServiceTest.php
docker exec laravel.test php artisan dusk
```

### Code Quality
```bash
# Run static analysis
./vendor/bin/phpstan analyse

# Run mess detector (code quality issues)
composer phpmd

# Format PHP code
./vendor/bin/pint

# Format/lint frontend code
npm run format
npm run lint

# Docker commands (recommended for local development)
docker exec laravel.test ./vendor/bin/phpstan analyse
docker exec laravel.test composer phpmd
docker exec laravel.test ./vendor/bin/pint
```

### Build Commands
```bash
# Development build (watch mode)
npm run dev

# Production build
npm run build

# Production build with SSR
npm run build:ssr

# Preview production build
npm run preview
```

### Database Commands
```bash
# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Fresh migration (drop all tables and re-run)
php artisan migrate:fresh --seed

# Create a new migration
php artisan make:migration create_table_name

# Seed database
php artisan db:seed

# Docker commands
docker exec laravel.test php artisan migrate
docker exec laravel.test php artisan db:seed
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
- **ImageTranscodingService**: Handles automatic image optimization with multiple formats (includes Imagick resource limit safety checks)
- **CreationConversionService**: Converts drafts to published creations with relationship synchronization
- **UploadedFilesService**: Manages file uploads and storage, dispatches background optimization jobs
- **BunnyStreamService**: Video streaming service with Bunny CDN integration for upload, transcoding, and playback (two-phase upload with error recovery)

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
- **Feature Tests**: Test HTTP routes, workflows, and business logic integration
- **Unit Tests**: Test individual classes, methods, and isolated components
- **Browser Tests**: End-to-end testing with Laravel Dusk
- **Service Tests**: Dedicated tests for service classes

#### Test Organization
Tests are organized into a clear directory structure:

```
tests/
├── Browser/           # End-to-end browser tests
├── Feature/           # Integration and workflow tests
│   ├── Controllers/   # HTTP controller tests
│   ├── Models/        # Model integration tests (organized by domain)
│   ├── Services/      # Service integration tests
│   └── ...
├── Unit/              # Pure unit tests
│   ├── Enums/         # Enum tests (logic only, no database)
│   ├── Models/        # Model unit tests (organized by domain)
│   │   ├── Blog/      # Blog-related model tests
│   │   └── Metadata/  # Metadata model tests
│   └── Services/      # Service unit tests
└── Traits/            # Shared test traits
```

**Test Separation Guidelines:**
- **Enums**: All enum tests in `tests/Unit/Enums/` - test only enum logic, no database interactions
- **Models**: Unit tests for model methods/attributes, Feature tests for model integration/workflows
- **Services**: Unit tests for isolated logic, Feature tests for service integration with external dependencies
- **Controllers**: Feature tests only (HTTP workflows)

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
- Public routes: `/`, `/projects`, `/projects/{slug}`, `/experiences`, `/technologies`
- Dashboard routes: `/dashboard/*` (requires authentication)
- API routes: `/dashboard/api/*` (for AJAX requests from admin)
- Asset routes: `/storage/*` (public uploads)

### Model Relationships
Most models use pivot tables for many-to-many relationships. Draft entities mirror published entity relationships but with separate pivot tables. Key relationships:
- Creation ↔ Technology (many-to-many via pivot)
- Creation ↔ Person (many-to-many via pivot)
- Creation ↔ Picture (many-to-many via pivot)
- Creation ↔ Video (many-to-many via pivot)
- CreationDraft follows same pattern with separate pivot tables

### File Storage
- **Images**: Stored in `storage/app/public/uploads/` with automatic optimization generating multiple variants
- **Videos**: Uploaded directly to Bunny Stream CDN with iframe embedding for playback
- **CDN Integration**: BunnyCDN filesystem support for production deployments
- **Storage Link**: Run `php artisan storage:link` to create public symlink

## Local Development
Docker usage for local development is strongly recommended. All required dependencies are included in the `docker-compose.yml` file. Run tests and build commands inside the container:

```bash
# Start Docker environment
docker-compose up -d

# Access container shell
docker exec -it laravel.test bash

# Stop Docker environment
docker-compose down
```

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

## Common Tasks

### Creating New Features
1. Create migration: `php artisan make:migration create_feature_table`
2. Create model: `php artisan make:model Feature`
3. Create service: Create in `app/Services/` following existing patterns
4. Create controller: `php artisan make:controller FeatureController`
5. Add routes in `routes/web.php` or `routes/dashboard.php`
6. Create Vue components in appropriate directory (`resources/js/components/dashboard/` or `resources/js/components/public/`)
7. Run tests to ensure nothing broke

### Working with Translations
1. For static UI text: Add to `/lang/{locale}/` files
2. For dynamic content: Use TranslationKey/Translation models via dashboard
3. In Vue components: Use `const { t } = useTranslation()` and `t('key')`
4. Always provide fallback translations

## Frontend Best Practices
- Always use Axios instead of fetch or xhr since Inertia handles the CSRF token
- Use TypeScript interfaces for all data structures
- Follow existing component patterns in the codebase
- Use Shadcn Vue/Reka UI components when possible

## Queue Jobs and Background Processing
- **Image Optimization**: `PictureJob` handles automatic image transcoding to AVIF/WebP with 5 size variants
- **Error Recovery**: Services implement comprehensive error handling with cleanup (e.g., failed video uploads delete video entries)
- **Resource Management**: ImageTranscodingService includes safety checks against Imagick memory limits

## Theme System
- **Dual Theme Support**: Both public and dashboard applications support light/dark/system themes
- **SSR Compatible**: Theme detection works with server-side rendering via inline scripts
- **Persistence**: Theme preferences stored in localStorage with cookie fallback

## Environment Configuration
Key environment variables to configure:
- `APP_URL`: Your application URL
- `DB_CONNECTION`: Database driver (mysql/pgsql/sqlite)
- `BUNNY_CDN_*`: Bunny CDN credentials for video streaming
- `IMAGICK_*`: ImageMagick resource limits
- `QUEUE_CONNECTION`: Queue driver (sync/database/redis)
- `FILESYSTEM_DISK`: Storage driver (local/bunnycdn)
- Pour les tests Laravel dusk, on utilise le container laravel.dusk
- Lance toujours les commandes npm en local et non sur docker car WSL est très lent