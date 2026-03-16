# Classes CSS PokeHub

Ce document décrit toutes les classes CSS spécifiques ajoutées par le plugin PokeHub pour l'affichage frontend. Ces classes sont distinctes des classes génériques `me5rine-lab-form-*` et `admin-lab-*` utilisées dans les autres modules.

## Préfixe des Classes

**Préfixe PokeHub** : `pokehub-`

Toutes les classes CSS spécifiques à PokeHub commencent par ce préfixe pour éviter les conflits avec d'autres plugins ou thèmes.

## Structure des Classes par Module

### Module Events (Événements)

#### Conteneurs Principaux
- `.pokehub-events-wrapper` - Wrapper principal pour la liste des événements
- `.pokehub-event-card` - Carte d'un événement individuel
- `.pokehub-event-card-inner` - Contenu interne de la carte d'événement
- `.pokehub-event-card-inner::before` - Image de fond de l'événement (pseudo-élément)
- `.pokehub-event-card-inner::after` - Dégradé de l'image (pseudo-élément)

#### États des Événements
- `.pokehub-event-status-current` - Événement en cours
- `.pokehub-event-status-upcoming` - Événement à venir
- `.pokehub-event-status-past` - Événement passé

#### Sections Internes
- `.event-inner-left` - Section gauche (badge de type)
- `.event-inner-center` - Section centrale (titre et dates)
- `.event-inner-right` - Section droite (image optionnelle)

#### Badges et Types
- `.event-type-badge` - Badge indiquant le type d'événement
- `.event-title` - Titre de l'événement

#### Dates
- `.event-dates-row` - Conteneur pour les dates (début/fin)
- `.event-date-chip` - Chip individuel pour une date
- `.event-date-chip--start` - Chip pour la date de début
- `.event-date-chip--end` - Chip pour la date de fin
- `.event-date-dot` - Point indicateur de date
- `.event-date-dot--start` - Point pour la date de début
- `.event-date-dot--end` - Point pour la date de fin
- `.event-date-text` - Texte de la date
- `.event-date-middle` - Séparateur entre les dates (···)

#### Bloc Dates
- `.pokehub-event-dates-block-wrapper` - Wrapper du bloc Gutenberg pour les dates d'événement
- `.pokehub-event-dates-block` - Bloc Gutenberg pour les dates d'événement (contenu)
- `.pokehub-event-dates-block .event-dates-row` - Ligne de dates dans le bloc
- `.pokehub-event-dates-block .event-date-chip` - Chip de date dans le bloc
- `.pokehub-event-dates-block .event-date-text` - Texte de date dans le bloc
- `.pokehub-event-dates-block .event-date-middle` - Séparateur dans le bloc

#### Filtres
- `.pokehub-event-type-filter-form` - Formulaire de filtrage par type
- `.pokehub-events-tabs` - Onglets de navigation (actuels/à venir/passés)
- `.pokehub-events-button` - Bouton d'onglet
- `.pokehub-events-button.active` - Bouton d'onglet actif
- `.pokehub-events-group-title` - Titre de groupe d'événements

### Module Bonus

#### Conteneurs Principaux
- `.pokehub-bonus-block-wrapper` - Wrapper du bloc Gutenberg des bonus
- `.pokehub-bonuses-visual` - Section contenant tous les bonus (layout visuel)
- `.pokehub-bonuses-layout-cards` - Layout en cartes
- `.pokehub-bonuses-layout-list` - Layout en liste
- `.pokehub-event-bonuses` - Section contenant tous les bonus d'un événement (shortcode/helper)

#### Cartes de Bonus
- `.pokehub-bonus-card` - Carte individuelle d'un bonus
- `.pokehub-bonus-card-inner` - Contenu interne de la carte
- `.pokehub-bonus-card-header` - En-tête de la carte
- `.pokehub-bonus-card-title` - Titre du bonus dans la carte
- `.pokehub-bonus-card-icon-wrapper` - Wrapper pour l'icône
- `.pokehub-bonus-card-icon` - Icône/image du bonus
- `.pokehub-bonus-card-icon-placeholder` - Placeholder si pas d'icône
- `.pokehub-bonus-card-badge` - Badge affichant le ratio (ex: "1/2")
- `.pokehub-bonus-card-description` - Description du bonus

