# Codes amis publics, IP et visiteurs non connectés

Ce document décrit le comportement du formulaire public (shortcodes `[poke_hub_friend_codes]` et `[poke_hub_vivillon]`), le stockage de l’IP et l’administration. Pour la **purge du cache de page** (Nginx) et le comportement côté visiteurs anonymes, voir [CACHE_AND_NGINX_PURGE.md](../CACHE_AND_NGINX_PURGE.md).

## Colonne `anonymous_ip` (table `user_profiles`)

- **Rôle** : enregistrer la **dernière adresse IP connue** lors d’une sauvegarde du profil / du code ami.
- **Front (HTTP)** : l’IP est lue via `poke_hub_get_client_ip()` (fallback `REMOTE_ADDR`) et n’est enregistrée que si elle est valide/non vide.
- **Mise à jour front** : pour une ligne existante, `anonymous_ip` est mise à jour **uniquement si la nouvelle IP diffère** de la valeur déjà stockée.
- **Admin/staff** : par défaut, l’édition d’un profil **préserve** `anonymous_ip` (pas d’écrasement par l’IP du back-office).
- **Override admin explicite** : le formulaire admin permet `Keep` / `Replace` / `Clear` :
  - `Keep` : conserve la valeur actuelle,
  - `Replace` : remplace par l’IP saisie (si format valide),
  - `Clear` : vide la colonne.
- **Contexte sans IP** (CLI, cron, etc.) : la colonne n’est **pas effacée** ; elle n’est tout simplement pas mise à jour.
- Le nom de colonne reste `anonymous_ip` pour compatibilité ; la valeur s’applique à **toutes** les lignes où une sauvegarde a eu lieu depuis le web.

## Administration

- **Liste** Poké HUB → User Profiles : colonne **« Last IP »** (`anonymous_ip`).
- **Édition** d’un profil : ligne **« Last recorded IP »** + contrôle admin (`Keep` / `Replace` / `Clear`) pour gérer explicitement la valeur.

## Visiteurs non connectés (formulaire public)

### Pseudo Pokémon GO obligatoire

- Le champ **Pokémon GO Username** est **requis** (HTML `required` + validation JS + validation PHP).
- Longueur max alignée sur la colonne BDD (191 caractères).

### Logique métier (résumé)

- **Nouvelle fiche** (nouveau couple code / identité) : limitation **cookie** + **au plus une création anonyme par IP sur 48 h** (parmi les lignes avec `anonymous_ip` renseigné).
- **Mise à jour d’une fiche existante** identifiée par le **code ami** ou le **même pseudo** (normalisé, insensible à la casse) : fenêtre **48 h** entre deux mises à jour sur la même ligne (sauf compte connecté, sans cette limite côté ce flux).
- **Changement de réseau (ex. 4G → Wi‑Fi)** : si le **pseudo soumis est le même** que celui déjà enregistré sur la ligne, la mise à jour du **code ami** (et des autres champs du formulaire) reste possible même si l’IP a changé. L’IP en base est alors **actualisée**.
- **Changement de pseudo** depuis une **IP différente** de celle enregistrée : refus avec message invitant à **se connecter** (notice **warning** côté front). Depuis la **même IP** que celle enregistrée, le renommage reste autorisé.
- **Conflits** (code ami et pseudo qui renvoient à deux lignes différentes) : message d’erreur explicite.

### Notices front

- Les shortcodes transmettent un type de message optionnel `message_type` (`error`, `warning`, `success`, `info`) pour afficher les blocs `me5rine-lab-form-message-*` de la même façon que les autres messages (voir `docs/POKEHUB_CSS_CLASSES.md`).

## Utilisateurs connectés (même page codes amis)

- Pas de limite 48 h ni de quota « nouvelle fiche » sur ce flux (identification par compte WordPress).
- Si le profil a **déjà un code ami** enregistré, le titre et le bouton du formulaire affichent **« Update My Friend Code »** (traduisible) au lieu de **« Add My Friend Code »**.
- L’IP du profil peut être mise à jour depuis le front si l’IP détectée diffère de la dernière valeur stockée.

## Visibilité du code ami sur les listes publiques (`friend_code_public`)

