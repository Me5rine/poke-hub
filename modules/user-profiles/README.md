# User Profiles Module

Module de gestion des profils Pokémon GO pour les utilisateurs WordPress.

> 📖 **Documentation complète** : Voir `docs/user-profiles/` à la racine du plugin

## Fichiers de Documentation

### Spécifiques au Module (`docs/user-profiles/`)

- **`SHORTCODE_USAGE.md`** - Documentation du shortcode
- **`ULTIMATE_MEMBER_SETUP.md`** - Configuration Ultimate Member
- **`CUSTOMIZATION.md`** - Personnalisation des listes
- **`README_DATA_CENTRALIZATION.md`** - Architecture de centralisation

### Documentation Générique (`docs/` - racine)

- **`CSS_RULES.md`** - CSS à copier dans le thème
- **`CSS_SYSTEM.md`** - Documentation du système de classes
- **`PLUGIN_INTEGRATION.md`** - Guide d'intégration pour autres plugins

## Structure du Module

- `user-profiles.php` - Fichier principal
- `admin/` - Interface d'administration
- `functions/` - Fonctions helper
- `includes/` - Données centralisées (équipes, raisons)
- `public/` - Shortcode et intégration Ultimate Member
- `templates/` - Templates Ultimate Member
