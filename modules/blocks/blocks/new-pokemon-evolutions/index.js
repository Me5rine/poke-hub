/**
 * Enregistrement côté client du bloc "Nouveaux Pokémon - Lignées d'évolution"
 * 
 * Même si le bloc est entièrement rendu côté serveur (render.php),
 * WordPress a besoin de ce fichier JavaScript pour afficher le bloc dans l'éditeur.
 */
(function() {
    'use strict';

    // Vérifier que wp.blocks est disponible
    if (typeof wp === 'undefined' || !wp.blocks) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor && wp.blockEditor.InspectorControls;
    var PanelBody = wp.components && wp.components.PanelBody;
    var ToggleControl = wp.components && wp.components.ToggleControl;
    var SelectControl = wp.components && wp.components.SelectControl;
    var Button = wp.components && wp.components.Button;
    var apiFetch = wp.apiFetch;

    // Enregistrer le bloc (le rendu est géré par render.php)
    registerBlockType('pokehub/new-pokemon-evolutions', {
        edit: function(props) {
            var attributes = props.attributes || {};
            var setAttributes = props.setAttributes || function() {};
            var autoDetect = attributes.autoDetect !== undefined ? attributes.autoDetect : true;
            var pokemonIds = attributes.pokemonIds || [];
            
            var blockProps = useBlockProps ? useBlockProps({ className: 'pokehub-block-placeholder' }) : { className: 'pokehub-block-placeholder' };

            // État pour la liste des Pokémon
            var pokemonListState = useState([]);
            var pokemonList = pokemonListState[0];
            var setPokemonList = pokemonListState[1];

            // Charger la liste des Pokémon au montage
            useEffect(function() {
                if (apiFetch) {
                    apiFetch({
                        path: '/poke-hub/v1/pokemon-for-select'
                    }).then(function(data) {
                        if (Array.isArray(data)) {
                            setPokemonList(data);
                        }
                    }).catch(function(error) {
                        console.error('Erreur lors du chargement des Pokémon:', error);
                    });
                }
            }, []);

            // Options pour le SelectControl
            var pokemonOptions = [];
            if (Array.isArray(pokemonList) && pokemonList.length > 0) {
                pokemonOptions = pokemonList.map(function(pokemon) {
                    return {
                        label: pokemon.text || (pokemon.name_fr || pokemon.name_en || 'Pokémon #' + pokemon.id),
                        value: String(pokemon.id)
                    };
                });
            }
            pokemonOptions.unshift({ label: __('Sélectionner un Pokémon...', 'poke-hub'), value: '' });

            // Gérer le changement de sélection
            var handlePokemonChange = function(newValue) {
                if (!newValue || newValue === '') {
                    return;
                }
                
                var newId = parseInt(newValue, 10);
                if (isNaN(newId)) {
                    return;
                }

                var currentIds = Array.isArray(pokemonIds) ? pokemonIds : [];
                if (currentIds.indexOf(newId) === -1) {
                    setAttributes({ pokemonIds: currentIds.concat([newId]) });
                }
            };

            // Supprimer un Pokémon de la liste
            var handleRemovePokemon = function(idToRemove) {
                var currentIds = Array.isArray(pokemonIds) ? pokemonIds : [];
                var newIds = currentIds.filter(function(id) {
                    return id !== idToRemove;
                });
                setAttributes({ pokemonIds: newIds });
            };

            // Construire la liste des Pokémon sélectionnés (seulement si autoDetect est false)
            var selectedPokemonList = [];
            if (!autoDetect && Array.isArray(pokemonIds) && pokemonIds.length > 0) {
                pokemonIds.forEach(function(pokemonId) {
                    var pokemon = pokemonList.find(function(p) {
                        return p.id === pokemonId;
                    });
                    if (pokemon) {
                        selectedPokemonList.push({
                            id: pokemonId,
                            name: pokemon.text || (pokemon.name_fr || pokemon.name_en || 'Pokémon #' + pokemon.id)
                        });
                    } else {
                        selectedPokemonList.push({
                            id: pokemonId,
                            name: 'Pokémon #' + pokemonId
                        });
                    }
                });
            }

            // Construire le texte d'affichage
            var displayText = '';
            if (autoDetect) {
                displayText = __('Nouveaux Pokémon - Lignées d\'évolution', 'poke-hub');
            } else if (Array.isArray(pokemonIds) && pokemonIds.length > 0) {
                var pokemonNames = pokemonIds.map(function(pokemonId) {
                    var pokemon = pokemonList.find(function(p) {
                        return p.id === pokemonId;
                    });
                    return pokemon ? (pokemon.name_fr || pokemon.name_en || 'Pokémon #' + pokemon.id) : 'Pokémon #' + pokemonId;
                });
                displayText = __('Nouveaux Pokémon:', 'poke-hub') + ' ' + pokemonNames.join(', ');
            } else {
                displayText = __('Nouveaux Pokémon - Lignées d\'évolution', 'poke-hub');
            }

            // Construire les contrôles de l'inspecteur
            var inspectorControls = null;
            if (InspectorControls && PanelBody && ToggleControl && SelectControl) {
                inspectorControls = el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Options', 'poke-hub'), initialOpen: true },
                        el(ToggleControl, {
                            label: __('Auto detection', 'poke-hub'),
                            checked: autoDetect,
                            onChange: function(value) { setAttributes({ autoDetect: value }); },
                            help: __('Récupère automatiquement les nouveaux Pokémon depuis la metabox de l\'article.', 'poke-hub')
                        }),
                        !autoDetect ? el(
                            'div',
                            { style: { marginTop: '15px' } },
                            el(SelectControl, {
                                label: __('Ajouter un Pokémon', 'poke-hub'),
                                value: '',
                                options: pokemonOptions,
                                onChange: handlePokemonChange,
                                help: __('Sélectionnez un ou plusieurs nouveaux Pokémon à afficher avec leur lignée d\'évolution.', 'poke-hub')
                            }),
                            selectedPokemonList.length > 0 ? el(
                                'div',
                                { style: { marginTop: '15px' } },
                                el('strong', { style: { display: 'block', marginBottom: '10px' } }, __('Pokémon sélectionnés:', 'poke-hub')),
                                el(
                                    'ul',
                                    { style: { marginTop: '5px', marginBottom: '0', paddingLeft: '20px', listStyle: 'disc' } },
                                    selectedPokemonList.map(function(pokemon) {
                                        return el(
                                            'li',
                                            { 
                                                key: 'pokemon-' + pokemon.id, 
                                                style: { 
                                                    marginBottom: '8px',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'space-between'
                                                } 
                                            },
                                            el('span', {}, pokemon.name),
                                            Button ? el(
                                                Button,
                                                {
                                                    isSmall: true,
                                                    isDestructive: true,
                                                    onClick: function() {
                                                        handleRemovePokemon(pokemon.id);
                                                    },
                                                    style: { marginLeft: '10px' }
                                                },
                                                __('Retirer', 'poke-hub')
                                            ) : el(
                                                'button',
                                                {
                                                    type: 'button',
                                                    className: 'button button-small',
                                                    style: { marginLeft: '10px' },
                                                    onClick: function() {
                                                        handleRemovePokemon(pokemon.id);
                                                    }
                                                },
                                                __('Retirer', 'poke-hub')
                                            )
                                        );
                                    })
                                )
                            ) : el(
                                'p',
                                { style: { fontStyle: 'italic', color: '#666', marginTop: '10px' } },
                                __('Aucun Pokémon sélectionné. Utilisez le menu déroulant ci-dessus pour ajouter des Pokémon.', 'poke-hub')
                            )
                        ) : null
                    )
                );
            }

            return el(
                'div',
                blockProps,
                inspectorControls,
                el('p', {}, displayText),
                el('small', {}, __('Ce bloc affiche les nouveaux Pokémon avec leur lignée d\'évolution complète.', 'poke-hub'))
            );
        },
        save: function() {
            // Bloc dynamique, pas de save
            return null;
        }
    });
})();
