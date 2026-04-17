# Traductions (i18n) – Poké HUB

Le plugin Poké HUB est préparé pour l’internationalisation : toutes les chaînes affichées à l’utilisateur utilisent les fonctions de traduction WordPress, avec **l’anglais comme langue de base** dans le code.

## Text domain et chemin

- **Text domain :** `poke-hub`
- **Domain path :** `/languages` (relatif au répertoire du plugin)
- **Chargement :** `load_plugin_textdomain('poke-hub', false, dirname(plugin_basename(__FILE__)) . '/languages')` sur le hook `plugins_loaded`

Les chaînes sont enregistrées avec le domaine `poke-hub` dans tout le plugin (PHP et blocs Gutenberg).

## Langue de base : anglais

Dans le code, les chaînes passées à `__()`, `_e()`, `esc_html__()`, `esc_attr_e()`, etc. sont **en anglais**. Exemples :

- Menus : "Quests", "Settings", "Collection management"
- Blocs : titres et descriptions dans les `block.json` en anglais (Event Dates, Wild Pokémon, Collection Challenges, etc.)
- Messages : "Collection created.", "Settings saved.", "No changes."

Pour afficher l’interface en français (ou autre langue), il faut fournir les fichiers de traduction dans le dossier `languages/` du plugin.

## Fichiers de traduction

### PHP (admin et front)

1. Créer ou mettre à jour un fichier `.po` pour la locale (ex. `poke-hub-fr_FR.po`).
2. Compiler en `.mo` (avec Poedit, msgfmt, ou un outil WP comme Loco Translate / WP-CLI).
3. Placer les fichiers dans :
   - `wp-content/plugins/poke-hub/languages/`
   - Noms attendus : `poke-hub-fr_FR.po` et `poke-hub-fr_FR.mo` (ou selon la locale active).

### Blocs Gutenberg

Les métadonnées des blocs (titre, description dans `block.json`) sont en anglais. Pour les traduire dans l’éditeur :

- Soit utiliser les traductions JavaScript du text domain `poke-hub` (fichiers JSON de traduction pour les blocs, si générés).
- Soit s’appuyer sur les mécanismes WordPress pour les métadonnées de blocs et le même domaine `poke-hub`.

Les chaînes affichées côté PHP (render, shortcodes, admin) sont déjà dans le système de traduction PHP classique.

## Où sont les chaînes

- **Includes :** `includes/` (settings, admin-ui, content, helpers)
- **Modules :** chaque module (quests, collections, blocks, events, bonus, pokemon, games, user-profiles, eggs, etc.) utilise `__()`, `_e()`, `esc_html_e()`, etc. avec le text domain `poke-hub`
- **Collections (vue grille)** : libellés du filtre d’affichage et du message d’aide dans `modules/collections/public/collections-shortcode.php` (sources en anglais, ex. *Show in grid*, *Owned*, *For trade*, *Missing*, *Select at least one status…*). Le sens **métier** en français (possédé, à l’échange, manquant, filtre **Afficher dans la grille**) est aligné sur **docs/COLLECTIONS_MODULE.md** (section *Statuts d’une entrée*).
- **Blocs :** `modules/blocks/blocks/*/block.json` (title, description en anglais) ; le rendu PHP des blocs utilise aussi les fonctions de traduction

## Bonnes pratiques pour le code

- Toujours utiliser le **text domain** `'poke-hub'` dans les appels de traduction.
- Mettre la **chaîne en anglais** comme premier argument (langue par défaut).
- Pour les chaînes avec contexte (ex. singulier/pluriel, libellé d’admin), utiliser `_x()` ou `_n()` avec le même domain.

Exemple :

```php
echo esc_html__('Collection management', 'poke-hub');
printf(_n('%s result', '%s results', $total, 'poke-hub'), number_format_i18n($total));
```

## Voir aussi

- [Charte rédactionnelle de la documentation](./REDACTION.md) (langue, citations de chaînes sources, liens)
- [Documentation WordPress sur l’internationalisation](https://developer.wordpress.org/plugins/internationalization/)
- Dossier du plugin : `languages/` (à créer si besoin pour y déposer les .po/.mo)

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md)*
