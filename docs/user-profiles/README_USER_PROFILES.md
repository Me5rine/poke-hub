# User Profiles Module

Module de gestion des profils Pokémon GO pour les utilisateurs WordPress.

> 📍 **Emplacement** : `docs/user-profiles/` (à la racine du plugin)

## Fonctionnalités

- Édition du profil Pokémon GO (équipe, code ami, XP, pays, pseudo, motif Scatterbug, raisons)
- Intégration avec Ultimate Member (onglet de profil)
- Shortcode `[poke_hub_user_profile]` pour afficher le profil
- Synchronisation avec Ultimate Member pour le pays

## Documentation

### 📄 Fichiers Spécifiques au Module

- **`SHORTCODE_USAGE.md`** → Documentation du shortcode `[poke_hub_user_profile]`
- **`ULTIMATE_MEMBER_SETUP.md`** → Configuration et dépannage pour Ultimate Member
- **`CUSTOMIZATION.md`** → Comment personnaliser les listes (équipes, raisons) via les filtres WordPress
- **`README_DATA_CENTRALIZATION.md`** → Architecture de centralisation des données

### 🎨 Documentation Générique (CSS)

> Ces fichiers sont à la racine de `docs/` car ils sont réutilisables dans d'autres projets

- **`../CSS_RULES.md`** → **CSS à copier dans le thème** (fichier principal)
- **`../CSS_SYSTEM.md`** → Documentation du système de classes génériques `me5rine-lab-form-*`
- **`../PLUGIN_INTEGRATION.md`** → Guide pour utiliser les classes CSS dans d'autres plugins

## 🚀 Démarrage Rapide

1. **CSS** : Copier le contenu de `../CSS_RULES.md` (à la racine de `docs/`) dans votre thème
2. **Shortcode** : Utiliser `[poke_hub_user_profile]` dans vos templates
3. **Ultimate Member** : Suivre les instructions dans `ULTIMATE_MEMBER_SETUP.md`
