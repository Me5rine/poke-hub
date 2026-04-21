# Initialisation Select2

Ce document décrit surtout les méthodes d’initialisation Select2 héritées du **Me5rine LAB** (classe `.admin-lab-select2`, etc.). Le **plugin Poké HUB** réutilise les mêmes principes sur d’autres écrans, mais certains cas (ex. **types d’événement** du module Events) ont un **chemin d’enqueue et un script dédiés** : voir la section **[Poké HUB — Types d’événement](#pokehub-select2-event-types)** ci-dessous et la doc du module [events/README.md](./events/README.md#event-type-select2-admin).

## Table des matières

1. [🚀 Initialisation automatique (Recommandé)](#initialisation-automatique-recommandé)
2. [Chargement global dans l'admin](#chargement-global-dans-ladmin)
3. [Initialisation dans les Meta Boxes](#initialisation-dans-les-meta-boxes)
4. [Initialisation dans les filtres admin](#initialisation-dans-les-filtres-admin)
5. [Initialisation dans les pages front-end](#initialisation-dans-les-pages-front-end)
6. [Initialisation par classe CSS (ancienne méthode)](#initialisation-par-classe-css-ancienne-méthode)
7. [Configuration des options](#configuration-des-options)
8. [Empêcher l'initialisation sur certains selects](#empêcher-linitialisation-sur-certains-selects)
9. [Poké HUB — Types d’événement (module Events)](#pokehub-select2-event-types)

---

## 🚀 Initialisation automatique (Recommandé)

**Fichier** : `assets/js/admin-lab-select2-init.js`  
**Enqueue** : Automatique via `me5rine-lab.php` dans l'admin

C'est la méthode **recommandée** pour initialiser Select2 dans l'admin. Un script centralisé initialise automatiquement tous les selects ayant la classe `.admin-lab-select2`.

### Avantages

- ✅ **Automatique** : Aucun code JavaScript à écrire
- ✅ **Centralisé** : Une seule gestion pour tout le plugin
- ✅ **Dynamique** : Détecte les nouveaux selects ajoutés après le chargement
- ✅ **Configurable** : Utilise des attributs `data-*` pour la personnalisation
- ✅ **Compatible AJAX** : Support intégré pour la recherche AJAX

### Utilisation de base

Il suffit d'ajouter la classe `admin-lab-select2` à votre select :

```html
<select name="campaign_id" id="campaign_id" class="admin-lab-select2" data-placeholder="Choose a campaign...">
    <option value="">Choose...</option>
    <option value="1">Campaign 1</option>
    <option value="2">Campaign 2</option>
</select>
```

### Configuration via attributs data-*

Le script lit les attributs `data-*` pour configurer Select2 :

#### Placeholder personnalisé

```html
<select class="admin-lab-select2" data-placeholder="Sélectionnez une option...">
    <!-- options -->
</select>
```

#### Mode AJAX (recherche dynamique)

Pour activer la recherche AJAX, utilisez `data-ajax-action` :

```html
<select 
    class="admin-lab-select2" 
    data-placeholder="Search for a reward..."
    data-ajax-action="search_rewards"
    data-minimum-input-length="1"
    data-ajax-delay="250">
    <!-- options préchargées (optionnel) -->
</select>
```

**Attributs disponibles pour AJAX** :
- `data-ajax-action` : **Requis**. L'action WordPress AJAX (ex: `search_rewards`)
- `data-minimum-input-length` : Nombre minimum de caractères avant la recherche (défaut: `1`)
- `data-ajax-delay` : Délai en ms avant l'envoi de la requête (défaut: `250`)

**Exemple de callback AJAX WordPress** :

```php
add_action('wp_ajax_search_rewards', 'search_rewards_callback');
function search_rewards_callback() {
    $search = sanitize_text_field($_POST['search']);
    
    // Votre logique de recherche...
    $items = get_items_by_search($search);
    
    // Format requis par Select2
    $results = array();
    foreach ($items as $item) {
        $results[] = array(
            'id' => $item->ID,
            'text' => $item->post_title
        );
    }
    
    wp_send_json($results);
}
```

**Format de réponse AJAX** :

Le callback doit retourner soit :
- Un tableau d'objets : `[{id: 1, text: 'Item 1'}, {id: 2, text: 'Item 2'}]`
- Un objet avec propriété `results` : `{results: [{id: 1, text: 'Item 1'}]}`

#### Select multiple

Pour un select multiple, ajoutez simplement l'attribut `multiple` :

```html
<select class="admin-lab-select2" data-placeholder="Select countries..." multiple>
    <option value="fr">France</option>
    <option value="us">United States</option>
</select>
```

Le script détecte automatiquement et configure `closeOnSelect: false`.

### Configuration par défaut

Le script utilise ces valeurs par défaut :
- `width: 'resolve'` - S'ajuste au conteneur parent
- `allowClear: true` - Affiche un bouton pour effacer la sélection
- `placeholder` - Récupéré depuis `data-placeholder` ou "Choose..."
- `closeOnSelect: false` - Si le select est multiple

### Notes importantes

- Le script vérifie automatiquement que Select2 est chargé
- Il ignore les selects avec la classe `no-select2`
- Il détecte les nouveaux selects ajoutés dynamiquement via `MutationObserver`
- Si vous avez besoin d'une configuration très spécifique, utilisez les autres méthodes ci-dessous

---

## Chargement global dans l'admin

**Fichier** : `me5rine-lab.php`

Select2 est chargé globalement pour toute l'administration WordPress via un hook `admin_enqueue_scripts`. Cette méthode garantit que Select2 est disponible sur toutes les pages d'administration.

```php
// Charger Select2 dans l'administration WordPress
function load_select2_admin_scripts() {
    wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
    wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true);
    // Script d'initialisation automatique pour les selects avec la classe .admin-lab-select2
    wp_enqueue_script('admin-lab-select2-init', plugin_dir_url(__FILE__) . 'assets/js/admin-lab-select2-init.js', array('jquery', 'select2-js'), ME5RINE_LAB_VERSION, true);
}
add_action('admin_enqueue_scripts', 'load_select2_admin_scripts');
```

**Note** : Le script `admin-lab-select2-init.js` est automatiquement chargé et initialise tous les selects avec la classe `.admin-lab-select2`. Vous n'avez plus besoin d'écrire de code JavaScript pour ces selects.

**Avantages** :
- Chargé une seule fois pour toute l'admin
- Disponible partout sans rechargement
- Gestion centralisée

**Inconvénients** :
- Charge Select2 même si pas utilisé sur la page

---

## Initialisation dans les Meta Boxes

**Fichier** : `modules/giveaways/admin-filters/giveaways-meta-boxes.php`

Cette méthode utilise un script inline directement dans le HTML de la meta box. Elle est idéale pour des champs spécifiques avec des configurations personnalisées.

### Exemple : Champ avec recherche AJAX

```php
<script>
jQuery(document).ready(function($){
    $('#giveaway_partner_id').select2({
        placeholder: '<?php _e("Search Partner...", "me5rine-lab"); ?>',
        allowClear: true,
        ajax: {
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    action: 'search_partners',
                    search: params.term
                };
            },
            processResults: function(data) {
                return { results: data };
            },
            cache: true
        },
        minimumInputLength: 1
    });
});
</script>
```

**Points importants** :
- Utilise `jQuery(document).ready()` pour s'assurer que le DOM est prêt
- Utilise `ajaxurl` (variable WordPress globale) pour les requêtes AJAX
- `minimumInputLength: 1` oblige l'utilisateur à taper au moins 1 caractère avant la recherche
- `delay: 250` évite trop de requêtes pendant la frappe

### Exemple : Deux champs Select2 dans la même meta box

```php
<script>
jQuery(document).ready(function($){
    // Premier champ
    $('#giveaway_partner_id').select2({
        placeholder: '<?php _e("Search Partner...", "me5rine-lab"); ?>',
        allowClear: true,
        ajax: { /* ... */ },
        minimumInputLength: 1
    });

    // Deuxième champ
    $('#rafflepress_campaign').select2({
        placeholder: '<?php _e("Search RafflePress campaigns...", "me5rine-lab"); ?>',
        allowClear: true,
        ajax: { /* ... */ },
        minimumInputLength: 1
    });
});
</script>
```

---

## Initialisation dans les filtres admin

**Fichier** : `modules/giveaways/admin-filters/giveaways-filters.php`

Cette méthode utilise `wp_add_inline_script()` pour ajouter le code JavaScript après l'enqueue de Select2. Elle est idéale pour les filtres de pages de liste (edit.php).

### Exemple complet

```php
add_action('admin_enqueue_scripts', 'enqueue_admin_scripts');
function enqueue_admin_scripts($hook) {
    // Vérifier que nous sommes sur la bonne page
    if ($hook !== 'edit.php' || get_current_screen()->post_type !== 'giveaway') return;

    // Enqueue Select2
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js', array('jquery'), null, true);
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css', array(), null);

    // Ajouter le script d'initialisation inline
    wp_add_inline_script('select2-js', "
        jQuery(document).ready(function($) {
            $('#filter_reward').select2({
                placeholder: '" . __('Search for a reward...', 'me5rine-lab') . "',
                allowClear: true,
                ajax: {
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'search_rewards',
                            search: params.term
                        };
                    },
                    processResults: function(data) {
                        return { results: data };
                    },
                    cache: true
                },
                minimumInputLength: 1
            });
        });
    ");
}
```

**Points importants** :
- Vérifie toujours le hook et le post type avant d'enqueue
- Utilise `wp_add_inline_script()` pour garantir que le script s'exécute après le chargement de Select2
- Les traductions PHP doivent être échappées dans les chaînes JavaScript

---

## Initialisation dans les pages front-end

**Fichier** : `assets/js/giveaway-form-campaign.js`

Cette méthode utilise un fichier JavaScript externe. L'enqueue se fait dans un fichier PHP (shortcode ou fonction), et l'initialisation dans le fichier JS.

### Étape 1 : Enqueue dans le PHP

**Fichier** : `modules/giveaways/shortcodes/giveaways-shortcodes.php`

```php
function enqueue_campaign_form_assets() {
    static $enqueued = false;
    if ($enqueued) return; // Éviter les doublons
    
    $base_url = plugin_dir_url(dirname(dirname(__DIR__))) . 'assets/';
    
    // Charger Select2
    wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
    wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', ['jquery'], null, true);
    
    // Charger le script avec Select2 comme dépendance
    wp_enqueue_script('admin-lab-campaign-form', $base_url . 'js/giveaway-form-campaign.js', ['jquery', 'select2-js'], ME5RINE_LAB_VERSION, true);
    
    // Localiser les traductions si nécessaire
    wp_localize_script('admin-lab-campaign-form', 'mlab_i18n', [
        'selectCountries' => __('Select countries...', 'me5rine-lab'),
        'searchCountries' => __('Search a country...', 'me5rine-lab'),
    ]);
    
    $enqueued = true;
}
```

### Étape 2 : Initialisation dans le JS

**Fichier** : `assets/js/giveaway-form-campaign.js`

```javascript
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier que jQuery et Select2 sont disponibles
    const countryField = document.getElementById('eligible_countries');
    if (countryField && typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        jQuery(countryField).select2({
            placeholder: mlab_i18n.selectCountries || 'Select countries...',
            allowClear: true,
            width: '100%',
            closeOnSelect: false, // Ne pas fermer après sélection (pour sélection multiple)
            language: {
                noResults: function() {
                    return mlab_i18n.searchCountries || 'No results found';
                },
                searching: function() {
                    return mlab_i18n.searchCountries || 'Searching...';
                }
            }
        });
    }
});
```

**Points importants** :
- Utilise `document.addEventListener('DOMContentLoaded')` au lieu de `jQuery(document).ready()` en JavaScript vanilla
- Vérifie toujours que jQuery et Select2 sont disponibles avant l'initialisation
- Utilise `wp_localize_script()` pour passer les traductions au JavaScript
- La dépendance `['jquery', 'select2-js']` garantit l'ordre de chargement

---

## Initialisation par classe CSS (ancienne méthode)

**Fichier** : `modules/marketing/admin/marketing-admin-ui.php`

⚠️ **Cette méthode est dépréciée**. Utilisez plutôt l'[initialisation automatique](#initialisation-automatique-recommandé) qui ne nécessite aucun code JavaScript.

Cette ancienne méthode initialisait Select2 manuellement via un script inline. Elle est conservée pour référence mais n'est plus nécessaire :

```html
<select name="campaign_id" id="campaign_<?php echo esc_attr($zone_key); ?>" class="admin-lab-select2 campaign-zone-select" data-placeholder="<?php esc_attr_e('Choose a campaign…', 'me5rine-lab'); ?>">
    <!-- options -->
</select>

<!-- ❌ Plus nécessaire avec l'initialisation automatique -->
<script>
jQuery(document).ready(function($) {
    $('.admin-lab-select2').select2({
        width: 'resolve',
        allowClear: true,
        placeholder: function() {
            return $(this).data('placeholder') || 'Choose a campaign…';
        }
    });
});
</script>
```

**Migration** : Supprimez simplement le `<script>` et ajoutez seulement la classe `admin-lab-select2` au select. L'initialisation automatique fera le reste.

---

## Configuration des options

### Options courantes

| Option | Type | Description | Exemple |
|--------|------|-------------|---------|
| `placeholder` | string/function | Texte affiché quand aucune option n'est sélectionnée | `'Select...'` ou `function() { return $(this).data('placeholder'); }` |
| `allowClear` | boolean | Affiche un bouton pour effacer la sélection | `true` |
| `width` | string | Largeur du select | `'100%'`, `'resolve'`, `'auto'` |
| `minimumInputLength` | number | Nombre minimum de caractères pour lancer une recherche AJAX | `1` |
| `closeOnSelect` | boolean | Ferme le dropdown après sélection (important pour multiple) | `false` |
| `delay` | number | Délai en ms avant d'envoyer la requête AJAX | `250` |

### Configuration AJAX complète

```javascript
ajax: {
    url: ajaxurl,                    // URL de l'endpoint AJAX WordPress
    type: 'POST',                    // Méthode HTTP
    dataType: 'json',                // Type de données attendu
    delay: 250,                      // Délai avant requête (ms)
    data: function(params) {         // Fonction qui construit les paramètres
        return {
            action: 'search_items',  // Action WordPress AJAX
            search: params.term      // Terme de recherche
        };
    },
    processResults: function(data) { // Fonction qui formate les résultats
        return { 
            results: data            // Format: [{id: 1, text: 'Item 1'}, ...]
        };
    },
    cache: true                      // Activer le cache des résultats
}
```

### Configuration de traduction (i18n)

```javascript
language: {
    noResults: function() {
        return 'Aucun résultat trouvé';
    },
    searching: function() {
        return 'Recherche en cours...';
    },
    inputTooShort: function(args) {
        return 'Tapez au moins ' + args.minimum + ' caractères';
    }
}
```

### Format des données AJAX

Les résultats AJAX doivent être au format suivant :

```php
// Dans votre callback WordPress AJAX
function search_items_callback() {
    $search = sanitize_text_field($_POST['search']);
    
    // Votre logique de recherche...
    $items = get_items_by_search($search);
    
    // Format requis par Select2
    $results = array();
    foreach ($items as $item) {
        $results[] = array(
            'id' => $item->ID,
            'text' => $item->post_title
        );
    }
    
    wp_send_json($results);
}
add_action('wp_ajax_search_items', 'search_items_callback');
```

---

## Empêcher l'initialisation sur certains selects

**Fichier** : `assets/js/giveaways-user-participation.js`

Pour empêcher Select2 de s'initialiser automatiquement sur certains selects (par exemple, si Select2 est chargé globalement), utilisez une classe spéciale et détruisez Select2 si nécessaire.

### HTML

```html
<select id="status_filter" name="status_filter" class="me5rine-lab-form-select me5rine-lab-filter-select no-select2">
    <!-- options -->
</select>
```

### JavaScript

```javascript
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        // Fonction pour détruire Select2 sur les selects avec la classe no-select2
        function destroySelect2OnFilters() {
            jQuery('.no-select2').each(function() {
                const $select = jQuery(this);
                // Vérifier si Select2 est initialisé
                if ($select.data('select2')) {
                    $select.select2('destroy');
                }
                // Retirer les classes et attributs ajoutés par Select2
                $select.removeClass('select2-hidden-accessible')
                      .removeAttr('data-select2-id')
                      .removeAttr('tabindex')
                      .removeAttr('aria-hidden');
            });
        }

        // Détruire immédiatement si Select2 est déjà chargé
        destroySelect2OnFilters();

        // Détruire après des délais au cas où Select2 s'initialise après le chargement
        setTimeout(destroySelect2OnFilters, 100);
        setTimeout(destroySelect2OnFilters, 500);
        setTimeout(destroySelect2OnFilters, 1000);

        // Observer les mutations DOM pour détecter si Select2 est ajouté dynamiquement
        const observer = new MutationObserver(function(mutations) {
            destroySelect2OnFilters();
        });

        const filtersContainer = document.querySelector('.me5rine-lab-filters');
        if (filtersContainer) {
            observer.observe(filtersContainer, {
                childList: true,
                subtree: true,
                attributeFilter: ['class', 'data-select2-id']
            });
        }
    }
});
```

**Points importants** :
- Ajoutez la classe `no-select2` aux selects qui ne doivent pas utiliser Select2
- Utilisez un `MutationObserver` pour détecter les initialisations dynamiques
- Nettoyez tous les attributs ajoutés par Select2 lors de la destruction

---

<a id="pokehub-select2-event-types"></a>

## Poké HUB — Types d’événement (module Events)

Ce cas **ne** repose **pas** sur la classe `.admin-lab-select2` (script Me5rine LAB décrit plus haut). Il est géré **dans le plugin Poké HUB** pour des listes d’options très longues (taxonomie distante `event_type`).

| Élément | Fichier / remarque |
|--------|---------------------|
| Initialisation JS | `assets/js/pokehub-special-events-admin.js` — `initEventTypeSelect2` / `initAllEventTypeSelect2` |
| Enqueue (admin) | `modules/events/events.php` — Select2 **4.1.0-rc.0** (jsDelivr) + script ci-dessus sur **Poké HUB → Events** et sur **`post.php` / `post-new.php`** |
| Champs couverts | `#event_type` (`pokehub-event-type-select2`), filtre liste `#filter-by-event-type`, tout `<select>` dont `name` ou `id` contient `event_type`, classes `pokehub-event-type-select` ; observer sur `#admin_lab_event_box` si DOM dynamique |
| Ancien code | Plus d’`wp_add_inline_script('select2', …)` uniquement pour le filtre : tout est centralisé dans le JS du plugin |

Documentation complète (comportement, obsolète, `PokeHubSpecialEvents`, `wp.media`) : **[events/README.md](./events/README.md#event-type-select2-admin)**.

---

## Checklist pour implémenter Select2

Lorsque vous ajoutez Select2 à un nouveau module :

### Pour l'admin (méthode recommandée)

- [ ] **Ajouter la classe** : Ajoutez `admin-lab-select2` à votre select
- [ ] **Configurer si nécessaire** : Utilisez `data-placeholder`, `data-ajax-action`, etc.
- [ ] **Si AJAX** : Créer le callback WordPress AJAX avec le bon format de données
- [ ] **Tester** : Vérifier que Select2 fonctionne automatiquement

**C'est tout !** Select2 est déjà chargé globalement dans l'admin et s'initialise automatiquement.

### Pour le front-end ou cas spéciaux

- [ ] **Enqueue Select2** : Si front-end, enqueue les styles et scripts
- [ ] **Choisir la méthode d'initialisation** : Script inline, fichier JS, ou wp_add_inline_script
- [ ] **Vérifier les dépendances** : S'assurer que jQuery est disponible
- [ ] **Configurer les options** : Placeholder, allowClear, width, etc.
- [ ] **Si traductions** : Utiliser `wp_localize_script()` pour i18n
- [ ] **Tester** : Vérifier que Select2 fonctionne et que les styles sont appliqués
- [ ] **Gérer les exceptions** : Ajouter `no-select2` si nécessaire

---

## Versions utilisées

- **Select2 CSS** : `4.0.13` (CDN : `https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css`)
- **Select2 JS** : `4.0.13` (CDN : `https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js`)

**Note** : Certains modules utilisent aussi `https://cdn.jsdelivr.net/npm/select2@4.0.13/` qui est équivalent.

**Poké HUB — module Events** (et quelques autres écrans du même plugin) : **Select2 `4.1.0-rc.0`** via jsDelivr, tel qu’enregistré dans `modules/events/events.php` (voir section [Poké HUB — Types d’événement](#pokehub-select2-event-types) ci-dessus).

---

## Références

- [Documentation officielle Select2](https://select2.org/)
- [Exemples dans le projet](./SELECT2_EXAMPLES.md) (si créé)
- [Styles CSS Select2](./ADMIN_CSS.md#select2)

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md)*
