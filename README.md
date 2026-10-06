# IDEM- Préfabrication

Ce site web gére les demandes de préfabrication, réaliser en php et docker.

# rajouter un users par défaut en admin :

Pour ajouter les contrôles Radiographie RT et Ressuage PT avec leurs pourcentages, appliquer `scripts/init/prefa_rt_pt.sql` à la base MySQL existante.

Pour ajouter la matière et sa disponibilité (« En stock » / « À commander »), appliquer `scripts/init/prefa_matiere.sql` à la base MySQL existante. Le choix propose Acier, Inox, Aluminium et « Autre » pour mémoriser une nouvelle matière. Les anciennes demandes restent sans matière renseignée.

```bash
docker exec -it php-app-php-1 php /scripts/init/add_user.php
```

```powershell
Get-Content scripts\init\add_user.php -Raw | docker exec -i php-app-php-1 php
```