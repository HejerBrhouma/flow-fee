# Flow Fee

Application de gestion de dépenses pour particuliers et entreprises : suivi des dépenses personnelles, budgets et objectifs d'épargne pour les particuliers ; gestion d'équipe, départements, budgets et validation des notes de frais pour les entreprises.

## Stack technique

- **Frontend web** : Angular 17 (Material, Tailwind CSS, Chart.js)
- **Application mobile** : Angular 20 + Ionic + Capacitor (iOS/Android), même API que le web
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
| Frontend web | http://localhost:4200 | Application Angular |
| App mobile (preview navigateur) | http://localhost:8100 | Même app en Ionic, voir [Application mobile](#application-mobile) |
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

- **Connexion Google/Facebook** : renseigner `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` et `FACEBOOK_CLIENT_ID`/`FACEBOOK_CLIENT_SECRET` dans `backend/.env.local`. Le flux est déjà câblé côté web (popup + `postMessage`) et côté mobile (navigateur système + deep link `com.flowfee.app://oauth-callback`) ; il ne manque que des identifiants OAuth réels. Dans la console Google Cloud / Meta for Developers, déclarer ces 4 URIs de redirection autorisées :
  - `http://localhost:8001/api/auth/oauth/google/check` (web)
  - `http://localhost:8001/api/auth/oauth/facebook/check` (web)
  - `http://<host-backend>:8001/api/auth/oauth/mobile/google/check` (mobile)
  - `http://<host-backend>:8001/api/auth/oauth/mobile/facebook/check` (mobile)

  Sur mobile, l'app doit être compilée (Xcode/Android Studio) pour que le schéma d'URL personnalisé `com.flowfee.app://` soit intercepté par l'OS (déjà déclaré dans `Info.plist` et `AndroidManifest.xml`) — en test via `ionic serve`/Safari mobile sans build natif, le retour de l'OAuth échouera après authentification.
- **Envoi d'emails** : `MAILER_DSN` pointe par défaut vers un serveur SMTP local factice (`localhost:1025`, ex. Mailhog) — à adapter si besoin.

**Conversion de devises** : les dépenses, budgets et objectifs d'épargne peuvent chacun avoir leur propre devise (EUR, USD, GBP, TND). Pour que les totaux (tableau de bord, consommation de budget) restent cohérents, `App\Service\CurrencyConverter` convertit tout dans une devise commune avant de sommer, en utilisant les taux de change en temps réel de [open.er-api.com](https://www.exchangerate-api.com/docs/free) — une API gratuite, sans clé requise. Les taux sont mis en cache 12h ; si l'API est injoignable, un repli sur des taux approximatifs statiques (codés dans le service) est utilisé automatiquement.

## Application mobile

Le dossier `mobile/` contient une application Ionic + Angular + Capacitor qui consomme la **même API** que le frontend web (aucune différence côté backend). Le périmètre actuel couvre l'essentiel : authentification, tableau de bord, dépenses (liste, création, détail, justificatif photo via la caméra native), notifications, profil/déconnexion. Budgets, objectifs d'épargne et gestion d'entreprise ne sont pas encore portés sur mobile.

### Tester dans le navigateur (déjà lancé avec `docker compose up`)

L'app tourne sur http://localhost:8100. Les plugins Capacitor (caméra, stockage) ont un fallback web automatique (sélecteur de fichier natif du navigateur à la place de la caméra native).

**Pour tester depuis un téléphone**, connecté au même réseau Wi-Fi que la machine qui fait tourner Docker :

```
http://<IP_LAN_DE_VOTRE_MACHINE>:8100
```

Trouver son IP LAN : `ipconfig getifaddr en0` (Mac) ou `ipconfig` (Windows, chercher "Adresse IPv4").

### Build natif iOS / Android

Nécessite Xcode (Mac, pour iOS) et/ou Android Studio (pour Android) installés localement — ces outils ne tournent pas dans Docker.

```bash
cd mobile
npm install          # si pas déjà fait via Docker
npm run build        # compile le bundle web dans www/
npx cap sync         # copie www/ vers les projets natifs android/ et ios/
npx cap open android # ouvre le projet dans Android Studio
npx cap open ios     # ouvre le projet dans Xcode (nécessite `pod install` au préalable dans ios/App)
```

Depuis Android Studio / Xcode, lancer sur un simulateur/émulateur ou un appareil physique connecté (USB debugging activé côté Android).

**Live-reload sur device pendant le développement** : pointer `capacitor.config.ts` (`server.url`) vers l'IP LAN de la machine de dev (`http://<IP_LAN>:8100`), puis `npx cap sync`.

### Notes avant publication sur les stores

- **Android** : le projet a été généré avec l'`applicationId` par défaut (`io.ionic.starter`) — à renommer dans `android/app/build.gradle` (et la structure de package Java associée) avant publication. `capacitor.config.ts` utilise déjà le bon identifiant (`com.flowfee.app`) pour iOS.
- **iOS** : les descriptions de permission caméra/photothèque sont déjà configurées dans `Info.plist`.
- **URL de l'API** : `mobile/src/environments/environment.prod.ts` pointe vers un domaine placeholder (`api.flow-fee.com`) — à remplacer par l'URL réelle du backend en production.

## Commandes utiles

```bash
# Logs
docker compose logs -f backend
docker compose logs -f frontend
docker compose logs -f mobile

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
├── frontend/    # Application web Angular (features, services, composants)
├── mobile/      # Application mobile Ionic + Angular + Capacitor
└── docker-compose.yml
```
