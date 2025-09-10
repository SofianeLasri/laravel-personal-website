# 🧪 Guide des Tests

## Vue d'ensemble

Le projet utilise une stratégie de tests complète avec plusieurs niveaux de tests pour assurer la qualité et la fiabilité du code.

## Types de tests

### Tests unitaires
Tests isolés de classes et méthodes individuelles.

```bash
# Exécuter les tests unitaires
php artisan test --testsuite=Unit

# Avec Docker
docker-compose exec app php artisan test --testsuite=Unit
```

### Tests d'intégration (Feature)
Tests des endpoints HTTP et de la logique métier.

```bash
# Exécuter les tests d'intégration
php artisan test --testsuite=Feature

# Avec Docker
docker-compose exec app php artisan test --testsuite=Feature
```

### Tests end-to-end (Dusk)
Tests automatisés du navigateur simulant les interactions utilisateur.

```bash
# Exécuter les tests Dusk
php artisan dusk

# Avec Docker
docker-compose exec app php artisan dusk
```

## Configuration des tests

### Configuration Dusk

Les tests Dusk utilisent un seeder dédié (`DuskTestSeeder`) qui crée des données minimales pour les tests :

- Technologies essentielles (Laravel, PHP, JavaScript, Vue.js)
- Projets de test (Portfolio Website, E-commerce Platform)
- Expériences professionnelles de test
- Traductions UI de base

### Environnement de test CI

Le fichier `.env.ci` est utilisé pour les tests dans GitHub Actions :

```env
APP_ENV=ci
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

## Commandes de test

### Tests complets

```bash
# Tous les tests sans Dusk
php artisan test

# Tous les tests avec parallélisation
php artisan test --parallel

# Tests avec couverture de code
php artisan test --coverage

# Tests avec rapport XML pour CI
php artisan test --coverage --coverage-clover coverage.xml --log-junit junit.xml
```

### Tests spécifiques

```bash
# Tester un fichier spécifique
php artisan test tests/Feature/Services/PublicControllersServiceTest.php

# Tester une méthode spécifique
php artisan test --filter test_homepage_loads

# Exclure des groupes de tests
php artisan test --exclude-group=real-api
```

## GitHub Actions

Le projet utilise deux workflows séparés pour les tests :

### Workflow tests.yml
- Tests PHPUnit (unitaires et d'intégration)
- Analyse de couverture avec Codecov
- S'exécute sur les branches `master` et `develop`

### Workflow dusk.yml
- Tests end-to-end avec Laravel Dusk
- Configuration complète avec MariaDB et Redis
- Migrations et seeding automatiques
- Capture d'écran en cas d'échec
- S'exécute sur les branches principales et les pull requests

## Structure des tests

```
tests/
├── Browser/              # Tests Dusk (end-to-end)
│   ├── ExampleTest.php
│   ├── HomepageTest.php
│   ├── console/         # Logs de console en cas d'erreur
│   └── screenshots/     # Captures d'écran en cas d'échec
├── Feature/             # Tests d'intégration
│   ├── Controllers/     # Tests des contrôleurs
│   ├── Models/         # Tests des modèles
│   └── Services/       # Tests des services
└── Unit/               # Tests unitaires
    ├── Enums/          # Tests des enums
    ├── Helpers/        # Tests des helpers
    └── Services/       # Tests unitaires des services
```

## Bonnes pratiques

### Isolation des tests
- Utiliser `RefreshDatabase` pour les tests de base de données
- Utiliser des factories pour générer les données de test
- Nettoyer les ressources après chaque test

### Nommage des tests
- Préfixer avec `test_` ou utiliser l'annotation `@test`
- Utiliser des noms descriptifs : `test_user_can_create_project`
- Grouper les tests liés dans la même classe

### Assertions
- Une assertion par test quand possible
- Utiliser les assertions spécifiques de Laravel
- Vérifier les codes de statut HTTP et la structure des réponses

### Performance
- Utiliser `--parallel` pour accélérer l'exécution
- Minimiser les interactions avec la base de données
- Utiliser des mocks pour les services externes

## Dépannage

### Tests Dusk qui échouent en CI

Si les tests Dusk échouent dans GitHub Actions :

1. Vérifier les captures d'écran dans les artifacts
2. Vérifier les logs de console
3. S'assurer que le seeder `DuskTestSeeder` est à jour
4. Vérifier les timeouts dans les tests

### Base de données de test

Pour réinitialiser la base de données de test :

```bash
# Réinitialiser et re-seeder
php artisan migrate:fresh --seed --env=testing

# Avec le seeder de test Dusk
php artisan migrate:fresh --env=testing
php artisan db:seed --class=DuskTestSeeder --env=testing
```

## Couverture de code

Le projet vise une couverture de code minimale de 70%. La couverture est automatiquement calculée et envoyée à Codecov lors de chaque push.

Pour générer un rapport de couverture local :

```bash
# Rapport en console
php artisan test --coverage

# Rapport HTML
php artisan test --coverage-html coverage-report
```

## Ressources

- [Documentation Laravel Testing](https://laravel.com/docs/testing)
- [Documentation Laravel Dusk](https://laravel.com/docs/dusk)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)