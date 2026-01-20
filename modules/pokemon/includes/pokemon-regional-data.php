<?php
// File: modules/pokemon/includes/pokemon-regional-data.php
// UNE SEULE SOURCE DE VÉRITÉ pour TOUTES les données régionales
// Contient : régions géographiques + patterns Vivillon + tous les autres Pokémon régionaux

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get geographic regions data for seeding
 * 
 * @return array Array of regions with slug, name_fr, name_en, countries, description
 */
function poke_hub_get_regional_regions_data() {
    return [
        [
            'slug' => 'europe',
            'name_fr' => 'Europe',
            'name_en' => 'Europe',
            'countries' => [
                'Albanie', 'Allemagne', 'Andorre', 'Autriche', 'Belgique', 'Biélorussie',
                'Bosnie-Herzégovine', 'Bulgarie', 'Chypre', 'Croatie', 'Danemark', 'Espagne',
                'Estonie', 'Finlande', 'France', 'Grèce', 'Hongrie', 'Irlande', 'Islande',
                'Italie', 'Lettonie', 'Liechtenstein', 'Lituanie', 'Luxembourg', 'North Macedonia',
                'Malte', 'Moldavie', 'Monaco', 'Monténégro', 'Norvège', 'Pays-Bas', 'Pologne',
                'Portugal', 'République Tchèque', 'Roumanie', 'Royaume-Uni', 'Russie', 'Saint Marin',
                'Serbie', 'Slovaquie', 'Slovénie', 'Suède', 'Suisse', 'Ukraine', 'Vatican',
            ],
            'description' => '',
        ],
        [
            'slug' => 'asia',
            'name_fr' => 'Asie',
            'name_en' => 'Asia',
            'countries' => [
                'Afghanistan', 'Arménie', 'Azerbaïdjan', 'Bahreïn', 'Bangladesh', 'Bhoutan',
                'Birmanie', 'Brunei', 'Cambodge', 'Chine', 'Corée du Nord', 'Corée du Sud',
                'Géorgie', 'Hong Kong', 'Inde', 'Indonésie', 'Iran', 'Irak', 'Israël', 'Japon',
                'Jordanie', 'Kazakhstan', 'Koweït', 'Kirghizistan', 'Personne de la République démocratique du Laos',
                'Liban', 'Macao', 'Malaisie', 'Maldives', 'Mongolie', 'Népal', 'Oman', 'Pakistan',
                'Palestine', 'Philippines', 'Qatar', 'Arabie Saoudite', 'Singapour', 'Sri Lanka',
                'Syrie', 'Taïwan', 'Tadjikistan', 'Thaïlande', 'Timor-Leste', 'Turquie',
                'Turkménistan', 'Emirats Arabes Unis', 'Ouzbékistan', 'Viêtnam', 'Yemen',
            ],
            'description' => '',
        ],
        [
            'slug' => 'oceania',
            'name_fr' => 'Océanie',
            'name_en' => 'Oceania',
            'countries' => [
                'Australie', 'Fidji', 'Kiribati', 'Iles Marshall', 'Micronésie', 'Nauru',
                'Nouvelle-Zélande', 'Palaos', 'Papouasie Nouvelle Guinée', 'Samoa', 'Îles Salomon',
                'Tonga', 'Tuvalu', 'Vanuatu', 'Iles Cook', 'Niuéen', 'Polynésie française',
                'Nouvelle Calédonie',
            ],
            'description' => '',
        ],
        [
            'slug' => 'africa',
            'name_fr' => 'Afrique',
            'name_en' => 'Africa',
            'countries' => [
                'Algérie', 'Angola', 'Bénin', 'Botswana', 'Burkina Faso', 'Burundi', 'Cameroun',
                'Cap-Vert', 'République centrafricaine', 'Tchad', 'Comores', 'Congo',
                'République démocratique du Congo', "Côte d'Ivoire", 'Djibouti', 'Égypte',
                'Guinée Équatoriale', 'Érythrée', 'Éthiopie', 'Gabon', 'Gambie', 'Ghana',
                'Guinée', 'Guinée-Bissau', 'Kenya', 'Lesotho', 'Liberia', 'Libye', 'Madagascar',
                'Malawi', 'Mali', 'Mauritanie', 'Île Maurice', 'Maroc', 'Mozambique', 'Namibie',
                'Niger', 'Nigeria', 'Rwanda', 'São Tomé-et-Principe', 'Sénégal', 'Seychelles',
                'Sierra Leone', 'Somalie', 'Afrique du Sud', 'Soudan', 'Soudan du Sud', 'Eswatini',
                'Tanzanie', 'Togo', 'Tunisie', 'Ouganda', 'Zambie', 'Zimbabwe',
            ],
            'description' => '',
        ],
        [
            'slug' => 'middle-east',
            'name_fr' => 'Moyen-Orient',
            'name_en' => 'Middle East',
            'countries' => [
                'Arabie Saoudite', 'Bahreïn', 'Emirats Arabes Unis', 'Irak', 'Iran', 'Israël',
                'Jordanie', 'Koweït', 'Liban', 'Oman', 'Palestine', 'Qatar', 'Syrie', 'Turquie', 'Yemen',
                'Égypte', 'Chypre',
            ],
            'description' => '',
        ],
        [
            'slug' => 'north-america',
            'name_fr' => 'Amérique du Nord',
            'name_en' => 'North America',
            'countries' => [
                'Canada', "États-Unis d'Amérique", 'Mexique', 'Groenland', 'Bermudes',
                'Saint Pierre et Miquelon',
            ],
            'description' => '',
        ],
        [
            'slug' => 'south-america',
            'name_fr' => 'Amérique du Sud',
            'name_en' => 'South America',
            'countries' => [
                'Argentine', 'Bolivie', 'Brésil', 'Chili', 'Colombie', 'Équateur', 'Guyane',
                'Paraguay', 'Pérou', 'Suriname', 'Uruguay', 'Venezuela', 'Guyane française',
            ],
            'description' => '',
        ],
        [
            'slug' => 'central-america',
            'name_fr' => 'Amérique centrale',
            'name_en' => 'Central America',
            'countries' => [
                'Belize', 'Costa Rica', 'Salvador', 'Guatemala', 'Honduras', 'Nicaragua', 'Panama',
            ],
            'description' => '',
        ],
        [
            'slug' => 'caribbean',
            'name_fr' => 'Caraïbes',
            'name_en' => 'Caribbean',
            'countries' => [
                'Cuba', 'Haïti', 'République Dominicaine', 'Jamaïque', 'Porto Rico',
                'Guadeloupe', 'Martinique', 'Îles Caïmans', 'Îles Turques-et-Caïques',
                'Îles Vierges britanniques', 'Îles Vierges américaines', 'Bahamas',
                'Barbade', 'Trinidad et Tobago',
            ],
            'description' => '',
        ],
        [
            'slug' => 'western-hemisphere',
            'name_fr' => 'Hémisphère Ouest',
            'name_en' => 'Western Hemisphere',
            'countries' => [
                'Amérique du Nord', 'Amérique du Sud', 'Amérique centrale', 'Caraïbes',
            ],
            'description' => '',
        ],
        [
            'slug' => 'eastern-hemisphere',
            'name_fr' => 'Hémisphère Est',
            'name_en' => 'Eastern Hemisphere',
            'countries' => [
                'Europe', 'Asie', 'Afrique', 'Océanie',
            ],
            'description' => '',
        ],
        [
            'slug' => 'northern-hemisphere',
            'name_fr' => 'Hémisphère Nord',
            'name_en' => 'Northern Hemisphere',
            'countries' => [
                'Amérique du Nord', 'Europe', 'Asie (partie nord)',
            ],
            'description' => '',
        ],
        [
            'slug' => 'southern-hemisphere',
            'name_fr' => 'Hémisphère Sud',
            'name_en' => 'Southern Hemisphere',
            'countries' => [
                'Amérique du Sud', 'Afrique (partie sud)', 'Océanie',
            ],
            'description' => '',
        ],
        [
            'slug' => 'southeast-asia',
            'name_fr' => 'Asie du Sud-Est',
            'name_en' => 'Southeast Asia',
            'countries' => [
                'Birmanie', 'Brunei', 'Cambodge', 'Indonésie', 'Malaisie', 'Philippines',
                'Singapour', 'Thaïlande', 'Viêtnam', 'Laos', 'Timor-Leste',
            ],
            'description' => '',
        ],
        [
            'slug' => 'tropical-regions',
            'name_fr' => 'Régions tropicales',
            'name_en' => 'Tropical Regions',
            'countries' => [
                'Amérique centrale', 'Caraïbes', 'Amérique du Sud (partie nord)',
                'Asie du Sud-Est', 'Océanie (partie tropicale)', 'Afrique (partie centrale)',
            ],
            'description' => '',
        ],
    ];
}

