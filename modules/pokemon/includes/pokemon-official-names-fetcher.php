<?php
// File: modules/pokemon/includes/pokemon-official-names-fetcher.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère les noms officiels d'un Pokémon depuis Bulbapedia via l'API MediaWiki.
 * 
 * Bulbapedia (https://bulbapedia.bulbagarden.net/) a les noms officiels dans toutes les langues.
 * 
 * @param int $dex_number Numéro de Pokédex (1-1010)
 * @param string $name_en Nom anglais du Pokémon (optionnel, pour trouver la page)
 * @return array|false Tableau des noms par langue ['en' => '...', 'fr' => '...', ...] ou false en cas d'erreur
 */
function poke_hub_pokemon_fetch_official_names_from_bulbapedia($dex_number, $name_en = '') {
    $dex_number = (int) $dex_number;
    if ($dex_number <= 0) {
        return false;
    }

    // Cache pour éviter trop d'appels API
    $cache_key = 'poke_hub_bulbapedia_names_' . $dex_number;
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    // Essayer de trouver le nom de la page Bulbapedia
    $page_title = '';
    if (!empty($name_en)) {
        // Format Bulbapedia : "Bulbasaur (Pokémon)" ou juste "Bulbasaur"
        $page_title = ucfirst($name_en) . ' (Pokémon)';
    } else {
        // Fallback : essayer avec le numéro de Pokédex
        $page_title = sprintf('Pokémon #%03d', $dex_number);
    }

    // Appel à l'API MediaWiki de Bulbapedia (POST requis pour l'API MediaWiki)
    $api_url = 'https://bulbapedia.bulbagarden.net/w/api.php';
    
    // Récupérer le contenu de la page
    $response = wp_remote_post($api_url, [
        'timeout' => 30, // Augmenté à 30 secondes pour éviter les timeouts
        'connect_timeout' => 10,
        'user-agent' => 'Poke-Hub WordPress Plugin',
        'body' => [
            'action' => 'parse',
            'page' => $page_title,
            'prop' => 'text',
            'format' => 'json',
        ],
    ]);

    if (is_wp_error($response)) {
        // Si la page avec "(Pokémon)" n'existe pas, essayer sans
        if (!empty($name_en)) {
            $page_title = ucfirst($name_en);
            $response = wp_remote_post($api_url, [
                'timeout' => 30, // Augmenté à 30 secondes
                'connect_timeout' => 10,
                'user-agent' => 'Poke-Hub WordPress Plugin',
                'body' => [
                    'action' => 'parse',
                    'page' => $page_title,
                    'prop' => 'text',
                    'format' => 'json',
                ],
            ]);
        }
        
        if (is_wp_error($response)) {
            return false;
        }
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data) || empty($data['parse']['text']['*'])) {
        return false;
    }

    $html = $data['parse']['text']['*'];

    // Parser le HTML pour extraire les noms depuis l'infobox
    // L'infobox Bulbapedia contient les noms dans différentes langues
    $names = [];

    // Utiliser DOMDocument pour parser
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // Chercher dans l'infobox (table avec class "roundy" ou "infobox")
    // Les noms sont généralement dans des lignes avec des labels comme "French", "German", etc.
    $infobox_rows = $xpath->query('//table[contains(@class, "roundy")]//tr | //table[contains(@class, "infobox")]//tr');

    foreach ($infobox_rows as $row) {
        $cells = $row->getElementsByTagName('td');
        if ($cells->length < 2) {
            continue;
        }

        $label = trim($cells->item(0)->textContent);
        $value = trim($cells->item(1)->textContent);

        // Mapping des labels Bulbapedia vers nos codes de langue
        $label_mapping = [
            'English' => 'en',
            'Japanese' => 'ja',
            'Japanese (transliteration)' => 'ja',
            'French' => 'fr',
            'German' => 'de',
            'Italian' => 'it',
            'Spanish' => 'es',
            'Korean' => 'ko',
        ];

        // Chercher aussi dans les liens si le texte contient des liens
        if (empty($value)) {
            $links = $cells->item(1)->getElementsByTagName('a');
            if ($links->length > 0) {
                $value = trim($links->item(0)->textContent);
            }
        }

        // Nettoyer la valeur (enlever les caractères spéciaux, parenthèses, etc.)
        $value = preg_replace('/\s*\([^)]*\)\s*/', '', $value); // Enlever les parenthèses
        $value = trim($value);

        if (!empty($value) && isset($label_mapping[$label])) {
            $lang = $label_mapping[$label];
            
            // Pour le japonais et le coréen, extraire uniquement la partie dans la langue
            // Format attendu : "キャタピー Caterpie" ou "캐터피 Caterpie" ou "キャタピー キャタピー Caterpie"
            // On veut seulement "キャタピー" ou "캐터피" (sans la prononciation en alphabet latin)
            if ($lang === 'ja' || $lang === 'ko') {
                // Détecter où commence la prononciation en alphabet latin (A-Z, a-z)
                // On prend tout ce qui est avant la première lettre latine
                // Les caractères japonais/coréens sont dans les ranges Unicode appropriés (non-ASCII)
                
                // Méthode 1 : Prendre tous les caractères non-ASCII consécutifs au début
                // Cela capture les caractères japonais/coréens et ignore la prononciation latine
                if (preg_match('/^([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)/u', $value, $matches)) {
                    // Prendre seulement la partie avec les caractères non-ASCII (japonais/coréens)
                    // Cela peut inclure plusieurs mots séparés par des espaces, mais pas de lettres latines
                    $value = trim($matches[1]);
                } else {
                    // Méthode 2 : Si pas de caractères non-ASCII, séparer par espace et prendre la première partie
                    $parts = preg_split('/\s+/', $value, 2);
                    if (!empty($parts[0])) {
                        $value = trim($parts[0]);
                    }
                }
            }
            
            $names[$lang] = $value;
        }
    }

    // Si on n'a pas trouvé via l'infobox, chercher dans le titre de la page
    if (empty($names['en']) && !empty($data['parse']['title'])) {
        $page_title_clean = $data['parse']['title'];
        // Enlever " (Pokémon)" si présent
        $page_title_clean = preg_replace('/\s*\(Pokémon\)\s*$/', '', $page_title_clean);
        $names['en'] = $page_title_clean;
    }

    if (empty($names)) {
        return false;
    }

    // Mettre en cache pour 30 jours
    set_transient($cache_key, $names, 30 * DAY_IN_SECONDS);

    return $names;
}

/**
 * Récupère les noms officiels d'un Pokémon depuis Bulbapedia (alias pour compatibilité).
 * 
 * @param int $dex_number Numéro de Pokédex (1-1010)
 * @return array|false Tableau des noms par langue ou false en cas d'erreur
 */
function poke_hub_pokemon_fetch_official_names_from_pokeapi($dex_number) {
    // PokeAPI ne fournit pas les noms multilingues, utiliser Bulbapedia à la place
    return poke_hub_pokemon_fetch_official_names_from_bulbapedia($dex_number);
}

/**
 * Récupère les noms officiels d'une attaque depuis Bulbapedia.
 * 
 * @param string $move_name Nom de l'attaque en anglais (ex: "Tackle")
 * @return array|false Tableau des noms par langue ou false en cas d'erreur
 */
