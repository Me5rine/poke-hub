# Contrat Front des Modules

Référence **générique** pour tous les modules front existants et à venir (Collections, Events, User Profiles, Games, etc.).

Objectif : définir un cadre commun de structure HTML, classes CSS, comportements JS et exigences UX/perf pour limiter les divergences entre modules.

## 1) Principes globaux

- Réutiliser en priorité les classes système `me5rine-lab-*`.
- Réserver le préfixe `pokehub-*` aux besoins métier d’un module.
- Éviter les styles inline, sauf cas justifié (ex. image dynamique issue d’URL).
- Séparer clairement :
  - **Structure** (HTML / classes),
  - **Skin** (CSS),
  - **Comportement** (JS + `data-*`).

## 2) Structure standard de page front

Pour une page module front classique :

```html
<div class="module-slug-wrap me5rine-lab-dashboard">
    <h2 class="me5rine-lab-title-large">...</h2>

    <div class="me5rine-lab-dashboard-header">
        <p class="me5rine-lab-subtitle">...</p>
        <div class="me5rine-lab-dashboard-header-actions">...</div>
    </div>

    <!-- contenu module -->
</div>
```

Règles :

- conserver `me5rine-lab-dashboard` au wrapper racine,
- garder `me5rine-lab-dashboard-header` comme base commune de header,
- utiliser `me5rine-lab-dashboard-header-actions` pour les CTA/actions.

## 3) Contrat header (modules)

### Header “liste/dashboard”

- Base obligatoire : `.me5rine-lab-dashboard-header`.
- Pas de refonte structurelle locale si le même pattern existe déjà sur d’autres modules.
- Les variations module doivent rester légères et documentées.

### Header “vue détaillée”

Si un module a une vue détaillée, utiliser la même logique :

- gauche = navigation/context,
- centre = titre + méta (progression, état),
- droite = actions.

Nommer les classes module de manière explicite (ex. `*-view-header-*`) et les documenter.

## 4) CSS : règles d’implémentation

- Source des règles communes : `docs/FRONT_CSS.md`, `docs/CSS_SYSTEM.md`, `docs/CSS_RULES.md`.
- Si thème Me5rine actif, charger/surcharger côté thème selon `docs/THEME_FRONT_CSS.md`.
- Toute nouvelle variable module doit :
  - être préfixée (`--pokehub-<module>-...`),
  - avoir un fallback vers `--me5rine-lab-*`,
  - être documentée dans la doc du module.

## 5) JS front : conventions

- Utiliser des hooks JS stables via `data-*` pour les éléments dynamiques.
- Éviter la dépendance à des sélecteurs purement décoratifs.
- Si une classe/attribut est un contrat JS, le documenter explicitement.
- Throttler/debouncer les handlers scroll/resize quand nécessaire.
- Favoriser les mises à jour ciblées plutôt que des recalculs globaux du DOM.

## 6) Accessibilité minimale requise

- Boutons interactifs : `button` (ou rôle + clavier si contrainte).
- États dynamiques : `aria-expanded`, `aria-hidden`, `aria-live` selon le cas.
- Icônes décoratives : `aria-hidden="true"`.
- Texte lecteur d’écran : `me5rine-lab-sr-only` si nécessaire.
- Focus visible cohérent sur les éléments clavier.

## 7) Responsive et sticky

- Définir les breakpoints dès la conception du composant.
- Tester les en-têtes sur desktop/tablette/mobile (et mode inspection).
- Pour les zones sticky/pinned :
  - documenter les offsets attendus,
  - documenter les classes d’état (ex. `*-pinned`),
  - éviter les collisions avec le header global du site.

## 8) Checklist “nouveau module front”

1. Créer un wrapper `me5rine-lab-dashboard`.
2. Utiliser `me5rine-lab-dashboard-header` pour l’en-tête principal.
3. Réutiliser boutons/formulaires/cartes système avant de créer de nouvelles classes.
4. Introduire des `data-*` dédiés pour les comportements JS.
5. Documenter les hooks stables (classes + `data-*`) du module.
6. Vérifier accessibilité clavier + responsive + performance scroll/resize.
7. Ajouter/mettre à jour la doc module (`README` ou doc dédiée).

## 9) Documentation module attendue

Chaque module front devrait contenir, au minimum :

- un `README.md` (entrée module),
- une section “Front contract” (ou un fichier dédié) listant :
  - structure header/page,
  - classes clés,
  - hooks JS (`data-*`),
  - variables CSS module,
  - comportements responsive/sticky spécifiques.

---

*Index de la documentation : [README du dossier docs](./README.md) · [Charte rédactionnelle](./REDACTION.md)*
