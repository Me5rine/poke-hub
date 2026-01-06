# Guide d'Intégration dans le Thème

Ce guide explique comment intégrer les styles CSS unifiés du plugin dans votre thème WordPress.

## 📋 Fichiers à Copier

Vous devez copier le contenu de ces fichiers dans votre thème :

1. **`docs/FRONT_CSS.md`** → Tous les styles front-end unifiés (boutons, tableaux, tuiles, pagination, filtres, etc.)
2. **`docs/CSS_RULES.md`** → Styles des formulaires (si vous utilisez les formulaires)

## 🎯 Méthode 1 : Fichier CSS dédié (Recommandé)

### Étape 1 : Créer le fichier CSS dans votre thème

Créez un fichier `assets/css/me5rine-lab-unified.css` (ou `css/me5rine-lab-unified.css`) dans votre thème.

### Étape 2 : Copier le contenu

Copiez **TOUT le contenu** du fichier `docs/FRONT_CSS.md` (sauf les titres markdown) dans ce fichier CSS.

**Important** : Copiez uniquement les blocs CSS entre les triple backticks (```css ... ```), pas les commentaires markdown.

### Étape 3 : Enqueue le fichier dans functions.php

Ajoutez ce code dans le `functions.php` de votre thème :

```php
/**
 * Charger les styles CSS unifiés Me5rine Lab
 */
function mon_theme_enqueue_me5rine_lab_styles() {
    // Charger le CSS unifié
    wp_enqueue_style(
        'me5rine-lab-unified',
        get_template_directory_uri() . '/assets/css/me5rine-lab-unified.css',
        [], // Pas de dépendances
        '1.0.0' // Version (changez à chaque mise à jour)
    );
}
add_action('wp_enqueue_scripts', 'mon_theme_enqueue_me5rine_lab_styles');
```

**Note** : Si votre fichier CSS est dans un autre emplacement, ajustez le chemin dans `get_template_directory_uri()`.

## 🎯 Méthode 2 : Intégrer dans style.css

### Étape 1 : Copier le contenu

Copiez **TOUT le contenu** du fichier `docs/FRONT_CSS.md` (sauf les titres markdown) à la fin de votre `style.css`.

**Important** : Copiez uniquement les blocs CSS entre les triple backticks (```css ... ```), pas les commentaires markdown.

### Étape 2 : Vérifier l'ordre de chargement

Assurez-vous que votre `style.css` est chargé **après** les styles du plugin. **Note** : Les variables CSS de `global-colors.css` doivent être définies dans votre thème.

## 🎨 Personnalisation des Variables CSS

Toutes les variables CSS sont définies dans la section `:root` au début de `FRONT_CSS.md`. Vous pouvez les surcharger dans votre thème.

### Exemple de surcharge dans votre thème

Ajoutez ceci dans votre `style.css` ou dans un fichier CSS personnalisé :

```css
:root {
    /* Surcharger les couleurs principales */
    --me5rine-lab-primary: #1a4a5c;
    --me5rine-lab-secondary: #0066cc;
    
    /* Surcharger les espacements */
    --me5rine-lab-spacing-md: 20px;
    --me5rine-lab-spacing-lg: 30px;
    
    /* Surcharger les rayons */
    --me5rine-lab-radius-md: 10px;
    --me5rine-lab-radius-lg: 15px;
}
```

**Important** : Placez ces surcharges **après** le chargement du CSS unifié pour qu'elles prennent effet.

## 📦 Structure Recommandée

```
votre-theme/
├── style.css
├── functions.php
└── assets/
    └── css/
        ├── me5rine-lab-unified.css  ← Copier FRONT_CSS.md ici
        └── theme-custom.css         ← Vos surcharges personnalisées
```

## ✅ Vérification

Après l'intégration, vérifiez que :

1. ✅ Le CSS est bien chargé (inspectez la page avec les outils développeur)
2. ✅ Les variables CSS sont définies (vérifiez dans l'inspecteur)
3. ✅ Les styles s'appliquent correctement aux éléments avec les classes `me5rine-lab-*`

## 🔧 Exemple Complet

### functions.php

```php
/**
 * Charger les styles CSS unifiés Me5rine Lab
 */
function mon_theme_enqueue_me5rine_lab_styles() {
    // CSS unifié (copié depuis FRONT_CSS.md)
    wp_enqueue_style(
        'me5rine-lab-unified',
        get_template_directory_uri() . '/assets/css/me5rine-lab-unified.css',
        [],
        '1.0.0'
    );
    
    // Vos surcharges personnalisées (optionnel)
    wp_enqueue_style(
        'me5rine-lab-theme-custom',
        get_template_directory_uri() . '/assets/css/theme-custom.css',
        ['me5rine-lab-unified'], // Dépend du CSS unifié
        '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'mon_theme_enqueue_me5rine_lab_styles');
```

### assets/css/theme-custom.css (optionnel)

```css
/* Surcharges personnalisées pour Me5rine Lab */
:root {
    --me5rine-lab-primary: #1a4a5c;
    --me5rine-lab-secondary: #0066cc;
    --me5rine-lab-spacing-md: 20px;
}

/* Styles spécifiques au thème si nécessaire */
.me5rine-lab-form-button {
    /* Vos surcharges ici */
}
```

## 📝 Notes Importantes

1. **Ordre de chargement** : Le CSS unifié doit être chargé **avant** vos surcharges personnalisées
2. **Variables CSS** : Les variables utilisent des valeurs par défaut (ex: `var(--admin-lab-color-white, #ffffff)`) donc elles fonctionneront même si certaines variables ne sont pas définies
3. **Mise à jour** : Quand le plugin est mis à jour, vérifiez si `FRONT_CSS.md` a changé et mettez à jour votre fichier CSS si nécessaire
4. **Performance** : Un seul fichier CSS unifié est plus performant que plusieurs fichiers séparés

## 🚀 Résultat

Une fois intégré, tous les éléments front-end du plugin utiliseront automatiquement les styles unifiés définis dans votre thème. Une seule modification de variable CSS changera le style partout !

