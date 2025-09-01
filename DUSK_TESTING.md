# Laravel Dusk E2E Testing Guide

## Architecture

Les tests E2E Laravel Dusk sont configurés pour fonctionner avec une base de données MariaDB complètement isolée, permettant d'exécuter les tests sans affecter les données de développement.

### Composants Docker

Tous les services Dusk sont intégrés dans le `docker-compose.yml` principal avec un profil `dusk` :

- **laravel.dusk** : Container PHP/Laravel dédié aux tests (port 8001)
- **mariadb-dusk** : Base de données MariaDB isolée (port 3307)
- **redis-dusk** : Instance Redis dédiée (port 6380)
- **selenium** : Serveur Selenium pour l'exécution des tests browser (port 4444)

## Utilisation

### Démarrer l'environnement Dusk
```bash
# Démarrer tous les services Dusk (avec initialisation automatique)
docker-compose --profile dusk up -d

# Le container va automatiquement :
# - Configurer l'environnement Dusk
# - Installer Chrome Driver
# - Migrer la base de données
# - Construire les assets
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

1. **Isolation complète** : Les tests utilisent une base MariaDB dédiée (`laravel_dusk`) qui est créée et détruite à chaque exécution
2. **Migrations automatiques** : Le trait `DatabaseMigrations` assure que la base est migrée avant chaque test
3. **Factories** : Vous pouvez utiliser les factories Laravel pour créer des données de test
4. **Nettoyage automatique** : La base est réinitialisée entre chaque test

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

✅ **Isolation totale** : Aucun impact sur la base de développement  
✅ **Reproductibilité** : Même environnement pour tous les développeurs  
✅ **CI/CD Ready** : Configuration identique pour GitHub Actions  
✅ **Factories support** : Utilisation complète des factories Laravel  
✅ **Performance** : Base dédiée = tests plus rapides