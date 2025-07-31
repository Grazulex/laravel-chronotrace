# Enhanced Replay Command - Toutes les Informations Disponibles

## 🚀 Nouvelles Fonctionnalités

La commande `chronotrace:replay` a été considérablement améliorée pour afficher **TOUTES** les informations disponibles dans les traces JSON.

## 📋 Options Disponibles

### Options de Base
- `--db` : Afficher uniquement les événements de base de données
- `--cache` : Afficher uniquement les événements de cache
- `--http` : Afficher uniquement les événements HTTP
- `--jobs` : Afficher uniquement les événements de jobs/queue
- `--format=table|json|raw` : Format de sortie

### Nouvelles Options de Détail
- `--detailed` : Afficher toutes les informations détaillées (équivalent à toutes les options ci-dessous)
- `--context` : Afficher le contexte Laravel (versions, config, env vars)
- `--headers` : Afficher les headers de requête et réponse
- `--content` : Afficher le contenu de la réponse
- `--bindings` : Afficher les paramètres des requêtes SQL préparées
- `--compact` : Affichage minimal uniquement

## 🆕 Nouvelles Sections Affichées

### 1. Informations de Trace Améliorées
```
=== TRACE INFORMATION ===
🆔 Trace ID: ct_4uZ5EhLVCtV5bbpH_1753989158
🕒 Timestamp: 2025-07-31T19:12:39+00:00
🌍 Environment: local
🔗 Request URL: http://localhost:8000/test-chronotrace
📊 Response Status: 200
⏱️  Duration: 0.13729691505432ms
💾 Memory Usage: 0.00 KB
👤 User: [si connecté]
🌐 IP Address: 127.0.0.1
🖥️  User Agent: GuzzleHttp/7
```

### 2. Contexte Laravel (--context ou --detailed)
```
=== LARAVEL CONTEXT ===
🚀 Laravel Version: 12.21.0
🐘 PHP Version: 8.4.10
📋 Git Commit: [si disponible]
🌿 Git Branch: [si disponible]
⚙️  Configuration:
   • app.debug: true
   • app.env: local
   • database.default: sqlite
🌱 Environment Variables:
   • APP_ENV: local
   • APP_DEBUG: true
📦 Installed Packages: [si disponibles]
🔒 Active Middlewares: [si disponibles]
🏗️  Service Providers: [si disponibles]
```

### 3. Détails de la Requête (--headers ou --detailed)
```
=== REQUEST DETAILS ===
📝 Method: GET
🔗 URL: http://localhost:8000/test-chronotrace
❓ Query Parameters: [si présents]
📥 Input Data: [si POST/PUT/PATCH]
📁 Uploaded Files: [si présents]
🔐 Session Data:
   • _token: [SCRUBBED]
📋 Request Headers:
   • host: localhost:8000
   • user-agent: GuzzleHttp/7
```

### 4. Événements Database Améliorés (--bindings ou --detailed)
```
📊 DATABASE EVENTS
  🔍 [19:12:38.000] Query: insert into "cache" (...) (5.29ms on sqlite)
     📎 Bindings: [1753989218,"laravel-cache-test-key","s:10:\"test-value\";"]
```

### 5. Nouveaux Types d'Événements
```
📧 MAIL EVENTS
  📤 [timestamp] Sending email to: user@example.com - Subject: Welcome

🔔 NOTIFICATION EVENTS  
  📤 [timestamp] Sending notification via mail to: User#1

🎯 CUSTOM EVENTS
  🎯 [timestamp] Event: UserRegistered
     📊 Data: {"user_id": 123}

📁 FILESYSTEM EVENTS
  📖 [timestamp] File READ: storage/app/file.txt (disk: local)
```

