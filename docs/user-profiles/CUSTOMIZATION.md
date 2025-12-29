# User Profiles Module - Customization Guide

## Centralized Data Definitions

All lists and options are centralized in a single location to avoid duplication. This allows you to modify them in one place and have the changes apply everywhere.

### Location

All default data definitions are in:
```
modules/user-profiles/includes/user-profiles-data.php
```

### Available Lists

#### 1. Teams (`poke_hub_get_teams()`)

Default teams:
- `instinct` - Instinct (Yellow)
- `mystic` - Mystic (Blue)
- `valor` - Valor (Red)

#### 2. Reasons (`poke_hub_get_reasons()`)

Default reasons:
- `xp` - Earn XP
- `send_gifts` - Send gifts
- `open_gifts` - Open gifts
- `join_raids` - Join raids
- `invite_raids` - Invite to raids
- `trade` - Trade

## How to Customize Lists

### Method 1: Using WordPress Filters (Recommended)

Add this code to your theme's `functions.php` or a custom plugin:

```php
/**
 * Customize Pokémon GO teams list
 */
function my_custom_pokemon_teams($teams) {
    // Add a new team
    $teams['team_rocket'] = __('Team Rocket', 'poke-hub');
    
    // Remove a team (optional)
    unset($teams['instinct']);
    
    // Modify an existing label
    $teams['mystic'] = __('Mystic Team (Best Team!)', 'poke-hub');
    
    return $teams;
}
add_filter('poke_hub_user_profiles_teams', 'my_custom_pokemon_teams');

/**
 * Customize reasons list
 */
function my_custom_pokemon_reasons($reasons) {
    // Add a new reason
    $reasons['remote_raids'] = __('Remote raids', 'poke-hub');
    
    // Remove a reason (optional)
    unset($reasons['trade']);
    
    // Reorder reasons (arrays maintain order in PHP 7+)
    return [
        'xp'           => $reasons['xp'],
        'remote_raids' => $reasons['remote_raids'],
        'join_raids'   => $reasons['join_raids'],
        'invite_raids' => $reasons['invite_raids'],
        'send_gifts'   => $reasons['send_gifts'],
        'open_gifts'   => $reasons['open_gifts'],
    ];
}
add_filter('poke_hub_user_profiles_reasons', 'my_custom_pokemon_reasons');
```

### Method 2: Modify the Data File Directly

You can directly edit `modules/user-profiles/includes/user-profiles-data.php`:

```php
function poke_hub_get_default_teams() {
    return [
        'instinct' => __('Instinct (Yellow)', 'poke-hub'),
        'mystic'   => __('Mystic (Blue)', 'poke-hub'),
        'valor'    => __('Valor (Red)', 'poke-hub'),
        'team_rocket' => __('Team Rocket', 'poke-hub'), // Add your custom team
    ];
}
```

**⚠️ Warning**: Direct modification will be lost when the plugin is updated. Use Method 1 (filters) instead.

## How It Works

1. **Single Source of Truth**: All lists are defined once in `user-profiles-data.php`
2. **WordPress Filters**: Functions use `apply_filters()` to allow customization
3. **Automatic Validation**: The validation in `poke_hub_save_user_profile()` automatically uses the same list
4. **Consistency**: All admin forms, front-end forms, and shortcodes use the same functions

## Usage in Code

All parts of the plugin use the same functions:

```php
// Get teams list (uses centralized data + filters)
$teams = poke_hub_get_teams();

// Get reasons list (uses centralized data + filters)
$reasons = poke_hub_get_reasons();

// Validation automatically uses the same list
// In poke_hub_save_user_profile(), validation uses:
$allowed_teams = array_keys(poke_hub_get_teams());
```

## Adding New List Types

If you need to add a new type of list:

1. Add the default data in `includes/user-profiles-data.php`:
   ```php
   function poke_hub_get_default_new_list() {
       return [
           'option1' => __('Option 1', 'poke-hub'),
           'option2' => __('Option 2', 'poke-hub'),
       ];
   }
   ```

2. Create a public function in `functions/user-profiles-helpers.php`:
   ```php
   function poke_hub_get_new_list() {
       $list = poke_hub_get_default_new_list();
       return apply_filters('poke_hub_user_profiles_new_list', $list);
   }
   ```

3. Use it everywhere with `poke_hub_get_new_list()` instead of hardcoding values.

