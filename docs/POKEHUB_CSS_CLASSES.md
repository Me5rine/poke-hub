# Classes CSS Poké HUB (`pokehub-`)

Ce document décrit les classes CSS **spécifiques au plugin** (préfixe `pokehub-`) pour l’affichage front-end. Elles complètent les classes génériques `me5rine-lab-*` et `admin-lab-*`. **Où est le CSS** : en production, le lot public est surtout dans le **thème** Me5rine (`css/poke-hub/`) — voir **[THEME_FRONT_CSS.md](./THEME_FRONT_CSS.md)**. Nommage produit / slug : voir **[REDACTION.md](./REDACTION.md)**.

## Préfixe des classes

**Préfixe des sélecteurs** : `pokehub-`

Toutes les classes listées ici commencent par ce préfixe pour limiter les conflits avec d’autres extensions ou le thème.

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
- `.pokehub-event-dates-block-wrapper` - Wrapper du bloc Gutenberg : habillage type carte (fond, bordure, ombre, padding) ; **pas** de séparateur entre le titre « Date » et la ligne de chips
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

Fichier CSS : `assets/css/poke-hub-bonus-front.css`.

#### Variables (thème — pas d’option admin)

Surcharge possible dans le thème ou une feuille additionnelle :

- `--pokehub-bonus-icon-color` — couleur des icônes (hérite par défaut des variables Admin Lab).
- `--pokehub-bonus-icon-bg` — fond de la pastille icône.
- `--pokehub-bonus-icon-radius` — rayon des pastilles dans les cartes.

Les SVG **inline** sont teintés via `currentColor` sur les formes (`path`, `circle`, etc.) : privilégier des SVG **monochromes** sur le bucket pour un rendu cohérent. Voir [BONUS_SOURCE_AND_BLOCKS.md](./BONUS_SOURCE_AND_BLOCKS.md).

#### Bloc Gutenberg et grille cartes
- `.pokehub-bonus-block-wrapper` — Wrapper du bloc Bonus (contient `h2.pokehub-block-title` + grille).
- `.pokehub-bonuses-grid` — Grille responsive des cartes.
- `.pokehub-bonus-card` — Carte (tuile carrée, ombre, hover).
- `.pokehub-bonus-card-inner` — Contenu interne (dégradé, padding).
- `.pokehub-bonus-image-wrapper` — Zone centrale : icône + badge ratio.
- `.pokehub-bonus-icon-wrap` — Pastille autour de l’icône (fond + padding).
- `.pokehub-bonus-icon--svg` — Conteneur flex du SVG inline.
- `.pokehub-bonus-image` — Balise `<img>` en repli raster.
- `.pokehub-bonus-badge` — Badge ratio (ex. `1/2`) en bas à droite de l’icône.
- `.pokehub-bonus-description` — Texte sous l’icône (cartes).

#### Shortcode `[pokehub-bonus]`
- `.pokehub-bonuses-shortcode` — Colonne d’articles.
- `.pokehub-bonus-item` — Ligne (flex).
- `.pokehub-bonus-item-image` — Colonne icône.
- `.pokehub-bonus-item-content` — Colonne texte (`h3`, `p`).

#### Bonus d’événement (injection `the_content` / `pokehub_render_post_bonuses`)
- `.pokehub-event-bonuses` — Section liste.
- `.pokehub-event-bonus` — Une ligne (flex).
- `.pokehub-event-bonus-image` — Icône (même logique `.pokehub-bonus-icon-wrap` que la grille).
- `.pokehub-event-bonus-content` — Texte.
- `.pokehub-event-bonus-title` — Titre.
- `.pokehub-event-bonus-desc` — Description spécifique à l’événement.
- `.pokehub-event-bonus-desc-global` — Description catalogue du bonus.

### Module Quêtes (Event Quests)

#### Bloc Gutenberg `pokehub/event-quests` (liste actuelle)

Fichier principal des styles : `assets/css/poke-hub-blocks-front.css`.