### 6. Détails de la Réponse (--content/--headers ou --detailed)
```
=== RESPONSE DETAILS ===
📊 Status: 200
⏱️  Duration: 0.13729691505432ms
💾 Memory: 0.00 KB
📋 Response Headers:
   • cache-control: no-cache, private
   • content-type: application/json
🍪 Cookies Set: [si présents]
❌ Exception: [si erreur]
📄 Response Content:
   {
    "message": "ChronoTrace test endpoint",
    "users_count": 0,
    "cache_value": "test-value"
   }
```

### 7. Résumé Statistique Amélioré
```
📈 EVENTS SUMMARY
  📊 Database events: 3
  🗄️  Cache events: 2
  🌐 HTTP events: 2
  ⚙️  Job events: 0
  📧 Mail events: 0
  🔔 Notification events: 0
  🎯 Custom events: 0
  📁 Filesystem events: 0
  📝 Total events: 7
```

## 🎯 Exemples d'Usage

### Affichage Standard
```bash
php artisan chronotrace:replay ct_4uZ5EhLVCtV5bbpH_1753989158
```

### Affichage Complet avec Tout
```bash
php artisan chronotrace:replay ct_4uZ5EhLVCtV5bbpH_1753989158 --detailed
```

### Affichage Spécifique
```bash
# Voir uniquement le contexte Laravel
php artisan chronotrace:replay ct_4uZ5EhLVCtV5bbpH_1753989158 --context

# Voir uniquement les paramètres SQL
php artisan chronotrace:replay ct_4uZ5EhLVCtV5bbpH_1753989158 --bindings

# Voir uniquement le contenu de réponse
php artisan chronotrace:replay ct_4uZ5EhLVCtV5bbpH_1753989158 --content

# Voir uniquement les headers
php artisan chronotrace:replay ct_4uZ5EhLVCtV5bbpH_1753989158 --headers

# Combinaisons
php artisan chronotrace:replay ct_4uZ5EhLVCtV5bbpH_1753989158 --context --bindings --content
```

### Filtrage par Type d'Événement + Détails
```bash
# Database events avec bindings
php artisan chronotrace:replay ct_4uZ5EhLVCtV5bbpH_1753989158 --db --bindings

# HTTP events avec headers
php artisan chronotrace:replay ct_4uZ5EhLVCtV5bbpH_1753989158 --http --headers
```

## 📊 Comparaison Avant/Après

### AVANT (Version Originale)
- ✅ 7 informations de base
- ✅ 4 types d'événements (DB, Cache, HTTP, Jobs)
- ❌ Pas de contexte Laravel
- ❌ Pas de headers détaillés
- ❌ Pas de contenu de réponse
- ❌ Pas de bindings SQL
- ❌ Pas d'événements Mail/Notification/Custom/Filesystem

### APRÈS (Version Améliorée)
- ✅ 9+ informations de base (+ IP, User Agent, User)
- ✅ 8 types d'événements (+ Mail, Notifications, Custom, Filesystem)
- ✅ Contexte Laravel complet (versions, config, env, packages, middlewares, providers)
- ✅ Headers de requête et réponse détaillés
- ✅ Contenu de réponse formaté (JSON beautifié)
- ✅ Bindings SQL pour requêtes préparées
- ✅ Session data et query parameters
- ✅ Files uploadés et cookies
- ✅ Exceptions capturées
- ✅ Options de filtrage flexibles

## 🎉 Résultat

Maintenant la commande `chronotrace:replay` affiche **100% des informations** disponibles dans le JSON de trace, avec des options flexibles pour personnaliser l'affichage selon les besoins !

Toutes les données du rapport de test sont maintenant visibles :
- ✅ Versions Laravel/PHP
- ✅ Configuration et variables d'environnement  
- ✅ Headers HTTP complets
- ✅ Bindings SQL des requêtes préparées
- ✅ Contenu de réponse formaté
- ✅ Session data
- ✅ User Agent et IP
- ✅ Support pour tous les types d'événements Laravel

La commande est maintenant **complète** et **production-ready** ! 🚀
