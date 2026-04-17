# 🔧 Dépannage - Blocs de contenu automatiques

Si rien ne s'affiche en front, suivez ce guide de diagnostic étape par étape.

## ✅ Vérifications de base

### 1. Modules activés

- **Blocs Gutenberg** : le module **Blocks** doit être actif ; la plupart des blocs listés dans `docs/blocks/README.md` exigent aussi **Events**.
- **Bonus (bloc + metabox)** : le module **Bonus** n’est **pas** obligatoire — le module **Blocks** charge la metabox et le catalogue est lu sur le site principal (voir [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md)).
- **Écran d’admin catalogue des types de bonus** (Poké HUB → Bonus) : nécessite le module **Bonus** en plus si vous voulez éditer les types sur le site principal.

1. Allez dans **Poké HUB → Settings → General**
2. Cochez **Blocks** et **Events** (et **Bonus** seulement si vous en avez besoin pour l’admin catalogue)
3. Cliquez sur **Save Changes**

### 2. Données présentes

#### Pour les dates d'événement :
- Les meta `_admin_lab_event_start` et `_admin_lab_event_end` doivent être définies sur le post
- Ces meta sont généralement ajoutées via Admin Lab ou un autre système

#### Pour les bonus :
- Les bonus doivent être associés au post via la metabox "Bonus de l'événement"
- Les types de bonus doivent exister dans la table catalogue effective (`pokehub_get_bonus_types_table()` : locale ou distante selon le préfixe Pokémon — voir [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md))

#### Pour le bloc « Day Pokémon Hours » (featured / Spotlight) :
- Le module **Events** doit être actif et les tables **`special_events`** / **`special_event_pokemon`** doivent exister (sinon repli sur `content_day_pokemon_hours`).
- Les créneaux **featured_hours** sont saisis dans la metabox **Day Pokémon Hours** sur l’article ; en mode SQL, la liaison au post utilise **`content_source_type`** / **`content_source_id`**. Voir [CONTENT_BLOCKS.md](./CONTENT_BLOCKS.md) et [events/EVENEMENTS-DISTANTS.md](./events/EVENEMENTS-DISTANTS.md).
- Si les heures sont bonnes dans la metabox mais **fausses** après ouverture / enregistrement sous **Special events** : les lignes Spotlight doivent avoir **`mode` = `local`** (heure du site). Une migration plugin remet les anciennes lignes en `local` ; sinon repasser le mode à **local** dans le formulaire ou re-sauver l’article depuis la metabox. Détail : [events/EVENEMENTS-DISTANTS.md](./events/EVENEMENTS-DISTANTS.md) § Événements Spotlight.
- Si le **titre FR** d’une Spotlight repasse en anglais après sauvegarde de l’article : mettre à jour le plugin avec le correctif de préservation séparée **`title_en` / `title_fr`**, corriger une fois les lignes déjà impactées dans **Special events**, puis re-sauver l’article.

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

- [ ] Module **Blocks** activé dans Poké HUB → Settings
- [ ] Module **Events** activé dans Poké HUB → Settings
- [ ] Module **Bonus** activé seulement si vous utilisez l’écran catalogue admin (optionnel pour le bloc Bonus)
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

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md)*
