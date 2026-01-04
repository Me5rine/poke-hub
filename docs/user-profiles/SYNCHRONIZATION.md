# Synchronisation user_profiles ↔ subscription_accounts

## Vue d'ensemble

La table `user_profiles` stocke les profils Pokémon GO et utilise `subscription_accounts` comme source de vérité pour les IDs (user_id et discord_id).

## Prérequis

- Le plugin **Me5rine LAB** doit être actif (requis pour le préfixe global et la table `subscription_accounts`)
- Le module `user-profiles` ne peut pas être activé si Me5rine LAB n'est pas actif

## Synchronisation automatique

### Depuis subscription_accounts vers user_profiles

Quand `subscription_accounts` est mis à jour, vous pouvez déclencher la synchronisation en appelant :

```php
do_action('poke_hub_sync_user_profile_from_subscription', $user_id);
```

Ou directement :

```php
if (function_exists('poke_hub_sync_user_profile_ids_from_subscription')) {
    poke_hub_sync_user_profile_ids_from_subscription($user_id);
}
```

Cette fonction :
- Récupère le `discord_id` depuis `subscription_accounts` pour le `user_id` donné
- Met à jour le profil dans `user_profiles` avec le `discord_id` si le profil existe

### Depuis user_profiles vers subscription_accounts

La synchronisation se fait automatiquement lors de la sauvegarde d'un profil :
- Si on sauvegarde avec seulement `user_id`, le `discord_id` est récupéré depuis `subscription_accounts` et stocké dans `user_profiles`
- Si on sauvegarde avec seulement `discord_id`, le `user_id` est récupéré depuis `subscription_accounts` et stocké dans `user_profiles`

## Intégration avec d'autres plugins

### Hook à utiliser dans votre plugin de subscription

Si vous avez un plugin qui gère `subscription_accounts`, ajoutez cette ligne après chaque insertion/mise à jour :

```php
// Après avoir inséré/mis à jour dans subscription_accounts
do_action('poke_hub_sync_user_profile_from_subscription', $user_id);
```

**Note de sécurité :** Ce hook est sécurisé et peut être appelé même si la table `user_profiles` n'existe pas encore. La fonction vérifie l'existence de la table avant d'exécuter la synchronisation.

### Exemple complet

```php
// Dans votre plugin qui gère subscription_accounts
function my_plugin_update_subscription_account($user_id, $discord_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscription_accounts'; // ou votre préfixe global
    
    // Votre logique d'insertion/mise à jour
    $wpdb->replace($table_name, [
        'user_id' => $user_id,
        'provider_slug' => 'discord',
        'external_user_id' => $discord_id,
        'is_active' => 1,
        // ... autres champs
    ]);
    
    // Déclencher la synchronisation vers user_profiles
    do_action('poke_hub_sync_user_profile_from_subscription', $user_id);
}
```

## Actions WordPress disponibles

### `poke_hub_sync_user_profile_from_subscription`
Déclenché pour synchroniser un profil depuis `subscription_accounts`.

**Paramètres :**
- `$user_id` (int) : WordPress user ID

**Usage :**
```php
do_action('poke_hub_sync_user_profile_from_subscription', $user_id);
```

### `poke_hub_user_profile_saved`
Déclenché après la sauvegarde d'un profil Pokémon GO.

**Paramètres :**
- `$user_id` (int|null) : WordPress user ID (peut être null si seulement discord_id)
- `$profile` (array) : Données du profil sauvegardées
- `$discord_id` (string|null) : Discord ID (peut être null si seulement user_id)

**Usage :**
```php
add_action('poke_hub_user_profile_saved', function($user_id, $profile, $discord_id) {
    // Votre logique
}, 10, 3);
```

## Fonctions disponibles

### `poke_hub_sync_user_profile_ids_from_subscription($user_id)`
Synchronise les IDs depuis `subscription_accounts` vers `user_profiles`.

**Paramètres :**
- `$user_id` (int) : WordPress user ID

**Retourne :**
- `bool` : Succès ou échec

### `poke_hub_get_discord_id_from_user_id($user_id)`
Récupère le Discord ID depuis `subscription_accounts`.

**Paramètres :**
- `$user_id` (int) : WordPress user ID

**Retourne :**
- `string|null` : Discord ID ou null si non trouvé

### `poke_hub_get_user_id_from_discord_id($discord_id)`
Récupère le WordPress user ID depuis `subscription_accounts`.

**Paramètres :**
- `$discord_id` (string) : Discord ID

**Retourne :**
- `int|null` : WordPress user ID ou null si non trouvé

## Notes importantes

1. **Source de vérité** : `subscription_accounts` est la source de vérité pour les IDs. Les IDs dans `user_profiles` sont des copies/cache pour la performance.

2. **Synchronisation bidirectionnelle** : 
   - `subscription_accounts` → `user_profiles` : via l'action `poke_hub_sync_user_profile_from_subscription`
   - `user_profiles` → `subscription_accounts` : automatique lors de la sauvegarde (récupère depuis subscription_accounts)

3. **Performance** : Stocker les IDs dans `user_profiles` évite les JOIN à chaque requête, améliorant les performances.

4. **Flexibilité** : Permet d'avoir des profils avec seulement `user_id` (utilisateur WordPress sans Discord) ou seulement `discord_id` (bot Discord).

