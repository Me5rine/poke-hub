# Data Centralization - User Profiles Module

## Overview

All lists (teams, reasons, etc.) are now centralized in a single location to avoid duplication. This ensures consistency and makes customization easier.

## Architecture

### 1. Central Data File

**Location**: `modules/user-profiles/includes/user-profiles-data.php`

This file contains all default data definitions:
- `poke_hub_get_default_teams()` - Default teams list
- `poke_hub_get_default_reasons()` - Default reasons list
- Future lists can be added here

### 2. Helper Functions

**Location**: `modules/user-profiles/functions/user-profiles-helpers.php`

Public functions that use the central data with WordPress filter support:
- `poke_hub_get_teams()` - Returns teams list (uses filter `poke_hub_user_profiles_teams`)
- `poke_hub_get_reasons()` - Returns reasons list (uses filter `poke_hub_user_profiles_reasons`)

### 3. Usage Everywhere

All parts of the plugin use the same functions:
- Admin interface (`admin/user-profiles-admin.php`)
- Shortcode (`public/user-profiles-shortcode.php`)
- Validation (`poke_hub_save_user_profile()`)

## Benefits

✅ **Single Source of Truth**: Modify lists in one place
✅ **No Duplication**: Lists are never hardcoded multiple times
✅ **Automatic Validation**: Validation uses the same source
✅ **WordPress Filters**: Easy customization via filters
✅ **Consistency**: All forms use the same data

## How to Modify Lists

See `CUSTOMIZATION.md` for detailed instructions.

### Quick Example

```php
// In your theme's functions.php
add_filter('poke_hub_user_profiles_teams', function($teams) {
    $teams['team_rocket'] = __('Team Rocket', 'poke-hub');
    return $teams;
});
```

## File Loading Order

The module loads files in this order:
1. `includes/user-profiles-data.php` (definitions)
2. `functions/user-profiles-helpers.php` (uses definitions)
3. Admin, public, and other files (use helpers)

This ensures definitions are available when helpers need them.

