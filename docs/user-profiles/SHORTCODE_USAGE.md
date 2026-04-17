# Shortcode Usage - Pokémon GO User Profiles

## Overview

This module provides three shortcodes for displaying Pokémon GO user profiles and friend codes.

## Shortcodes Available

1. **`[poke_hub_user_profile]`** - Personal Pokémon GO profile form/view
2. **`[poke_hub_friend_codes]`** - Public friend codes listing with filters
3. **`[poke_hub_vivillon]`** - Vivillon patterns friend codes listing with filters

---

## 1. `[poke_hub_user_profile]` - Personal Profile

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

4. **Messages et notices** : Les shortcodes User Profiles affichent des blocs de message (succès, erreur, avertissement, information) avec les classes `me5rine-lab-form-message` et modificateurs :
   - **Succès (vert)** : profil sauvegardé, code ajouté
   - **Erreur (rouge)** : validation, échec sauvegarde
   - **Avertissement (orange)** : utilisateur non connecté, action à confirmer
   - **Information (bleu)** : notification (ex. changement d’email)
   Voir **`../POKEHUB_CSS_CLASSES.md`** (section « Notices ») pour les classes exactes et leur utilisation détaillée dans le module User Profiles.

## Styling

The shortcode uses Ultimate Member CSS classes by default:
- `.um-profile-note`
- `.pokehub-profile-tab`
- `.pokehub-profile-form`
- `.um-field`, `.um-field-label`, `.um-field-area`
- `.um-form-field`
- `.um-button`

You can customize the appearance by adding CSS in your theme targeting these classes.

---

## 2. `[poke_hub_friend_codes]` - Friend Codes Listing

Displays a public listing of friend codes with filtering options (country, team, reason).

### Basic Usage

```php
echo do_shortcode('[poke_hub_friend_codes]');
```

### Features

- Filter by country, team, or reason
- Copy friend code to clipboard with one click
- QR code display for easy scanning
- Pagination support
- Add/update form: logged-in users see **Update My Friend Code** when they already have a friend code saved; otherwise **Add My Friend Code**
- **Anonymous (not logged in)**:
  - **Pokémon GO username is required** (browser + JS + server validation)
  - New public row: cookie + **at most one new anonymous profile per IP per 48 hours** (among rows with a stored IP)
  - Updates to the **same listing** (matched by friend code or by same username): **48 hours** between updates on that row
  - **Same username, different IP** (e.g. mobile ↔ Wi‑Fi): still allowed to update friend code; stored IP is refreshed
  - **Username change** from a **different** IP than stored: blocked with a **warning** notice; login required to rename
- Server may return `message_type` (`error`, `warning`, etc.) for the same notice styles as elsewhere (`me5rine-lab-form-message-*`)

See **`FRIEND_CODES_PUBLIC_AND_IP.md`** (same folder) for full detail (French).

### URL Parameters

- `country` - Filter by country name
- `team` - Filter by team (valor, mystic, instinct)
- `reason` - Filter by reason
- `pg` - Page number for pagination

### Example with Filters

```
/friend-codes/?country=France&team=valor&pg=2
```

---

## 3. `[poke_hub_vivillon]` - Vivillon Patterns Listing

Displays friend codes filtered by Vivillon pattern with filtering options.

### Basic Usage

```php
echo do_shortcode('[poke_hub_vivillon]');
```

### Features

- Filter by Vivillon pattern (required for adding code)
- Filter by country
- Copy friend code to clipboard
- QR code display
- Pagination support
- Add/update form for logged-in users (pattern is required); same **Add / Update** labelling as friend codes when a code already exists
- **Anonymous** users: same rules as `[poke_hub_friend_codes]` (required username, IP / rate limits, notices) — see **`FRIEND_CODES_PUBLIC_AND_IP.md`**

### URL Parameters

- `pattern` - Filter by scatterbug pattern (required when adding)
- `country` - Filter by country name
- `pg` - Page number for pagination

### Example with Filters

```
/vivillon/?pattern=continental&country=France
```

---

## Automatic Page Creation

When the `user-profiles` module is activated, it can automatically create pages (if enabled in settings):

1. **Parent page**: `pokemon-go` (if it doesn't exist)
2. **Child pages**:
   - `friend-codes` with shortcode `[poke_hub_friend_codes]`
   - `vivillon` with shortcode `[poke_hub_vivillon]`

These pages are created as children of the `pokemon-go` page and can be managed from WordPress admin.

### Disable Automatic Page Creation

You can disable automatic page creation in **Poké HUB > Settings > General** under "User Profiles Settings".

When multiple sites share the same database, you can set the **Base URL for profile links** in **Poké HUB > Settings > Sources** (section "User Profiles Source", visible only when the User Profiles module is active). This ensures profile links in friend codes and Vivillon pages point to the correct site.

If disabled, you will need to manually create pages with the shortcodes:
- Create a page with slug `friend-codes` and add `[poke_hub_friend_codes]` in the content
- Create a page with slug `vivillon` and add `[poke_hub_vivillon]` in the content
- Optionally create a parent page `pokemon-go` and set the other pages as children

---

## Files

### Shortcodes
- `[poke_hub_user_profile]`: `modules/user-profiles/public/user-profiles-shortcode.php`
- `[poke_hub_friend_codes]`: `modules/user-profiles/public/user-profiles-friend-codes-shortcode.php`
- `[poke_hub_vivillon]`: `modules/user-profiles/public/user-profiles-vivillon-shortcode.php`

### Templates (Reusable)
- Friend codes list: `modules/user-profiles/public/user-profiles-friend-codes-list-template.php`
- Filters: `modules/user-profiles/public/user-profiles-friend-codes-filters-template.php`
- Header: `modules/user-profiles/public/user-profiles-friend-codes-header.php`
- Form: `modules/user-profiles/public/user-profiles-friend-codes-form.php`

### Helpers
- Helper functions: `modules/user-profiles/functions/user-profiles-helpers.php`
- Friend codes helpers: `modules/user-profiles/functions/user-profiles-friend-codes-helpers.php`
- Keycloak sync: `modules/user-profiles/functions/user-profiles-keycloak-sync.php`
- Pages creation: `modules/user-profiles/functions/user-profiles-pages.php`

### Assets
- CSS: `assets/css/user-profiles-friend-codes.css` (centralized in main plugin assets)
- JavaScript: `assets/js/user-profiles-friend-codes.js` (centralized in main plugin assets)
- JavaScript (Ultimate Member): `assets/js/poke-hub-user-profiles-um.js`

### Styling

The shortcodes use global CSS classes `me5rine-lab-*` (see `../CSS_SYSTEM.md` at docs root). Additional specific styles are in `user-profiles-friend-codes.css`. For **notices** (success, error, warning, info), see `../POKEHUB_CSS_CLASSES.md` (section « Notices ») — red / green / orange / blue convention and usage in User Profiles pages.

---

*Index de la documentation : [README du dossier docs](../README.md) · [Charte rédactionnelle](../REDACTION.md)*
