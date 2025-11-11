# Utilisation

## Backup manuel

Pour déclencher un backup manuellement :

```bash
# Exécuter un backup complet (dump + upload FTP si configuré)
docker-compose -f docker-compose.production.yml exec backup /app/scripts/run-backup.sh

# Backup de la base de données uniquement (sans upload)
docker-compose -f docker-compose.production.yml exec backup /app/scripts/backup-database.sh
```

## Nettoyage manuel

```bash
# Nettoyer les anciens backups
docker-compose -f docker-compose.production.yml exec backup /app/scripts/cleanup-old-backups.sh
```

# Accès aux backups locaux

```bash
# Lister les backups locaux
docker-compose -f docker-compose.production.yml exec backup ls -la /app/backups/

# Vérifier le dernier backup
docker-compose -f docker-compose.production.yml exec backup ls -la /app/backups/$(ls -t /app/backups | head -1)

# Copier un backup vers l'hôte
docker cp $(docker-compose -f docker-compose.production.yml ps -q backup):/app/backups/2024-01-15_02-00-00 ./backup-local/
```