#### Anciennes Classes (Compatibilité)
- `.pokehub-event-bonus` - Bonus individuel (ancien format)
- `.pokehub-event-bonus-image` - Image du bonus (ancien format)
- `.pokehub-event-bonus-content` - Contenu textuel du bonus (ancien format)
- `.pokehub-event-bonus-title` - Titre du bonus (ancien format)
- `.pokehub-event-bonus-desc` - Description spécifique à l'événement (ancien format)
- `.pokehub-event-bonus-desc-global` - Description globale du bonus (ancien format)

#### Liste de Bonus (Single Event)
- `.pokehub-event-bonus-list` - Liste des bonus sur la page single d'un événement
- `.pokehub-event-bonus-item` - Item individuel dans la liste

### Module Quêtes (Event Quests)

#### Conteneurs Principaux
- `.pokehub-event-quests-block-wrapper` - Wrapper du bloc Gutenberg des quêtes
- `.event-field-research-list` - Liste des quêtes de terrain (⚠️ Note: pas de préfixe `pokehub-` pour compatibilité)
- `.event-field-research-list li` - Item de quête individuel
- `.event-field-research-list li.expanded` - Quête avec récompenses visibles
- `.event-field-research-list li.active` - Quête active
- `.quest-header` - En-tête d'une quête (tâche + toggle)
- `.task` - Texte de la tâche de la quête
- `.quest-toggle` - Bouton pour afficher/masquer les récompenses
- `.reward-list` - Liste des récompenses d'une quête
- `.rewards-header` - En-tête de la section récompenses

#### Récompenses
- `.reward` - Conteneur d'une récompense individuelle
- `.reward-bubble` - Bulle contenant l'image de la récompense
- `.reward-bubble.{type}` - Classe dynamique basée sur le type du Pokémon (ex: `.water`, `.electric`)
- `.reward-image` - Image du Pokémon dans la bulle
- `.shiny-icon` - Icône shiny (image) affichée sur la bulle
- `.reward-label` - Label textuel de la récompense (nom du Pokémon)
- `.cp-values` - Conteneur pour les valeurs de CP
- `.cp-values .max-cp` - Valeur CP maximum
- `.cp-values .min-cp` - Valeur CP minimum

### Module Wild Pokémon (Pokémon Sauvages)

#### Conteneurs Principaux
- `.pokehub-wild-pokemon-block-wrapper` - Wrapper du bloc Gutenberg
- `.pokehub-wild-pokemon-grid` - Grille responsive pour les cartes de Pokémon
- `.pokehub-wild-pokemon-grid--rare` - Variante pour la section des Pokémon rares

#### Cartes de Pokémon
- `.pokehub-wild-pokemon-card` - Carte individuelle d'un Pokémon
- `.pokehub-wild-pokemon-card--rare` - Variante pour les Pokémon rares
- `.pokehub-wild-pokemon-card-inner` - Contenu interne de la carte
- `.pokehub-wild-pokemon-image-wrapper` - Wrapper pour l'image du Pokémon
- `.pokehub-wild-pokemon-image` - Image du Pokémon
- `.pokehub-wild-pokemon-name` - Nom du Pokémon

#### Icônes
- `.pokehub-wild-pokemon-shiny-icon` - Icône shiny (✨ emoji)
- `.pokehub-wild-pokemon-regional-icon` - Icône régional (🌍 emoji)

#### Section Rare
- `.pokehub-wild-pokemon-rare-section` - Section séparée pour les Pokémon rares
- `.pokehub-wild-pokemon-rare-title` - Titre de la section rare

