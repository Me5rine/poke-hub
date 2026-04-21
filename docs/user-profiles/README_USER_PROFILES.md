# User Profiles Module

Module de gestion des profils Pokémon GO pour les utilisateurs WordPress.

> 📍 **Emplacement** : `docs/user-profiles/` (à la racine du plugin)

## Fonctionnalités

- Édition du profil Pokémon GO (équipe, code ami, XP, pays, pseudo, motif Scatterbug, raisons)
- Intégration avec Ultimate Member (onglet de profil)
- Shortcodes :
  - `[poke_hub_user_profile]` - Profil Pokémon GO personnel
  - `[poke_hub_friend_codes]` - Liste publique des codes amis avec filtres (pays, équipe, raison)
  - `[poke_hub_vivillon]` - Liste des codes amis par motif Vivillon avec filtres (motif, pays)
- Pages automatiques : création automatique des pages "friend-codes" et "vivillon" comme enfants de la page "pokemon-go" (configurable dans les settings)
- Synchronisation :
  - Ultimate Member pour le pays
  - Keycloak pour le pseudo/nickname
- Gestion du changement d'email : redirection automatique vers le profil avec notification
- Templates réutilisables pour optimiser le code
- **Codes amis publics** : pseudo obligatoire pour les visiteurs non connectés, contrôle IP / pseudo, mise à jour de la fiche par pseudo ou par code, notices d’erreur ou d’avertissement selon le cas
- **Dernière IP** : enregistrée à chaque sauvegarde de profil (colonne `anonymous_ip`) ; visible en admin (liste + fiche d’édition)
- **Drapeaux pays automatiques** :
  - Selects pays (`country`, `filter_country`, admin) enrichis avec `data-icon` (URL drapeau) et rendu Select2
  - Drapeau affiché aussi dans la tuile d’un code ami, à gauche du nom du pays
  - Chargement distant (flagcdn) : aucun fichier image à sauvegarder en local
  - Mapping extensible via filtres WordPress (`poke_hub_country_flag_iso2`, `poke_hub_country_flag_icon_url`)

## Documentation

### 📄 Fichiers Spécifiques au Module

- **`SHORTCODE_USAGE.md`** → Documentation des shortcodes (`[poke_hub_user_profile]`, `[poke_hub_friend_codes]`, `[poke_hub_vivillon]`)
- **`ULTIMATE_MEMBER_SETUP.md`** → Configuration et dépannage pour Ultimate Member
- **`CUSTOMIZATION.md`** → Comment personnaliser les listes (équipes, raisons) via les filtres WordPress
- **`SYNCHRONIZATION.md`** → Synchronisation avec subscription_accounts et Keycloak
- **`README_DATA_CENTRALIZATION.md`** → Architecture de centralisation des données
- **`FRIEND_CODES_PUBLIC_AND_IP.md`** → Codes amis en public (non connectés), colonne IP, admin, boutons Add/Update
- **[CACHE_AND_NGINX_PURGE.md](../CACHE_AND_NGINX_PURGE.md)** → Purge cache page (Nginx) et listes codes amis / Vivillon après sauvegarde admin ou front

### 🎨 Documentation Générique (CSS)

> Ces fichiers sont à la racine de `docs/` car ils sont réutilisables dans d'autres projets

- **`../CSS_RULES.md`** → **CSS à copier dans le thème** (fichier principal)
- **`../CSS_SYSTEM.md`** → Documentation du système de classes génériques `me5rine-lab-form-*`
- **`../PLUGIN_INTEGRATION.md`** → Guide pour utiliser les classes CSS dans d'autres plugins
- **`../POKEHUB_CSS_CLASSES.md`** → **Notices** : convention des couleurs (rouge / vert / orange / bleu) et utilisation détaillée dans les pages User Profiles (profil, codes amis, Vivillon)

## Réglages

Les réglages liés au module User Profiles se trouvent dans **Réglages > Poké HUB > Settings** :

- **General** : option « User Profiles Settings » pour la création automatique des pages (friend-codes, vivillon).
- **Sources** (onglet Sources) : la section **User Profiles Source** n’apparaît **que si le module User Profiles est actif**. Elle permet de définir l’**URL de base des liens vers les profils** (« Base URL for profile links »). Utile lorsque plusieurs sites partagent la même base de données et les mêmes utilisateurs : on indique l’URL du site où les profils sont affichés (ex. site principal) pour que les liens générés (codes amis, Vivillon, onglet Ultimate Member) pointent vers ce site. Si le champ est vide, l’URL du site actuel est utilisée.

## 🚀 Démarrage Rapide

1. **CSS** : Copier le contenu de `../CSS_RULES.md` (à la racine de `docs/`) dans votre thème
2. **Shortcode** : Utiliser `[poke_hub_user_profile]` dans vos templates
3. **Ultimate Member** : Suivre les instructions dans `ULTIMATE_MEMBER_SETUP.md`

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
