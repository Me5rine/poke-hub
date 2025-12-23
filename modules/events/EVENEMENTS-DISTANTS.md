# 🌐 Support des événements spéciaux distants

## ✅ Fonctionnalité activée

Le système de routing gère maintenant **automatiquement** les deux types d'événements spéciaux :

1. **Événements locaux** (table `{prefix}_pokehub_special_events`)
2. **Événements distants** (table `{prefix_distant}_pokehub_remote_special_events`)

## 🔍 Comment ça marche

### Ordre de recherche

Quand un utilisateur visite `/pokemon-go/events/mon-evenement`, le système :

1. ✅ **Cherche d'abord dans la table locale**
   - Si trouvé → Affiche l'événement local
   
2. 🌐 **Si non trouvé, cherche dans la table distante**
   - Si trouvé → Affiche l'événement distant
   
3. ❌ **Si toujours pas trouvé**
   - Affiche une page 404

### Exemple de flux

```
Utilisateur visite : /pokemon-go/events/community-day-pikachu

┌─────────────────────────────────────────────┐
│  1. Recherche dans special_events (local)   │
│     → Non trouvé                            │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  2. Recherche dans remote_special_events    │
│     → Trouvé !                              │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  3. Affiche l'événement distant             │
│     avec le bon template                    │
└─────────────────────────────────────────────┘
```

## 🎨 Gestion des images

### Images locales vs distantes

Le système gère automatiquement les différences :

| Source | Méthode utilisée |
|--------|------------------|
| **Local** | `wp_get_attachment_image_url()` |
| **Distant** | `poke_hub_events_get_remote_attachment_url()` |

### Ordre de priorité pour les images

1. **Image spécifique de l'événement**
   - `image_url` (URL directe)
   - `image_id` (ID d'attachment, local ou distant selon la source)

2. **Image par défaut du type d'événement**
   - Récupérée depuis la taxonomy `event_type` (distante)

### Utilisation dans le code

L'URL de l'image est pré-calculée et disponible dans `$event->computed_image_url` :

```php
add_action('pokehub_special_event_content', function($event) {
    // Utiliser l'URL pré-calculée (fonctionne pour local ET distant)
    if (!empty($event->computed_image_url)) {
        echo '<img src="' . esc_url($event->computed_image_url) . '">';
    }
});
```

## 🔧 Différences techniques

### Structure de l'objet `$event`

Les deux types d'événements ont la même structure de base, mais comportent un marqueur :

```php
// Événement local
$event->_source = 'local';

// Événement distant
$event->_source = 'remote';
```

### Vérifier la source dans le code

```php
add_action('pokehub_special_event_content', function($event) {
    $is_remote = !empty($event->_source) && $event->_source === 'remote';
    
    if ($is_remote) {
        echo '<span class="badge">Événement JV Actu</span>';
    } else {
        echo '<span class="badge">Événement Me5rine LAB</span>';
    }
});
```

### Attribut HTML

Un attribut `data-source` est ajouté au conteneur principal :

```html
<!-- Événement local -->
<div class="pokehub-special-event-content" data-source="local">
    ...
</div>

<!-- Événement distant -->
<div class="pokehub-special-event-content" data-source="remote">
    ...
</div>
```

Cela permet de styliser différemment les deux types :

```css
/* Style spécifique pour les événements distants */
.pokehub-special-event-content[data-source="remote"] {
    border-left: 4px solid #ff6b35;
}

/* Style spécifique pour les événements locaux */
.pokehub-special-event-content[data-source="local"] {
    border-left: 4px solid #0073aa;
}
```

## 📊 Données disponibles

Tous les champs sont identiques entre local et distant :

```php
$event->id               // ID de l'événement
$event->title            // Titre
$event->slug             // Slug (URL)
$event->description      // Description HTML
$event->start_ts         // Timestamp de début
$event->end_ts           // Timestamp de fin
$event->event_type       // Slug du type d'événement
$event->image_id         // ID de l'image
$event->image_url        // URL directe de l'image
$event->mode             // 'local' ou 'fixed'
$event->recurring        // Événement récurrent (0 ou 1)

// Champs calculés
$event->_source          // 'local' ou 'remote'
$event->computed_image_url // URL de l'image (calculée automatiquement)
```

## 🔗 URLs

Les URLs sont identiques pour les deux types :

```
Local :   /pokemon-go/events/mon-evenement-local
Distant : /pokemon-go/events/mon-evenement-distant
```

Aucune différence dans l'URL, c'est totalement transparent pour l'utilisateur !

## ✨ Avantages

| Avantage | Description |
|----------|-------------|
| 🔄 **Synchronisation** | Les événements distants sont automatiquement disponibles |
| 🎯 **URL uniques** | Un seul format d'URL pour tous les événements |
| 🎨 **Template unifié** | Un seul template pour afficher tous les événements |
| 🖼️ **Images gérées** | Les images distantes S3/CDN fonctionnent automatiquement |
| 📱 **Responsive** | Même expérience utilisateur pour tous les événements |

## 🚀 Utilisation

### Aucune action requise !

Le système fonctionne automatiquement dès que :

1. ✅ Vous avez une table `remote_special_events` dans votre base distante
2. ✅ La configuration de connexion distante est active
3. ✅ Les événements ont des slugs uniques

### Tester

```
# Événement local
https://votre-site.com/pokemon-go/events/spotlight-hour-pikachu

# Événement distant (même format !)
https://votre-site.com/pokemon-go/events/go-fest-2024
```

## 💡 Conseils

1. **Slugs uniques** : Assurez-vous que les slugs sont uniques entre locaux et distants
2. **Priorité locale** : Si un slug existe en local ET distant, le local a la priorité
3. **Images** : Utilisez toujours `$event->computed_image_url` dans vos hooks pour la compatibilité
4. **Test** : Testez avec les deux types d'événements pour vérifier le rendu

## 🐛 Dépannage

### Les événements distants ne s'affichent pas

**Vérifiez :**
1. La table `remote_special_events` existe
2. La connexion à la base distante fonctionne
3. Les événements ont bien un `slug` renseigné
4. Le slug dans l'URL est correct

### Les images distantes ne s'affichent pas

**Vérifiez :**
1. La table `remote_as3cf_items` est accessible
2. Les chemins S3/CDN sont corrects
3. Utilisez `$event->computed_image_url` dans votre code








