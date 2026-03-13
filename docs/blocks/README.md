# 📦 Module Blocs Gutenberg - Poké HUB

Index de la documentation du module Blocs et liste des blocs disponibles.

## 📚 Documentation

| Fichier | Description |
|--------|-------------|
| **[README.md](./README.md)** | Ce fichier — index et liste des blocs |
| **[ARCHITECTURE.md](./ARCHITECTURE.md)** | Architecture, structure des fichiers, règles d’organisation |
| **[BLOCK_TYPES.md](./BLOCK_TYPES.md)** | Types de blocs (PHP dynamique vs JavaScript/React) |
| **[QUICK_START.md](./QUICK_START.md)** | Créer un nouveau bloc (PHP ou JS) |

Voir aussi à la racine de `docs/` :
- **[CONTENT_BLOCKS.md](../CONTENT_BLOCKS.md)** — Utilisation des blocs (attributs, exemples, CSS)
- **[BLOCKS_TROUBLESHOOTING.md](../BLOCKS_TROUBLESHOOTING.md)** — Dépannage
- **[BLOCKS_MODULE_MIGRATION.md](../BLOCKS_MODULE_MIGRATION.md)** — Migration depuis l’ancien système

## 📋 Liste des blocs

Tous les blocs sont dans la catégorie **Poké HUB** dans l’éditeur. Ils ne sont enregistrés que si les modules requis sont actifs.

| Bloc | Nom (éditeur) | Modules requis | Description |
|------|----------------|----------------|-------------|
| `pokehub/event-dates` | Dates d'événement | events | Dates de début/fin avec feux verts/rouges |
| `pokehub/event-quests` | Quêtes d'événement | events | Quêtes et récompenses de l’événement |
| `pokehub/bonus` | Bonus | bonus | Cartes de bonus (auto ou liste d’IDs) |
| `pokehub/wild-pokemon` | Pokémon Sauvages | pokemon, events | Liste des Pokémon dans la nature (shiny, rare, régional) |
| `pokehub/habitats` | Habitats | events, pokemon | Habitats avec Pokémon et horaires |
| `pokehub/new-pokemon-evolutions` | Nouveaux Pokémon - Lignées d'évolution | pokemon | Lignées d’évolution et conditions |
| `pokehub/collection-challenges` | Défis de Collection | pokemon, events | Défis de collection (post meta) |
| `pokehub/special-research` | Études Spéciales | pokemon, events | Études ponctuelles / spéciales / magistrales |

## 🎯 Dépendances

L’enregistrement est géré dans `modules/blocks/functions/blocks-register.php` : chaque bloc déclare un tableau `requires` (modules qui doivent être actifs). Si un module est désactivé, le bloc n’apparaît pas dans l’éditeur.

## 🔗 Où est le code ?

- **Définition des blocs** : `modules/blocks/blocks/{nom-du-bloc}/` (block.json, index.js, render.php)
- **Rendu / données** : selon le bloc — events, bonus, pokemon, ou helpers dans `modules/blocks/functions/`
- **Meta boxes** (défis de collection, études spéciales) : `modules/blocks/admin/`

Pour le détail des attributs et exemples d’utilisation, voir **[CONTENT_BLOCKS.md](../CONTENT_BLOCKS.md)**.
