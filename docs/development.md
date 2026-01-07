# 📖 Guide de Développement

## Prérequis

### Avec Docker (recommandé)
- Docker Desktop ou Docker Engine
- Docker Compose v2+
- Git

### Sans Docker
- PHP 8.4+ avec extensions : `imagick`, `zip`, `curl`, `mbstring`, `pdo_mysql`, `redis`
- Bun (runtime JavaScript rapide)
- Composer 2+
- MariaDB/MySQL ou PostgreSQL
- Redis (optionnel mais recommandé)

## Installation

### 🐳 Développement avec Docker

```bash
# Cloner le repository
git clone https://github.com/SofianeLasri/laravel-personal-website.git
cd laravel-personal-website

# Configuration
cp .env.example .env

# Démarrer les containers
docker-compose up -d

# Installer les dépendances (si pas fait automatiquement)
docker-compose exec app composer install
docker-compose exec app bun install

# Générer la clé d'application
docker-compose exec app php artisan key:generate

# Migrations et seeding
docker-compose exec app php artisan migrate --seed

# Compiler les assets
docker-compose exec app bun run dev
```

L'application sera accessible sur http://localhost

### 💻 Développement local

```bash
# Installation des dépendances
composer install
bun install

# Configuration
cp .env.example .env
php artisan key:generate

# Base de données
php artisan migrate --seed

# Démarrer le serveur de développement
composer dev
# ou pour le développement avec SSR
composer dev:ssr
```

## Commandes de développement

### Composer scripts

```bash
# Démarrer tous les services de développement
composer dev

# Avec SSR (Server-Side Rendering)
composer dev:ssr

# Services individuels
composer dev:server    # Serveur Laravel
composer dev:queue     # Worker de queue
composer dev:logs      # Monitoring des logs
composer dev:vite      # Serveur Vite
```

### Artisan commands

```bash
# Générer les routes Ziggy pour TypeScript
php artisan ziggy:generate

# Optimiser les images existantes
php artisan optimize:pictures

# Démarrer le serveur SSR Inertia
php artisan inertia:start-ssr

# Installer/mettre à jour Chrome Driver pour Dusk
php artisan dusk:chrome-driver --detect
```

## Structure du projet

```
laravel-personal-website/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/           # Contrôleurs du dashboard
│   │   │   └── Public/          # Contrôleurs publics
│   │   └── Middleware/
│   ├── Models/                  # Modèles Eloquent
│   ├── Services/                # Logique métier
│   │   ├── BunnyStreamService.php
│   │   ├── CreationConversionService.php
│   │   ├── ImageTranscodingService.php
│   │   ├── PublicControllersService.php
│   │   └── UploadedFilesService.php
│   └── Enums/                   # Énumérations
├── database/
│   ├── factories/               # Model factories
│   ├── migrations/              # Migrations
│   └── seeders/                 # Seeders
│       ├── DatabaseSeeder.php   # Seeder principal
│       └── DuskTestSeeder.php   # Seeder pour tests Dusk
├── resources/
│   ├── js/
│   │   ├── components/
│   │   │   ├── dashboard/       # Composants admin
│   │   │   ├── public/          # Composants publics
│   │   │   └── ui/              # Composants UI partagés
│   │   ├── layouts/             # Layouts Vue
│   │   ├── pages/               # Pages Vue (Inertia)
│   │   ├── composables/         # Composables Vue
│   │   ├── types/               # Types TypeScript
│   │   ├── public-app.ts        # Point d'entrée app publique
│   │   └── dashboard-app.ts     # Point d'entrée dashboard
│   ├── css/                     # Styles Tailwind
│   └── lang/                    # Fichiers de traduction
├── tests/
│   ├── Browser/                 # Tests Dusk
│   ├── Feature/                 # Tests d'intégration
│   └── Unit/                    # Tests unitaires
└── docs/                        # Documentation
```

## Architecture double application

Le projet utilise deux applications Vue.js distinctes :

