# Cache page Nginx, Redis (objet) et purge Poké HUB

Ce document décrit comment Poké HUB **invalide le cache de page** (souvent **Nginx FastCGI**) et le **cache objet** (souvent **Redis Object Cache**) pour les pages dynamiques : listes d’**événements**, **codes amis** / **Vivillon**, et comment **brancher d’autres URLs** ensuite.

## Deux couches distinctes

| Couche | Rôle typique | Où ça se joue |
|--------|----------------|---------------|
| **Cache objet WordPress** (Redis, etc.) | Requêtes BDD, options, agrégats (`poke_hub_events_all`, etc.) | Redis / extension Object Cache |
| **Cache page HTML** (Nginx FastCGI) | Réponse HTML complète pour visiteurs **non connectés** | Fichiers sous une racine type `/run/nginx-cache` |

Modifier une clé Redis **ne remplace pas** la suppression du fichier Nginx : ce sont deux systèmes. Poké HUB appelle les deux quand c’est pertinent (purge module + TTL court côté objet).

## Prérequis serveur (Nginx Helper)

1. **Nginx Helper** configuré pour la purge (souvent mode **Delete local server cache files**).
2. Dans **`wp-config.php`**, définir la racine du cache FastCGI alignée sur Nginx, par exemple :

```php
define('RT_WP_NGINX_HELPER_CACHE_PATH', '/run/nginx-cache');
```

Sans cette constante (ou sans filtre équivalent ci‑dessous), la **suppression directe des fichiers** par Poké HUB est ignorée ; il reste alors la fonction `rt_wp_nginx_helper_purge_url()` si le plugin l’expose correctement.

La directive Nginx usuelle est : `fastcgi_cache_key "$scheme$request_method$host$request_uri";` avec `levels=1:2` — c’est ce modèle que le complément **suppression de fichier** reproduit.

## Fonctions globales (`includes/functions/pokehub-helpers.php`)

### `poke_hub_purge_nginx_cache( $urls, $purge_all = false )`

- **`$urls`** : une URL (string) ou un tableau d’URLs absolues (`https://exemple.fr/chemin/`).
- **`$purge_all`** : si `true`, purge globale via le helper (`rt_wp_nginx_helper_purge_all()` ou équivalent) — à utiliser avec parcimonie.

Comportement : appelle **`rt_wp_nginx_helper_purge_url()`** pour chaque URL si disponible, puis **`poke_hub_delete_nginx_fastcgi_cache_files_for_urls()`** en complément (calcul MD5 + chemins `levels=1:2` + `unlink`). Retourne `true` si une action a été tentée ou si au moins un fichier a été supprimé.

### `poke_hub_purge_module_cache( array $shortcodes, ?string $cache_group, ?string $cache_key )`

- Résout les **URLs** des pages contenant les shortcodes indiqués (contenu post, pages auto‑créées, meta Elementor `_elementor_data`, filtre d’extension).
- Appelle **`poke_hub_purge_nginx_cache( $urls )`**.
- Si **`$cache_group`** / **`$cache_key`** sont fournis : `wp_cache_delete` + `wp_cache_flush_group` lorsque disponible.

### `poke_hub_get_pages_with_shortcodes( array $shortcodes )`

Retourne les permaliens des pages / articles dont le contenu ou Elementor référencent les shortcodes demandés.

### `poke_hub_delete_nginx_fastcgi_cache_files_for_urls( array $urls )`

Suppression **fichier par fichier** du cache FastCGi (sans dépendre uniquement du helper). Retourne le nombre de fichiers supprimés. Désactivable via le filtre ci‑dessous.

### `poke_hub_nginx_fastcgi_cache_root()`

Racine du cache : `RT_WP_NGINX_HELPER_CACHE_PATH` + filtre `poke_hub_nginx_fastcgi_cache_root`.

## Filtres (extensions, thème, mu-plugin)