#### Éditeur Gutenberg
- `.pokehub-wild-pokemon-block-editor` - Styles spécifiques à l'éditeur
- `.pokehub-wild-pokemon-preview` - Aperçu dans l'éditeur

## Variables CSS

Le plugin utilise des variables CSS pour la personnalisation :

### Événements
- `--event-color` - Couleur principale de l'événement (utilisée pour la bordure gauche)
- `--event-image` - URL de l'image de fond de l'événement

### Pokémon Sauvages
- `--pokemon-type-color` - Couleur du type du Pokémon (utilisée pour le dégradé de fond)

## Responsive Design

Toutes les classes sont conçues pour être responsive :

- **Mobile** : Les grilles s'adaptent automatiquement (1 colonne)
- **Tablette** : 2-3 colonnes selon l'espace disponible
- **Desktop** : 4+ colonnes avec `repeat(auto-fill, minmax(...))`

## États et Interactions

### Hover
- `.pokehub-event-card-inner:hover` - Effet au survol d'une carte d'événement
- `.pokehub-wild-pokemon-card:hover` - Effet au survol d'une carte de Pokémon
- `.pokehub-wild-pokemon-card--rare:hover` - Effet au survol d'une carte rare

### Focus
- Les éléments interactifs (liens, boutons) ont des états `:focus` pour l'accessibilité

## Exemples d'Utilisation

### Structure d'un Événement
```html
<div class="pokehub-event-card pokehub-event-status-current">
    <div class="pokehub-event-card-inner" style="--event-color: #ff9800; --event-image: url(...)">
        <div class="event-inner-left">
            <span class="event-type-badge">Community Day</span>
        </div>
        <div class="event-inner-center">
            <h3 class="event-title">Titre de l'événement</h3>
            <div class="event-dates-row">
                <div class="event-date-chip event-date-chip--start">
                    <span class="event-date-dot event-date-dot--start"></span>
                    <span class="event-date-text">1 Jan 2024</span>
                </div>
                <span class="event-date-middle">···</span>
                <div class="event-date-chip event-date-chip--end">
                    <span class="event-date-dot event-date-dot--end"></span>
                    <span class="event-date-text">2 Jan 2024</span>
                </div>
            </div>
        </div>
    </div>
</div>
```

### Structure d'un Pokémon Sauvage
```html
<div class="pokehub-wild-pokemon-card" style="--pokemon-type-color: #6890F0;">
    <div class="pokehub-wild-pokemon-card-inner">
        <span class="pokehub-wild-pokemon-shiny-icon" title="Shiny disponible">✨</span>
        <span class="pokehub-wild-pokemon-regional-icon" title="Pokémon régional">🌍</span>
        <div class="pokehub-wild-pokemon-image-wrapper">
            <img class="pokehub-wild-pokemon-image" src="..." alt="Pikachu">
        </div>
        <div class="pokehub-wild-pokemon-name">Pikachu</div>
    </div>
</div>
```

### Structure d'une Quête
```html
<ul class="event-field-research-list">
    <li>
        <div class="quest-header">
            <span class="task">Attrapez 10 Pokémon</span>
            <span class="quest-toggle" data-quest-index="0">
                <svg>...</svg>
            </span>
        </div>
        <div class="reward-list">
            <span class="rewards-header">REWARD</span>
            <div class="reward">
                <span class="reward-bubble water" data-type-color="#6890F0">
                    <img class="reward-image" src="..." alt="Squirtle">
                    <img class="shiny-icon" src="..." alt="shiny">
                </span>
                <span class="reward-label">
                    <span>Squirtle</span>
                </span>
                <span class="cp-values water">
                    <span class="max-cp">1234</span>
                    <span class="min-cp">567</span>
                </span>
            </div>
        </div>
    </li>
</ul>
```

## Notes Importantes

