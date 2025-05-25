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

### Key Services
- **PublicControllersService**: Transforms and aggregates data for public pages (complex business logic)
- **ImageTranscodingService**: Handles automatic image optimization with multiple formats
- **CreationConversionService**: Converts drafts to published creations
- **UploadedFilesService**: Manages file uploads and storage

### Translation System
Custom translation system using `TranslationKey` and `Translation` models instead of Laravel's built-in i18n. Keys are managed through the admin interface.

### Image Optimization Pipeline
Sophisticated image handling with automatic transcoding to modern formats:
- **Formats**: Original, AVIF, WebP
- **Sizes**: thumbnail, small, medium, large, full
- **Processing**: Queue-based using Intervention Image

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

## Development Patterns

### Draft-First Workflow
All content editing happens on draft entities (`CreationDraft`, `CreationDraftFeature`, etc.) before being converted to published versions. This allows safe editing without affecting the live site.

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
Uploaded files are stored in `storage/app/public/uploads/` with automatic optimization generating multiple variants.