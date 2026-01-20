# 🎨 Blocs de contenu automatiques - Poké HUB

Ce système permet d'automatiser l'affichage des dates d'événements et des bonus dans les articles Poké HUB avec un rendu visuel moderne.

## 📋 Fonctionnalités

### 1. Dates d'événement avec feux verts/rouges

Les dates d'événement s'affichent automatiquement avec des indicateurs visuels :
- 🔵 **Feu vert** : Date de début
- 🔴 **Feu rouge** : Date de fin

### 2. Bonus visuels en cartes

Les bonus s'affichent automatiquement sous forme de cartes modernes avec :
- Icônes des bonus
- Badges de ratio (ex: "1/2", "1/4") détectés automatiquement
- Design sombre et moderne

## 🚀 Utilisation

### Affichage automatique

Par défaut, les dates et bonus s'affichent automatiquement dans les articles si :
- Les dates sont définies dans les meta `_admin_lab_event_start` et `_admin_lab_event_end`
- Les bonus sont associés à l'article via la metabox "Bonus de l'événement"

**Post types supportés par défaut :**
- `post`
- `pokehub_event`

Vous pouvez modifier cette liste via les filtres :
```php
// Pour les dates
add_filter('pokehub_events_dates_auto_post_types', function($types) {
    $types[] = 'mon_custom_post_type';
    return $types;
});

// Pour les bonus
add_filter('pokehub_bonus_auto_post_types', function($types) {
    $types[] = 'mon_custom_post_type';
    return $types;
});
```

### Blocs Gutenberg

Vous pouvez également insérer manuellement les dates et bonus via les blocs Gutenberg :

#### Bloc "Dates d'événement" (`pokehub/event-dates`)

**Attributs :**
- `autoDetect` (boolean, défaut: `true`) : Détecte automatiquement les dates depuis les meta
- `startDate` (string) : Date de début (format: "YYYY-MM-DD HH:MM")
- `endDate` (string) : Date de fin (format: "YYYY-MM-DD HH:MM")

**Exemple d'utilisation :**
```
<!--wp:pokehub/event-dates {"autoDetect":true} /-->
```

#### Bloc "Bonus" (`pokehub/bonus`)

**Attributs :**
- `autoDetect` (boolean, défaut: `true`) : Détecte automatiquement les bonus depuis les meta
- `bonusIds` (array) : Liste des IDs de bonus à afficher
- `layout` (string, défaut: `cards`) : Layout d'affichage (`cards` ou `list`)

**Exemple d'utilisation :**
```
<!--wp:pokehub/bonus {"autoDetect":true,"layout":"cards"} /-->
```

## 🎨 Styles CSS

Les styles sont automatiquement chargés :
- **Dates** : `assets/css/poke-hub-events-front.css`
- **Bonus** : `assets/css/poke-hub-bonus-front.css`

### Classes CSS disponibles

#### Dates d'événement
```css
.pokehub-event-dates-block       /* Conteneur principal */
.event-dates-row                  /* Ligne des dates */
.event-date-chip                  /* Chip de date */
.event-date-chip--start           /* Chip de date de début */
.event-date-chip--end             /* Chip de date de fin */
.event-date-dot                   /* Point indicateur */
.event-date-dot--start            /* Point vert (début) */
.event-date-dot--end              /* Point rouge (fin) */
.event-date-text                  /* Texte de la date */
.event-date-middle                /* Séparateur (···) */
```

#### Bonus
```css
.pokehub-bonuses-visual           /* Conteneur principal */
.pokehub-bonus-card               /* Carte de bonus */
.pokehub-bonus-card-inner         /* Contenu de la carte */
.pokehub-bonus-card-header        /* En-tête */
.pokehub-bonus-card-title         /* Titre du bonus */
.pokehub-bonus-card-icon-wrapper  /* Conteneur de l'icône */
.pokehub-bonus-card-icon          /* Icône du bonus */
.pokehub-bonus-card-badge         /* Badge de ratio */
.pokehub-bonus-card-description   /* Description */
```

## 🔧 Personnalisation

### Désactiver l'affichage automatique

```php
// Pour les dates
remove_filter('the_content', 'pokehub_events_append_dates_to_content', 10);

// Pour les bonus
remove_filter('the_content', 'pokehub_bonus_append_to_content_visual', 20);
```

### Utiliser les fonctions directement

```php
// Afficher les dates
$start_ts = get_post_meta($post_id, '_admin_lab_event_start', true);
$end_ts = get_post_meta($post_id, '_admin_lab_event_end', true);
echo pokehub_render_event_dates((int) $start_ts, (int) $end_ts);

// Afficher les bonus
$bonuses = pokehub_get_bonuses_for_post($post_id);
echo pokehub_render_bonuses_visual($bonuses, 'cards'); // ou 'list'
```

## 📝 Détection automatique des ratios

Le système détecte automatiquement les ratios (ex: "1/2", "1/4") dans :
- Le titre du bonus
- La description du bonus
- La description spécifique à l'événement

Ces ratios sont affichés dans un badge circulaire rouge sur l'icône du bonus.

## 🎯 Prochaines étapes

Ce système peut être étendu pour gérer :
- Nouveaux Pokémon
- Nouveaux shiny
- Pokémon dans la nature
- Pokémon dans les quêtes
- Etc.

Les blocs Gutenberg peuvent être étendus pour inclure des sélecteurs de Pokémon et d'autres éléments.






