# Option --full-id pour la commande chronotrace:list

## Description

L'option `--full-id` permet d'afficher les trace IDs complets au lieu de les tronquer.

## Utilisation

### Affichage par défaut (IDs tronqués)
```bash
php artisan chronotrace:list
```

Sortie :
```
Listing stored traces...
+----------+----------+---------------------+
| Trace ID | Size     | Created At          |
+----------+----------+---------------------+
| abc12345... | 1,024 bytes | 2025-07-31 14:30:22 |
| xyz98765... | 2,048 bytes | 2025-07-31 13:30:22 |
+----------+----------+---------------------+
Showing 20 of 2 traces.
```

### Affichage avec IDs complets
```bash
php artisan chronotrace:list --full-id
```

Sortie :
```
Listing stored traces...
+--------------------------------------+----------+---------------------+
| Trace ID                             | Size     | Created At          |
+--------------------------------------+----------+---------------------+
| abc12345-def6-7890-abcd-ef1234567890 | 1,024 bytes | 2025-07-31 14:30:22 |
| xyz98765-fed4-3210-zyxw-987654321abc | 2,048 bytes | 2025-07-31 13:30:22 |
+--------------------------------------+----------+---------------------+
Showing 20 of 2 traces.
```

## Combinaison avec d'autres options

```bash
# Afficher les 5 dernières traces avec IDs complets
php artisan chronotrace:list --limit=5 --full-id

# Aide de la commande
php artisan chronotrace:list --help
```

## Cas d'usage

- **Développement/Debug** : Utiliser `--full-id` pour copier-coller facilement les IDs complets
- **Production/Monitoring** : Utiliser le mode par défaut pour une vue condensée
- **Scripts/Automation** : Utiliser `--full-id` pour parser les IDs dans des scripts

## Tests

Les tests valident :
- ✅ Affichage par défaut avec IDs tronqués (8 caractères + "...")
- ✅ Affichage complet avec option `--full-id`
- ✅ Compatibilité avec l'option `--limit`
- ✅ Gestion des erreurs inchangée
