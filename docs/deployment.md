# 🚀 Guide de Déploiement

## Vue d'ensemble

Ce guide couvre le déploiement de l'application en production avec Docker, la configuration des services externes et l'intégration CI/CD.

## Prérequis de production

- Serveur Linux (Ubuntu 20.04+ recommandé)
- Docker et Docker Compose installés
- Nom de domaine configuré
- Certificat SSL (Let's Encrypt recommandé)
- Compte BunnyCDN (optionnel pour les médias)

## Configuration Docker Production

### 1. Utiliser le fichier Docker Compose de production

```bash
# Cloner le repository
git clone https://github.com/SofianeLasri/laravel-personal-website.git
cd laravel-personal-website

# Utiliser la configuration de production
docker-compose -f docker-compose.production.yml up -d
```

### 2. Variables d'environnement de production

Créer un fichier `.env.production` :

```env
APP_NAME="Sofiane Lasri"
APP_ENV=production
APP_KEY=base64:VOTRE_CLE_SECRETE
APP_DEBUG=false
APP_URL=https://votredomaine.com

# Base de données
DB_CONNECTION=mariadb
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=MOTDEPASSE_FORT

# Redis pour cache et sessions
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Sessions et cache
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.votreservice.com
MAIL_PORT=587
MAIL_USERNAME=votre@email.com
MAIL_PASSWORD=motdepasse
MAIL_ENCRYPTION=tls

# BunnyCDN (optionnel)
BUNNY_CDN_STORAGE_ZONE=votre-zone
BUNNY_CDN_API_KEY=votre-api-key
BUNNY_CDN_PULL_ZONE=https://votre-zone.b-cdn.net

# Bunny Stream (optionnel)
BUNNY_STREAM_API_KEY=votre-api-key
BUNNY_STREAM_LIBRARY_ID=votre-library-id
```

### 3. Build et optimisation

```bash
# Build de l'image de production
docker build -t laravel-portfolio:latest -f Dockerfile .

# Ou avec docker-compose
docker-compose -f docker-compose.production.yml build

# Optimisations Laravel
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
docker-compose exec app php artisan icons:cache

# Build des assets frontend
docker-compose exec app bun run build:ssr
```

## Déploiement avec Portainer

### Installation de Portainer

```bash
# Créer le volume Portainer
docker volume create portainer_data

# Démarrer Portainer
docker run -d \
  -p 8000:8000 \
  -p 9443:9443 \
  --name portainer \
  --restart=always \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v portainer_data:/data \
  portainer/portainer-ce:latest
```

### Déploiement via Portainer

1. Accéder à Portainer : `https://votre-serveur:9443`
2. Créer un stack "laravel-portfolio"
3. Coller le contenu de `docker-compose.production.yml`
4. Configurer les variables d'environnement
5. Déployer le stack

## Configuration Nginx (Reverse Proxy)

Si vous utilisez Nginx comme reverse proxy :

```nginx
server {
    listen 80;
    server_name votredomaine.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name votredomaine.com;

    ssl_certificate /etc/letsencrypt/live/votredomaine.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/votredomaine.com/privkey.pem;

    client_max_body_size 100M;

    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /socket.io/ {
        proxy_pass http://localhost:8000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

## CI/CD avec GitHub Actions

### Workflows disponibles

Le projet inclut plusieurs workflows GitHub Actions :

1. **tests.yml** - Tests PHPUnit automatiques
2. **dusk.yml** - Tests end-to-end avec Laravel Dusk
3. **lint.yml** - Vérification de la qualité du code

### Déploiement automatique

Créer un workflow `.github/workflows/deploy.yml` :

```yaml
name: Deploy

on:
  push:
    branches: [master]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy to server
        uses: appleboy/ssh-action@v0.1.5
        with:
          host: ${{ secrets.HOST }}
          username: ${{ secrets.USERNAME }}
          key: ${{ secrets.SSH_KEY }}
          script: |
            cd /var/www/laravel-portfolio
            git pull origin master
            docker-compose -f docker-compose.production.yml down
            docker-compose -f docker-compose.production.yml up -d --build
            docker-compose exec -T app composer install --no-dev --optimize-autoloader
            docker-compose exec -T app bun install --frozen-lockfile
            docker-compose exec -T app bun run build:ssr
            docker-compose exec -T app php artisan migrate --force
            docker-compose exec -T app php artisan config:cache
            docker-compose exec -T app php artisan route:cache
            docker-compose exec -T app php artisan view:cache
```

### Secrets GitHub nécessaires

Dans les paramètres du repository, ajouter ces secrets :

- `HOST` : Adresse IP ou domaine du serveur
- `USERNAME` : Utilisateur SSH
- `SSH_KEY` : Clé privée SSH
- `CODECOV_TOKEN` : Token Codecov (pour la couverture de tests)

## Laravel Octane

Pour utiliser Laravel Octane en production :

```bash
# Installation
docker-compose exec app composer require laravel/octane

# Configuration
docker-compose exec app php artisan octane:install --server=swoole

# Démarrage
docker-compose exec app php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000
```

Modifier le Dockerfile pour utiliser Octane :

```dockerfile
CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]
```

## Monitoring et logs

### Logs Docker

```bash
# Voir tous les logs
docker-compose -f docker-compose.production.yml logs -f

