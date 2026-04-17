# 📘 Types de blocs - Guide de développement

Ce guide explique les deux approches pour créer des blocs dans le module Blocks.

## 🎯 Comparaison des approches

| Caractéristique | Bloc PHP dynamique | Bloc JavaScript/React |
|----------------|-------------------|----------------------|
| **Complexité** | Simple | Avancée |
| **Interface éditeur** | Basique (attributs via block.json) | Riche (composants React) |
| **Rendu** | Dynamique uniquement | Statique + Dynamique |
| **Dépendances** | Aucune | Node.js, npm, @wordpress/scripts |
| **Build** | Non requis | Requis (npm run build) |
| **Performance** | Excellente | Bonne (avec cache) |
| **Cas d'usage** | Affichage de données, contenu contextuel | Interface complexe, contrôles personnalisés |

## 📝 Exemples concrets

### Bloc PHP dynamique : `event-dates`

**Pourquoi PHP uniquement ?**
- Affiche des données depuis la base de données
- Pas besoin d'interface d'édition complexe
- Auto-détection des dates depuis les meta

**Fichiers** :
- `block.json` : Configuration
- `render.php` : Rendu dynamique

### Bloc JavaScript/React : Futur bloc "Pokémon"

**Pourquoi JavaScript/React ?**
- Sélecteur de Pokémon avec recherche
- Prévisualisation dans l'éditeur
- Contrôles avancés (types, génération, etc.)
- Rendu statique pour performance

**Fichiers** :
- `block.json` : Configuration
- `index.js` : Enregistrement
- `edit.js` : Composant éditeur React
- `save.js` : Rendu statique (optionnel)
- `render.php` : Rendu dynamique (fallback)

## 🚀 Quand utiliser chaque approche ?

### Utilisez PHP dynamique si :
- ✅ Le bloc affiche des données depuis la DB
- ✅ Pas besoin de contrôles d'édition complexes
- ✅ Le contenu change selon le contexte
- ✅ Vous voulez une solution simple et rapide

**Exemples** : Dates d'événement, Bonus, Statistiques

### Utilisez JavaScript/React si :
- ✅ Vous avez besoin d'une interface d'édition riche
- ✅ Contrôles personnalisés (sélecteurs, toggles, etc.)
- ✅ Prévisualisation en temps réel
- ✅ Rendu statique pour performance
- ✅ Bloc réutilisable dans des patterns

**Exemples** : Sélecteur de Pokémon, Infographie personnalisée, Formulaire

## 🔄 Migration d'un bloc PHP vers JavaScript

Si vous commencez avec un bloc PHP et que vous avez besoin de plus de fonctionnalités :

1. Créer la structure avec `create-block`
2. Copier la logique de `render.php`
3. Ajouter `edit.js` avec les contrôles
4. Optionnel : Ajouter `save.js` pour rendu statique
5. Compiler avec `npm run build`

## 📚 Ressources

- [Tutoriel Copyright Date Block](https://developer.wordpress.org/block-editor/getting-started/create-block/) - Exemple complet
- [Block Development Environment](https://developer.wordpress.org/block-editor/getting-started/devenv/)
- [Block API Reference](https://developer.wordpress.org/block-editor/reference-guides/block-api/)

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
