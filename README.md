# Composer Workspaces

Plugin Composer pour gérer automatiquement les modules dans une architecture modulaire ou monorepo PHP.

## Fonctionnalités

- Découverte automatique des modules
- Injection automatique des namespaces PSR-4
- Installation automatique des dépendances des modules
- Compatible Laravel Modules
- Compatible monorepo PHP

## Installation

```bash
composer require azteck/composer-workspaces
```

## Configuration

```json
{
  "config": {
    "allow-plugins": {
      "azteck/composer-workspaces": true
    }
  }
}
```