function poke_hub_pokemon_fetch_move_official_names_from_bulbapedia($move_name) {
    $move_name = trim($move_name);
    if (empty($move_name)) {
        return false;
    }

    // Cache
    $cache_key = 'poke_hub_bulbapedia_move_' . md5(strtolower($move_name));
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    // Format du titre Bulbapedia : "Tackle (move)" ou "Tackle"
    $page_title = ucfirst($move_name) . ' (move)';

    // Appel à l'API MediaWiki de Bulbapedia (POST requis)
    $api_url = 'https://bulbapedia.bulbagarden.net/w/api.php';
    
    $response = wp_remote_post($api_url, [
        'timeout' => 30, // Augmenté à 30 secondes pour éviter les timeouts
        'connect_timeout' => 10,
        'user-agent' => 'Poke-Hub WordPress Plugin',
        'body' => [
            'action' => 'parse',
            'page' => $page_title,
            'prop' => 'text',
            'format' => 'json',
        ],
    ]);

    if (is_wp_error($response)) {
        // Essayer sans "(move)"
        $page_title = ucfirst($move_name);
        $response = wp_remote_post($api_url, [
            'timeout' => 30, // Augmenté à 30 secondes pour éviter les timeouts
            'connect_timeout' => 10,
            'user-agent' => 'Poke-Hub WordPress Plugin',
            'body' => [
                'action' => 'parse',
                'page' => $page_title,
                'prop' => 'text',
                'format' => 'json',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data) || empty($data['parse']['text']['*'])) {
        return false;
    }

    $html = $data['parse']['text']['*'];
    
    // Vérifier si c'est une redirection
    if (stripos($html, 'redirectMsg') !== false || stripos($html, 'Redirect to:') !== false) {
        // Parser le HTML pour extraire l'URL de redirection
        $dom_redirect = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom_redirect->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath_redirect = new DOMXPath($dom_redirect);
        // Chercher le lien dans le message de redirection (plusieurs sélecteurs possibles)
        $redirect_links = $xpath_redirect->query('//div[contains(@class, "redirectMsg")]//a[@href] | //ul[contains(@class, "redirectText")]//a[@href]');
        
        if ($redirect_links->length > 0) {
            $redirect_href = $redirect_links->item(0)->getAttribute('href');
            // Extraire le titre de la page depuis l'URL (format: /wiki/Vise_Grip_(move))
            if (preg_match('/\/wiki\/(.+)$/', $redirect_href, $matches)) {
                $redirect_title = urldecode(str_replace('_', ' ', $matches[1]));
                
                // Faire un nouvel appel API avec le titre de redirection
                $redirect_response = wp_remote_post($api_url, [
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'user-agent' => 'Poke-Hub WordPress Plugin',
                    'body' => [
                        'action' => 'parse',
                        'page' => $redirect_title,
                        'prop' => 'text',
                        'format' => 'json',
                    ],
                ]);
                
                if (!is_wp_error($redirect_response)) {
                    $redirect_code = wp_remote_retrieve_response_code($redirect_response);
                    if ($redirect_code === 200) {
                        $redirect_body = wp_remote_retrieve_body($redirect_response);
                        $redirect_data = json_decode($redirect_body, true);
                        
                        if (is_array($redirect_data) && !empty($redirect_data['parse']['text']['*'])) {
                            $html = $redirect_data['parse']['text']['*'];
                        }
                    }
                }
            }
        }
    }
    
    $names = [];

    // Parser le HTML pour extraire les noms
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    
    // Méthode 1 : Chercher dans l'infobox (pour certaines pages)
    $infobox_rows = $xpath->query('//table[contains(@class, "roundy")]//tr | //table[contains(@class, "infobox")]//tr');

    foreach ($infobox_rows as $row) {
        $cells = $row->getElementsByTagName('td');
        if ($cells->length < 2) {
            continue;
        }

        $label = trim($cells->item(0)->textContent);
        $value = trim($cells->item(1)->textContent);

        if (empty($value)) {
            $links = $cells->item(1)->getElementsByTagName('a');
            if ($links->length > 0) {
                $value = trim($links->item(0)->textContent);
            }
        }

        $value = preg_replace('/\s*\([^)]*\)\s*/', '', $value);
        $value = trim($value);

        $label_mapping = [
            'English' => 'en',
            'Japanese' => 'ja',
            'French' => 'fr',
            'German' => 'de',
            'Italian' => 'it',
            'Spanish' => 'es',
            'Korean' => 'ko',
        ];

        if (!empty($value) && isset($label_mapping[$label])) {
            $lang = $label_mapping[$label];
            
            // Pour le japonais et le coréen, extraire uniquement la partie dans la langue
            if ($lang === 'ja' || $lang === 'ko') {
                if (preg_match('/^([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)/u', $value, $matches)) {
                    $value = trim($matches[1]);
                } else {
                    $parts = preg_split('/\s+/', $value, 2);
                    if (!empty($parts[0])) {
                        $value = trim($parts[0]);
                    }
                }
            }
            
            $names[$lang] = $value;
        }
    }
    
    // Méthode 2 : Chercher dans la section "In other languages" (tableau avec les traductions)
    $lang_section = $xpath->query('//h2[contains(., "In other languages")] | //h2[contains(., "in other languages")] | //span[@id="In_other_languages"] | //h2[contains(., "other languages")]');
    
    if ($lang_section->length > 0) {
        // Chercher le tableau suivant la section "In other languages"
        $lang_tables = $xpath->query('//h2[contains(., "In other languages")]/following-sibling::table[1] | //h2[contains(., "in other languages")]/following-sibling::table[1] | //span[@id="In_other_languages"]/following-sibling::table[1] | //h2[contains(., "other languages")]/following-sibling::table[1]');
        
        foreach ($lang_tables as $table) {
            // Chercher les lignes du tableau (tr)
            $rows = $table->getElementsByTagName('tr');
            
            // Parcourir les lignes de données (en sautant la première ligne d'en-tête)
            for ($i = 1; $i < $rows->length; $i++) {
                $row = $rows->item($i);
                $cells = $row->getElementsByTagName('td');
                
                // Si pas de cellules td, essayer avec th
                if ($cells->length === 0) {
                    $cells = $row->getElementsByTagName('th');
                }
                
                if ($cells->length >= 2) {
                    // Les premières cellules contiennent le nom de la langue
                    // Il peut y avoir plusieurs cellules pour une langue (rowspan)
                    // On prend la première cellule non vide
                    $lang_cell = null;
                    $lang_name = '';
                    
                    // Parcourir les cellules pour trouver celle qui contient le nom de la langue
                    for ($cell_idx = 0; $cell_idx < $cells->length - 1; $cell_idx++) {
                        $test_cell = $cells->item($cell_idx);
                        $cell_text = trim($test_cell->textContent);
                        
                        // Si la cellule contient du texte, c'est probablement le nom de la langue
                        if (!empty($cell_text)) {
                            $lang_cell = $test_cell;
                            break;
                        }
                    }
                    
                    // Si pas trouvé, prendre la première cellule
                    if (!$lang_cell) {
                        $lang_cell = $cells->item(0);
                    }
                    
                    // Chercher dans les liens d'abord
                    $links = $lang_cell->getElementsByTagName('a');
                    if ($links->length > 0) {
                        $link = $links->item(0);
                        $lang_name = trim($link->textContent);
                        
                        // Aussi vérifier le titre du lien (attribut title) qui peut contenir "Pokémon in France"
                        $title_attr = $link->getAttribute('title');
                        $href_attr = $link->getAttribute('href');
                        
                        // Si le texte contient "European French", c'est bon
                        // Sinon, si le href ou title contient "France", c'est probablement "European French"
                        if (stripos($lang_name, 'European French') === false && 
                            (stripos($title_attr, 'France') !== false || stripos($href_attr, 'France') !== false) &&
                            stripos($lang_name, 'French') !== false) {
                            // Le texte contient "French" mais pas "European", et le lien pointe vers France
                            // C'est probablement "European French" mais le texte n'est pas complet
                            $lang_name = 'European French';
                        } elseif (stripos($lang_name, 'French') === false && 
                                  (stripos($title_attr, 'France') !== false || stripos($href_attr, 'France') !== false)) {
                            // Pas de "French" dans le texte mais le lien pointe vers France = "European French"
                            $lang_name = 'European French';
                        }
                    } else {
                        $lang_name = trim($lang_cell->textContent);
                    }
                    
                    // Mapper le nom de la langue vers le code (pour les attaques, on garde toutes les langues)
                    // Pour le français, on privilégie "French" puis "European French" puis "Canadian French"
                    $lang_code = null;
                    $is_french = false;
                    $is_european_french = false;
                    
                    // Vérifier d'abord si c'est "French" (sans "European" ni "Canadian") - priorité
                    if (stripos($lang_name, 'French') !== false && 
                        stripos($lang_name, 'European') === false && 
                        stripos($lang_name, 'Canadian') === false) {
                        $lang_code = 'fr';
                        $is_french = true;
                        $is_european_french = true;
                    } elseif (stripos($lang_name, 'European French') !== false) {
                        // "European French" = français européen (fallback)
                        // On ne l'utilise que si on n'a pas déjà de "French"
                        if (!isset($names['fr'])) {
                            $lang_code = 'fr';
                            $is_french = true;
                            $is_european_french = true;
                        }
                    } elseif (stripos($lang_name, 'Canadian French') !== false) {
                        // Français canadien : on ne l'utilise que si on n'a pas déjà de français européen
                        if (!isset($names['fr'])) {
                            $lang_code = 'fr';
                            $is_french = true;
                        }
                    } elseif (stripos($lang_name, 'English') !== false) {
                        $lang_code = 'en';
                    } elseif (stripos($lang_name, 'Japanese') !== false) {
                        $lang_code = 'ja';
                    } elseif (stripos($lang_name, 'German') !== false) {
                        $lang_code = 'de';
                    } elseif (stripos($lang_name, 'Italian') !== false) {
                        $lang_code = 'it';
                    } elseif (stripos($lang_name, 'Spanish') !== false || stripos($lang_name, 'España') !== false || stripos($lang_name, 'Latin America') !== false) {
                        // Pour l'espagnol, on peut avoir "Spain" ou "Latin America"
                        // On privilégie "Spain" si disponible, sinon "Latin America"
                        if (stripos($lang_name, 'Spain') !== false && !isset($names['es'])) {
                            $lang_code = 'es';
                        } elseif (stripos($lang_name, 'Latin America') !== false && !isset($names['es'])) {
                            $lang_code = 'es';
                        } elseif (stripos($lang_name, 'Spanish') !== false) {
                            $lang_code = 'es';
                        }
                    } elseif (stripos($lang_name, 'Korean') !== false || stripos($lang_name, 'South Korea') !== false) {
                        $lang_code = 'ko';
                    } elseif (stripos($lang_name, 'Japan') !== false) {
                        $lang_code = 'ja';
                    }
                    
                    // Pour les attaques, on garde toutes les langues (en, fr, de, it, es, ja, ko)
                    if ($lang_code) {
                        // Pour le français, ne pas écraser si on a déjà une valeur et que c'est du français canadien
                        if ($lang_code === 'fr' && isset($names['fr']) && !$is_european_french) {
                            // On a déjà du français (probablement européen), on skip le canadien
                            continue;
                        }
                        
                        // Pour l'anglais, ne pas écraser
                        if ($lang_code === 'en' && isset($names['en'])) {
                            continue;
                        }
                        
                        // La dernière cellule contient le nom de l'attaque
                        $title_cell = $cells->item($cells->length - 1);
                        
                        // Récupérer le HTML brut de la cellule pour pouvoir nettoyer correctement les balises
                        $html_content = '';
                        foreach ($title_cell->childNodes as $node) {
                            if ($node->nodeType === XML_ELEMENT_NODE || $node->nodeType === XML_TEXT_NODE) {
                                $html_content .= $dom->saveHTML($node);
                            }
                        }
                        
                        // Si pas de HTML, utiliser textContent comme fallback
                        if (empty($html_content)) {
                            $html_content = $title_cell->textContent;
                        }
                        
                        // Nettoyer la valeur : enlever les références, notes, etc.
                        // Format possible : "Ligotage" ou "Constricción (games)<br />Envoltura (EP122–JN036)"
                        // ou "Coup Croix<sup><a>VIII</a>+</sup><br />Coup-Croix<sup>II–VII</sup>"
                        // ou "Lance-FlammesVI+Lance-FlammeI–V" (références collées au nom)
                        // On prend la première ligne (avant <br />)
                        $parts = preg_split('/<br\s*\/?>/i', $html_content, 2);
                        $value = $parts[0];
                        
                        // Enlever TOUTES les balises HTML et leur contenu (surtout <sup> avec références de génération)
                        // On doit faire ça AVANT strip_tags pour éviter que le contenu des balises soit conservé
                        $value = preg_replace('/<sup[^>]*>.*?<\/sup>/is', '', $value); // Enlever <sup>...</sup> et tout son contenu
                        $value = preg_replace('/<i[^>]*>.*?<\/i>/is', '', $value); // Enlever <i>...</i> (prononciations)
                        $value = preg_replace('/<span[^>]*>.*?<\/span>/is', '', $value); // Enlever <span>...</span>
                        $value = preg_replace('/<a[^>]*>.*?<\/a>/is', '', $value); // Enlever <a>...</a>
                        $value = strip_tags($value); // Enlever les balises HTML restantes
                        
                        // Nettoyer les références et notes restantes
                        // D'abord, enlever les références de génération collées au nom (ex: "Lance-FlammesVI+Lance-FlammeI–V")
                        // Pattern pour détecter les références collées : lettres suivies de chiffres romains/chiffres + symboles
                        $value = preg_replace('/([a-zA-ZÀ-ÿ\-]+)([IVX]+[+\-–][IVX0-9+\-–]+)/i', '$1', $value); // Enlever "NomVI+..." ou "NomI–V..."
                        $value = preg_replace('/([a-zA-ZÀ-ÿ\-]+)([0-9]+[+\-–][0-9IVX+\-–]+)/i', '$1', $value); // Enlever "Nom8+..." ou "Nom2–7..."
                        $value = preg_replace('/\s*\([^)]*\)\s*/', '', $value); // Enlever les parenthèses
                        $value = preg_replace('/\s*\*.*$/', '', $value); // Enlever les notes avec *
                        $value = preg_replace('/\s*\[.*?\]\s*/', '', $value); // Enlever les références [EP049]
                        $value = preg_replace('/\s*[IVX]+[+\-–].*$/', '', $value); // Enlever les références de génération (VIII+, II–VII, etc.)
                        $value = preg_replace('/\s*[0-9]+[+\-–].*$/', '', $value); // Enlever les références numériques (8+, 2–7, etc.)
                        $value = trim($value);
                        
                        // Pour le japonais et le coréen, extraire uniquement la partie dans la langue
                        if ($lang_code === 'ja' || $lang_code === 'ko') {
                            if (preg_match('/^([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)/u', $value, $matches)) {
                                $value = trim($matches[1]);
                            } else {
                                $parts = preg_split('/\s+/', $value, 2);
                                if (!empty($parts[0])) {
                                    $value = trim($parts[0]);
                                }
                            }
                        }
                        
                        // Ignorer les valeurs vides ou trop courtes
                        if (!empty($value) && strlen($value) > 1 && !isset($names[$lang_code])) {
                            $names[$lang_code] = $value;
                        }
                    }
                }
            }
        }
    }
    
    // Fallback pour le japonais depuis l'infobox ou le paragraphe qui suit si non trouvé dans le tableau "In other languages"
    if (empty($names['ja'])) {
        // Méthode 1 : Chercher directement dans le HTML brut de l'infobox avec regex
        // Format typique : **Cross Chop** (Japanese: **クロスチョップ** _Cross Chop_)
        // ou dans l'infobox : <th>...</th> avec "Japanese: **nom_japonais**"
        if (preg_match('/<table[^>]*class="[^"]*infobox[^"]*"[^>]*>.*?Japanese:\s*<b>([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)<\/b>/is', $html, $matches)) {
            $japanese_name = trim($matches[1]);
            if (!empty($japanese_name) && strlen($japanese_name) > 1) {
                $names['ja'] = $japanese_name;
            }
        } elseif (preg_match('/Japanese:\s*\*\*([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)\*\*/u', $html, $matches)) {
            // Chercher aussi le pattern avec ** (markdown bold)
            $japanese_name = trim($matches[1]);
            if (!empty($japanese_name) && strlen($japanese_name) > 1) {
                $names['ja'] = $japanese_name;
            }
        } elseif (preg_match('/Japanese:\s*([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)/u', $html, $matches)) {
            // Chercher le pattern simple sans bold
            $japanese_name = trim($matches[1]);
            if (!empty($japanese_name) && strlen($japanese_name) > 1) {
                $names['ja'] = $japanese_name;
            }
        }
        
        // Méthode 2 : Chercher dans l'en-tête de l'infobox via DOM
        if (empty($names['ja'])) {
            $infobox_tables = $xpath->query('//table[contains(@class, "infobox")]');
            
            foreach ($infobox_tables as $infobox_table) {
                // Chercher dans l'en-tête de l'infobox (première ligne ou cellule d'en-tête)
                $header_cells = $xpath->query('.//th | .//tr[1]//td | .//tr[1]//th', $infobox_table);
                
                foreach ($header_cells as $header_cell) {
                    $header_text = $header_cell->textContent;
                    
                    // Chercher le pattern "Japanese: **nom_japonais**" ou "Japanese: nom_japonais"
                    if (preg_match('/Japanese:\s*\*\*([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)\*\*/u', $header_text, $matches)) {
                        $japanese_name = trim($matches[1]);
                        if (!empty($japanese_name) && strlen($japanese_name) > 1) {
                            $names['ja'] = $japanese_name;
                            break 2;
                        }
                    } elseif (preg_match('/Japanese:\s*([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)/u', $header_text, $matches)) {
                        $japanese_name = trim($matches[1]);
                        if (!empty($japanese_name) && strlen($japanese_name) > 1) {
                            $names['ja'] = $japanese_name;
                            break 2;
                        }
                    }
                    
                    // Chercher aussi dans les spans avec class="explain" dans l'en-tête
                    $explain_spans = $xpath->query('.//span[@class="explain"]', $header_cell);
                    if ($explain_spans->length > 0) {
                        $japanese_name = trim($explain_spans->item(0)->textContent);
                        if (preg_match('/^([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)/u', $japanese_name, $matches)) {
                            $japanese_name = trim($matches[1]);
                            if (!empty($japanese_name) && strlen($japanese_name) > 1) {
                                $names['ja'] = $japanese_name;
                                break 2;
                            }
                        }
                    }
                }
            }
        }
        
        // Méthode 3 : Chercher dans les paragraphes qui suivent l'infobox (pour les cas comme "Vise Grip")
        if (empty($names['ja'])) {
            $infobox_tables = $xpath->query('//table[contains(@class, "infobox")]');
            
            foreach ($infobox_tables as $infobox_table) {
                // Chercher dans les 10 premiers paragraphes qui suivent l'infobox
                for ($i = 1; $i <= 10; $i++) {
                    $following_paragraphs = $xpath->query('./following-sibling::p[' . $i . ']', $infobox_table);
                    
                    if ($following_paragraphs->length > 0) {
                        $paragraph = $following_paragraphs->item(0);
                        
                        // Vérifier que le paragraphe contient "Japanese:" pour éviter de chercher dans tous les paragraphes
                        $paragraph_text = $paragraph->textContent;
                        if (stripos($paragraph_text, 'Japanese:') === false) {
                            continue;
                        }
                        
                        // Chercher le span avec class="explain" qui contient le nom japonais
                        $explain_spans = $xpath->query('.//span[@class="explain"]', $paragraph);
                        
                        if ($explain_spans->length > 0) {
                            $japanese_name = trim($explain_spans->item(0)->textContent);
                            
                            // Extraire uniquement la partie japonaise (non-ASCII)
                            if (preg_match('/^([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)/u', $japanese_name, $matches)) {
                                $japanese_name = trim($matches[1]);
                                if (!empty($japanese_name) && strlen($japanese_name) > 1) {
                                    $names['ja'] = $japanese_name;
                                    break 2;
                                }
                            }
                        }
                    } else {
                        break;
                    }
                }
            }
        }
    }

    if (empty($names['en']) && !empty($data['parse']['title'])) {
        $page_title_clean = preg_replace('/\s*\(move\)\s*$/i', '', $data['parse']['title']);
        $names['en'] = $page_title_clean;
    }

    if (empty($names)) {
        return false;
    }

    set_transient($cache_key, $names, 30 * DAY_IN_SECONDS);
    return $names;
}

/**
 * Récupère les noms officiels d'une attaque depuis PokeAPI (alias pour compatibilité).
 * 
 * @param string $move_name Nom de l'attaque en anglais
 * @return array|false Tableau des noms par langue ou false en cas d'erreur
 */
function poke_hub_pokemon_fetch_move_official_names_from_pokeapi($move_name) {
    // PokeAPI ne fournit pas les noms multilingues, utiliser Bulbapedia à la place
    return poke_hub_pokemon_fetch_move_official_names_from_bulbapedia($move_name);
}

/**
 * Récupère les noms officiels d'un type depuis Bulbapedia.
 * 
 * @param string $type_name Nom du type en anglais (ex: "Fire")
 * @return array|false Tableau des noms par langue ou false en cas d'erreur
 */
function poke_hub_pokemon_fetch_type_official_names_from_bulbapedia($type_name) {
    $type_name = trim($type_name);
    if (empty($type_name)) {
        return false;
    }

    // Cache
    $cache_key = 'poke_hub_bulbapedia_type_' . md5(strtolower($type_name));
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    // Format du titre Bulbapedia : "Fire (type)" ou "Fire type"
    $page_title = ucfirst($type_name) . ' (type)';

    // Appel à l'API MediaWiki de Bulbapedia (POST requis)
    $api_url = 'https://bulbapedia.bulbagarden.net/w/api.php';
    
    $response = wp_remote_post($api_url, [
        'timeout' => 30, // Augmenté à 30 secondes pour éviter les timeouts
        'connect_timeout' => 10,
        'user-agent' => 'Poke-Hub WordPress Plugin',
        'body' => [
            'action' => 'parse',
            'page' => $page_title,
            'prop' => 'text',
            'format' => 'json',
        ],
    ]);

    if (is_wp_error($response)) {
        // Essayer sans "(type)"
        $page_title = ucfirst($type_name) . ' type';
        $response = wp_remote_post($api_url, [
            'timeout' => 30, // Augmenté à 30 secondes pour éviter les timeouts
            'connect_timeout' => 10,
            'user-agent' => 'Poke-Hub WordPress Plugin',
            'body' => [
                'action' => 'parse',
                'page' => $page_title,
                'prop' => 'text',
                'format' => 'json',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data) || empty($data['parse']['text']['*'])) {
        return false;
    }

    $html = $data['parse']['text']['*'];
    
    // Vérifier si c'est une redirection
    if (stripos($html, 'redirectMsg') !== false || stripos($html, 'Redirect to:') !== false) {
        // Parser le HTML pour extraire l'URL de redirection
        $dom_redirect = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom_redirect->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath_redirect = new DOMXPath($dom_redirect);
        $redirect_links = $xpath_redirect->query('//div[contains(@class, "redirectMsg")]//a[@href] | //ul[contains(@class, "redirectText")]//a[@href]');
        
        if ($redirect_links->length > 0) {
            $redirect_href = $redirect_links->item(0)->getAttribute('href');
            if (preg_match('/\/wiki\/(.+)$/', $redirect_href, $matches)) {
                $redirect_title = urldecode(str_replace('_', ' ', $matches[1]));
                
                // Faire un nouvel appel API avec le titre de redirection
                $redirect_response = wp_remote_post($api_url, [
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'user-agent' => 'Poke-Hub WordPress Plugin',
                    'body' => [
                        'action' => 'parse',
                        'page' => $redirect_title,
                        'prop' => 'text',
                        'format' => 'json',
                    ],
                ]);
                
                if (!is_wp_error($redirect_response)) {
                    $redirect_code = wp_remote_retrieve_response_code($redirect_response);
                    if ($redirect_code === 200) {
                        $redirect_body = wp_remote_retrieve_body($redirect_response);
                        $redirect_data = json_decode($redirect_body, true);
                        
                        if (is_array($redirect_data) && !empty($redirect_data['parse']['text']['*'])) {
                            $html = $redirect_data['parse']['text']['*'];
                        }
                    }
                }
            }
        }
    }
    
    $names = [];

    // Parser le HTML pour extraire les noms
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    
    // Méthode 1 : Chercher dans l'infobox
    $infobox_rows = $xpath->query('//table[contains(@class, "roundy")]//tr | //table[contains(@class, "infobox")]//tr');

    foreach ($infobox_rows as $row) {
        $cells = $row->getElementsByTagName('td');
        if ($cells->length < 2) {
            continue;
        }

        $label = trim($cells->item(0)->textContent);
        $value = trim($cells->item(1)->textContent);

        if (empty($value)) {
            $links = $cells->item(1)->getElementsByTagName('a');
            if ($links->length > 0) {
                $value = trim($links->item(0)->textContent);
            }
        }

        $value = preg_replace('/\s*\([^)]*\)\s*/', '', $value);
        $value = trim($value);

        $label_mapping = [
            'English' => 'en',
            'Japanese' => 'ja',
            'French' => 'fr',
            'German' => 'de',
            'Italian' => 'it',
            'Spanish' => 'es',
            'Korean' => 'ko',
        ];

        if (!empty($value) && isset($label_mapping[$label])) {
            $lang = $label_mapping[$label];
            
            // Pour le japonais et le coréen, extraire uniquement la partie dans la langue
            if ($lang === 'ja' || $lang === 'ko') {
                if (preg_match('/^([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)/u', $value, $matches)) {
                    $value = trim($matches[1]);
                } else {
                    $parts = preg_split('/\s+/', $value, 2);
                    if (!empty($parts[0])) {
                        $value = trim($parts[0]);
                    }
                }
            }
            
            $names[$lang] = $value;
        }
    }
    
    // Méthode 2 : Chercher dans la section "In other languages" (comme pour les attaques)
    $lang_section = $xpath->query('//h2[contains(., "In other languages")] | //h2[contains(., "in other languages")] | //span[@id="In_other_languages"] | //h2[contains(., "other languages")]');
    
    if ($lang_section->length > 0) {
        // Chercher le tableau suivant la section "In other languages"
        $lang_tables = $xpath->query('//h2[contains(., "In other languages")]/following-sibling::table[1] | //h2[contains(., "in other languages")]/following-sibling::table[1] | //span[@id="In_other_languages"]/following-sibling::table[1] | //h2[contains(., "other languages")]/following-sibling::table[1]');
        
        foreach ($lang_tables as $table) {
            $rows = $table->getElementsByTagName('tr');
            
            // Structure similaire aux attaques : [Language cells] [Title cell]
            // Parcourir toutes les lignes (en sautant la première qui est l'en-tête)
            for ($i = 1; $i < $rows->length; $i++) {
                $row = $rows->item($i);
                $cells = $row->getElementsByTagName('td');
                
                if ($cells->length === 0) {
                    $cells = $row->getElementsByTagName('th');
                }
                
                if ($cells->length >= 2) {
                    // Les premières cellules contiennent le nom de la langue
                    // Certaines lignes ont colspan="2" pour la langue, donc on doit trouver la cellule avec le nom du type
                    // La structure est : [Language cells (peut être colspan=2)] [Title cell]
                    
                    // Chercher le nom de la langue dans les premières cellules
                    $lang_name = '';
                    
                    // Parcourir les cellules pour trouver celle qui contient le nom de la langue
                    // On s'arrête à l'avant-dernière cellule (la dernière contient le nom du type)
                    for ($cell_idx = 0; $cell_idx < $cells->length - 1; $cell_idx++) {
                        $cell = $cells->item($cell_idx);
                        
                        // Chercher dans les liens d'abord
                        $links = $cell->getElementsByTagName('a');
                        if ($links->length > 0) {
                            $link_text = trim($links->item(0)->textContent);
                            // Vérifier si c'est une langue connue
                            if (stripos($link_text, 'English') !== false || 
                                stripos($link_text, 'Japanese') !== false ||
                                stripos($link_text, 'French') !== false ||
                                stripos($link_text, 'German') !== false ||
                                stripos($link_text, 'Italian') !== false ||
                                stripos($link_text, 'Spanish') !== false ||
                                stripos($link_text, 'Korean') !== false) {
                                $lang_name = $link_text;
                                break;
                            }
                        }
                        
                        // Si pas de lien, vérifier le texte de la cellule
                        $cell_text = trim($cell->textContent);
                        if (empty($lang_name) && !empty($cell_text)) {
                            if (stripos($cell_text, 'English') !== false || 
                                stripos($cell_text, 'Japanese') !== false ||
                                stripos($cell_text, 'French') !== false ||
                                stripos($cell_text, 'German') !== false ||
                                stripos($cell_text, 'Italian') !== false ||
                                stripos($cell_text, 'Spanish') !== false ||
                                stripos($cell_text, 'Korean') !== false) {
                                $lang_name = $cell_text;
                                break;
                            }
                        }
                    }
                    
                    if (empty($lang_name)) {
                        // Fallback : prendre la première cellule
                        $lang_cell = $cells->item(0);
                        $links = $lang_cell->getElementsByTagName('a');
                        if ($links->length > 0) {
                            $lang_name = trim($links->item(0)->textContent);
                        } else {
                            $lang_name = trim($lang_cell->textContent);
                        }
                    }
                    
                    // Mapping des noms de langues vers codes
                    $lang_name_mapping = [
                        'English' => 'en',
                        'Japanese' => 'ja',
                        'French' => 'fr',
                        'German' => 'de',
                        'Italian' => 'it',
                        'Spanish' => 'es',
                        'Korean' => 'ko',
                    ];
                    
                    $lang_code = null;
                    foreach ($lang_name_mapping as $name => $code) {
                        if (stripos($lang_name, $name) !== false) {
                            $lang_code = $code;
                            break;
                        }
                    }
                    
                    if ($lang_code && !isset($names[$lang_code])) {
                        // La dernière cellule contient le nom du type
                        $title_cell = $cells->item($cells->length - 1);
                        $value = trim($title_cell->textContent);
                        
                        // Nettoyer la valeur
                        $value = preg_split('/<br\s*\/?>/i', $value)[0];
                        $value = preg_replace('/<sup[^>]*>.*?<\/sup>/is', '', $value);
                        $value = preg_replace('/<i[^>]*>.*?<\/i>/is', '', $value);
                        $value = preg_replace('/<span[^>]*>.*?<\/span>/is', '', $value);
                        $value = preg_replace('/<a[^>]*>.*?<\/a>/is', '', $value);
                        $value = strip_tags($value);
                        $value = preg_replace('/\s*\([^)]*\)\s*/', '', $value);
                        $value = preg_replace('/\s*\*.*$/', '', $value);
                        $value = preg_replace('/\s*\[.*?\]\s*/', '', $value);
                        $value = trim($value);
                        
                        // Pour le japonais et le coréen, extraire uniquement la partie dans la langue
                        if ($lang_code === 'ja' || $lang_code === 'ko') {
                            if (preg_match('/^([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)/u', $value, $matches)) {
                                $value = trim($matches[1]);
                            } else {
                                $parts = preg_split('/\s+/', $value, 2);
                                if (!empty($parts[0])) {
                                    $value = trim($parts[0]);
                                }
                            }
                        }
                        
                        if (!empty($value) && strlen($value) > 1) {
                            $names[$lang_code] = $value;
                        }
                    }
                }
            }
        }
    }

    if (empty($names['en']) && !empty($data['parse']['title'])) {
        $page_title_clean = preg_replace('/\s*\(type\)\s*$/i', '', $data['parse']['title']);
        $page_title_clean = preg_replace('/\s*type\s*$/i', '', $page_title_clean);
        $names['en'] = trim($page_title_clean);
    }

    if (empty($names)) {
        return false;
    }

    set_transient($cache_key, $names, 30 * DAY_IN_SECONDS);
    return $names;
}

/**
 * Récupère les noms officiels d'un type depuis PokeAPI (alias pour compatibilité).
 * 
 * @param string $type_name Nom du type en anglais
 * @return array|false Tableau des noms par langue ou false en cas d'erreur
 */
function poke_hub_pokemon_fetch_type_official_names_from_pokeapi($type_name) {
    // PokeAPI ne fournit pas les noms multilingues, utiliser Bulbapedia à la place
    return poke_hub_pokemon_fetch_type_official_names_from_bulbapedia($type_name);
}

/**
 * Filtre pour intégrer les noms officiels depuis Bulbapedia dans le processus d'import.
 * 
 * DÉSACTIVÉ : Les appels API Bulbapedia sont trop lents et bloquent l'import.
 * Les traductions seront récupérées après l'import via l'onglet Translation.
 * 
 * @param array $i18n_map Carte des traductions existantes
 * @return array Carte enrichie avec les noms officiels depuis Bulbapedia
 */
function poke_hub_pokemon_pokeapi_i18n_filter($i18n_map) {
    // DÉSACTIVÉ TEMPORAIREMENT : Les appels API Bulbapedia sont trop lents pendant l'import
    // Les traductions seront récupérées après via l'onglet Translation
    return $i18n_map;
    
    /* CODE DÉSACTIVÉ
    if (!is_array($i18n_map)) {
        $i18n_map = [
            'pokemon' => [],
            'moves'   => [],
            'types'   => [],
        ];
    }

    // Traiter les Pokémon
    // Note: Le filtre est appelé pendant l'import, donc on ne peut pas toujours récupérer le dex_number depuis la BDD
    // On essaie d'extraire depuis le slug ou on utilise une table de mapping en mémoire
    if (isset($i18n_map['pokemon']) && is_array($i18n_map['pokemon'])) {
        foreach ($i18n_map['pokemon'] as $slug => $entry) {
            if (is_array($entry)) {
                $name_en = $entry['en'] ?? '';
                if (!empty($name_en)) {
                    // Essayer d'extraire le numéro de Pokédex depuis le slug
                    // Format attendu : "bulbasaur" ou "001-bulbasaur"
                    $dex_number = 0;
                    if (preg_match('/^(\d+)-/', $slug, $matches)) {
                        $dex_number = (int) $matches[1];
                    } elseif (function_exists('pokehub_get_table')) {
                        // Chercher dans la base de données (si le Pokémon existe déjà)
                        global $wpdb;
                        $pokemon_table = pokehub_get_table('pokemon');
                        if ($pokemon_table) {
                            $row = $wpdb->get_row($wpdb->prepare(
                                "SELECT dex_number FROM {$pokemon_table} WHERE slug = %s LIMIT 1",
                                $slug
                            ));
                            if ($row) {
                                $dex_number = (int) $row->dex_number;
                            }
                        }
                    }

                    // Si on n'a pas le dex_number, on ne peut pas récupérer les noms officiels
                    // Les noms seront récupérés après l'import via l'outil "Fetch Official Names"
                    if ($dex_number > 0) {
                        $official_names = poke_hub_pokemon_fetch_official_names_from_bulbapedia($dex_number, $name_en);
                        if ($official_names !== false) {
                            // Fusionner avec les noms existants (ne pas écraser si déjà présents)
                            foreach ($official_names as $lang => $name) {
                                if (empty($i18n_map['pokemon'][$slug][$lang])) {
                                    $i18n_map['pokemon'][$slug][$lang] = $name;
                                }
                            }
                        }
                        // Pause pour respecter les limites de taux de l'API MediaWiki
                        usleep(1000000); // 1 seconde (Bulbapedia peut être lent) (Bulbapedia peut être plus lent)
                    }
                }
            }
        }
    }

    // Traiter les attaques
    if (isset($i18n_map['moves']) && is_array($i18n_map['moves'])) {
        foreach ($i18n_map['moves'] as $slug => $entry) {
            if (is_array($entry)) {
                $name_en = $entry['en'] ?? '';
                if (!empty($name_en)) {
                    // Récupérer depuis Bulbapedia
                    $official_names = poke_hub_pokemon_fetch_move_official_names_from_bulbapedia($name_en);
                    if ($official_names !== false) {
                        foreach ($official_names as $lang => $name) {
                            if (empty($i18n_map['moves'][$slug][$lang])) {
                                $i18n_map['moves'][$slug][$lang] = $name;
                            }
                        }
                    }
                    // Pause pour respecter les limites de taux
                    usleep(1000000); // 1 seconde (Bulbapedia peut être lent)
                }
            }
        }
    }

    // Traiter les types
    if (isset($i18n_map['types']) && is_array($i18n_map['types'])) {
        foreach ($i18n_map['types'] as $slug => $entry) {
            if (is_array($entry)) {
                $name_en = $entry['en'] ?? '';
                if (!empty($name_en)) {
                    // Récupérer depuis Bulbapedia
                    $official_names = poke_hub_pokemon_fetch_type_official_names_from_bulbapedia($name_en);
                    if ($official_names !== false) {
                        foreach ($official_names as $lang => $name) {
                            if (empty($i18n_map['types'][$slug][$lang])) {
                                $i18n_map['types'][$slug][$lang] = $name;
                            }
                        }
                    }
                    // Pause pour respecter les limites de taux
                    usleep(1000000); // 1 seconde (Bulbapedia peut être lent)
                }
            }
        }
    }

    return $i18n_map;
    */
}
// DÉSACTIVÉ : Le filtre est désactivé pour éviter les ralentissements pendant l'import
// Les traductions seront récupérées après via l'onglet Translation
// add_filter('poke_hub_pokemon_import_i18n', 'poke_hub_pokemon_pokeapi_i18n_filter', 10);

/**
 * Filtre pour enrichir les noms avec Bulbapedia lors de l'appel direct à poke_hub_pokemon_get_i18n_names.
 * Ce filtre est appelé après que le cache ait été construit, donc on peut récupérer depuis Bulbapedia.
 * 
 * @param array $names Tableau des noms par langue
 * @param string $category 'pokemon' | 'moves' | 'types'
 * @param string $slug Slug de l'élément
 * @param string $default_en Nom anglais par défaut
 * @return array Tableau enrichi avec les noms Bulbapedia
 */
function poke_hub_pokemon_bulbapedia_i18n_names_filter($names, $category, $slug, $default_en) {
    if (!is_array($names) || empty($default_en)) {
        return $names;
    }

    // DÉSACTIVÉ : Ne pas récupérer depuis Bulbapedia pendant l'import Game Master
    // Cela ralentit trop l'import. Les traductions seront récupérées après via l'onglet Translation.
    // Toujours retourner les noms sans modification pendant l'import
    if (defined('POKE_HUB_GM_IMPORT_IN_PROGRESS') && POKE_HUB_GM_IMPORT_IN_PROGRESS) {
        return $names;
    }
    
    // DÉSACTIVÉ TEMPORAIREMENT : Désactiver complètement ce filtre pour éviter les ralentissements
    // Les traductions seront récupérées manuellement via l'onglet Translation
    return $names;

    // Ne récupérer que si on n'a pas déjà toutes les langues
    $has_all_langs = true;
    $allowed_langs = ['en', 'fr', 'de', 'it', 'es', 'ja', 'ko'];
    foreach ($allowed_langs as $lang) {
        if ($lang !== 'en' && (empty($names[$lang]) || $names[$lang] === $default_en)) {
            $has_all_langs = false;
            break;
        }
    }

    // Si on a déjà toutes les langues, ne rien faire
    if ($has_all_langs) {
        return $names;
    }

    // Récupérer depuis Bulbapedia selon la catégorie
    $official_names = false;

    if ($category === 'pokemon') {
        // Pour les formes spéciales (Mega-, Copy, Fall 2019, etc.), ne pas récupérer depuis Bulbapedia
        // car Bulbapedia n'a que le nom de base. On préserve le nom complet existant.
        $is_special_form = false;
        $special_form_patterns = [
            '/\bMega\s*-?\s*/i',
            '/\bCopy\b/i',
            '/\bFall\s+\d{4}\b/i',
            '/\bSpring\s+\d{4}\b/i',
            '/\bSummer\s+\d{4}\b/i',
            '/\bWinter\s+\d{4}\b/i',
            '/\bX\b/i',
            '/\bY\b/i',
            '/\bAlola\b/i',
            '/\bGalar\b/i',
            '/\bHisui\b/i',
            '/\bPaldea\b/i',
        ];
        
        foreach ($special_form_patterns as $pattern) {
            if (preg_match($pattern, $default_en)) {
                $is_special_form = true;
                break;
            }
        }
        
        // Si c'est une forme spéciale, ne pas récupérer depuis Bulbapedia
        // On garde les traductions existantes (qui peuvent être vides, mais on ne les écrase pas)
        if ($is_special_form) {
            return $names;
        }
        
        // Pour les Pokémon, on a besoin du dex_number
        // Essayer de l'extraire depuis le slug ou le chercher en BDD
        $dex_number = 0;
        
        // Format attendu : "001-bulbasaur" ou "bulbasaur"
        if (preg_match('/^(\d+)-/', $slug, $matches)) {
            $dex_number = (int) $matches[1];
        } elseif (function_exists('pokehub_get_table')) {
            global $wpdb;
            $pokemon_table = pokehub_get_table('pokemon');
            if ($pokemon_table) {
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT dex_number FROM {$pokemon_table} WHERE slug = %s LIMIT 1",
                    $slug
                ));
                if ($row) {
                    $dex_number = (int) $row->dex_number;
                }
            }
        }

        if ($dex_number > 0 && function_exists('poke_hub_pokemon_fetch_official_names_from_bulbapedia')) {
            // Extraire le nom de base (sans les suffixes de forme spéciale) pour Bulbapedia
            $base_name = $default_en;
            foreach ($special_form_patterns as $pattern) {
                $base_name = preg_replace($pattern, '', $base_name);
            }
            $base_name = trim($base_name);
            
            if (!empty($base_name)) {
                $official_names = poke_hub_pokemon_fetch_official_names_from_bulbapedia($dex_number, $base_name);
            }
        }
    } elseif ($category === 'moves' && function_exists('poke_hub_pokemon_fetch_move_official_names_from_bulbapedia')) {
        $official_names = poke_hub_pokemon_fetch_move_official_names_from_bulbapedia($default_en);
    } elseif ($category === 'types' && function_exists('poke_hub_pokemon_fetch_type_official_names_from_bulbapedia')) {
        $official_names = poke_hub_pokemon_fetch_type_official_names_from_bulbapedia($default_en);
    }

    // Fusionner les noms Bulbapedia avec les noms existants
    if ($official_names !== false && is_array($official_names)) {
        foreach ($official_names as $lang => $name) {
            // Ne pas écraser l'anglais
            if ($lang === 'en') {
                continue;
            }
            
            // Ne remplacer que si la traduction est vide ou identique à l'anglais
            if (empty($names[$lang]) || $names[$lang] === $default_en) {
                $names[$lang] = $name;
            }
        }
    }

    return $names;
}
add_filter('poke_hub_pokemon_i18n_names', 'poke_hub_pokemon_bulbapedia_i18n_names_filter', 10, 4);

/**
 * Récupère les noms officiels pour les Pokémon existants depuis Bulbapedia.
 * 
 * @param int $limit Nombre maximum de Pokémon à traiter (0 = tous)
 * @param bool $force Force la mise à jour même si les noms existent déjà
 * @return array Statistiques : ['updated' => x, 'skipped' => y, 'errors' => z]
 */
function poke_hub_pokemon_fetch_official_names_existing($limit = 0, $force = false) {
    global $wpdb;

    if (!function_exists('pokehub_get_table')) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'Helper function not found.'];
    }

    $pokemon_table = pokehub_get_table('pokemon');
    if (!$pokemon_table) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'Pokemon table not found.'];
    }

    // Protection : limiter à 20 maximum pour éviter les timeouts
    if ($limit <= 0 || $limit > 20) {
        $limit = 5; // Limite par défaut de 5
    }

    // Construire la requête
    $where = "WHERE dex_number > 0 AND name_en != ''";
    if (!$force) {
        $where .= " AND (name_fr = '' OR name_fr = name_en)";
    }

    $limit_clause = $wpdb->prepare("LIMIT %d", $limit);

    $pokemon_list = $wpdb->get_results(
        "SELECT id, dex_number, name_en, name_fr, slug, extra
         FROM {$pokemon_table}
         {$where}
         ORDER BY dex_number ASC, id ASC
         {$limit_clause}"
    );

    if (empty($pokemon_list)) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'No Pokemon found to update.'];
    }

    $updated = 0;
    $skipped = 0;
    $errors = 0;

    // Patterns pour détecter les formes spéciales
    $special_form_patterns = [
        '/\bMega\s*-?\s*/i',
        '/\bPrimal\s*/i',
        '/\bCopy\b/i',
        '/\bFall\s+\d{4}\b/i',
        '/\bSpring\s+\d{4}\b/i',
        '/\bSummer\s+\d{4}\b/i',
        '/\bWinter\s+\d{4}\b/i',
        '/\bAlola\b/i',
        '/\bGalar\b/i',
        '/\bGalarian\b/i',
        '/\bHisui\b/i',
        '/\bPaldea\b/i',
        '/\bX\b/i',
        '/\bY\b/i',
    ];

    foreach ($pokemon_list as $pokemon) {
        $dex_number = (int) $pokemon->dex_number;
        if ($dex_number <= 0) {
            $skipped++;
            continue;
        }

        $name_en = trim($pokemon->name_en);
        if (empty($name_en)) {
            $skipped++;
            continue;
        }

        // Détecter si c'est une forme spéciale
        // On cherche des patterns qui indiquent une forme spéciale (Mega, Alola, Fall 2019, etc.)
        $is_special_form = false;
        $name_lower = strtolower($name_en);
        
        // Patterns pour détecter les formes spéciales (plus précis)
        // On cherche des mots-clés qui apparaissent APRÈS le nom de base
        $special_keywords = [
            ' mega',
            ' primal',
            ' copy',
            ' fall ',
            ' spring ',
            ' summer ',
            ' winter ',
            ' alola',
            ' galar',
            ' galarian',
            ' hisui',
            ' paldea',
            ' x',
            ' y',
        ];
        
        // Vérifier si le nom contient un mot-clé de forme spéciale (avec espace avant pour éviter les faux positifs)
        foreach ($special_keywords as $keyword) {
            if (strpos($name_lower, $keyword) !== false) {
                $is_special_form = true;
                break;
            }
        }
        
        // Vérifier aussi les patterns regex pour les cas plus complexes (Mega-Charizard, etc.)
        if (!$is_special_form) {
            foreach ($special_form_patterns as $pattern) {
                if (preg_match($pattern, $name_en)) {
                    $is_special_form = true;
                    break;
                }
            }
        }
        
        // Vérifier aussi les cas comme "Mega-Charizard X" ou "Charizard Mega X"
        if (!$is_special_form) {
            // Pattern pour détecter "Mega-" au début ou " Mega" au milieu
            if (preg_match('/^(mega|primal)[\s-]/i', $name_en) || preg_match('/\s(mega|primal)[\s-]/i', $name_en)) {
                $is_special_form = true;
            }
        }

        // Si c'est une forme spéciale, on ne récupère PAS depuis Bulbapedia
        // car Bulbapedia n'a que le nom de base. On skip cette forme.
        if ($is_special_form) {
            $skipped++;
            continue;
        }

        // Récupérer les noms officiels depuis Bulbapedia (seulement pour les formes de base)
        $official_names = poke_hub_pokemon_fetch_official_names_from_bulbapedia($dex_number, $name_en);
        
        if ($official_names === false || empty($official_names)) {
            $errors++;
            // Pause pour éviter de surcharger l'API
            usleep(1000000); // 1 seconde
            continue;
        }

        // Récupérer les données existantes
        $extra = [];
        if (!empty($pokemon->extra)) {
            $decoded = json_decode($pokemon->extra, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        if (!isset($extra['names']) || !is_array($extra['names'])) {
            $extra['names'] = [];
        }

        // Initialiser avec les valeurs existantes
        $extra['names']['en'] = $name_en;
        if (!empty($pokemon->name_fr)) {
            $extra['names']['fr'] = $pokemon->name_fr;
        }

        $has_updates = false;

        // Mettre à jour avec les noms officiels pour TOUTES les langues (fr, de, it, es, ja, ko)
        foreach ($official_names as $lang => $name) {
            if ($lang === 'en') {
                continue; // Ne pas écraser le nom anglais
            }
            
            // Pour le japonais et le coréen, extraire uniquement la partie dans la langue (sans prononciation latine)
            if ($lang === 'ja' || $lang === 'ko') {
                // Format attendu : "フシギダネ Fushigidane" ou "이상해씨 Isanghessi"
                // On veut seulement "フシギダネ" ou "이상해씨" (sans la prononciation en alphabet latin)
                if (preg_match('/^([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)/u', $name, $matches)) {
                    // Prendre seulement la partie avec les caractères non-ASCII (japonais/coréens)
                    $name = trim($matches[1]);
                } else {
                    // Méthode 2 : Si pas de caractères non-ASCII, séparer par espace et prendre la première partie
                    $parts = preg_split('/\s+/', $name, 2);
                    if (!empty($parts[0])) {
                        $name = trim($parts[0]);
                    }
                }
            }
            
            // Vérifier si on doit mettre à jour
            $existing = $extra['names'][$lang] ?? '';
            if ($force || empty($existing) || $existing === $name_en) {
                $extra['names'][$lang] = $name;
                $has_updates = true;
            }
        }

        if (!$has_updates) {
            $skipped++;
            continue;
        }

        // Mettre à jour name_fr si traduit en français
        $update_data = [
            'extra' => wp_json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        if (isset($extra['names']['fr']) && !empty($extra['names']['fr'])) {
            $update_data['name_fr'] = $extra['names']['fr'];
        }

        $result = $wpdb->update(
            $pokemon_table,
            $update_data,
            ['id' => (int) $pokemon->id],
            isset($update_data['name_fr']) ? ['%s', '%s'] : ['%s'],
            ['%d']
        );

        if ($result !== false) {
            $updated++;
        } else {
            $errors++;
        }

        // Pause pour éviter de surcharger l'API Bulbapedia
        usleep(1000000); // 1 seconde (Bulbapedia peut être lent)
    }

    return [
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'total' => count($pokemon_list),
    ];
}

/**
 * Récupère les noms officiels pour les attaques existantes depuis Bulbapedia.
 * 
 * @param int $limit Nombre maximum d'attaques à traiter (0 = tous)
 * @param bool $force Force la mise à jour même si les noms existent déjà
 * @return array Statistiques : ['updated' => x, 'skipped' => y, 'errors' => z]
 */
function poke_hub_attacks_fetch_existing_official_names($limit = 0, $force = false) {
    global $wpdb;

    if (!function_exists('pokehub_get_table')) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'Helper function not found.'];
    }

    $attacks_table = pokehub_get_table('attacks');
    if (!$attacks_table) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'Attacks table not found.'];
    }

    // Protection : limiter à 20 maximum pour éviter les timeouts
    if ($limit <= 0 || $limit > 20) {
        $limit = 5; // Limite par défaut de 5
    }

    // Construire la requête
    $where = "WHERE name_en != ''";
    if (!$force) {
        $where .= " AND (name_fr = '' OR name_fr = name_en)";
    }

    $limit_clause = $wpdb->prepare("LIMIT %d", $limit);

    $attacks_list = $wpdb->get_results(
        "SELECT id, name_en, name_fr, slug, extra
         FROM {$attacks_table}
         {$where}
         ORDER BY id ASC
         {$limit_clause}"
    );

    if (empty($attacks_list)) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'No attacks found to update.'];
    }

    $updated = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($attacks_list as $attack) {
        $name_en = trim($attack->name_en);
        if (empty($name_en)) {
            $skipped++;
            continue;
        }

        // Récupérer les noms officiels depuis Bulbapedia
        $official_names = poke_hub_pokemon_fetch_move_official_names_from_bulbapedia($name_en);
        
        if ($official_names === false || empty($official_names)) {
            $errors++;
            usleep(1000000); // 1 seconde (Bulbapedia peut être lent)
            continue;
        }

        // Récupérer les données existantes
        $extra = [];
        if (!empty($attack->extra)) {
            $decoded = json_decode($attack->extra, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        if (!isset($extra['names']) || !is_array($extra['names'])) {
            $extra['names'] = [];
        }

        // Initialiser avec les valeurs existantes
        $extra['names']['en'] = $name_en;
        if (!empty($attack->name_fr)) {
            $extra['names']['fr'] = $attack->name_fr;
        }

        $has_updates = false;

        // Pour les attaques, on met à jour toutes les langues (fr, de, it, es, ja, ko) comme pour les Pokémon et types
        foreach ($official_names as $lang => $name) {
            if ($lang === 'en') {
                continue; // Ne pas écraser le nom anglais
            }
            
            // Pour le japonais et le coréen, extraire uniquement la partie dans la langue (sans prononciation latine)
            if ($lang === 'ja' || $lang === 'ko') {
                // Format attendu : "キャタピー Caterpie" ou "캐터피 Caterpie"
                // On veut seulement "キャタピー" ou "캐터피" (sans la prononciation en alphabet latin)
                if (preg_match('/^([^\x00-\x7F\s]+(?:\s+[^\x00-\x7F\s]+)*)/u', $name, $matches)) {
                    // Prendre seulement la partie avec les caractères non-ASCII (japonais/coréens)
                    $name = trim($matches[1]);
                } else {
                    // Méthode 2 : Si pas de caractères non-ASCII, séparer par espace et prendre la première partie
                    $parts = preg_split('/\s+/', $name, 2);
                    if (!empty($parts[0])) {
                        $name = trim($parts[0]);
                    }
                }
            }
            
            // Vérifier si on doit mettre à jour
            $existing = $extra['names'][$lang] ?? '';
            
            if ($force || empty($existing) || $existing === $name_en) {
                $extra['names'][$lang] = $name;
                $has_updates = true;
            }
        }

        if (!$has_updates) {
            $skipped++;
            continue;
        }

        // Mettre à jour name_fr si traduit en français
        $update_data = [
            'extra' => wp_json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        if (isset($extra['names']['fr']) && !empty($extra['names']['fr'])) {
            $update_data['name_fr'] = $extra['names']['fr'];
        }

        $result = $wpdb->update(
            $attacks_table,
            $update_data,
            ['id' => (int) $attack->id],
            isset($update_data['name_fr']) ? ['%s', '%s'] : ['%s'],
            ['%d']
        );

        if ($result !== false) {
            $updated++;
        } else {
            $errors++;
        }

        // Pause pour éviter de surcharger l'API Bulbapedia
        usleep(1000000); // 1 seconde (Bulbapedia peut être lent)
    }

    return [
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'total' => count($attacks_list),
    ];
}

/**
 * Récupère les noms officiels pour les types existants depuis Bulbapedia.
 * 
 * @param int $limit Nombre maximum de types à traiter (0 = tous)
 * @param bool $force Force la mise à jour même si les noms existent déjà
 * @return array Statistiques : ['updated' => x, 'skipped' => y, 'errors' => z]
 */
function poke_hub_types_fetch_existing_official_names($limit = 0, $force = false) {
    global $wpdb;

    if (!function_exists('pokehub_get_table')) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'Helper function not found.'];
    }

    $types_table = pokehub_get_table('pokemon_types');
    if (!$types_table) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'Types table not found.'];
    }

    // Protection : limiter à 20 maximum pour éviter les timeouts
    if ($limit <= 0 || $limit > 20) {
        $limit = 5; // Limite par défaut de 5
    }

    // Construire la requête
    $where = "WHERE name_en != ''";
    if (!$force) {
        $where .= " AND (name_fr = '' OR name_fr = name_en)";
    }

    $limit_clause = $wpdb->prepare("LIMIT %d", $limit);

    $types_list = $wpdb->get_results(
        "SELECT id, name_en, name_fr, slug, extra
         FROM {$types_table}
         {$where}
         ORDER BY id ASC
         {$limit_clause}"
    );

    if (empty($types_list)) {
        return ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'No types found to update.'];
    }

    $updated = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($types_list as $type) {
        $name_en = trim($type->name_en);
        if (empty($name_en)) {
            $skipped++;
            continue;
        }

        // Récupérer les noms officiels depuis Bulbapedia
        $official_names = poke_hub_pokemon_fetch_type_official_names_from_bulbapedia($name_en);
        
        if ($official_names === false || empty($official_names)) {
            $errors++;
            usleep(1000000); // 1 seconde (Bulbapedia peut être lent)
            continue;
        }

        // Récupérer les données existantes
        $extra = [];
        if (!empty($type->extra)) {
            $decoded = json_decode($type->extra, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        if (!isset($extra['names']) || !is_array($extra['names'])) {
            $extra['names'] = [];
        }

        // Initialiser avec les valeurs existantes
        $extra['names']['en'] = $name_en;
        if (!empty($type->name_fr)) {
            $extra['names']['fr'] = $type->name_fr;
        }

        $has_updates = false;

        // Mettre à jour avec les noms officiels
        foreach ($official_names as $lang => $name) {
            if ($lang === 'en') {
                continue; // Ne pas écraser le nom anglais
            }
            
            // Vérifier si on doit mettre à jour
            $existing = $extra['names'][$lang] ?? '';
            if ($force || empty($existing) || $existing === $name_en) {
                $extra['names'][$lang] = $name;
                $has_updates = true;
            }
        }

        if (!$has_updates) {
            $skipped++;
            continue;
        }

        // Mettre à jour name_fr si traduit en français
        $update_data = [
            'extra' => wp_json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        if (isset($extra['names']['fr']) && !empty($extra['names']['fr'])) {
            $update_data['name_fr'] = $extra['names']['fr'];
        }

        $result = $wpdb->update(
            $types_table,
            $update_data,
            ['id' => (int) $type->id],
            isset($update_data['name_fr']) ? ['%s', '%s'] : ['%s'],
            ['%d']
        );

        if ($result !== false) {
            $updated++;
        } else {
            $errors++;
        }

        // Pause pour éviter de surcharger l'API Bulbapedia
        usleep(1000000); // 1 seconde (Bulbapedia peut être lent)
    }

    return [
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'total' => count($types_list),
    ];
}

