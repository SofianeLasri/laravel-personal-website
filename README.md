<a href="https://sofianelasri.fr" target="_blank"><img src="a1readme-assets/orange-short.png" height="45"></a>

[![tests](https://github.com/SofianeLasri/laravel-personal-website/actions/workflows/tests.yml/badge.svg)](https://github.com/SofianeLasri/laravel-personal-website/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/SofianeLasri/laravel-personal-website/graph/badge.svg?token=Q2UNOVRD1P)](https://codecov.io/gh/SofianeLasri/laravel-personal-website)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.x-4FC08D?logo=vue.js&logoColor=white)](https://vuejs.org)
[![wakatime](https://wakatime.com/badge/user/018da7b9-5ddd-4615-a805-e871e840191c/project/de338958-b8f7-48de-b7e8-ee61cf64b4a7.svg)](https://wakatime.com/badge/user/018da7b9-5ddd-4615-a805-e871e840191c/project/de338958-b8f7-48de-b7e8-ee61cf64b4a7)

# Site Personnel Laravel - Sofiane Lasri

> Site portfolio moderne construit avec Laravel 12, Vue.js 3 et Inertia.js

Le présent readme a été généré avec [Claude](https://claude.ai). Déso, l'ia est devenue trop puissante de nos jours. xD

## 📖 À propos

Ce projet est un site web personnel qui sert de portfolio et de vitrine professionnelle. Il présente une architecture double application avec une interface publique élégante et un tableau de bord d'administration complet pour la gestion de contenu.

### ✨ Fonctionnalités principales

- **🎨 Portfolio interactif** - Présentation des projets et réalisations
- **📱 Design responsive** - Optimisé pour tous les appareils
- **🖼️ Gestion d'images avancée** - Optimisation automatique AVIF/WebP avec 5 variantes de taille
- **🛠️ Tableau de bord administrateur** - Interface complète de gestion de contenu
- **🌐 Système de traduction** - Support multilingue français/anglais
- **🔒 Authentification sécurisée** - Protection des zones d'administration
- **⚡ Performance optimisée** - Laravel Octane et SSR pour des temps de chargement rapides

## 🏗️ Architecture technique

### Stack technologique

**Backend :**
- **Laravel 12** - Framework PHP moderne
- **Laravel Octane** - Serveur d'application haute performance
- **Inertia.js** - Pont entre Laravel et Vue.js
- **Intervention Image** - Traitement et optimisation d'images

**Frontend :**
- **Vue.js 3** - Framework JavaScript réactif avec Composition API
- **TypeScript** - Typage strict pour une meilleure robustesse
- **Tailwind CSS 4** - Framework CSS utilitaire avec motion et animations
- **Shadcn Vue** - Composants UI modernes et accessibles
- **TipTap** - Éditeur de texte riche pour le contenu markdown

**Développement et tests :**
- **PHPStan** - Analyse statique PHP
- **Pint** - Formatage de code PHP
- **ESLint + Prettier** - Linting et formatage TypeScript/Vue
- **PHPUnit** - Tests unitaires et d'intégration (79+ tests)
- **Laravel Dusk** - Tests end-to-end
- **Codecov** - Couverture de code

### Architecture double application

Le projet utilise une architecture innovante avec deux applications frontend distinctes :

- **Application publique** (`resources/js/public-app.ts`) - Portfolio et pages publiques
- **Application dashboard** (`resources/js/dashboard-app.ts`) - Interface d'administration

Chaque application a ses propres points d'entrée, layouts et composants, tout en partageant le même backend Laravel.

### Workflow de contenu avec brouillons

Toute la gestion de contenu utilise un système de brouillons pour permettre l'édition sécurisée :
- **Entités de brouillon** pour l'édition (`CreationDraft`, `CreationDraftFeature`, etc.)
- **Conversion vers entités publiées** une fois validées
- **Préservation du contenu live** pendant l'édition

## 🚀 Installation

### Prérequis

- **Docker** et **Docker Compose** (recommandé)
- **PHP 8.2+** avec extensions : `imagick`, `zip`, `curl`, `mbstring`
- **Node.js 18+** et **npm**
- **Base de données** compatible (MySQL, PostgreSQL, SQLite)

### Installation avec Docker (recommandée)

1. **Cloner le repository**
   ```bash
   git clone https://github.com/SofianeLasri/laravel-personal-website.git
   cd laravel-personal-website
   ```

2. **Configurer l'environnement**
   ```bash
   cp .env.example .env
   # Éditer .env avec vos configurations
   ```

3. **Démarrer avec Docker**
   ```bash
   # Construire et démarrer les containers
   docker-compose up -d
   
   # L'entrypoint se charge de la configuration initiale
   # Mais vous pouvez aussi exécuter manuellement les commandes suivantes :

   # Installer les dépendances PHP
   docker-compose exec app composer install
   
   # Installer les dépendances Node.js
   docker-compose exec app npm install
   
   # Générer la clé d'application
   docker-compose exec app php artisan key:generate
   
   # Exécuter les migrations
   docker-compose exec app php artisan migrate
   
   # Seeder les données (optionnel)
   docker-compose exec app php artisan db:seed
   ```

### Installation locale (alternative)

1. **Cloner et installer les dépendances**
   ```bash
   git clone https://github.com/SofianeLasri/laravel-personal-website.git
   cd laravel-personal-website
   composer install
   npm install
   ```

2. **Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   php artisan db:seed
   ```

### Tests

```bash
# Tous les tests
docker-compose exec app php artisan test

# Tests avec couverture
docker-compose exec app php artisan test --coverage

# Tests spécifiques
docker-compose exec app php artisan test --testsuite=Feature
docker-compose exec app php artisan test --testsuite=Unit

# Tests navigateur (Dusk)
docker-compose exec app php artisan dusk

# Tests en parallèle
docker-compose exec app php artisan test --parallel
```

### Qualité de code

```bash
# Analyse statique PHP
docker-compose exec app ./vendor/bin/phpstan analyse

# Formatage PHP
docker-compose exec app ./vendor/bin/pint

# Linting frontend
docker-compose exec app npm run lint

# Formatage frontend
docker-compose exec app npm run format
```

### Production

```bash
# Optimisation pour la production
docker-compose exec app php artisan optimize

# Cache des routes, configs, vues
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan view:cache

# Build frontend optimisé
docker-compose exec app npm run build:ssr
```

## 🗂️ Structure du projet

```
├── app/
│   ├── Http/Controllers/
│   │   ├── Admin/           # Contrôleurs d'administration
│   │   └── Public/          # Contrôleurs publics
│   ├── Models/              # Modèles Eloquent
│   └── Services/            # Logique métier
├── resources/
│   ├── js/
│   │   ├── components/      # Composants Vue partagés
│   │   ├── pages/           # Pages/vues
│   │   ├── layouts/         # Layouts d'application
│   │   ├── public-app.ts    # Point d'entrée app publique
│   │   └── dashboard-app.ts # Point d'entrée dashboard
│   └── css/                 # Styles Tailwind
├── tests/
│   ├── Feature/             # Tests d'intégration
│   ├── Unit/                # Tests unitaires
│   └── Browser/             # Tests Dusk (E2E)
└── docker-compose.yml       # Configuration Docker
```

## 🎯 Fonctionnalités détaillées

### Portfolio public
- **Page d'accueil** avec présentation personnelle
- **Galerie de projets** avec filtrage et recherche
- **Pages de détail** pour chaque réalisation
- **CV téléchargeable** et liens sociaux

### Dashboard administrateur
- **Gestion des créations** avec système de brouillons
- **Gestion des expériences** professionnelles et éducatives
- **Gestion des technologies** et niveaux d'expertise
- **Gestion des images** avec optimisation automatique
- **Système de traductions** pour le contenu multilingue
- **Liens sociaux** configurables

### Optimisation d'images
- **Formats modernes** : AVIF, WebP avec fallback
- **Variantes de taille** : thumbnail, small, medium, large, full
- **Traitement en arrière-plan** via queues Laravel
- **CDN ready** avec support de disques multiples

## 🤝 Contribution

Les contributions sont les bienvenues ! Veuillez suivre ces étapes :

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/AmazingFeature`)
3. Commiter vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Pousser vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

### Standards de code

- **PHP** : PSR-12, analyse PHPStan niveau 5
- **TypeScript/Vue** : ESLint + Prettier
- **Tests** : Couverture minimale de 80%
- **Commits** : Messages descriptifs en français

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 📞 Contact

**Sofiane Lasri**
- Site web : [sofianelasri.fr](https://sofianelasri.fr)
- GitHub : [@SofianeLasri](https://github.com/SofianeLasri)

---

## 📝 Notes techniques

### Version Laravel
Basé sur le [Vue Starter Kit](https://github.com/laravel/vue-starter-kit) de Laravel, à jour avec le commit [0a17da2](https://github.com/laravel/vue-starter-kit/commit/0a17da247c1e273fc2f9e210df52f47884c31910)

### Déploiement
Le projet inclut :
- **Dockerfile** pour la production
- **GitHub Actions** pour CI/CD automatisé
- **Support Laravel Octane** pour de meilleures performances
- **Configuration SSR** pour un SEO optimal

### Surveillance
- **Codecov** pour la couverture de tests
- **GitHub Actions** pour les tests automatisés
- **Laravel Pail** pour le monitoring en temps réel
- **Wakatime** pour le suivi du temps de développement