- `.pokehub-event-quests-block-wrapper` — wrapper du bloc
- `.pokehub-event-quests-list` — liste `<ul>` des quêtes
- `.pokehub-quest-item` — une quête (`<li>`)
- `.pokehub-quest-main` — ligne repliée (tâche + aperçu + chevron)
- `.pokehub-quest-task` / `.pokehub-quest-task-placeholder` — texte de la tâche
- `.pokehub-quest-rewards-preview` — bandeau d’aperçu (tuiles Pokémon + métas)
- `.pokehub-quest-preview-more` — badge `+N` (Pokémon au-delà des 3 vignettes)
- `.pokehub-quest-preview-other-count` — une ligne non-Pokémon : quantité réelle ; plusieurs lignes : libellé **`Other × M`**
- `.pokehub-quest-toggle` — ouverture / fermeture du détail
- `.pokehub-quest-details` — zone dépliée
- `.pokehub-quest-rewards-list` — grille des récompenses détaillées
- `.pokehub-quest-reward-item--pokemon` / `.pokehub-quest-reward-item--other` — ligne Pokémon vs autre
- `.pokehub-field-research-preview-tile` / `.pokehub-field-research-detail-tile` — tuiles (réutilise `.pokehub-wild-pokemon-card`)
- `.pokehub-quest-reward-cp` — conteneur flex des deux pastilles CP
- `.pokehub-quest-cp-box` / `.pokehub-quest-cp-box--min` / `.pokehub-quest-cp-box--max` — pastille min (gauche, plus discrète) puis max (droite), label au-dessus (`.pokehub-quest-cp-label`, `text-transform: uppercase` en CSS) et valeur (`.pokehub-quest-cp-value`)
- `.pokehub-quest-reward-resource-visual` — icône / visuel bonbon, objet, etc. ; si présent, le nom affiché dans `.pokehub-quest-reward-name` peut être réduit à **`×quantité`**

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

### Shop highlights (avatar + stickers en jeu)

Fichier : `assets/css/poke-hub-blocks-front.css`.

- `.pokehub-shop-highlights` — modificateur sur le wrapper du bloc (`pokehub-shop-avatar-highlights-block` ou `pokehub-shop-sticker-highlights-block`).
- `.pokehub-shop-highlights-panel` — carte (fond, bordure, ombre) englobant l’accroche et les tuiles.
- `.pokehub-shop-highlights-lead` — ligne du haut (flex) : image + paragraphe.
- `.pokehub-shop-highlights-lead__figure` — zone image (contain, largeur max).
- `.pokehub-shop-highlights-lead__text` — paragraphe d’accroche.
- `.pokehub-shop-highlights-panel__tiles` — zone liste sous le séparateur.
- `.pokehub-shop-highlights-panel__tiles-title` — sous-titre au-dessus de la grille.
- `.pokehub-shop-highlights-items` — modificateur sur `.pokehub-wild-pokemon-grid` (tuiles carrées héritées des classes Wild Pokémon).

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

## Notices (messages utilisateur)

Les notices sont des blocs de message affichés à l'utilisateur (succès, erreur, avertissement, information). Le plugin utilise le système commun **`me5rine-lab-form-message`** (défini dans **docs/FRONT_CSS.md**, **docs/CSS_RULES.md**).

### Convention des couleurs

| Type        | Couleur  | Classe modificateur              | Usage |
|------------|----------|-----------------------------------|--------|
| **Erreur** | Rouge    | `.me5rine-lab-form-message-error` | Échec, erreur de validation, problème bloquant |
| **Succès** | Vert     | `.me5rine-lab-form-message-success` | Confirmation (sauvegarde, mise à jour réussie) |
| **Avertissement** | Orange | `.me5rine-lab-form-message-warning` | Attention (données non enregistrées, action à confirmer) |
| **Information** | Bleu  | `.me5rine-lab-form-message-info` | Message informatif (changement d’email, aide contextuelle) |

Toute notice doit avoir la classe de base **`.me5rine-lab-form-message`** plus **une** des classes modificateurs ci‑dessus. Le contenu texte est placé dans un `<p>` à l’intérieur du bloc.

### Variables CSS (couleurs) — `assets/css/global-colors.css`

Les couleurs utilisées pour les notices (texte, bordures, fonds) sont définies dans **`assets/css/global-colors.css`** :

