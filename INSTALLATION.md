# Installation et Configuration

## Installation Automatique (Recommandée)

```bash
composer require grazulex/laravel-chronotrace
php artisan chronotrace:install
```

La commande `chronotrace:install` :
- ✅ Publie automatiquement la configuration
- ✅ Configure le middleware pour Laravel 11+
- ✅ Détecte votre version de Laravel
- ✅ Fournit des instructions claires

## Configuration Manuelle (Laravel 11+)

Si l'installation automatique échoue, ajoutez manuellement dans `bootstrap/app.php` :

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(/* ... */)
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,
        ]);
        $middleware->api(append: [
            \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,
        ]);
    })
    ->withExceptions(/* ... */);
```

## Configuration Laravel <11

Pour Laravel 10 et antérieur, le middleware est **automatiquement enregistré** par le service provider.
Aucune configuration manuelle n'est nécessaire !

## Vérification

```bash
php artisan chronotrace:list
# Doit afficher "Listing stored traces..." sans erreur
```

## Variables d'Environnement

```env
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=always
CHRONOTRACE_DEBUG=false
```

## Pourquoi cette Configuration ?

**Laravel 11+** a introduit un nouveau système de middleware dans `bootstrap/app.php` qui remplace l'ancien système du kernel. Les packages ne peuvent plus auto-enregistrer les middlewares via les service providers.

Cette approche garantit :
- ✅ **Transparence** : Vous voyez exactement quel middleware est actif
- ✅ **Contrôle** : Vous pouvez facilement désactiver ou modifier l'ordre
- ✅ **Performance** : Pas de "magie" cachée qui ralentit le boot
- ✅ **Sécurité** : Aucun middleware n'est ajouté sans votre consentement explicite