# Logs d'un service spécifique
docker-compose -f docker-compose.production.yml logs -f app

# Logs Laravel
docker-compose exec app tail -f storage/logs/laravel.log
```

### Laravel Pail (monitoring temps réel)

```bash
docker-compose exec app php artisan pail
```

### Healthcheck

Ajouter un endpoint de healthcheck :

```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'redis' => Redis::connection()->ping() ? 'connected' : 'disconnected',
    ]);
});
```

## Backup et restauration

### Backup de la base de données

```bash
# Créer un backup
docker-compose exec mariadb mysqldump -u root -p laravel > backup_$(date +%Y%m%d).sql

# Restaurer un backup
docker-compose exec -T mariadb mysql -u root -p laravel < backup_20240101.sql
```

### Backup des fichiers uploadés

```bash
# Créer une archive des uploads
tar -czf uploads_$(date +%Y%m%d).tar.gz storage/app/public/uploads/

# Restaurer les uploads
tar -xzf uploads_20240101.tar.gz
```

## Sécurité

### Checklist de sécurité

- [ ] `APP_DEBUG=false` en production
- [ ] `APP_KEY` unique et sécurisée
- [ ] HTTPS activé avec certificat SSL valide
- [ ] Firewall configuré (ports 80, 443, 22 uniquement)
- [ ] Fail2ban installé pour prévenir les attaques brute force
- [ ] Mots de passe forts pour la base de données
- [ ] Mises à jour de sécurité automatiques activées
- [ ] Backup automatique configuré

### Headers de sécurité

Ajouter dans Nginx :

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';" always;
```

## Optimisation des performances

### Cache CDN avec BunnyCDN

1. Créer une Pull Zone dans BunnyCDN
2. Configurer l'origine vers votre domaine
3. Activer la compression Brotli
4. Configurer les règles de cache appropriées

### Redis pour les sessions et le cache

```env
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

### Queue workers

```bash
# Démarrer les workers
docker-compose exec app php artisan queue:work --sleep=3 --tries=3

# Ou avec Supervisor (recommandé)
docker-compose exec app supervisord -c /etc/supervisor/conf.d/laravel-worker.conf
```

## Dépannage

### L'application ne démarre pas

```bash
# Vérifier les logs
docker-compose logs app

# Vérifier les permissions
docker-compose exec app ls -la storage/
docker-compose exec app chmod -R 775 storage bootstrap/cache

# Régénérer la clé
docker-compose exec app php artisan key:generate
```

### Erreurs 500

```bash
# Activer temporairement le debug
docker-compose exec app sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env

# Vider les caches
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
```

### Performance lente

```bash
# Optimiser Composer
docker-compose exec app composer install --optimize-autoloader --no-dev

# Optimiser Laravel
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache

# Vérifier Redis
docker-compose exec redis redis-cli ping
```

## Ressources

- [Documentation Docker](https://docs.docker.com/)
- [Laravel Deployment](https://laravel.com/docs/deployment)
- [Laravel Octane](https://laravel.com/docs/octane)
- [Portainer Documentation](https://docs.portainer.io/)
- [BunnyCDN Documentation](https://docs.bunny.net/)