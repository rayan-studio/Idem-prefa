# IDEM- Préfabrication

Ce site web gére les demandes de préfabrication, réaliser en php et docker.

## Installation

Démarrer les conteneurs :

```bash
docker compose up -d --build
```

Créer les tables dans la base vide. `db/schema.sql` contient la structure complète
(30 tables et leurs clés étrangères) et les seules données sans lesquelles
l'application ne tourne pas : les rôles, les statuts de demande et le paramétrage
du rendement. Il ne contient aucun compte, aucune demande et aucun document.

```bash
docker compose exec -T db mysql -umyuser -pmypassword myapp < db/schema.sql
```

```powershell
Get-Content db\schema.sql -Raw | docker compose exec -T db mysql -umyuser -pmypassword myapp
```

Le fichier ne supprime aucune table : l'appliquer sur une base déjà remplie échoue
au lieu d'effacer quoi que ce soit.

Les sauvegardes complètes de la base ne sont pas suivies par git : elles contiennent
les mots de passe hachés et les documents déposés.

## Créer le premier administrateur

```bash
docker exec -it php-app-php-1 php /scripts/init/add_user.php
```

```powershell
Get-Content scripts\init\add_user.php -Raw | docker exec -i php-app-php-1 php
```

## Références des demandes

Les références des demandes suivent le format `26-DP-0001`, avec un compteur par année
de création. Sur une base créée avec `db/schema.sql`, il n'y a rien à faire. Sur une base
plus ancienne, appliquer une fois cette migration (elle peut être relancée sans
renuméroter les demandes) :

```powershell
Get-Content scripts\init\prefa_references.php -Raw | docker compose exec -T php php
```

Les identifiants techniques restent inchangés. Les références supprimées restent
réservées et ne sont pas réutilisées.