/**
 * Get ALL regional Pokémon data (Vivillon patterns + other regional Pokémon)
 * This is the SINGLE SOURCE OF TRUTH for all regional data
 * 
 * @return array Array with:
 *   - 'vivillon': array with 'patterns', 'mappings', 'pokemon'
 *   - 'pokemon': array of regional Pokémon mappings (pokemon_id_proto => countries/regions)
 */
function poke_hub_get_all_regional_data() {
    // Load geographic regions data
    $geographic_regions = poke_hub_get_regional_regions_data();
    
    // ====== VIVILLON PATTERNS ======
    $vivillon_patterns = [
        'continental', 'garden', 'elegant', 'modern', 'marine', 'archipelago',
        'high-plains', 'jungle', 'ocean', 'meadow', 'polar', 'river',
        'sandstorm', 'savanna', 'sun', 'icy-snow', 'monsoon', 'tundra'
    ];
    
    $vivillon_mappings = [
        'continental' => ['Allemagne', 'Belgique', 'Biélorussie', 'Danemark', 'Estonie', 'France', 'Hongrie', 'Lettonie', 'Lituanie', 'Luxembourg', 'Moldavie', 'Norvège', 'Pays-Bas', 'Pologne', 'Russie', 'Slovaquie', 'Suède', 'Ukraine', 'République Tchèque', 'Argentine', 'Birmanie', 'Chili', 'Corée du Sud', 'Hong Kong', 'Inde', 'Japon', 'Népal', 'Taïwan', 'Viêtnam'],
        'garden' => ['Royaume-Uni', 'Irlande', 'Guernesey', 'Jersey', 'Île de Man', 'Australie', 'Nouvelle-Zélande'],
        'elegant' => ['Japon'],
        'modern' => ["États-Unis d'Amérique", 'Canada', 'Bermudes'],
        'marine' => ['Açores', 'Îles Baléares', 'Madère', 'Kosovo', 'Espagne', 'Portugal', 'Italie', 'Grèce', 'Chypre', 'Malte', 'Croatie', 'Albanie', 'Monténégro', 'Bosnie-Herzégovine', 'Allemagne', 'Andorre', 'Autriche', 'Bulgarie', 'France', 'Gibraltar', 'Guernesey', 'Hongrie', 'Jersey', 'Moldavie', 'Pologne', 'Roumanie', 'Russie', 'Saint Marin', 'Serbie', 'Slovaquie', 'Slovénie', 'République Tchèque', 'Tunisie', 'Turquie', 'Ukraine', 'Vatican', 'Argentine', 'Chili', 'Maroc'],
        'archipelago' => ['Anguilla', 'Antigua-et-Barbuda', 'Antilles néerlandaises', 'Aruba', 'Bahamas', 'Barbade', 'Cuba', 'Dominique', 'Grenade', 'Guadeloupe', 'Haïti', 'Îles Caïmans', 'Îles Turques-et-Caïques', 'Îles Vierges américaines', 'Îles Vierges britanniques', 'Jamaïque', 'Martinique', 'Montserrat', 'Porto Rico', 'République Dominicaine', 'Saint Barthélemy', 'Saint-Christophe-et-Niévès', 'Sainte Lucie', 'Saint-Vincent-et-les-Grenadines', 'Trinidad et Tobago', 'Colombie', 'Mexique', 'Venezuela', 'Afrique du Sud', 'Lesotho', 'Espagne', "États-Unis d'Amérique"],
        'high-plains' => ["États-Unis d'Amérique", 'Canada', 'Mexique', 'Arménie', 'Azerbaïdjan', 'Géorgie', 'Kazakhstan', 'Kirghizistan', 'Mongolie', 'Ouzbékistan', 'Russie', 'Tadjikistan', 'Turkménistan', 'Turquie'],
        'jungle' => ['Brésil', 'Colombie', 'Équateur', 'Guyane', 'Guyane française', 'Panama', 'Pérou', 'Suriname', 'Venezuela', 'Angola', 'Bénin', 'Burundi', 'Cameroun', "Côte d'Ivoire", 'Gabon', 'Ghana', 'Guinée Équatoriale', 'Kenya', 'Liberia', 'Nigeria', 'République centrafricaine', 'République démocratique du Congo', 'Rwanda', 'São Tomé-et-Principe', 'Sierra Leone', 'Soudan du Sud', 'Tanzanie', 'Togo', 'Ouganda', 'Zambie', 'Birmanie', 'Brunei', 'Cambodge', 'Costa Rica', 'Inde', 'Indonésie', 'Malaisie', 'Papouasie Nouvelle Guinée', 'Philippines', 'Singapour', 'Sri Lanka', 'Viêtnam', 'Comores', 'Îles Salomon', 'Trinidad et Tobago'],
        'ocean' => ['Hawaï', 'Galápagos', 'Açores', 'Îles Baléares', 'Madère', 'Fidji', 'Nouvelle Calédonie', 'Polynésie française', 'Samoa', 'Réunion', 'Kiribati', 'Micronésie', 'Nauru', 'Niuéen', 'Palaos', 'Pitcairn', 'Tokélaou', 'Tonga', 'Tuvalu', 'Vanuatu', 'Wallis et Futuna', 'Barbade', 'Cap-Vert', 'Grenade', 'Guam', 'Iles Cook', 'Îles Mariannes du Nord', 'Îles Marshall', 'Îles Salomon', 'Japon', 'Madagascar', 'Île Maurice', 'Saint-Vincent-et-les-Grenadines', 'Seychelles', 'Trinidad et Tobago', 'Venezuela'],
        'meadow' => ['Allemagne', 'Autriche', 'France', 'Italie', 'Suisse', 'Belgique', 'Luxembourg', 'Pays-Bas', 'Andorre', 'Liechtenstein', 'Monaco', 'Espagne'],
        'polar' => ['Canada', "États-Unis d'Amérique", 'Norvège', 'Suède', 'Finlande', 'Islande', 'Danemark', 'Groenland', 'Svalbard et Jan Mayen'],
        'river' => ['Australie', 'Afrique du Sud', 'Algérie', 'Angola', 'Bénin', 'Botswana', 'Burkina Faso', 'Tchad', "Côte d'Ivoire", 'Égypte', 'Gambie', 'Ghana', 'Guinée', 'Guinée-Bissau', 'Lesotho', 'Libye', 'Mali', 'Maroc', 'Mauritanie', 'Namibie', 'Niger', 'Nigeria', 'Sahara occidental', 'Sénégal', 'Sierra Leone', 'Soudan', 'Togo', 'Tunisie', 'Zimbabwe'],
        'sandstorm' => ['Arabie Saoudite', 'Bahreïn', 'Emirats Arabes Unis', 'Irak', 'Iran', 'Israël', 'Jordanie', 'Koweït', 'Liban', 'Oman', 'Palestine', 'Qatar', 'Syrie', 'Turquie', 'Yemen', 'Algérie', 'Égypte', 'Libye', 'Maroc', 'Soudan', 'Tunisie', 'Afghanistan', 'Arménie', 'Chypre', 'Comores', 'Djibouti', 'Érythrée', 'Éthiopie', 'Inde', 'Kenya', 'Pakistan', 'Somalie', 'Turkménistan'],
        'savanna' => ['Argentine', 'Bolivie', 'Brésil', 'Paraguay', 'Pérou', 'Uruguay', 'Angola', 'Bénin', 'Botswana', 'Burundi', 'Cameroun', 'Tchad', 'Congo', 'République démocratique du Congo', 'Ghana', 'Kenya', 'Lesotho', 'Malawi', 'Mali', 'Mozambique', 'Namibie', 'Niger', 'Nigeria', 'Rwanda', 'Sénégal', 'Afrique du Sud', 'Soudan du Sud', 'Tanzanie', 'Togo', 'Zambie', 'Zimbabwe'],
        'sun' => ['Galápagos', 'Mexique', 'Guatemala', 'Belize', 'Salvador', 'Honduras', 'Nicaragua', 'Costa Rica', 'Afrique du Sud', 'Angola', 'Botswana', 'Comores', 'Éthiopie', 'Eswatini', 'Kenya', 'Lesotho', 'Madagascar', 'Malawi', 'Mayotte', 'Mozambique', 'Namibie', 'République démocratique du Congo', 'Somalie', 'Tanzanie', 'Zambie', 'Zimbabwe', 'Australie', 'Îles Caïmans'],
        'icy-snow' => ['Argentine', 'Canada', 'Chili', "États-Unis d'Amérique", 'Finlande', 'Groenland', 'Kazakhstan', 'Mongolie', 'Norvège', 'Russie', 'Suède', 'Îles Åland', 'Îles Féroé', 'Svalbard et Jan Mayen', 'Saint Pierre et Miquelon', 'Islande', 'Japon'],
        'monsoon' => ['Inde', 'Bangladesh', 'Bhoutan', 'Népal', 'Sri Lanka', 'Birmanie', 'Cambodge', 'Japon', "Personne de la République démocratique du Laos", 'Philippines', 'Taïwan', 'Thaïlande', 'Viêtnam'],
        'tundra' => ['Japon', 'Islande', 'Norvège', 'Suède', 'Finlande', 'Russie', 'Îles Åland', 'Îles Féroé', 'Svalbard et Jan Mayen', 'Groenland'],
    ];
    
    $vivillon_pokemon = [
        ['dex_number' => 664, 'name_en' => 'Scatterbug', 'name_fr' => 'Lépidonille', 'slug' => 'scatterbug'],
        ['dex_number' => 665, 'name_en' => 'Spewpa', 'name_fr' => 'Pérégrain', 'slug' => 'spewpa'],
        ['dex_number' => 666, 'name_en' => 'Vivillon', 'name_fr' => 'Prismillon', 'slug' => 'vivillon'],
    ];
    
    // ====== REGIONAL POKÉMON BY EXACT SLUG ======
    // ALL regional Pokémon are now defined by their EXACT slug (as stored in database)
    // Format: 'pokemon-slug' or 'pokemon-form-slug' => array of countries/regions
    // This is much simpler and avoids ambiguity!
    // NOTE: These arrays may contain both country names AND region names (which will be separated during processing)
    // IMPORTANT: 
    // - Only include Pokémon that are ACTUALLY regional in Pokémon GO (available only in specific geographic areas)
    // - Galarian/Alolan/Hisuian/Paldean forms are NOT regional in GO (they are available worldwide)
    // - Cosmetic forms (colors, sizes) are NOT regional (e.g., Minior colors, Squawkabilly colors)
    
    // This is now deprecated - all entries moved to $form_based_mappings below
    // Kept for backward compatibility during migration
    $regional_pokemon = [];
    
    // ====== FLABÉBÉ EVOLUTION LINE (Gen 6) ======
    // Flabébé → Floette → Florges with color forms (Red, Blue, Yellow)
    $flabebe_pokemon = [
        ['dex_number' => 669, 'name_en' => 'Flabébé', 'name_fr' => 'Flabébé', 'slug' => 'flabebe'],
        ['dex_number' => 670, 'name_en' => 'Floette', 'name_fr' => 'Floette', 'slug' => 'floette'],
        ['dex_number' => 671, 'name_en' => 'Florges', 'name_fr' => 'Florges', 'slug' => 'florges'],
    ];
    
    $flabebe_forms = [
        'red' => ['Europe', 'Moyen-Orient', 'Afrique'],
        'blue' => ['Asie', 'Océanie'],
        'yellow' => ['Amérique du Nord', 'Amérique du Sud'],
        // White and Orange are worldwide, so no regional mapping needed
    ];
    
    // ====== SHELLOS EVOLUTION LINE (Gen 4) ======
    // Shellos → Gastrodon with sea forms (East Sea, West Sea)
    $shellos_pokemon = [
        ['dex_number' => 422, 'name_en' => 'Shellos', 'name_fr' => 'Sancoki', 'slug' => 'shellos'],
        ['dex_number' => 423, 'name_en' => 'Gastrodon', 'name_fr' => 'Tritosor', 'slug' => 'gastrodon'],
    ];
    
    $shellos_forms = [
        'east-sea' => ['Hémisphère Est'],
        'west-sea' => ['Hémisphère Ouest'],
    ];
    
    // ====== REGIONAL POKÉMON MAPPINGS BY EXACT SLUG ======
    // UNIFIED system: ALL regional Pokémon defined by their EXACT slug (as stored in database)
    // Format: 'pokemon-slug' or 'pokemon-form-slug' => array of countries/regions
    // The slug MUST match exactly the slug in the pokemon table (column `slug`)
    // IMPORTANT: 
    // - Only include Pokémon that are ACTUALLY regional in Pokémon GO (available only in specific geographic areas)
    // - Galarian/Alolan/Hisuian/Paldean forms are NOT regional in GO (they are available worldwide)
    // - Cosmetic forms (colors, sizes) are NOT regional (e.g., Minior colors, Squawkabilly colors)
    $form_based_mappings = [
        // Gen 1 - Base forms only (Galarian forms are NOT regional in GO)
        // IMPORTANT: Use EXACT slug as stored in database (e.g., "farfetchd" not "FARFETCHD")
        // Only base form is regional, Galarian form is available worldwide
        'farfetchd' => ['Japon', 'Corée du Sud', 'Taïwan'], // Base form (Kantonian) only
        'tauros' => ["États-Unis d'Amérique", 'Canada', 'Mexique'], // Only Kantonian form exists in Go
        'mr-mime' => ['Europe'], // Base form (Kantonian) only, Galarian form is worldwide
        'mime-jr' => ['Europe'], // Same regions as Mr. Mime
        'kangaskhan' => ['Australie'],
        
        // Gen 2
        'heracross' => ['Amérique centrale', 'Amérique du Sud', 'Mexique', "États-Unis d'Amérique"],
        'corsola' => ['Régions tropicales'], // Base form (Johto) only, Galarian form is worldwide
        
        // Gen 3
        'volbeat' => ['Europe', 'Asie', 'Océanie'],
        'illumise' => ['Amérique du Nord', 'Amérique du Sud', 'Afrique'],
        'zangoose' => ['Europe', 'Asie', 'Océanie'],
        'seviper' => ['Amérique du Nord', 'Amérique du Sud', 'Afrique'],
        'solrock' => ['Hémisphère Est'],
        'lunatone' => ['Hémisphère Ouest'],
        'torkoal' => ['Inde', 'Asie du Sud-Est'],
        'tropius' => ['Afrique', 'Moyen-Orient'],
        'relicanth' => ['Nouvelle-Zélande', 'Océanie'],
        
        // Gen 4
        'pachirisu' => ['Canada', "États-Unis d'Amérique", 'Russie'],
        'carnivine' => ["États-Unis d'Amérique"],
        'chatot' => ['Hémisphère Sud'],
        
        // Gen 5
        'maractus' => ['Mexique', 'Amérique centrale', 'Amérique du Sud'],
        'sigilyph' => ['Égypte', 'Grèce', 'Israël'],
        'heatmor' => ['Europe', 'Asie', 'Océanie'],
        'durant' => ['Amérique du Nord', 'Amérique du Sud', 'Afrique'],
        
        // Gen 6
        'klefki' => ['France'],
        'hawlucha' => ['Mexique'],
        'comfey' => ["États-Unis d'Amérique"], // Hawaii is part of US
        
        // Gen 5 - Form-based regionals
        // Basculin - red-striped = Eastern Hemisphere, blue-striped = Western Hemisphere
        'basculin-red-striped' => ['Hémisphère Est'],
        'basculin-blue-striped' => ['Hémisphère Ouest'],
        
        // Gen 7 - Form-based regionals
        // Oricorio - Baile (Europe/Middle East/Africa), Pom-Pom (Americas), Pa'u (Islands), Sensu (Asia-Pacific)
        'oricorio-baile' => ['Europe', 'Moyen-Orient', 'Afrique'],
        'oricorio-pom-pom' => ['Amérique du Nord', 'Amérique du Sud'],
        'oricorio-pau' => [], // Pa'u form - Islands (complex, will be resolved via manual mapping)
        'oricorio-sensu' => ['Asie', 'Océanie'],
        
        // Gen 9 - Form-based regionals
        // Tauros Paldea
        'tauros-paldea-combat' => ['Espagne', 'Portugal'],
        'tauros-paldea-blaze' => ['Hémisphère Est'],
        'tauros-paldea-aqua' => ['Hémisphère Ouest'],
        
        // Tatsugiri forms
        'tatsugiri-droopy' => ['Amérique du Nord', 'Amérique du Sud'], // Includes Greenland (part of Amérique du Nord)
        'tatsugiri-curly' => ['Europe', 'Moyen-Orient', 'Afrique'],
        'tatsugiri-stretchy' => ['Asie', 'Océanie'],
    ];
    
    return [
        'geographic_regions' => $geographic_regions,
        'vivillon' => [
            'patterns' => $vivillon_patterns,
            'mappings' => $vivillon_mappings,
            'pokemon' => $vivillon_pokemon,
        ],
        'flabebe' => [
            'forms' => $flabebe_forms,
            'pokemon' => $flabebe_pokemon,
        ],
        'shellos' => [
            'forms' => $shellos_forms,
            'pokemon' => $shellos_pokemon,
        ],
        'pokemon' => $regional_pokemon,
        'form_based_mappings' => $form_based_mappings,
    ];
}

