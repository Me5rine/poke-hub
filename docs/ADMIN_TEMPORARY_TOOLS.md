# Outils temporaires (admin)

La page **Poké HUB → Temporary tools** (`admin.php?page=poke-hub-tools`) regroupe des **scripts ponctuels** (imports, migrations) : Pokekalos, imports Fandom récurrents (heures de raids / vedette), import Max Monday, etc. Le code vit surtout dans `includes/admin-tools.php` et, pour les événements, dans `modules/events/admin/`.

## Activer ou masquer le sous-menu

- **Réglage** : **Poké HUB → Settings → General** → section **Temporary tools (admin)** — case **Show Temporary tools** (option WordPress `poke_hub_temporary_tools_enabled`, **activée par défaut**).
- Si la case est **décochée** : le sous-menu **Temporary tools** disparaît ; l’accès direct à l’URL de la page renvoie une erreur **403** avec message ; les **POST** et imports **admin-post** liés à cette page (Fandom récurrent, Max Monday) sont **ignorés ou redirigés** vers les réglages pour éviter un traitement « orphelin ».

## Onglets sur la page

L’écran est découpé en **onglets** (paramètre d’URL `tab=…`) pour séparer les outils :

| Onglet (`tab`)   | Contenu principal |
|------------------|-------------------|
| `pokekalos`      | Import des dates de sortie Pokekalos (voir [pokemon/POKEKALOS_RELEASE_DATES.md](./pokemon/POKEKALOS_RELEASE_DATES.md)). |
| `raid-hour`      | Import Fandom — heure de raids (module **events** actif). |
| `spotlight-hour` | Import Fandom — heure vedette (module **events** actif). |
| `max-monday`     | Import Fandom — Lundi Max (module **events** actif). |

Les onglets événements ne s’affichent que si le module **events** est actif et que les helpers correspondants sont chargés.

## Fichiers et helpers utiles

| Élément | Rôle |
|---------|------|
| `includes/admin-tools.php` | Sous-menu, page, onglets, import Pokekalos depuis l’admin ; `poke_hub_temporary_tools_enabled()`, `poke_hub_admin_tools_url($tab)`. |
| `includes/settings/settings.php` | Enregistrement de l’option `poke_hub_temporary_tools_enabled`. |
| `includes/settings/tabs/settings-tab-general.php` | Case à cocher dans l’onglet **General**. |
| `poke-hub.php` | Fonction `poke_hub_admin_pages()` : retire `poke-hub-tools` de la liste utilisée pour le surlignage du menu parent lorsque l’option est désactivée. |
| `modules/events/admin/events-admin-fandom-recurring-imports.php` | Formulaires / redirects vers la page outils (avec `tab` cohérent). |
| `modules/events/admin/events-admin-max-monday-import.php` | Idem pour Lundi Max. |

## Évolution / retrait du code

Les imports Pokekalos restent aussi disponibles en **CLI** (`scripts/import-pokekalos-release-dates.php`) sans passer par l’admin. Retirer complètement la fonctionnalité « outils temporaires » du plugin impliquerait de supprimer ou de conditionner davantage de code (menu, handlers, modules events) — ce n’est plus documenté comme un simple retrait de `admin-tools.php` seul.

---

*Index de la documentation : [README du dossier docs](./README.md) · [Charte rédactionnelle](./REDACTION.md)*
