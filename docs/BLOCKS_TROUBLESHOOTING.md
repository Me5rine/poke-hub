# 🔧 Dépannage - Blocs non visibles dans l'éditeur

Si les blocs "Dates d'événement" et "Bonus" n'apparaissent pas dans l'éditeur Gutenberg, suivez ce guide.

## ✅ Vérifications essentielles

### 1. Utiliser le shortcode de diagnostic

Ajoutez `[pokehub_debug_blocks]` dans un article pour voir un diagnostic complet de l'état des blocs.

### 2. Modules activés

Les modules suivants **DOIVENT** être activés dans **Poké HUB → Settings → General** :

- ✅ **Blocks** (obligatoire)
- ✅ **Events** (requis pour le bloc "Dates d'événement")
- ✅ **Bonus** (requis pour le bloc "Bonus")

**Comment activer :**
1. Allez dans **Poké HUB → Settings → General**
2. Cochez les cases : **Blocks**, **Events**, **Bonus**
3. Cliquez sur **Save Changes**

### 3. Vider le cache

1. **Cache WordPress** : Si vous utilisez un plugin de cache (WP Super Cache, W3 Total Cache, etc.), videz-le
2. **Cache navigateur** : Videz le cache (Ctrl+F5 ou Cmd+Shift+R)
3. **Cache Gutenberg** : Déconnectez-vous et reconnectez-vous à WordPress

### 4. Vérifier l'éditeur

Assurez-vous d'utiliser l'**éditeur Gutenberg** (pas l'éditeur classique) :
- WordPress 5.0+ utilise Gutenberg par défaut
- Si vous avez l'éditeur classique, installez le plugin "Gutenberg"

## 🔍 Où trouver les blocs dans l'éditeur

Une fois activés, les blocs devraient apparaître :

1. **Dans la catégorie "Poké HUB"** dans l'inserter de blocs
2. **En recherchant** "dates", "événement", "bonus" dans la barre de recherche
3. **Avec les icônes** :
   - 📅 Calendrier pour "Dates d'événement"
   - 🏆 Trophée pour "Bonus"

## 🆘 Solutions étape par étape

### Solution 1 : Vérifier les modules

1. Allez dans **Poké HUB → Settings → General**
2. Vérifiez que ces modules sont cochés :
   - [ ] Blocks
   - [ ] Events
   - [ ] Bonus
3. Si ce n'est pas le cas, cochez-les et sauvegardez

### Solution 2 : Vider le cache et rafraîchir

1. Videz le cache WordPress (si plugin de cache installé)
2. Videz le cache du navigateur (Ctrl+F5)
3. Rafraîchissez la page de l'éditeur
4. Essayez de voir les blocs à nouveau

### Solution 3 : Vérifier les logs

Activez `WP_DEBUG` dans `wp-config.php` :
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Puis vérifiez `wp-content/debug.log` pour voir si des erreurs apparaissent lors de l'enregistrement des blocs.

### Solution 4 : Forcer le rechargement

1. Allez dans **Réglages → Permaliens**
2. Cliquez sur **Enregistrer** (sans rien modifier)
3. Retournez dans l'éditeur et rafraîchissez

### Solution 5 : Vérifier la version de WordPress

Les blocs avec `block.json` nécessitent **WordPress 5.8+**.

Vérifiez votre version dans **Tableau de bord → Mise à jour**.

## 📝 Checklist complète

- [ ] Module **Blocks** activé
- [ ] Module **Events** activé
- [ ] Module **Bonus** activé
- [ ] Cache vidé (WordPress + navigateur)
- [ ] Éditeur Gutenberg utilisé (pas classique)
- [ ] WordPress 5.8+ installé
- [ ] Pas d'erreurs dans `debug.log`
- [ ] Blocs recherchés dans la catégorie "Poké HUB"

## 🆘 Si rien ne fonctionne

1. Utilisez le shortcode `[pokehub_debug_blocks]` pour voir le diagnostic complet
2. Vérifiez les logs WordPress (`wp-content/debug.log`)
3. Vérifiez la console JavaScript du navigateur (F12) pour les erreurs
4. Contactez le support avec les résultats du diagnostic

## 📚 Structure attendue

```
modules/blocks/
├── blocks.php
├── functions/
│   ├── blocks-register.php
│   ├── blocks-helpers.php
│   └── blocks-debug.php
└── blocks/
    ├── event-dates/
    │   ├── block.json
    │   └── render.php
    └── bonus/
        ├── block.json
        └── render.php
```