### Application publique
- Point d'entrée : `resources/js/public-app.ts`
- Layout : `resources/js/layouts/public-layout.vue`
- Pages : `resources/js/pages/public/`
- URL : `/`

### Application dashboard
- Point d'entrée : `resources/js/dashboard-app.ts`
- Layout : `resources/js/layouts/dashboard-layout.vue`
- Pages : `resources/js/pages/dashboard/`
- URL : `/dashboard`

## Conventions de code

### PHP
- PSR-12 pour le style de code
- Utiliser les types stricts
- PHPDoc pour les méthodes complexes
- Pint pour le formatage automatique

```php
<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service de gestion des créations
 */
class CreationService
{
    /**
     * @param array<string, mixed> $data
     * @return Creation
     */
    public function create(array $data): Creation
    {
        // ...
    }
}
```

### TypeScript/Vue
- Composition API pour les composants Vue
- Types stricts avec TypeScript
- ESLint + Prettier pour le formatage

```vue
<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Creation } from '@/types'

interface Props {
  creation: Creation
}

const props = defineProps<Props>()
const isLoading = ref(false)
</script>
```

### Base de données
- Migrations versionnées
- Factories pour les tests
- Seeders pour les données de développement

## Workflow de développement

### 1. Créer une branche

```bash
git checkout -b feature/nouvelle-fonctionnalite
```

### 2. Développer et tester

```bash
# Développement avec hot reload
bun run dev

# Tests pendant le développement
php artisan test --filter MonTest
```

### 3. Vérifier la qualité

```bash
# Analyse statique PHP
./vendor/bin/phpstan analyse

# Formatage PHP
./vendor/bin/pint

# Linting JavaScript/Vue
bun run lint

# Tests complets
php artisan test
```

### 4. Commit et push

```bash
git add .
git commit -m "feat: ajouter nouvelle fonctionnalité"
git push origin feature/nouvelle-fonctionnalite
```

## Système de traductions

### Traductions statiques
Fichiers dans `/lang/{locale}/` pour l'UI :

```php
// lang/fr/navigation.php
return [
    'home' => 'Accueil',
    'projects' => 'Projets',
];
```

Utilisation dans Vue :
```vue
<script setup>
import { useTranslation } from '@/composables/useTranslation'
const { t } = useTranslation()
</script>

<template>
  <nav>
    {{ t('navigation.home') }}
  </nav>
</template>
```

### Traductions dynamiques
Via les modèles `TranslationKey` et `Translation` pour le contenu :

```php
$key = TranslationKey::create(['key' => 'project.title']);
Translation::create([
    'translation_key_id' => $key->id,
    'locale' => 'fr',
    'text' => 'Mon Projet',
]);
```

## Variables d'environnement

### Essentielles

```env
APP_URL=http://localhost:8000
APP_LOCALE=fr
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Services externes (optionnels)

```env
# BunnyCDN pour les médias
BUNNY_CDN_STORAGE_ZONE=
BUNNY_CDN_API_KEY=
BUNNY_CDN_PULL_ZONE=

# Bunny Stream pour les vidéos
BUNNY_STREAM_API_KEY=
BUNNY_STREAM_LIBRARY_ID=
BUNNY_STREAM_COLLECTION_ID=
```

## Dépannage

### Problèmes Docker

```bash
# Reconstruire les containers
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# Voir les logs
docker-compose logs -f app
```

### Problèmes de permissions

```bash
# Corriger les permissions storage
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Cache et optimisation

```bash
# Vider tous les caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimiser pour la production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Ressources utiles

- [Documentation Laravel](https://laravel.com/docs)
- [Documentation Vue.js](https://vuejs.org/guide/)
- [Documentation Inertia.js](https://inertiajs.com/)
- [Documentation Tailwind CSS](https://tailwindcss.com/docs)
- [CLAUDE.md](../CLAUDE.md) - Instructions pour l'assistant IA
