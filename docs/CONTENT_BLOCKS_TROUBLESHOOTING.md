# 🔧 Dépannage - Blocs de contenu automatiques

Si rien ne s'affiche en front, suivez ce guide de diagnostic étape par étape.

## ✅ Vérifications de base

### 1. Modules activés

Les modules **Events** et **Bonus** doivent être activés dans les paramètres Poké HUB :

1. Allez dans **Poké HUB → Settings → General**
2. Vérifiez que les cases **Events** et **Bonus** sont cochées
3. Cliquez sur **Save Changes**

### 2. Données présentes

#### Pour les dates d'événement :
- Les meta `_admin_lab_event_start` et `_admin_lab_event_end` doivent être définies sur le post
- Ces meta sont généralement ajoutées via Admin Lab ou un autre système

#### Pour les bonus :
- Les bonus doivent être associés au post via la metabox "Bonus de l'événement"
- Les bonus doivent exister dans le CPT `pokehub_bonus`

### 3. Post types autorisés

Par défaut, seuls les post types suivants sont supportés :
- `post` (articles WordPress)
- `pokehub_event` (si vous utilisez ce CPT)

## 🔍 Diagnostic avec les shortcodes de debug

Si `WP_DEBUG` est activé, vous pouvez utiliser des shortcodes de diagnostic :

### Dans votre article, ajoutez :

```
[pokehub_debug_dates]
[pokehub_debug_bonuses]
```

Ces shortcodes afficheront un diagnostic complet pour identifier le problème.

## 🐛 Problèmes courants et solutions

### Problème 1 : "Module Events actif : ❌ NON"

**Solution :**
1. Allez dans **Poké HUB → Settings → General**
2. Cochez la case **Events**
3. Cliquez sur **Save Changes**

### Problème 2 : "Meta _admin_lab_event_start : ❌ Absente"

**Solution :**
- Les dates doivent être définies dans les meta du post
- Vérifiez que vous utilisez bien les clés `_admin_lab_event_start` et `_admin_lab_event_end`
- Les valeurs doivent être des timestamps Unix

**Pour ajouter manuellement les dates :**
```php
// Dans functions.php ou un plugin personnalisé
add_action('save_post', function($post_id) {
    if (get_post_type($post_id) === 'post') {
        // Exemple : dates pour un événement
        update_post_meta($post_id, '_admin_lab_event_start', strtotime('2024-01-01 10:00:00'));
        update_post_meta($post_id, '_admin_lab_event_end', strtotime('2024-01-07 20:00:00'));
    }
});
```

### Problème 3 : "Post type : ❌ Non autorisé"

**Solution :**
Ajoutez votre post type personnalisé via un filtre :

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

### Problème 4 : "Dans la boucle : ❌ NON"

**Solution :**
- Assurez-vous d'utiliser `the_content()` dans votre template
- Ne pas utiliser `get_the_content()` ou `$post->post_content` directement
- Vérifiez que votre thème utilise bien la boucle WordPress standard

### Problème 5 : "Filtre the_content enregistré : ❌ NON"

**Solution :**
- Vérifiez que les modules sont bien activés
- Vérifiez qu'il n'y a pas d'erreurs PHP dans les logs
- Désactivez temporairement les autres plugins pour vérifier les conflits

### Problème 6 : CSS non chargé

**Solution :**
- Vérifiez que les fichiers CSS existent :
  - `assets/css/poke-hub-events-front.css`
  - `assets/css/poke-hub-bonus-front.css`
- Videz le cache de votre navigateur
- Videz le cache WordPress (si vous utilisez un plugin de cache)

## 🧪 Test manuel

Pour tester manuellement si les fonctions fonctionnent :

### Test des dates :
```php
// Dans votre template ou un shortcode
$start_ts = get_post_meta(get_the_ID(), '_admin_lab_event_start', true);
$end_ts = get_post_meta(get_the_ID(), '_admin_lab_event_end', true);

if ($start_ts && $end_ts) {
    echo pokehub_render_event_dates((int) $start_ts, (int) $end_ts);
}
```

### Test des bonus :
```php
// Dans votre template ou un shortcode
$bonuses = pokehub_get_bonuses_for_post(get_the_ID());
if (!empty($bonuses)) {
    echo pokehub_render_bonuses_visual($bonuses, 'cards');
}
```

## 📝 Checklist complète

- [ ] Module Events activé dans Poké HUB → Settings
- [ ] Module Bonus activé dans Poké HUB → Settings
- [ ] Meta `_admin_lab_event_start` présente sur le post
- [ ] Meta `_admin_lab_event_end` présente sur le post
- [ ] Bonus associés au post via la metabox
- [ ] Post type autorisé (post ou pokehub_event)
- [ ] CSS chargé (vérifier dans les outils de développement)
- [ ] Pas d'erreurs JavaScript dans la console
- [ ] Pas d'erreurs PHP dans les logs
- [ ] Cache vidé (navigateur et WordPress)

## 🆘 Si rien ne fonctionne

1. Activez `WP_DEBUG` dans `wp-config.php` :
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

2. Utilisez les shortcodes de diagnostic dans votre article

3. Vérifiez les logs WordPress dans `wp-content/debug.log`

4. Vérifiez la console JavaScript du navigateur (F12)

5. Contactez le support avec les résultats du diagnostic






