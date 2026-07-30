# Flow Fee

Application de gestion de dépenses pour particuliers et entreprises : suivi des dépenses personnelles, budgets et objectifs d'épargne pour les particuliers ; gestion d'équipe, départements, budgets et validation des notes de frais pour les entreprises.

## Stack technique

- **Frontend** : Angular 17 (Material, Tailwind CSS, Chart.js)
- **Backend** : Symfony 7.2 (PHP 8.2, API REST, JWT)
- **Base de données** : MySQL 8.0

## Prérequis

- [Docker](https://www.docker.com/) et Docker Compose

Aucune installation locale de PHP/Node/MySQL n'est nécessaire : tout tourne en conteneurs.

## Démarrage rapide

```bash
docker compose up
```

Au premier lancement, le conteneur `backend` installe automatiquement les dépendances Composer, génère les clés JWT, crée la base de données et applique les migrations. Cela peut prendre une à deux minutes la première fois.

Une fois les conteneurs démarrés :

| Service | URL | Description |
|---|---|---|
| Frontend | http://localhost:4200 | Application Angular |
| API backend | http://localhost:8001/api | API Symfony |
| phpMyAdmin | http://localhost:8082 | Interface d'administration MySQL |
| MySQL | `localhost:3307` | Base de données (accès direct, ex. client SQL) |

### Charger les données de démonstration

Les migrations créent le schéma mais ne remplissent pas la base. Pour avoir des comptes de test et les catégories de dépenses par défaut :

```bash
docker compose exec backend php bin/console doctrine:fixtures:load --no-interaction
```

⚠️ Cette commande **purge** la base de données avant de recharger les fixtures — à éviter si vous avez déjà des données que vous souhaitez conserver.

Comptes créés :

| Rôle | Email | Mot de passe |
|---|---|---|
| Particulier | `demo@flowfee.com` | `demo1234` |
| Admin entreprise | `admin@flowfee.com` | `admin1234` |

Sans fixtures, il reste possible de créer un compte via la page d'inscription (`/auth/register`).

## Configuration

Les variables d'environnement du backend (base de données, secrets, clés JWT) sont déjà définies dans `docker-compose.yml` pour l'environnement de développement — aucune action requise pour démarrer.

Deux fonctionnalités nécessitent des identifiants externes réels pour fonctionner (les boutons existent mais échoueront sans clés valides) :

- **Connexion Google/Facebook** : renseigner `GOOGLE_CLIENT_ID`/`FACEBOOK_CLIENT_ID` (et secrets associés) dans `backend/.env.local`, et `googleClientId`/`facebookAppId` dans `frontend/src/environments/environment.ts`.
- **Envoi d'emails** : `MAILER_DSN` pointe par défaut vers un serveur SMTP local factice (`localhost:1025`, ex. Mailhog) — à adapter si besoin.

## Commandes utiles

```bash
# Logs
docker compose logs -f backend
docker compose logs -f frontend

# Console Symfony
docker compose exec backend php bin/console <commande>

# Nouvelle migration après modification d'une entité
docker compose exec backend php bin/console make:migration
docker compose exec backend php bin/console doctrine:migrations:migrate

# Réinitialiser la base de données
docker compose exec backend php bin/console doctrine:database:drop --force
docker compose exec backend php bin/console doctrine:database:create
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec backend php bin/console doctrine:fixtures:load --no-interaction

# Arrêter et supprimer les conteneurs (garde les données MySQL)
docker compose down

# Arrêter et supprimer aussi les données MySQL
docker compose down -v
```

## Structure du projet

```
flow-fee/
├── backend/     # API Symfony (contrôleurs, entités, migrations)
├── frontend/    # Application Angular (features, services, composants)
└── docker-compose.yml
```
