# Charte rédactionnelle — documentation Poké HUB

Ce document fixe les **règles communes** pour toute la documentation du dépôt (`docs/`, `README.md` racine, fichiers `*.md` dans les modules lorsqu’ils servent de référence). L’objectif est un ensemble **clair, précis et cohérent** (fond et forme).

## Public et rôle des documents

- **Développeurs / intégrateurs** : structure du plugin, CSS, hooks, shortcodes, données, dépannage.
- **Contributeurs** : où placer une nouvelle page, comment nommer et lier.

L’**index à jour** des fichiers est **[README.md](./README.md)** dans ce dossier. Toute nouvelle page significative y est ajoutée (section appropriée + lien rapide si utile).

## Langue

| Contexte | Règle |
|----------|--------|
| Prose d’explication | **Français** (sauf citation d’interface ou de code). |
| Chaînes affichées dans le code (i18n) | **Anglais** dans les sources PHP/JS ; le sens métier en français pour la doc métier se trouve dans les fiches modules ou **[TRANSLATION.md](./TRANSLATION.md)**. |
| Identifiants techniques | Tels quels : `poke_hub_*`, noms de tables, slugs de modules, attributs HTML (`data-status`), valeurs d’enum (`owned`, `for_trade`). |

## Nom du produit et du plugin

- **Produit / interface** : **Poké HUB** (accent sur le « é »).
- **Slug WordPress / dossier** : `poke-hub` (tiret).
- **Préfixe fonctions PHP** : `poke_hub_` (underscore).

Ne pas utiliser comme nom du produit les formes incorrectes `PokeHub`, `Poke Hub` ou `pokehub` (tout en bas de casse) — seulement **Poké HUB** en prose ; `poke-hub` (slug) et `poke_hub_` (préfixe PHP) dans le code. Exception documentée : chemin de clone `pokehub` dans le README racine.

### Menus d’administration WordPress

Dans la doc en **français**, le menu principal WordPress est noté **Réglages** (équivalent `fr_FR` de *Settings*). L’entrée du plugin est **Poké HUB** ; les sous-pages reprennent souvent les libellés **anglais** du code source (*Settings*, *General*, *Sources*, *Dashboard*, etc.) tant que les fichiers `.po` ne les traduisent pas.

## Liens et chemins

- **Liens internes** : chemins **relatifs** depuis le fichier courant (ex. `./COLLECTIONS_MODULE.md`, `./blocks/README.md`).
- **Références à des fichiers du plugin** : chemin depuis la **racine du plugin** (ex. `modules/collections/public/collections-shortcode.php`, `assets/css/poke-hub-collections-front.css`).
- **Ancres** : lorsqu’une section existe, préférer un lien avec ancre (`./user-profiles/README_USER_PROFILES.md#réglages`).

## Structure type d’une page

1. **Titre unique (`#`)** — nom explicite du sujet.
2. **Introduction courte** (optionnelle) — ce que couvre la page et les prérequis.
3. **Sections (`##`, `###`)** — une idée par section ; sous-parties si le texte dépasse ~2 écrans.
4. **Voir aussi** (optionnel) — liens vers doc transversale (CSS, traduction, organisation).

Éviter les doublons massifs de contenu : renvoyer à la **source de vérité** (fichier de code ou doc canonique) et résumer.

## Listes, tableaux, code

- **Listes** : tiret `-` ; une idée par item ; phrases complètes ou syntagmes homogènes dans une même liste.
- **Tableaux** : en-têtes clairs ; colonnes alignées sur un même type d’information (ex. « Valeur technique » / « Rôle »).
- **Blocs de code** : langue indiquée quand ce n’est pas évident (`php`, `bash`, `css`). Les extraits restent **vérifiables** (chemins et noms de hooks réels).

## Terminologie transversale

- **Module** : fonctionnalité activable (réglages Poké HUB) ; la liste officielle est dans `includes/settings/settings-modules.php` (voir **[ORGANISATION.md](./ORGANISATION.md)**).
- **Bloc Gutenberg** : préfixe namespace `pokehub/` dans la doc blocs.
- **Tables de contenu** : scope `content_source`, préfixe aligné sur la source Pokémon (voir README dossier `docs/` et **BONUS** / **CONTENT_BLOCKS**).

En cas de doute sur un terme métier (collections, formes, événements), privilégier les **fiches dédiées** déjà indexées dans **[README.md](./README.md)** plutôt que de redéfinir à plusieurs endroits.

## Titres et mise en forme légère

- **Titres Markdown** : pas d’information critique uniquement dans le titre (le détail est dans le corps).
- **Gras** : termes clés, libellés d’interface en français documenté, noms de menu.
- **Italique** : noms d’écran ou citation de chaîne source anglaise (*Show in grid*, *For trade*).
- **Emojis** : optionnels ; utilisés dans certains guides historiques (ex. organisation). **Les nouvelles pages** peuvent s’en passer pour rester sobres ; ne pas multiplier les pictogrammes dans un même titre.

## Cohérence avec l’existant

- **CSS** : système commun `me5rine-lab-*` + classes `pokehub-*` — références **THEME_FRONT_CSS.md** (thème vs plugin, ordre de chargement), **FRONT_CSS.md**, **CSS_SYSTEM.md**, **POKEHUB_CSS_CLASSES.md**, et fiches thème par module (ex. `modules/collections/COLLECTIONS_THEME_CSS.md`).
- **Traduction** : **TRANSLATION.md** ; ne pas dupliquer toute la politique i18n dans chaque module.

## Révision

Lors d’une évolution fonctionnelle :

1. Mettre à jour la **doc canonique** du module concerné.
2. Ajuster **docs/README.md** si nouveau fichier ou changement d’emplacement.
3. Si le changement touche les chaînes utilisateur : **TRANSLATION.md** ou fichier `.po` selon le process du projet.
4. Pour (re)générer les pieds de page index + charte sur les `.md` : `python scripts/harmonize-doc-footers.py` à la racine du plugin.

---

*Index de la documentation : [README du dossier docs](README.md) · [Traductions](TRANSLATION.md)*

*Dernière intention de cette charte : garder la documentation navigable, peu redondante, et alignée sur le code réel du dépôt.*
