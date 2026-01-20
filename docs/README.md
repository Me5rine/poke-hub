# Documentation Poké HUB

Ce dossier contient toute la documentation du plugin Poké HUB.

## 📁 Structure

### Documentation Générale

La documentation générale se trouve à la racine du dossier `docs/` :

- **[ORGANISATION.md](./ORGANISATION.md)** - Organisation générale du plugin
- **[THEME_INTEGRATION.md](./THEME_INTEGRATION.md)** - Guide d'intégration dans le thème WordPress
- **[PLUGIN_INTEGRATION.md](./PLUGIN_INTEGRATION.md)** - Guide d'intégration pour utiliser les classes CSS dans d'autres plugins
- **[CSS_SYSTEM.md](./CSS_SYSTEM.md)** - Documentation complète du système de classes CSS
- **[CSS_RULES.md](./CSS_RULES.md)** - Règles CSS complètes pour les formulaires
- **[FRONT_CSS.md](./FRONT_CSS.md)** - Règles CSS unifiées pour les éléments front-end
- **[ADMIN_CSS.md](./ADMIN_CSS.md)** - Règles CSS unifiées pour l'administration
- **[TABLE_CSS.md](./TABLE_CSS.md)** - Règles CSS pour les tableaux
- **[PLUGIN_COPY_GUIDE.md](./PLUGIN_COPY_GUIDE.md)** - Guide : Fichiers à copier pour réutiliser la structure
- **[SELECT2_INITIALIZATION.md](./SELECT2_INITIALIZATION.md)** - Documentation de l'initialisation Select2
- **[POKEMON_IMAGES.md](./POKEMON_IMAGES.md)** - Gestion des images Pokémon

### Documentation par Module

La documentation spécifique à chaque module se trouve dans des sous-dossiers :

- **[blocks/](./blocks/)** - Documentation du module Blocks
  - [ARCHITECTURE.md](./blocks/ARCHITECTURE.md) - Architecture et organisation des blocs Gutenberg
  - [BLOCK_TYPES.md](./blocks/BLOCK_TYPES.md) - Types de blocs (PHP-only vs JS/React)
  - [QUICK_START.md](./blocks/QUICK_START.md) - Guide de création rapide de blocs

- **[events/](./events/)** - Documentation du module Events
  - [README-ROUTING.md](./events/README-ROUTING.md) - Documentation du système de routing
  - [INTEGRATION-ELEMENTOR.md](./events/INTEGRATION-ELEMENTOR.md) - Intégration avec Elementor
  - [EVENEMENTS-DISTANTS.md](./events/EVENEMENTS-DISTANTS.md) - Gestion des événements distants

- **[pokemon/](./pokemon/)** - Documentation du module Pokémon
  - [README-REGIONAL-AUTO-CONFIG.md](./pokemon/README-REGIONAL-AUTO-CONFIG.md) - Configuration automatique des Pokémon régionaux

- **[user-profiles/](./user-profiles/)** - Documentation du module User Profiles
  - [README_USER_PROFILES.md](./user-profiles/README_USER_PROFILES.md) - Documentation principale
  - [SHORTCODE_USAGE.md](./user-profiles/SHORTCODE_USAGE.md) - Documentation du shortcode
  - [ULTIMATE_MEMBER_SETUP.md](./user-profiles/ULTIMATE_MEMBER_SETUP.md) - Configuration Ultimate Member
  - [CUSTOMIZATION.md](./user-profiles/CUSTOMIZATION.md) - Personnalisation des listes
  - [README_DATA_CENTRALIZATION.md](./user-profiles/README_DATA_CENTRALIZATION.md) - Architecture de centralisation
  - [SYNCHRONIZATION.md](./user-profiles/SYNCHRONIZATION.md) - Synchronisation des données
  - [COUNTRIES-STORAGE-FORMAT.md](./user-profiles/COUNTRIES-STORAGE-FORMAT.md) - Format de stockage des pays

## 📖 Utilisation

### Pour les développeurs

**Pour intégrer les styles dans votre thème WordPress :**
1. Consultez [THEME_INTEGRATION.md](./THEME_INTEGRATION.md) - Guide complet étape par étape

**Pour utiliser les classes dans votre plugin/thème :**
1. Lisez [PLUGIN_INTEGRATION.md](./PLUGIN_INTEGRATION.md) pour comprendre la structure HTML
2. Consultez [CSS_SYSTEM.md](./CSS_SYSTEM.md) pour la liste complète des classes
3. Copiez le contenu de [CSS_RULES.md](./CSS_RULES.md) dans votre thème pour les formulaires
4. Copiez le contenu de [FRONT_CSS.md](./FRONT_CSS.md) dans votre thème pour les éléments front-end

### Pour la configuration des modules

Consultez la documentation dans le dossier correspondant au module (ex: `blocks/`, `events/`, `user-profiles/`, etc.).

## 🔗 Liens rapides

- [Organisation du plugin](./ORGANISATION.md)
- [Guide d'intégration dans le thème](./THEME_INTEGRATION.md)
- [Système CSS](./CSS_SYSTEM.md)
- [Module Blocks](./blocks/)
- [Module Events](./events/)
- [Module User Profiles](./user-profiles/)
- [Module Pokémon](./pokemon/)

