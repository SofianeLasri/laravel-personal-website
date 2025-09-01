# Laravel Dusk E2E Testing Guide

## Architecture

Les tests E2E Laravel Dusk utilisent une architecture simplifiée avec SQLite en mémoire, similaire aux tests PHPUnit, garantissant une isolation totale et des performances optimales.

### Composants Docker

Tous les services Dusk sont intégrés dans le `docker-compose.yml` principal avec un profil `dusk` :

- **laravel.dusk** : Container PHP/Laravel dédié aux tests (port 8001)
  - Base de données : SQLite en mémoire (créée automatiquement pour chaque test)
  - Cache : Système de fichiers local
  - Queue : Synchrone (pas de workers)
- **selenium** : Serveur Selenium pour l'exécution des tests browser (port 4444)

## Utilisation

### Prérequis (IMPORTANT sur WSL)
```bash
# Compiler les assets AVANT de démarrer les containers Dusk
npm run build
```
⚠️ **Note WSL** : La compilation des assets est très lente dans les containers sur WSL. 
Il est donc obligatoire de faire `npm run build` sur la machine hôte avant de lancer Dusk.

### Démarrer l'environnement Dusk
```bash
# Démarrer tous les services Dusk (avec initialisation automatique)
docker-compose --profile dusk up -d

# Le container va automatiquement :
# - Vérifier la présence des assets compilés
# - Configurer l'environnement Dusk (SQLite, cache file)
# - Installer Chrome Driver
# - Démarrer le serveur sur le port 8001
```

### Exécuter les tests
```bash
# Exécuter tous les tests Dusk
docker exec laravel.dusk php artisan dusk

# Exécuter un test spécifique
docker exec laravel.dusk php artisan dusk tests/Browser/HomepageTest.php

# Exécuter avec filtre
docker exec laravel.dusk php artisan dusk --filter test_homepage_loads
```

### Arrêter l'environnement Dusk
```bash
docker-compose --profile dusk down
```

### Configuration PhpStorm
Pour configurer PhpStorm pour exécuter les tests Dusk :
1. Aller dans Settings → PHP → Test Frameworks
2. Ajouter une configuration PHPUnit par Docker
3. Sélectionner le container `laravel.dusk`
4. Configurer le path vers PHPUnit : `/app/vendor/autoload.php`
5. Dans Run Configuration, ajouter `--testsuite Browser` pour cibler les tests Dusk

## Fonctionnement

1. **Isolation complète** : Chaque test utilise une base SQLite en mémoire, créée et détruite automatiquement
2. **Migrations automatiques** : Le trait `DatabaseMigrations` migre la base avant chaque test
3. **Factories** : Support complet des factories Laravel pour créer des données de test
4. **Nettoyage automatique** : La base est réinitialisée entre chaque test (SQLite en mémoire)
5. **Architecture légère** : Pas de services externes (MariaDB/Redis), tout est autonome

## Écriture de tests

```php
<?php

namespace Tests\Browser;

use App\Models\Creation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class MyTest extends DuskTestCase
{
    public function test_example(): void
    {
        // Créer des données avec les factories
        $creation = Creation::factory()->published()->create();

        $this->browse(function (Browser $browser) use ($creation) {
            $browser->visit('/')
                ->assertSee($creation->title);
        });
    }
}
```

## Debug

### Voir l'interface Selenium
Accédez à http://localhost:7900 pendant l'exécution des tests pour voir le navigateur en action.

### Logs
Les logs sont disponibles dans le container :
```bash
docker logs laravel.dusk
```

## Avantages de cette configuration

✅ **Architecture ultra-simple** : Seulement 2 containers (app-dusk + selenium)  
✅ **Isolation totale** : SQLite en mémoire, aucun impact sur les données de dev  
✅ **Performance optimale** : SQLite en mémoire = tests très rapides  
✅ **Cohérence** : Même approche que les tests PHPUnit (SQLite)  
✅ **Factories support** : Utilisation complète des factories Laravel  
✅ **CI/CD Ready** : Configuration légère et portable