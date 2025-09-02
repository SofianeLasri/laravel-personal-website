<a href="https://sofianelasri.fr" target="_blank"><img src="a1readme-assets/orange-short.png" height="45"></a>

[![tests](https://github.com/SofianeLasri/laravel-personal-website/actions/workflows/tests.yml/badge.svg)](https://github.com/SofianeLasri/laravel-personal-website/actions/workflows/tests.yml)
[![dusk](https://github.com/SofianeLasri/laravel-personal-website/actions/workflows/dusk.yml/badge.svg)](https://github.com/SofianeLasri/laravel-personal-website/actions/workflows/dusk.yml)
[![codecov](https://codecov.io/gh/SofianeLasri/laravel-personal-website/graph/badge.svg?token=Q2UNOVRD1P)](https://codecov.io/gh/SofianeLasri/laravel-personal-website)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.x-4FC08D?logo=vue.js&logoColor=white)](https://vuejs.org)
[![wakatime](https://wakatime.com/badge/user/018da7b9-5ddd-4615-a805-e871e840191c/project/de338958-b8f7-48de-b7e8-ee61cf64b4a7.svg)](https://wakatime.com/badge/user/018da7b9-5ddd-4615-a805-e871e840191c/project/de338958-b8f7-48de-b7e8-ee61cf64b4a7)

# Site Personnel Laravel - Sofiane Lasri

> Site portfolio moderne construit avec Laravel 12, Vue.js 3 et Inertia.js

## 📖 À propos

Ce projet est un site web personnel qui sert de portfolio et de vitrine professionnelle. Il présente une architecture double application avec une interface publique élégante et un tableau de bord d'administration complet pour la gestion de contenu.

### ✨ Fonctionnalités principales

- **🎨 Portfolio interactif** - Présentation des projets et réalisations
- **📱 Design responsive** - Optimisé pour tous les appareils
- **🖼️ Gestion d'images avancée** - Optimisation automatique AVIF/WebP avec 5 variantes de taille
- **🎥 Streaming vidéo** - Intégration Bunny Stream pour les vidéos de projets
- **🛠️ Tableau de bord administrateur** - Interface complète de gestion de contenu
- **🌐 Système de traduction** - Support multilingue français/anglais
- **🔒 Authentification sécurisée** - Protection des zones d'administration
- **⚡ Performance optimisée** - Laravel Octane et SSR pour des temps de chargement rapides

## 🚀 Démarrage rapide

### Avec Docker (recommandé)

```bash
# Cloner le repository
git clone https://github.com/SofianeLasri/laravel-personal-website.git
cd laravel-personal-website

# Configurer l'environnement
cp .env.example .env

# Démarrer avec Docker
docker-compose up -d

# L'application sera disponible sur http://localhost
```

### Installation locale

```bash
# Installer les dépendances
composer install
npm install

# Configuration
cp .env.example .env
php artisan key:generate

# Base de données
php artisan migrate
php artisan db:seed

# Démarrer le serveur de développement
composer dev
```

## 📚 Documentation

La documentation complète est disponible dans le dossier [`docs/`](./docs) :

- 📖 [Guide de développement](./docs/development.md) - Configuration locale, structure du projet, conventions
- 🧪 [Guide des tests](./docs/testing.md) - PHPUnit, Dusk, couverture de code
- 🚀 [Guide de déploiement](./docs/deployment.md) - Production, Docker, CI/CD

## 🛠️ Stack technologique

### Backend
- **Laravel 12** - Framework PHP moderne
- **Laravel Octane** - Serveur d'application haute performance
- **Inertia.js** - SPA sans API complexe
- **Intervention Image** - Traitement et optimisation d'images

### Frontend
- **Vue.js 3** - Framework JavaScript réactif avec Composition API
- **TypeScript** - Typage strict pour une meilleure robustesse
- **Tailwind CSS 4** - Framework CSS utilitaire moderne
- **Shadcn Vue** - Composants UI modernes et accessibles

### Infrastructure
- **Docker** - Conteneurisation pour le développement et la production
- **GitHub Actions** - CI/CD automatisé
- **BunnyCDN** - CDN pour les assets et streaming vidéo
- **Redis** - Cache et sessions

## 🧪 Tests

```bash
# Tests unitaires et d'intégration
docker-compose exec app php artisan test --parallel

# Tests end-to-end (Dusk)
docker-compose exec app-dusk php artisan dusk

# Tests avec couverture
docker-compose exec app php artisan test --parallel --coverage
```

## 📊 Qualité de code

```bash
# Analyse statique PHP
docker-compose exec app ./vendor/bin/phpstan analyse

# Formatage PHP
docker-compose exec app ./vendor/bin/pint

# Linting et formatage JavaScript/Vue
npm run lint
npm run format
```

## 📞 Contact

**Sofiane Lasri**
- Site web : [sofianelasri.fr](https://sofianelasri.fr)
- LinkedIn : [Sofiane Lasri](https://www.linkedin.com/in/sofiane-lasri-trienpont/)
- GitHub : [@SofianeLasri](https://github.com/SofianeLasri)

## 📝 Licence

Ce projet est sous licence propriétaire. Voir le fichier [LICENSE](./LICENSE) pour plus de détails.

---

<p align="center">
  Fait avec ❤️ en utilisant Laravel et Vue.js
</p>