# Shortcode Usage - Pokémon GO User Profile

## Overview

The `[poke_hub_user_profile]` shortcode displays the Pokémon GO user profile form or view, depending on user permissions.

## Basic Usage

### Simple usage (detects user automatically)

```php
echo do_shortcode('[poke_hub_user_profile]');
```

The shortcode will automatically:
- Detect the user from Ultimate Member context (`um_get_requested_user()` or `um_profile_id()`)
- Determine if the current user can edit the profile
- Display the edit form or view-only mode accordingly

### With specific user ID

```php
echo do_shortcode('[poke_hub_user_profile user_id="123"]');
```

### Force edit mode (if user has permission)

```php
echo do_shortcode('[poke_hub_user_profile mode="edit"]');
```

### Force view mode (read-only)

```php
echo do_shortcode('[poke_hub_user_profile mode="view"]');
```

## Integration with Ultimate Member

### Example 1: In your custom Ultimate Member template

```php
<?php
$user_id = um_get_requested_user();

if (function_exists('admin_lab_render_giveaway_promo_table')) {
    admin_lab_render_giveaway_promo_table();
}

echo do_shortcode('[admin_lab_participation_table]');
// Add Pokémon GO profile
echo do_shortcode('[poke_hub_user_profile]');
?>
```

### Example 2: In a tab-specific template

```php
<?php
$current_tab = isset($_GET['profiletab']) ? sanitize_key($_GET['profiletab']) : 'main';

if ($current_tab === 'pokehub-profile') {
    echo do_shortcode('[poke_hub_user_profile]');
}
?>
```

## Shortcode Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `user_id` | integer | `0` | User ID to display profile for. `0` means auto-detect from context. |
| `mode` | string | `auto` | Display mode: `auto`, `edit`, or `view`. `auto` determines based on permissions. |

## How It Works

1. **User Detection** (when `user_id="0"` or not provided):
   - First tries `um_profile_id()` (Ultimate Member function)
   - Then tries `um_get_requested_user()` (Ultimate Member function)
   - Falls back to `get_current_user_id()` if logged in

2. **Permission Check**:
   - User can edit if they are viewing their own profile OR are an administrator
   - Otherwise, view-only mode is displayed

3. **Form Submission**:
   - The form submits to the same page
   - Uses WordPress nonces for security
   - Saves data via `poke_hub_save_user_profile()` function
   - Shows success message after saving

## Styling

The shortcode uses Ultimate Member CSS classes by default:
- `.um-profile-note`
- `.pokehub-profile-tab`
- `.pokehub-profile-form`
- `.um-field`, `.um-field-label`, `.um-field-area`
- `.um-form-field`
- `.um-button`

You can customize the appearance by adding CSS in your theme targeting these classes.

## Files

- Shortcode function: `modules/user-profiles/public/user-profiles-shortcode.php`
- Helper functions: `modules/user-profiles/functions/user-profiles-helpers.php`
- CSS: Must be in your theme (see `../CSS_RULES.md` at docs root)
- JavaScript: `assets/js/poke-hub-user-profiles-um.js` (enqueued automatically)