| Filtre | Rôle |
|--------|------|
| `poke_hub_purge_cache_urls_for_shortcodes` | `( $urls, $shortcodes )` — ajouter des URLs à purger pour un jeu de shortcodes. |
| `poke_hub_nginx_fastcgi_cache_root` | Forcer ou corriger le chemin disque du cache Nginx. |
| `poke_hub_supplement_nginx_fastcgi_file_cache_delete` | `false` pour désactiver la suppression directe des fichiers (rester uniquement sur le helper). |
| `poke_hub_events_send_nocache_headers_on_shortcode_output` | `false` pour ne pas envoyer `nocache_headers()` au rendu de `[poke_hub_events]`. |
| `poke_hub_events_enable_scheduled_front_cache_purge` | `false` pour désactiver le cron de purge des pages événements. |
| `poke_hub_events_front_cache_purge_interval` | Intervalle en secondes (min 30, max 86400 ; défaut **45**). |
| `poke_hub_events_fetch_all_cache_ttl` | TTL du cache objet de l’agrégat événements (défaut **90** s). |
| `poke_hub_profile_submission_affects_public_friend_listings` | Étendre les champs profil qui déclenchent la purge codes amis / Vivillon. |

## Événements (liste `[poke_hub_events]`)

- **Purge après sauvegardes admin** liées aux événements : appels à `poke_hub_purge_module_cache( ['poke_hub_events'], 'poke_hub_events', 'poke_hub_events_all' )` (voir helpers admin du module Events).
- **Cron** `poke_hub_events_front_cache_purge` : purge périodique (défaut **45 s**) pour les visiteurs anonymes quand un événement change de statut sans passage par l’admin — `modules/events/functions/events-helpers.php`.
- **En-têtes** : au rendu du shortcode, `nocache_headers()` + `X-Accel-Expires: 0` — `modules/events/public/shortcode-events.php`. Sur les pages singulières détectées, `template_redirect` envoie aussi des en‑têtes anti‑cache — même fichier `events-helpers.php`.
- **Compte à rebours en liste** : le script inline utilise l’horloge cliente (`Date.now()`) pour ne pas dépendre d’un `time()` PHP figé dans un HTML en cache — `modules/events/functions/events-render.php`.

**Limite** : si Nginx **ignore** `Cache-Control` / `X-Accel-Expires`, il faut une règle serveur (`fastcgi_no_cache` / bypass sur ces URLs ou respect des en‑têtes amont). Sinon, la purge fichier + le cron restent le filet de sécurité.

## Codes amis et Vivillon

- Après sauvegarde via **`poke_hub_save_user_profile()`** : purge si un champ « liste publique » a été soumis (code ami, équipe, pays, motif, pseudo, raisons, visibilité) — `modules/user-profiles/functions/user-profiles-helpers.php` (`poke_hub_profile_submission_affects_public_friend_listings`).
- Formulaire public / AJAX : `poke_hub_purge_module_cache( ['poke_hub_friend_codes', 'poke_hub_vivillon'] )` — `user-profiles-friend-codes-helpers.php`.
- Les URLs ciblées incluent les options **`poke_hub_user_profiles_page_friend-codes`** et **`poke_hub_user_profiles_page_vivillon`**, plus la détection Elementor.

## Branchement d’autres pages

1. **URLs connues** : depuis n’importe quel hook métier, appeler  
   `poke_hub_purge_nginx_cache( [ 'https://site.fr/ma-page/' ], false );`
2. **Pages liées à un shortcode** :  
   `poke_hub_purge_module_cache( ['mon_shortcode'], null, null );`  
   et éventuellement enrichir les URLs via **`poke_hub_purge_cache_urls_for_shortcodes`**.
3. **Groupe de cache objet** : passer le groupe / la clé à `poke_hub_purge_module_cache` pour invalider Redis en même temps.

## Désactivation du cron à la désactivation du module

Lorsque le module **events** est retiré des modules actifs, la tâche planifiée `poke_hub_events_front_cache_purge` est désinscrite — `includes/settings/settings-module-hooks.php`.

## Voir aussi

- [Codes amis publics et IP](./user-profiles/FRIEND_CODES_PUBLIC_AND_IP.md)
- [Routing événements spéciaux](./events/README-ROUTING.md)
- [Événements distants et sources](./events/EVENEMENTS-DISTANTS.md)

---

*Index de la documentation : [README du dossier docs](./README.md) · [Charte rédactionnelle](./REDACTION.md)*
