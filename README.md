# Composer Workspaces

Plugin Composer pour gérer automatiquement les modules dans une architecture modulaire ou monorepo PHP.

## Fonctionnalités

- Découverte automatique des modules dans un ou plusieurs dossiers configurables
- Injection automatique des namespaces PSR-4 (avec support des dossiers en minuscules)
- Installation automatique des dépendances des modules (`composer install`)
- Compatible Laravel Modules (`nwidart/laravel-modules`)
- Compatible monorepo PHP

## Installation

```bash
composer require azteck/composer-workspaces
```

Autoriser le plugin dans votre `composer.json` :

```json
{
    "config": {
        "allow-plugins": {
            "azteck/composer-workspaces": true
        }
    }
}
```

## Configuration

La configuration se fait dans la clé `extra.workspaces.paths` de votre `composer.json` racine.

### Dossier unique

```json
{
    "extra": {
        "workspaces": {
            "paths": ["Modules"]
        }
    }
}
```

### Plusieurs dossiers

```json
{
    "extra": {
        "workspaces": {
            "paths": ["Modules", "MyModules", "Tools", "Extra"]
        }
    }
}
```

### Avec chemin absolu

```json
{
    "extra": {
        "workspaces": {
            "paths": ["/opt/shared-modules", "Modules"]
        }
    }
}
```

> Si aucun chemin n'est configuré, le plugin utilise `Modules/` par défaut.
> Les chemins inexistants sont ignorés silencieusement.

## Structure attendue

Chaque sous-dossier d'un chemin configuré est considéré comme un module s'il contient un `composer.json`.

```
project/
├── composer.json
├── Modules/
│   └── Blog/
│       ├── composer.json        ← détecté automatiquement
│       ├── Providers/
│       ├── Http/
│       ├── Models/
│       ├── database/
│       │   ├── seeders/
│       │   └── factories/
│       └── tests/
├── Tools/
│   └── MyTool/
│       └── composer.json        ← détecté automatiquement
└── Extra/                       ← ignoré si absent
```

## Configuration PSR-4 d'un module

Pour les modules dont les dossiers sont en minuscules (ex. `database/seeders/`) mais dont les namespaces utilisent des majuscules (ex. `Database\Seeders\`), déclarez des entrées PSR-4 spécifiques dans le `composer.json` du module :

```json
{
    "name": "modules/blog",
    "autoload": {
        "psr-4": {
            "Modules\\Blog\\":                      "",
            "Modules\\Blog\\Database\\Seeders\\":   "database/seeders/",
            "Modules\\Blog\\Database\\Factories\\": "database/factories/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Modules\\Blog\\Tests\\": "tests/"
        }
    }
}
```

> La clé `""` correspond à la racine du module. Ainsi `Modules\Blog\Providers\BlogServiceProvider`
> est résolu vers `Modules/Blog/Providers/BlogServiceProvider.php`.

## Fonctionnement

Le plugin se raccroche aux hooks Composer suivants :

| Hook | Action |
|---|---|
| `pre-autoload-dump` | Injecte les PSR-4 de tous les modules dans l'autoloader racine |
| `post-install-cmd` | Lance `composer install` dans les modules qui ont des dépendances |
| `post-update-cmd` | Lance `composer install` dans les modules mis à jour |

### Output attendu

```
[Workspaces] Injection des PSR-4...
  PSR-4     [Blog]  Modules\Blog\ → Modules/Blog
  PSR-4     [Blog]  Modules\Blog\Database\Seeders\ → Modules/Blog/database/seeders
  PSR-4 dev [Blog]  Modules\Blog\Tests\ → Modules/Blog/tests
  PSR-4     [MyTool]    Tools\MyTool\ → Tools/MyTool/src
Generated optimized autoload files ✅
```

## Exemple `composer.json` complet

```json
{
    "name": "vendor/my-project",
    "require": {
        "azteck/composer-workspaces": "*"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    },
    "config": {
        "allow-plugins": {
            "azteck/composer-workspaces": true
        }
    },
    "extra": {
        "workspaces": {
            "paths": ["Modules", "Tools"]
        }
    }
}
```

## Licence

[LICENCE MIT](LICENSE)
