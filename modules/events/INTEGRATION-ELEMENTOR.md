# ✨ Intégration Elementor - Événements Spéciaux

## 🎯 Solution mise en place

Le système crée maintenant une **vraie page WordPress virtuelle** que votre thème et Elementor reconnaissent automatiquement. Cela signifie :

✅ **Utilise le template de page de votre thème**
✅ **Compatible avec Elementor** (si vous avez un template Elementor pour les pages)
✅ **Sidebar native** incluse automatiquement
✅ **Largeur de contenu** respectée
✅ **Header et footer** de votre thème
✅ **Tous les styles CSS** de votre thème appliqués

## 🔧 Comment ça marche

### 1. Simulation d'une page WordPress

Le système crée un objet `WP_Post` virtuel qui fait croire à WordPress qu'il s'agit d'une vraie page. Cela permet à :
- Votre thème d'appliquer son template de page habituel
- Elementor de charger son canvas et ses widgets
- Les plugins de se comporter normalement

### 2. Injection du contenu

Le contenu de l'événement est injecté via le filtre `the_content`, ce qui signifie qu'il s'affiche exactement là où le contenu d'une page normale s'afficherait.

## 🎨 Personnalisation du contenu

### Option 1 : Utiliser le hook (recommandé)

Copiez le code de `modules/events/public/events-content-example.php` dans votre fichier `custom-hooks.php` du plugin :

```php
// Afficher l'image de l'événement
add_action('pokehub_special_event_content', function($event) {
    if (!empty($event->image_url)) {
        echo '<img src="' . esc_url($event->image_url) . '" alt="' . esc_attr($event->title) . '">';
    }
}, 3);

// Afficher les Pokémon
add_action('pokehub_special_event_content', function($event) {
    $pokemon_ids = poke_hub_special_event_get_pokemon($event->id);
    // ... afficher les Pokémon
}, 10);

// Afficher les bonus
add_action('pokehub_special_event_content', function($event) {
    $bonuses = poke_hub_special_event_get_bonus_rows($event->id);
    // ... afficher les bonus
}, 20);
```

### Option 2 : Créer un template Elementor

1. Allez dans **Elementor → Templates**
2. Créez un nouveau template de type "Page"
3. Utilisez des shortcodes ou du code personnalisé pour afficher les données de l'événement
4. Appliquez ce template à toutes les pages (il sera utilisé pour les événements)

### Option 3 : Modifier le template dans le thème

Si vous voulez un contrôle total, vous pouvez créer un fichier `page-pokehub-event.php` dans votre thème enfant.

## 📦 Données disponibles

L'objet `$event` contient :

```php
$event->id               // ID de l'événement
$event->title            // Titre
$event->slug             // Slug (URL)
$event->description      // Description HTML
$event->start_ts         // Timestamp de début
$event->end_ts           // Timestamp de fin
$event->event_type       // Slug du type d'événement
$event->image_id         // ID de l'image (si locale)
$event->image_url        // URL de l'image
$event->mode             // 'local' ou 'fixed'
$event->recurring        // Événement récurrent (0 ou 1)
```

### Récupérer les données liées :

```php
// Pokémon
$pokemon_ids = poke_hub_special_event_get_pokemon($event->id);

// Bonus
$bonuses = poke_hub_special_event_get_bonus_rows($event->id);

// Type d'événement complet (avec couleur)
$event_type = poke_hub_events_get_event_type_by_slug($event->event_type);
```

## 🎨 Styles CSS

Un fichier CSS de base est chargé automatiquement : `assets/css/poke-hub-special-events-single.css`

Vous pouvez le personnaliser ou ajouter vos propres styles dans votre thème.

### Classes CSS disponibles :

```css
.pokehub-special-event-content      /* Conteneur principal */
.pokehub-event-dates                /* Bloc des dates */
.pokehub-event-type-badge           /* Badge du type d'événement */
.pokehub-event-pokemon-list         /* Grille des Pokémon */
.pokehub-event-pokemon-item         /* Un Pokémon */
.pokehub-event-bonus-list           /* Liste des bonus */
.pokehub-event-bonus-item           /* Un bonus */
```

## 🔌 Intégration avec Elementor

### Utiliser un template Elementor spécifique

Si vous voulez appliquer un template Elementor Canvas à tous les événements :

```php
add_filter('template_include', function($template) {
    if (get_query_var('pokehub_special_event')) {
        // Forcer le template Canvas d'Elementor
        if (class_exists('\Elementor\Plugin')) {
            return ELEMENTOR_PATH . 'modules/page-templates/templates/canvas.php';
        }
    }
    return $template;
}, 100);
```

### Créer un widget Elementor personnalisé

Vous pouvez créer un widget Elementor qui affiche les données de l'événement :

```php
// Créer un widget "Titre de l'événement"
// Créer un widget "Liste des Pokémon"
// Créer un widget "Dates de l'événement"
// etc.
```

## 🚀 Mise en production

### 1. Flush les rewrite rules

**Important** : Après avoir mis à jour les fichiers, vous DEVEZ flush les rewrite rules :

```php
// Option A : Désactiver/Réactiver le plugin
// Option B : Code temporaire dans functions.php
flush_rewrite_rules();
```

### 2. Tester

Visitez : `https://votre-site.com/pokemon-go/events/slug-evenement`

### 3. Vérifier

- La page utilise-t-elle le bon template ?
- La sidebar s'affiche-t-elle ?
- Les styles Elementor sont-ils appliqués ?
- Le contenu s'affiche-t-il correctement ?

## 🐛 Dépannage

### Le layout n'est pas celui de mon thème

**Solution** : Vérifiez que vous avez bien flush les rewrite rules.

### Elementor ne se charge pas

**Solution** : Vérifiez que votre thème utilise bien `the_content()` dans son template de page.

### La sidebar ne s'affiche pas

**Solution** : Votre thème doit appeler `get_sidebar()` dans son template. Vérifiez votre `page.php`.

### Le contenu ne s'affiche pas

**Solution** : Vérifiez que l'événement existe bien dans la base de données avec le bon slug.

## 💡 Conseils

1. **Utilisez les hooks** pour ajouter du contenu plutôt que de modifier directement les fichiers
2. **Testez avec différents types d'événements** pour vous assurer que tout fonctionne
3. **Créez un template Elementor** si vous voulez un contrôle visuel total
4. **Utilisez le fichier CSS fourni** comme base et personnalisez selon vos besoins

## 📚 Exemples complets

Consultez le fichier `modules/events/public/events-content-example.php` pour voir des exemples d'utilisation complets.