- **Colonne** : `user_profiles.friend_code_public` (0 = privé, 1 = public). Les soumissions depuis les pages **codes amis / Vivillon** mettent le code en **public** (comportement attendu pour ces formulaires).
- **Listes** `[poke_hub_friend_codes]` et `[poke_hub_vivillon]` : la requête passe par `poke_hub_get_public_friend_codes()` qui n’affiche que les lignes **publiques** (valeur `1` ; les anciennes lignes avec `NULL` restent affichées pour compatibilité) et exclut les codes **masqués** après signalements (`friend_code_hidden`), lorsque ces colonnes existent.
- **Profil membre** (shortcode `[poke_hub_user_profile]` et onglet UM `modules/user-profiles/templates/um-user-pokehub-profile.php`) : case à cocher du type *afficher mon code ami publiquement sur mon profil* — si elle est **décochée**, le code ne doit **pas** apparaître sur les listes publiques (ni en lecture seule sur le profil des autres visiteurs, selon le rendu du template).
- **Admin** Poké HUB → User Profiles : champ **Friend Code Visibility** sur la fiche d’édition ; même règle pour l’inclusion dans les listes.

### Durcissement des formulaires (`friend_code_public_present`)

Pour éviter qu’un formulaire **incomplet** (thème surchargé, copie de template obsolète) n’envoie une sauvegarde **sans** intention explicite sur la visibilité :

- Les formulaires fournis par le plugin envoient un champ caché **`friend_code_public_present`** avec la case `friend_code_public`.
- **`poke_hub_save_user_profile()`** ne met à jour `friend_code_public` que lorsque cette intention est présente dans les données soumises ; sinon la valeur **déjà en base** est conservée sur une ligne existante (évite de repasser un profil privé en public par erreur).

Si vous **personnalisez** un template de formulaire profil, reproduisez ce couple (champ caché + checkbox) ou n’incluez pas `friend_code_public` dans le tableau passé à `poke_hub_save_user_profile()` pour laisser la valeur existante inchangée.

## Drapeaux pays (selects + tuiles)

- Les options pays injectent un attribut `data-icon="..."` quand une URL de drapeau est disponible.
- Le rendu Select2 affiche automatiquement le drapeau à gauche du texte (formulaire profil, formulaire codes amis, filtres et admin).
- Les tuiles de codes amis affichent aussi le drapeau à gauche de la valeur **Country**.
- Les drapeaux sont chargés à distance (flagcdn) : aucune image à enregistrer en médiathèque.

### Helpers concernés

- `poke_hub_resolve_country_flag_iso2()` : résolution ISO2 depuis clé/libellé pays (inclut les pays custom).
- `poke_hub_get_country_flag_icon_url()` : génération de l’URL de drapeau (filtrable).
- `poke_hub_get_country_option_flag_data_attr()` : attribut `data-icon` prêt à injecter dans `<option>`.
- `poke_hub_get_country_flag_icon_url_for_display()` : URL drapeau à partir de la valeur pays affichée en liste.

### Personnalisation

- Filtre `poke_hub_country_flag_iso2` pour forcer/ajuster la résolution ISO2.
- Filtre `poke_hub_country_flag_icon_url` pour changer la source d’images (CDN tiers, assets locaux, etc.).

### Alignement visuel (tuiles)

- Les classes CSS `user-profiles-friend-code-meta-item--inline-icon`, `user-profiles-friend-code-meta-value`, `user-profiles-friend-code-meta-raster` et `user-profiles-friend-code-meta-text` assurent l’alignement vertical label/icone/texte.
- Le layout est calé sur une hauteur d’icône de 20px pour éviter le décalage typographique entre label (`strong`) et valeur.

## Fichiers principaux

- Logique d’ajout / mise à jour : `modules/user-profiles/functions/user-profiles-friend-codes-helpers.php` (dont `poke_hub_get_public_friend_codes()`)
- Sauvegarde profil + IP : `modules/user-profiles/functions/user-profiles-helpers.php` (`poke_hub_save_user_profile`)
- Formulaire : `modules/user-profiles/public/user-profiles-friend-codes-form.php`
- Shortcodes : `user-profiles-friend-codes-shortcode.php`, `user-profiles-vivillon-shortcode.php`
- Schéma BDD / migration : `includes/pokehub-db.php` (`anonymous_ip`, index)

## Migration

- Les sites existants reçoivent la colonne via la migration du module (ex. passage en admin ou hooks d’activation). Les nouvelles installations l’ont dès la création de la table.

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
