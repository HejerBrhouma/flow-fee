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

Une fois les conteneurs démarrés, les services sont accessibles sur `localhost` aux ports définis dans `docker-compose.yml` :

| Service | Description |
|---|---|
| Frontend web | Application Angular |
| App mobile (preview navigateur) | Même app en Ionic, voir [Application mobile](#application-mobile) |
| API backend | API Symfony (préfixe `/api`) |
| phpMyAdmin | Interface d'administration MySQL |
| MySQL | Base de données (accès direct, ex. client SQL) |

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

- **Connexion Google/Facebook** : renseigner `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` et `FACEBOOK_CLIENT_ID`/`FACEBOOK_CLIENT_SECRET` dans `backend/.env.local`. Le flux est déjà câblé côté web (popup + `postMessage`) et côté mobile (navigateur système + deep link `com.flowfee.app://oauth-callback`) ; il ne manque que des identifiants OAuth réels. Dans la console Google Cloud / Meta for Developers, déclarer ces 4 URIs de redirection autorisées (remplacer `<host-backend>` et `<port-backend>` par l'adresse et le port réels du service `backend`, voir `docker-compose.yml`) :
  - `http://localhost:<port-backend>/api/auth/oauth/google/check` (web)
  - `http://localhost:<port-backend>/api/auth/oauth/facebook/check` (web)
  - `http://<host-backend>:<port-backend>/api/auth/oauth/mobile/google/check` (mobile)
  - `http://<host-backend>:<port-backend>/api/auth/oauth/mobile/facebook/check` (mobile)

  Sur mobile, l'app doit être compilée (Xcode/Android Studio) pour que le schéma d'URL personnalisé `com.flowfee.app://` soit intercepté par l'OS (déjà déclaré dans `Info.plist` et `AndroidManifest.xml`) — en test via `ionic serve`/Safari mobile sans build natif, le retour de l'OAuth échouera après authentification.
- **Envoi d'emails** : `MAILER_DSN` pointe par défaut vers un serveur SMTP local factice (ex. Mailhog) — à adapter si besoin.

**Conversion de devises** : les dépenses, budgets et objectifs d'épargne peuvent chacun avoir leur propre devise (EUR, USD, GBP, TND). Pour que les totaux (tableau de bord, consommation de budget) restent cohérents, `App\Service\CurrencyConverter` convertit tout dans une devise commune avant de sommer, en utilisant les taux de change en temps réel de [open.er-api.com](https://www.exchangerate-api.com/docs/free) — une API gratuite, sans clé requise. Les taux sont mis en cache 12h ; si l'API est injoignable, un repli sur des taux approximatifs statiques (codés dans le service) est utilisé automatiquement.

### Notifications push (Firebase)

Optionnel : sans configuration, les notifications restent disponibles en in-app (cloche, page `/notifications`) mais aucun push système n'est envoyé — `PushNotificationService` échoue silencieusement (log + no-op) tant que Firebase n'est pas configuré, sans jamais bloquer l'action déclenchante (validation de dépense, objectif atteint, etc.).

Pour activer les push (Android) :

1. Créer un projet sur la [Firebase Console](https://console.firebase.google.com).
2. Ajouter une app Android avec le package `com.flowfee.app`, télécharger `google-services.json` et le placer dans `mobile/android/app/google-services.json` (ignoré par git, déjà pris en charge par `android/app/build.gradle`).
3. Dans les paramètres du projet → **Comptes de service**, générer une clé privée. Placer le JSON téléchargé dans `backend/config/firebase-credentials.json` (ignoré par git). `backend/.env.local` doit contenir :
   ```
   FIREBASE_CREDENTIALS=%kernel.project_dir%/config/firebase-credentials.json
   ```
4. Redémarrer le conteneur `backend` (`docker compose restart backend`) pour que la config soit prise en compte.

**Build natif Android requis pour tester réellement** : les push nécessitent un vrai token FCM, obtenu uniquement via `npx cap run android` (émulateur avec image système **Google Play**, ou appareil physique) — le mode navigateur (preview web du service `mobile`) n'a pas de fallback push. Deux réglages natifs déjà en place dans `mobile/android/` sont nécessaires pour que l'app puisse effectivement parler au backend en HTTP local :
- `android/app/src/main/res/xml/network_security_config.xml` autorise le trafic HTTP en clair vers l'IP LAN de dev (Android bloque le HTTP par défaut depuis l'API 28).
- `capacitor.config.ts` force `server.androidScheme: 'http'`, sinon le WebView (servi en `https://localhost` par défaut) bloque les appels HTTP vers l'API comme "contenu mixte".

Ces deux réglages sont **spécifiques au développement local** (pas de backend HTTPS hébergé pour l'instant) — à revoir avant une publication en production avec une vraie API HTTPS.

## Application mobile

Le dossier `mobile/` contient une application Ionic + Angular + Capacitor qui consomme la **même API** que le frontend web (aucune différence côté backend). Elle a globalement la parité avec le web : authentification (classique + Google/Facebook + 2FA), tableau de bord, dépenses (liste, création, détail, justificatif photo via la caméra native, scan de reçu par OCR), budgets, objectifs d'épargne, notifications (in-app + push), gestion d'entreprise (équipe, départements, validation), profil.

Fonctionnalités spécifiques au mobile :

- **Mode hors-ligne** : création de dépense mise en file d'attente si pas de réseau (synchronisation automatique au retour de la connexion), cache local (stale-while-revalidate) pour le tableau de bord, les dépenses et les budgets.
- **Scan de reçu (OCR)** : extraction automatique du montant et de la date depuis une photo de reçu (Tesseract.js, traitement 100% local, pas d'API externe).
- **Catégorisation automatique** : suggestion de catégorie de dépense à partir du titre saisi (web et mobile).
- **Notifications push** : alertes système (budget dépassé, objectif d'épargne atteint, dépense soumise/validée, invitation d'équipe) en plus des notifications in-app — voir [Notifications push (Firebase)](#notifications-push-firebase) ci-dessous pour la configuration.
- **Dark mode manuel** : bascule clair/sombre indépendante du thème système (page Profil).

### Tester dans le navigateur (déjà lancé avec `docker compose up`)

L'app tourne sur `localhost`, au port du service `mobile` défini dans `docker-compose.yml`. Les plugins Capacitor (caméra, stockage) ont un fallback web automatique (sélecteur de fichier natif du navigateur à la place de la caméra native).

**Pour tester depuis un téléphone**, connecté au même réseau Wi-Fi que la machine qui fait tourner Docker :

```
http://<IP_LAN_DE_VOTRE_MACHINE>:<port-mobile>
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

**Live-reload sur device pendant le développement** : pointer `capacitor.config.ts` (`server.url`) vers l'IP LAN de la machine de dev (`http://<IP_LAN>:<port-mobile>`), puis `npx cap sync`.

### Notes avant publication sur les stores

- **Android** : `applicationId`/`namespace` déjà correctement définis à `com.flowfee.app` dans `android/app/build.gradle`, cohérent avec `capacitor.config.ts` et iOS.
- **iOS** : les descriptions de permission caméra/photothèque sont déjà configurées dans `Info.plist`.
- **URL de l'API** : `mobile/src/environments/environment.ts` et `environment.prod.ts` pointent vers l'IP LAN de la machine de dev (`DEV_MACHINE_LAN_IP`, en dur) — à remplacer par l'URL réelle du backend HTTPS en production, et à retirer par la même occasion `network_security_config.xml` (cleartext) et `androidScheme: 'http'` dans `capacitor.config.ts` (voir [Notifications push (Firebase)](#notifications-push-firebase)), qui ne servent qu'à autoriser le HTTP en local.

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
