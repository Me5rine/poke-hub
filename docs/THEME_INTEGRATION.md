# Intégration des styles CSS dans le thème WordPress

## Référence actuelle (Me5rine Lab + Poké HUB)

Pour le déploiement **Me5rine Lab** (thème enfant) avec Poké HUB, la procédure « copier FRONT_CSS.md à la main » a été remplacée par une **pilotage centralisé côté thème** (fichiers `css/poke-hub/`, ordre d’enqueue, filtre sur le plugin).

Lisez d’abord : **[THEME_FRONT_CSS.md](./THEME_FRONT_CSS.md)** — la section **« En bref — règle unique »** donne le tableau thème / plugin, le nom du filtre et un schéma ; le reste du fichier détaille l’ordre de cascade et les exceptions.

Côté thème, un court index du dossier CSS Poké HUB se trouve dans `css/poke-hub/README.md` (dépôt **me5rine-lab**).

## Autres thèmes (pas le bundle Me5rine)

Si vous n’utilisez **pas** le dépôt du thème enfant (ou intégration partielle) :

1. Vous pouvez **copier** le contenu des blocs CSS documentés dans **[FRONT_CSS.md](./FRONT_CSS.md)** (éléments front généraux) et **[CSS_RULES.md](./CSS_RULES.md)** (formulaires) vers votre thème, comme décrit historiquement ci‑dessous.
2. Si vous enfilez l’équivalent du lot front **dans le thème**, ajoutez `add_filter( 'poke_hub_load_default_plugin_front_css', '__return_false' );` pour éviter la double charge (voir [THEME_FRONT_CSS.md](./THEME_FRONT_CSS.md)).
3. Vérifiez l’**ordre** : thème de base → votre couche de composants → surcharges spécifiques aux modules Poké HUB.

### Méthode par fichier CSS dédié (générique)

1. Créer un fichier dans le thème, par ex. `assets/css/poke-hub-front-custom.css` (ou réutiliser un bundle existant).
2. Y coller le **CSS** issu de `FRONT_CSS.md` / `CSS_RULES.md` (uniquement le contenu des blocs de code, pas le markdown autour).
3. Enqueue dans `functions.php` :

```php
function mon_theme_enqueue_poke_hub_compat_styles() {
    wp_enqueue_style(
        'mon-theme-poke-hub-compat',
        get_stylesheet_directory_uri() . '/assets/css/poke-hub-front-custom.css',
        [ 'hello-elementor' ], // adapter les dépendances à votre thème parent
        wp_get_theme()->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', 'mon_theme_enqueue_poke_hub_compat_styles', 20 );
```

4. Surcharger les variables (`:root` ou le sélecteur kit Elementor) **après** ce fichier si besoin.

### Intégration dans `style.css` (générique)

Vous pouvez coller le contenu issu de `FRONT_CSS.md` en fin de `style.css` du thème. Contrôlez toutefois que l’**ordre** reste cohérent (parent puis enfant) et, si le plugin enque encore des feuilles front, tranchez avec le filtre `poke_hub_load_default_plugin_front_css` (voir [THEME_FRONT_CSS.md](./THEME_FRONT_CSS.md)).

## Personnalisation des variables

Les variables unifiées (`--me5rine-lab-*`, etc.) sont décrites dans **FRONT_CSS.md** et, pour Me5rine Lab, générées / reliées à Elementor dans le `functions.php` du thème. Les surcharges doivent venir **après** le chargement de la couche de base.

## Vérification

- Inspecter le document : feuilles dans l’ordre attendu (parent → thème enfant → Poké HUB).
- Pas de règles en double pour les mêmes handles si le filtre d’exclusion côté plugin est activé.

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md) · [CSS front thème / plugin : THEME_FRONT_CSS.md](./THEME_FRONT_CSS.md)*
