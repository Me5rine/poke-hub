# Codes amis publics, IP et visiteurs non connectés

Ce document décrit le comportement du formulaire public (shortcodes `[poke_hub_friend_codes]` et `[poke_hub_vivillon]`), le stockage de l’IP et l’administration.

## Colonne `anonymous_ip` (table `user_profiles`)

- **Rôle** : enregistrer la **dernière adresse IP connue** lors d’une sauvegarde du profil / du code ami.
- **Remplie** à chaque appel réussi à `poke_hub_save_user_profile()` lorsqu’une IP client est disponible (tous types de profils : classique WordPress, Discord, anonyme). En contexte HTTP, l’IP est obtenue via `poke_hub_get_client_ip()` si disponible, sinon `REMOTE_ADDR`.
- **Contexte sans IP** (CLI, cron, etc.) : la colonne n’est **pas effacée** ; elle n’est tout simplement pas mise à jour.
- Le nom de colonne reste `anonymous_ip` pour compatibilité ; la valeur s’applique à **toutes** les lignes où une sauvegarde a eu lieu depuis le web.

## Administration

- **Liste** Poké HUB → User Profiles : colonne **« Last IP »** (`anonymous_ip`).
- **Édition** d’un profil : champ informatif **« Last recorded IP »** (lecture seule), pour tout type de profil.

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

## Fichiers principaux

- Logique d’ajout / mise à jour : `modules/user-profiles/functions/user-profiles-friend-codes-helpers.php`
- Sauvegarde profil + IP : `modules/user-profiles/functions/user-profiles-helpers.php` (`poke_hub_save_user_profile`)
- Formulaire : `modules/user-profiles/public/user-profiles-friend-codes-form.php`
- Shortcodes : `user-profiles-friend-codes-shortcode.php`, `user-profiles-vivillon-shortcode.php`
- Schéma BDD / migration : `includes/pokehub-db.php` (`anonymous_ip`, index)

## Migration

- Les sites existants reçoivent la colonne via la migration du module (ex. passage en admin ou hooks d’activation). Les nouvelles installations l’ont dès la création de la table.