1. **Préfixe unique** : Toutes les classes commencent par `pokehub-` pour éviter les conflits
2. **Pas de dépendance** : Ces classes ne dépendent pas des classes `me5rine-lab-form-*` ou `admin-lab-*`
3. **Variables CSS** : Utilisez les variables CSS pour personnaliser les couleurs et images
4. **Responsive** : Toutes les grilles utilisent `repeat(auto-fill, minmax(...))` pour s'adapter automatiquement
5. **Accessibilité** : Les icônes ont des attributs `title` pour les tooltips et `alt` pour les images

### Module Collections

**Utilise au maximum les classes du système CSS commun** (voir **docs/FRONT_CSS.md**, **docs/CSS_RULES.md**, **docs/CSS_SYSTEM.md**) : `me5rine-lab-dashboard`, `me5rine-lab-title-large`, `me5rine-lab-subtitle`, `me5rine-lab-dashboard-header`, `me5rine-lab-form-button`, `me5rine-lab-form-button-secondary`, `me5rine-lab-form-button-remove`, `me5rine-lab-form-block`, `me5rine-lab-form-field`, `me5rine-lab-form-label`, `me5rine-lab-form-input`, `me5rine-lab-form-select`, `me5rine-lab-form-message`, `me5rine-lab-state-message`, `me5rine-lab-card`, `me5rine-lab-card-name`, `me5rine-lab-card-meta`, `me5rine-lab-card-actions`, `me5rine-lab-sr-only`.

**Classes spécifiques Collections** (préfixe `pokehub-collections-` / `pokehub-collection-`) pour le layout et le JS uniquement :
- `.pokehub-collections-wrap` (+ `me5rine-lab-dashboard`) - Wrapper page gestion
- `.pokehub-collections-grid` - Grille 3 colonnes, cartes 16:9
- `.pokehub-collections-card` (+ `me5rine-lab-card`) - Carte avec overlay et lien
- `.pokehub-collections-card-link`, `.pokehub-collections-card-bg`, `.pokehub-collections-card-actions`, `.pokehub-collections-card-btn`
- `.pokehub-collection-view-wrap` (+ `me5rine-lab-dashboard`), `.pokehub-collection-tiles`, `.pokehub-collection-tile`, `.pokehub-collection-tile-status`, `.pokehub-status-{owned|missing|for_trade}`
- `.pokehub-collection-legend`, `.pokehub-collection-legend-item`, `.pokehub-collection-legend-dot`, `.pokehub-legend-owned`, `.pokehub-legend-for-trade`, `.pokehub-legend-missing` — légende (vert / orange / gris = possédé / à échanger / manquant), couleurs via `--admin-lab-color-notice-sucess-border`, `--admin-lab-color-notice-warning`, `--admin-lab-color-borders`
- `.pokehub-collections-options-additive`, `.pokehub-collections-options-specific-hint` — bloc options « en plus » vs message pour catégories spécifiques (masquage avec `.is-hidden`)
- `.pokehub-collection-multiselect-wrap`, `.pokehub-collection-multiselect-list-wrap`, `.pokehub-collection-multiselect-list`, `.pokehub-collection-multiselect-item`
- `.pokehub-collections-modal`, `.pokehub-collections-modal-backdrop`, `.pokehub-collections-modal-content` (modal non défini dans FRONT_CSS)

**Variables** : le module utilise `--me5rine-lab-*` (FRONT_CSS) ; dégradés des cartes `--pokehub-collections-card-gradient-*`. Couleurs des statuts / légende : `--admin-lab-color-notice-sucess-border`, `--admin-lab-color-notice-warning`, `--admin-lab-color-borders` (voir **modules/collections/COLLECTIONS_THEME_CSS.md**).

## Fichiers CSS

Les styles sont définis dans :
- `assets/css/poke-hub-events-front.css` - Styles pour les événements et Pokémon sauvages
- `assets/css/poke-hub-bonus-front.css` - Styles pour les bonus
- `assets/css/poke-hub-special-events-single.css` - Styles pour les pages single d'événements
- `modules/collections/assets/css/collections-front.css` - Styles du module Collections (front)

