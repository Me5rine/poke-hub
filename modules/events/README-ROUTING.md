# Système de routing dynamique pour les événements spéciaux

## Vue d'ensemble

Ce système permet d'afficher les événements spéciaux avec des URLs personnalisées du type :
```
https://votre-site.com/pokemon-go/events/slug-evenement-special
```

## Installation et activation

### 1. Activer le plugin

Après avoir ajouté ces fichiers, vous devez **réactiver le plugin** ou **flush les rewrite rules** :

**Option A - Via l'admin WordPress :**
1. Allez dans **Extensions** dans l'admin WordPress
2. Désactivez puis réactivez le plugin "Poké HUB"

**Option B - Via le code (une seule fois) :**
Ajoutez temporairement ce code dans votre `functions.php` ou exécutez-le via un outil comme WP-CLI :
```php
flush_rewrite_rules();
```

### 2. Tester avec un événement existant

Si vous avez déjà des événements spéciaux dans votre base de données, testez avec leur slug :
```
https://votre-site.com/pokemon-go/events/[slug-de-votre-evenement]
```

## Structure des fichiers

### 1. `modules/events/public/events-front-routing.php`
Ce fichier gère :
- Les rewrite rules WordPress pour intercepter les URLs `/pokemon-go/events/{slug}`
- L'enregistrement de la query var `pokehub_special_event`
- Le chargement du template personnalisé
- La modification du titre de la page

### 2. `modules/events/public/template-special-event.php`
Template qui affiche l'événement spécial. Pour le moment, il affiche :
- Le titre de l'événement
- Un conteneur prêt pour ajouter plus de contenu

### 3. Fonction helper dans `events-queries.php`
```php
pokehub_get_special_event_url($slug)
// ou
poke_hub_special_event_get_url($slug)
```

## Utilisation

### Générer une URL d'événement

Dans votre code PHP :
```php
$url = pokehub_get_special_event_url('mon-evenement');
// Retourne : https://votre-site.com/pokemon-go/events/mon-evenement
```

### Lien dans un template

```php
<a href="<?php echo esc_url(pokehub_get_special_event_url($event->slug)); ?>">
    <?php echo esc_html($event->title); ?>
</a>
```

## Prochaines étapes

Pour enrichir le template (`template-special-event.php`), vous pourriez ajouter :

1. **Dates de l'événement** :
```php
$start = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $event->start_ts);
$end = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $event->end_ts);
```

2. **Description** :
```php
<?php if (!empty($event->description)) : ?>
    <div class="pokehub-event-description">
        <?php echo wp_kses_post($event->description); ?>
    </div>
<?php endif; ?>
```

3. **Image** :
```php
<?php if (!empty($event->image_url)) : ?>
    <img src="<?php echo esc_url($event->image_url); ?>" alt="<?php echo esc_attr($event->title); ?>" />
<?php endif; ?>
```

4. **Type d'événement avec couleur** :
```php
<?php if (!empty($event->event_type_name)) : ?>
    <span class="event-type" style="background-color: <?php echo esc_attr($event->event_type_color); ?>">
        <?php echo esc_html($event->event_type_name); ?>
    </span>
<?php endif; ?>
```

5. **Pokémon associés** :
```php
<?php
$pokemon_ids = poke_hub_special_event_get_pokemon($event->id);
if ($pokemon_ids) {
    // Afficher les Pokémon
}
?>
```

## Dépannage

### Les URLs retournent une 404

**Solution :** Flush les rewrite rules
```php
// Dans functions.php temporairement
add_action('init', function() {
    flush_rewrite_rules();
}, 999);
```
Puis rechargez la page, et supprimez ce code.

### Le template ne se charge pas

Vérifiez que :
1. Le fichier `template-special-event.php` existe bien
2. La constante `POKE_HUB_EVENTS_PATH` est définie
3. Le slug dans l'URL correspond à un événement dans la base de données

### Debug

Pour voir les rewrite rules actives :
```php
global $wp_rewrite;
print_r($wp_rewrite->rules);
```

## Notes techniques

- Les événements sont récupérés depuis la table `{prefix}_pokehub_special_events`
- Le slug doit être unique
- Le système est compatible avec les thèmes WordPress classiques (utilise `get_header()` et `get_footer()`)
- Le SEO est géré automatiquement (titre de page modifié via `document_title_parts`)