- **Succès (vert)** : texte / pastilles = `--admin-lab-color-var-green` (#42af13) (ligne 6) ; fonds/bordures de bloc = `--admin-lab-color-notice-sucess-background`, `--admin-lab-color-notice-sucess-border` (lignes 30-31).
- **Erreur (rouge)** : texte = `--admin-lab-color-red` (#df4848) (ligne 7) ; fonds/bordures = `--admin-lab-color-notice-error-background`, `--admin-lab-color-notice-error-border` (lignes 28-29).
- **Avertissement (orange)** : texte et bordures = `--admin-lab-color-notice-warning` (#ff9800) (ligne 32).
- **Information (bleu)** : texte, bordures et fonds = `--admin-lab-color-secondary` (#0485C8) (ligne 4). C’est la couleur secondaire du thème ; elle sert aux messages d’information (notification, aide contextuelle).

Pour les textes ou pastilles : succès = **ligne 6** (`--admin-lab-color-var-green`), erreur = **ligne 7** (`--admin-lab-color-red`), avertissement = **ligne 32** (`--admin-lab-color-notice-warning`), information = **ligne 4** (`--admin-lab-color-secondary`).

### Classes à utiliser

- **Base** : `.me5rine-lab-form-message`
- **Variantes** : `.me5rine-lab-form-message-success` (vert), `.me5rine-lab-form-message-error` (rouge), `.me5rine-lab-form-message-warning` (orange), `.me5rine-lab-form-message-info` (bleu)

Les styles (couleurs, bordures, fonds) sont définis dans le système CSS commun ; voir **docs/FRONT_CSS.md** (section « Messages et Notices Génériques ») et **docs/PLUGIN_INTEGRATION.md** (section « Messages et Notices »).

### Utilisation dans le module User Profiles

Les notices sont utilisées sur les pages liées au module **User Profiles** (shortcodes profil, codes amis, Vivillon). Voici où et comment elles sont affichées.

#### Page profil utilisateur (`[poke_hub_user_profile]`)

- **Succès (vert)** : après mise à jour du profil Pokémon GO (« Pokémon GO profile updated successfully »).  
  Classe : `me5rine-lab-form-message me5rine-lab-form-message-success`, id optionnel `poke-hub-profile-message`.
- **Information (bleu)** : message de notification (ex. après changement d’email).  
  Classe : `me5rine-lab-form-message me5rine-lab-form-message-info`, id `poke-hub-profile-notification`.
- **Erreur (rouge)** : message d’erreur (validation, échec sauvegarde).  
  Classe : `me5rine-lab-form-message me5rine-lab-form-message-error`, id optionnel `poke-hub-profile-message`.

Fichier : `modules/user-profiles/public/user-profiles-shortcode.php`.

#### Formulaire codes amis (`[poke_hub_friend_codes]`) et Vivillon (`[poke_hub_vivillon]`)

- **Succès / Erreur / Avertissement** : message après soumission (code ajouté, erreur, doublon, etc.).  
  Classe dynamique : `me5rine-lab-form-message me5rine-lab-form-message-<?php echo esc_attr($args['form_message_type']); ?>` avec `form_message_type` = `success`, `error` ou `warning`.
- **Avertissement (orange)** : utilisateur non connecté avec lien vers la page de connexion.  
  Classe : `me5rine-lab-form-message me5rine-lab-form-message-warning`.

Fichiers : `modules/user-profiles/public/user-profiles-friend-codes-form.php`, `user-profiles-friend-codes-shortcode.php`, `user-profiles-vivillon-shortcode.php`.

#### Template Ultimate Member (profil Poké HUB)

- **Succès (vert)** : confirmation après sauvegarde du profil dans le contexte UM.  
  Classe : `me5rine-lab-form-message me5rine-lab-form-message-success`.

Fichier : `modules/user-profiles/templates/um-user-pokehub-profile.php`.

#### Messages injectés en JavaScript (user-profiles)

Le JS peut afficher des notices dynamiques (validation, mise à jour pays, etc.) en créant des blocs avec les mêmes classes :

- **Erreur** : `.me5rine-lab-form-message-error` (ex. `user-profiles-friend-codes.js`, `poke-hub-user-profiles-um.js`).
- **Succès** : `.me5rine-lab-form-message-success` (ex. message « Country updated »).
- **Avertissement** : `.me5rine-lab-form-message-warning` (ex. décalage pays / pays du profil).

Pour garder une cohérence visuelle, utiliser **toujours** les classes `me5rine-lab-form-message` + modificateur (success / error / warning / info) et la convention rouge / vert / orange / bleu ci‑dessus.

## Exemples d'Utilisation

### Notices (erreur / succès / avertissement / information)

```html
<!-- Erreur (rouge) -->
<div class="me5rine-lab-form-message me5rine-lab-form-message-error">
    <p>Une erreur est survenue.</p>
</div>

<!-- Succès (vert) -->
<div class="me5rine-lab-form-message me5rine-lab-form-message-success">
    <p>Profil mis à jour avec succès.</p>
</div>

<!-- Avertissement (orange) -->
<div class="me5rine-lab-form-message me5rine-lab-form-message-warning">
    <p>Vous n'êtes pas connecté. Les données seront stockées localement.</p>
</div>

<!-- Information (bleu) -->
<div class="me5rine-lab-form-message me5rine-lab-form-message-info">
    <p>Un email de confirmation vous a été envoyé.</p>
</div>
```

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

**On utilise en priorité les classes du système CSS commun** (voir **docs/FRONT_CSS.md**, **docs/CSS_RULES.md**, **docs/CSS_SYSTEM.md**) : `me5rine-lab-dashboard`, `me5rine-lab-title-large`, `me5rine-lab-subtitle`, `me5rine-lab-dashboard-header`, `me5rine-lab-form-button`, `me5rine-lab-form-button-secondary`, `me5rine-lab-form-button-remove`, `me5rine-lab-form-block`, `me5rine-lab-form-field`, `me5rine-lab-form-label`, `me5rine-lab-form-input`, `me5rine-lab-form-select`, `me5rine-lab-form-message`, `me5rine-lab-state-message`, `me5rine-lab-card`, `me5rine-lab-card-name`, `me5rine-lab-card-meta`, `me5rine-lab-card-actions`, `me5rine-lab-sr-only`, `me5rine-lab-card-header`, `me5rine-lab-title-medium`. Les éléments portent ces classes ; le CSS ne fait que des surcharges (ex. `.pokehub-collections-grid .me5rine-lab-card`).

**Classes spécifiques Collections** (préfixe `pokehub-collections-` / `pokehub-collection-`) pour le layout et le JS uniquement :
- `.pokehub-collections-wrap` (+ `me5rine-lab-dashboard`) - Wrapper page gestion
- `.pokehub-collections-grid` - Grille 3 colonnes, cartes 16:9
- Les cartes sont des `li.me5rine-lab-card` ; pas de classe `.pokehub-collections-card`
- `.pokehub-collections-card-link`, `.pokehub-collections-card-bg` — lien et fond (pas d’équivalent thème) ; `.pokehub-collections-card-btn`, `.pokehub-collections-card-btn-settings`, `.pokehub-collections-card-btn-delete` (boutons icône + JS)
- `.pokehub-collection-view-wrap` (+ `me5rine-lab-dashboard`), `.pokehub-collection-tiles`, `.pokehub-collection-tile`, `.pokehub-collection-tile-status`, `.pokehub-status-{owned|missing|for_trade}`
- `.pokehub-collection-legend`, `.pokehub-collection-legend-item`, `.pokehub-collection-legend-dot`, `.pokehub-legend-owned`, `.pokehub-legend-for-trade`, `.pokehub-legend-missing` — légende : **possédé** = vert (contour + bulle = `--admin-lab-color-var-green`, fond = notice success), **à l'échange** = orange (notice warning), **manquant** = gris. Variables : `--admin-lab-color-notice-sucess-*`, `--admin-lab-color-var-green`, `--admin-lab-color-notice-warning`, `--me5rine-lab-border` (global-colors en dépendance)
- `.pokehub-collection-status-filters`, `.pokehub-collection-status-filters-inner`, `.pokehub-collection-status-filters-heading`, `.pokehub-collection-status-filters-checkboxes`, `.pokehub-collection-status-filter-label`, `.pokehub-collection-filter-status`, `.pokehub-collection-filter-empty-hint` — filtre **Afficher dans la grille** : affichage par statut (`owned` / `for_trade` / `missing` sur `data-status` et `data-filter-status`)
- `.pokehub-collections-options-additive`, `.pokehub-collections-options-specific-hint` — bloc options « en plus » vs message pour catégories spécifiques (masquage avec `.is-hidden`)
- `.pokehub-collection-multiselect-wrap`, `.pokehub-collection-multiselect-list-wrap`, `.pokehub-collection-multiselect-list`, `.pokehub-collection-multiselect-item`
- `.pokehub-collection-generation-block` — bloc details/summary par génération (summary = `me5rine-lab-title-medium`)
- `.pokehub-collections-drawer`, `.pokehub-collections-drawer-*` — panneau latéral (header = `me5rine-lab-card-header` + `me5rine-lab-title-medium`)
- `.pokehub-collections-modal`, `.pokehub-collections-modal-backdrop`, `.pokehub-collections-modal-content` (modal non défini dans FRONT_CSS)

**Variables** : le module utilise `--me5rine-lab-*` (FRONT_CSS) ; dégradés des cartes `--pokehub-collections-card-gradient-*`. Tuiles et légende = **exactement les notices** : `--admin-lab-color-notice-sucess-border`, `--admin-lab-color-notice-sucess-background`, `--admin-lab-color-notice-warning` (global-colors.css chargé en front avec le CSS collections). Voir **modules/collections/COLLECTIONS_THEME_CSS.md**.

## Fichiers CSS

- **Thème Me5rine Lab (source de vérité front)** : le bundle `css/poke-hub/poke-hub-front.css` importe les `parts/*` (événements, bonus, blocs, collections, profils, etc.) ; `css/poke-hub/poke-hub-late-overrides.css` ferme la cascade. Détail : **[THEME_FRONT_CSS.md](./THEME_FRONT_CSS.md)**.
- **Plugin** : `assets/css/` conserve surtout `global-colors.css`, l’**admin** et d’**éventuels** fichiers `poke-hub-*-front.css` si vous réactivez le lot packagé (`poke_hub_load_default_plugin_front_css` = `true` et présence des fichiers). Les noms historiques côté plugin (`poke-hub-events-front.css`, `poke-hub-bonus-front.css`, `poke-hub-collections-front.css`, etc.) correspondent aux morceaux reportés en `parts/` dans le thème.

---

*Index de la documentation : [README du dossier docs](README.md) · [Charte rédactionnelle](REDACTION.md)*
