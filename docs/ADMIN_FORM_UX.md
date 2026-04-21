# Formulaires admin Poké HUB — redirections et retour à la liste

Ce document décrit le **comportement unifié** des écrans d’édition sous **Poké HUB** (Pokémon et sous-onglets, quêtes, œufs, événements, profils, bonus) : après une sauvegarde réussie, l’utilisateur est renvoyé vers **la liste** de la page concernée ; un lien **Retour à la liste** visible est affiché en haut des formulaires d’édition.

## Redirection après sauvegarde

Règle métier : **enregistrement réussi → liste** (sans rester sur le formulaire ni rouvrir l’élément via `edit` / `action=edit` dans l’URL, sauf cas d’erreur de validation où l’écran d’édition peut rester affiché).

Zones concernées (implémentation dans le code du plugin) :

- **Pokémon** : les handlers existants redirigent déjà vers la liste de l’onglet (`page` + `ph_section` + message `ph_msg` si besoin).
- **Œufs** : après création ou mise à jour d’un pool, redirection vers `poke-hub-eggs` avec `ph_msg` uniquement (plus de `edit=` dans l’URL).
- **Quêtes** : sauvegarde d’un ensemble → onglet Quêtes avec `updated=1` ou messages `quest_set_created` / `quest_set_exists` / `quest_global_exists` ; mise à jour d’une catégorie → onglet **Catégories de quêtes** avec `updated=1`.
- **Bonus** (types) : redirection vers la liste avec `ph_bonus_msg` (sans `edit=` après succès).
- **Profils utilisateurs** : redirection vers la liste avec `profile_updated=1` (filtres d’URL conservés quand ils sont présents).
- **Événements** (spéciaux / Pass GO) : la redirection existante via `pokehub_special_events_redirect_after_save()` reste orientée **liste** (`poke-hub-events`).

## Lien « Retour à la liste »

### Helpers et styles

- **`poke_hub_admin_back_to_list_bar( string $url, ?string $label = null )`** — affiche une barre avec un bouton secondaire (`button button-secondary`) et la classe **`pokehub-admin-back-bar`**. Fichier : `includes/admin-ui.php`.
- **`poke_hub_admin_enqueue_common_styles( string $hook )`** — enregistre un style inline minimal pour `.pokehub-admin-back-bar` sur les écrans admin dont le hook contient `poke-hub` (même fichier).

Libellé par défaut du lien : chaîne traduite **« Back to list »** (`poke-hub`). Un second argument permet un libellé personnalisé (ex. **« Back to Regions »** sur le formulaire des régions géographiques).

### Événements — URL de liste avec filtres

Pour que le retour reprenne les filtres de la liste (statut, source, type, recherche, pagination, tri), utiliser :

- **`pokehub_events_admin_list_url()`** — `modules/events/functions/events-admin-helpers.php` (chargé avant les formulaires admin événements).

## Fichiers de référence (non exhaustif)

| Zone | Exemples de fichiers |
|------|----------------------|
| Helpers communs | `includes/admin-ui.php` |
| Formulaires Pokémon | `modules/pokemon/admin/forms/*.php` |
| Œufs | `modules/eggs/admin/eggs-admin.php` |
| Quêtes | `modules/quests/admin/quests-admin.php` |
| Bonus | `modules/bonus/admin/bonus-types-admin.php` |
| Profils | `modules/user-profiles/admin/user-profiles-admin.php`, `modules/user-profiles/admin/forms/user-profile-form.php` |
| Événements (liste URL) | `modules/events/functions/events-admin-helpers.php` |
| Événements (formulaires) | `modules/events/admin/forms/events-admin-special-events-form.php`, `events-admin-go-pass-form.php` ; **Select2 « type d’événement »** (liste + metabox article) : `assets/js/pokehub-special-events-admin.js`, enqueue `modules/events/events.php` — [events/README.md](./events/README.md#event-type-select2-admin) |
| Régions géographiques (admin Pokémon / réglages) | `includes/settings/forms/regional-region-form.php` |

Pour le **CSS générique** des formulaires admin (classes `admin-lab-*`, variables), voir [ADMIN_CSS.md](./ADMIN_CSS.md).

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md)*
