/**
 * Poke Hub - Pokedle Game
 */

(function($) {
    'use strict';

    // Variables globales du jeu
    let gameData = null;
    let attempts = [];
    let startTime = null;
    let isGameComplete = false;
    let wrongAttemptsCount = 0; // Compteur de mauvaises réponses
    let gameConfig = {
        mode: 'all', // 'all' ou ID de génération
        selectedGeneration: null,
        gameDate: null
    };

    /**
     * Initialisation du jeu
     */
    function initPokedle() {
        if (typeof pokeHubPokedle === 'undefined') {
            return;
        }

        gameData = pokeHubPokedle;
        
        // Déterminer le mode selon les données
        gameConfig.mode = gameData.isAllGenerationsMode ? 'all' : 'generation';
        gameConfig.selectedGeneration = gameData.currentGenerationId;
        gameConfig.gameDate = gameData.currentGameDate || gameData.today;

        // Si l'utilisateur a déjà joué, afficher le message de complétion
        if (gameData.hasPlayed && gameData.userScore && gameData.userScore.is_success) {
            showCompletedMessage();
            loadLeaderboard();
            
            // Gérer le bouton "Voir le résultat"
            $('.pokedle-show-result').on('click', function() {
                const pokemonId = $(this).data('pokemon-id');
                showPokemonResult(pokemonId);
            });
        } else {
            // Initialiser le jeu directement
            startGame();
            loadLeaderboard();
        }
        
        // Gérer le bouton pour changer de Pokedle
        $('#pokedle-change-game').on('click', function() {
            $('#pokedle-game-selector').slideToggle();
        });
        
        // Gérer le chargement d'un autre Pokedle
        $('#pokedle-load-game').on('click', function() {
            const selectedDate = $('#pokedle-select-date').val();
            const selectedGen = $('#pokedle-select-generation').val();
            
            if (!selectedDate) {
                alert(gameData.i18n.pleaseSelectDate);
                return;
            }
            
            // Recharger la page avec les paramètres
            const url = new URL(window.location.href);
            url.searchParams.set('pokedle_date', selectedDate);
            if (selectedGen && selectedGen !== 'all') {
                url.searchParams.set('pokedle_gen', selectedGen);
            } else {
                url.searchParams.delete('pokedle_gen');
            }
            window.location.href = url.toString();
        });
    }

    /**
     * Initialise l'écran de configuration
     */
    function initConfigScreen() {
        // Vérifier que l'écran de configuration existe
        if ($('#pokedle-config').length === 0) {
            console.error('Écran de configuration non trouvé');
            return;
        }

        // Bouton pour démarrer le jeu
        $('#pokedle-start-game').on('click', function(e) {
            e.preventDefault();
            
            // Mode de jeu - vérifier si le sélecteur existe
            const $modeSelect = $('#pokedle-config-mode');
            if ($modeSelect.length > 0) {
                const selectedMode = $modeSelect.val();
                if (selectedMode && selectedMode !== 'all') {
                    gameConfig.mode = 'generation';
                    gameConfig.selectedGeneration = parseInt(selectedMode);
                    
                    // Filtrer les Pokémon de la génération choisie
                    const generationPokemon = gameData.allPokemon.filter(p => 
                        p.generation_id === gameConfig.selectedGeneration
                    );
                    
                    if (generationPokemon.length === 0) {
                        alert(gameData.i18n.noPokemonForGeneration);
                        return;
                    }
                    
                    // Sélectionner un Pokémon du jour déterministe pour cette génération
                    // Basé sur la date actuelle pour que tous les joueurs aient le même Pokémon
                    const today = new Date();
                    const dateString = today.toISOString().split('T')[0]; // YYYY-MM-DD
                    const seed = dateString + '_gen' + gameConfig.selectedGeneration;
                    
                    // Créer un hash simple pour avoir un index déterministe
                    let hash = 0;
                    for (let i = 0; i < seed.length; i++) {
                        const char = seed.charCodeAt(i);
                        hash = ((hash << 5) - hash) + char;
                        hash = hash & hash; // Convert to 32bit integer
                    }
                    
                    // Utiliser le hash pour sélectionner un Pokémon de manière déterministe
                    const randomIndex = Math.abs(hash) % generationPokemon.length;
                    gameData.dailyPokemonId = generationPokemon[randomIndex].id;
                    
                    // Filtrer les Pokémon disponibles pour l'autocomplétion
                    gameData.allPokemon = generationPokemon;
                } else {
                    gameConfig.mode = 'all';
                    gameConfig.selectedGeneration = null;
                }
            } else {
                // Pas de sélecteur, mode par défaut = toutes générations
                gameConfig.mode = 'all';
                gameConfig.selectedGeneration = null;
            }

            // Cacher l'écran de config et afficher le jeu
            $('#pokedle-config').hide();
            $('#pokedle-game-container').show();

            // Afficher les infos de configuration
            updateGameInfo();

            // Initialiser le jeu
            startGame();
        });
    }

    /**
     * Démarre le jeu
     */
    function startGame() {
        startTime = Date.now();
        wrongAttemptsCount = 0; // Réinitialiser le compteur de mauvaises réponses
        attempts = []; // Réinitialiser la liste des tentatives
        
        // Initialiser l'autocomplétion
        initAutocomplete();
        
        // Initialiser le bouton de soumission
        $('#pokedle-submit').on('click', handleSubmit);
        
        // Initialiser le bouton "Voir la réponse"
        const $showAnswerBtn = $('#pokedle-show-answer');
        if ($showAnswerBtn.length) {
            $showAnswerBtn.on('click', function(e) {
                e.preventDefault();
                console.log('Bouton "Voir la réponse" cliqué');
                endGameWithoutSuccess();
            }).show();
        } else {
            console.error('Bouton "Voir la réponse" non trouvé');
        }
        
        // Soumission avec Enter
        $('#pokedle-pokemon-input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                handleSubmit();
            }
        });

        // Charger le classement
        loadLeaderboard();
        
        // Mettre à jour les infos
        updateGameInfo();
    }

    /**
     * Met à jour l'affichage des infos du jeu
     */
    function updateGameInfo() {
        // Mode de jeu
        // Vérifier si on est en mode toutes générations ou génération spécifique
        // Utiliser directement gameData.isAllGenerationsMode et gameData.currentGenerationId
        const isAllGen = gameData.isAllGenerationsMode === true || 
                        gameData.isAllGenerationsMode === 'true' ||
                        !gameData.currentGenerationId || 
                        gameData.currentGenerationId === 0 || 
                        gameData.currentGenerationId === null ||
                        gameData.currentGenerationId === '0';
        
        console.log('updateGameInfo:', {
            isAllGenerationsMode: gameData.isAllGenerationsMode,
            currentGenerationId: gameData.currentGenerationId,
            isAllGen: isAllGen
        });
        
        if (isAllGen) {
            $('#pokedle-current-mode').text(gameData.i18n.allGenerations).show();
        } else {
            // Utiliser currentGenerationId depuis gameData
            const genId = gameData.currentGenerationId;
            const gen = gameData.allGenerations.find(g => parseInt(g.id) === parseInt(genId));
            if (gen) {
                $('#pokedle-current-mode').text(gen.name).show();
            } else {
                // Fallback si la génération n'est pas trouvée
                console.warn('Generation not found for ID:', genId, 'Available generations:', gameData.allGenerations.map(g => ({ id: g.id, name: g.name })));
                $('#pokedle-current-mode').text(gameData.i18n.allGenerations).show();
            }
        }
        
        // Nombre de tentatives
        const attemptsText = attempts.length === 1 ? gameData.i18n.attempt : gameData.i18n.attempts;
        $('#pokedle-attempts-count').text(`${attempts.length} ${attemptsText}`).show();
    }

    /**
     * Initialise l'autocomplétion pour la recherche de Pokémon
     */
    function initAutocomplete() {
        const $input = $('#pokedle-pokemon-input');
        const pokemonList = gameData.allPokemon || [];

        // Simple autocomplétion avec liste déroulante
        $input.on('input', function() {
            const value = $(this).val().toLowerCase().trim();
            
            if (value.length < 2) {
                removeAutocomplete();
                return;
            }

            const matches = pokemonList.filter(p => 
                p.name.toLowerCase().includes(value) ||
                p.slug.toLowerCase().includes(value)
            ).slice(0, 10);

            showAutocomplete(matches, value);
        });

        // Fermer l'autocomplétion en cliquant ailleurs
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.pokedle-input-container').length) {
                removeAutocomplete();
            }
        });
    }

    /**
     * Affiche la liste d'autocomplétion
     */
    function showAutocomplete(matches, searchValue) {
        removeAutocomplete();

        if (matches.length === 0) {
            return;
        }

        const $container = $('<div class="pokedle-autocomplete"></div>');
        
        matches.forEach(pokemon => {
            const $item = $('<div class="pokedle-autocomplete-item"></div>')
                .data('pokemon-id', pokemon.id)
                .data('pokemon-name', pokemon.name);
            
            // Ajouter l'image du Pokémon si disponible
            const imageUrl = pokemon.image_url || (gameData.imageBaseUrl ? `${gameData.imageBaseUrl}/${pokemon.slug}.png` : '');
            if (imageUrl) {
                const $img = $('<img class="pokedle-autocomplete-image" />')
                    .attr('src', imageUrl)
                    .attr('alt', pokemon.name)
                    .on('error', function() {
                        $(this).hide();
                    });
                $item.append($img);
            }
            
            // Ajouter le nom
            const $name = $('<span class="pokedle-autocomplete-name"></span>').text(pokemon.name);
            $item.append($name);
            
            // Ajouter le numéro du Pokédex
            if (pokemon.dex_number) {
                const $dex = $('<span class="pokedle-autocomplete-dex"></span>').text('#' + pokemon.dex_number.toString().padStart(3, '0'));
                $item.append($dex);
            }
            
            $item.on('click', function() {
                $('#pokedle-pokemon-input').val($(this).data('pokemon-name'));
                removeAutocomplete();
                handleSubmit();
            });
            
            $container.append($item);
        });

        $('.pokedle-input-container').append($container);
    }

    /**
     * Supprime la liste d'autocomplétion
     */
    function removeAutocomplete() {
        $('.pokedle-autocomplete').remove();
    }

    /**
     * Gère la soumission d'une tentative
     */
    function handleSubmit() {
        if (isGameComplete) {
            return;
        }

        const inputValue = $('#pokedle-pokemon-input').val().trim();
        if (!inputValue) {
            return;
        }

        // Trouver le Pokémon correspondant
        const pokemon = gameData.allPokemon.find(p => 
            p.name.toLowerCase() === inputValue.toLowerCase() ||
            p.slug.toLowerCase() === inputValue.toLowerCase()
        );

        if (!pokemon) {
            alert(gameData.i18n.pokemonNotFound);
            return;
        }

        // Vérifier si ce Pokémon n'a pas déjà été deviné
        if (attempts.some(a => a.pokemon_id === pokemon.id)) {
            alert(gameData.i18n.alreadyGuessed);
            return;
        }

        // Soumettre la tentative via AJAX
        submitGuess(pokemon.id);
    }

    /**
     * Soumet une tentative au serveur
     */
    function submitGuess(pokemonId) {
        $('#pokedle-submit').prop('disabled', true).text(gameData.i18n.checking);

        $.ajax({
            url: gameData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'poke_hub_pokedle_submit_guess',
                nonce: gameData.nonce,
                pokemon_id: pokemonId,
                daily_pokemon_id: gameData.dailyPokemonId,
                wrong_attempts_count: wrongAttemptsCount,
                is_all_generations_mode: gameConfig.mode === 'all'
            },
            success: function(response) {
                if (response.success) {
                    // Trouver le Pokémon pour l'affichage
                    const pokemon = gameData.allPokemon.find(p => p.id === pokemonId);
                    
                    // Ajouter la tentative à la liste (correcte ou incorrecte)
                    attempts.push({
                        pokemon: pokemon,
                        pokemon_id: pokemonId,
                        comparison: response.data
                    });
                    
                    if (!response.data.is_correct) {
                        // Incrémenter le compteur de mauvaises réponses
                        wrongAttemptsCount++;
                        
                        // Afficher les indices révélés si disponibles
                        if (response.data.revealed_hints) {
                            showRevealedHints(response.data.revealed_hints);
                        }
                    }
                    
                    // Afficher la tentative
                    displayAttempt(response.data, pokemonId);
                    
                    // Si correct, terminer le jeu
                    if (response.data.is_correct) {
                        console.log('Pokémon trouvé ! Appel de completeGame()');
                        completeGame(response.data.hints);
                    } else {
                        $('#pokedle-pokemon-input').val('').focus();
                        $('#pokedle-submit').prop('disabled', false).text(gameData.i18n.guess);
                        updateGameInfo();
                    }
                } else {
                    alert(response.data.message || gameData.i18n.errorVerification);
                    $('#pokedle-submit').prop('disabled', false).text(gameData.i18n.guess);
                }
            },
            error: function() {
                alert(gameData.i18n.errorConnection);
                $('#pokedle-submit').prop('disabled', false).text(gameData.i18n.guess);
            }
        });
    }

    /**
     * Affiche une tentative sur la grille (style pokedle.com/classic)
     */
    function displayAttempt(comparison, pokemonId) {
        // Ne pas ajouter ici car c'est déjà fait dans submitGuess
        // attempts.push({...}) 

        const pokemon = gameData.allPokemon.find(p => p.id === pokemonId);
        const hints = comparison.hints;

        // Container principal avec ligne horizontale
        const $attempt = $('<div class="pokedle-attempt-row"></div>');
        const $hints = $('<div class="pokedle-attempt-hints-row"></div>');

        // 1. Image du Pokémon (rectangle blanc avec bordure pointillée)
        const imageUrl = pokemon.image_url || (gameData.imageBaseUrl ? `${gameData.imageBaseUrl}/${pokemon.slug}.png` : '');
        const $pokemonCell = $('<div class="pokedle-hint-cell pokedle-pokemon-image-cell"></div>');
        if (imageUrl) {
            const $img = $('<img class="pokedle-pokemon-sprite" />')
                .attr('src', imageUrl)
                .attr('alt', hints.pokemon || pokemon.name)
                .on('error', function() {
                    $(this).hide();
                });
            $pokemonCell.append($img);
        }
        $hints.append($pokemonCell);

        // 2. Type 1
        if (hints.type1 && hints.type1 !== 'none') {
            let type1Class = 'wrong';
            if (hints.type1 === 'correct') {
                type1Class = 'correct';
            } else if (hints.type1 === 'misplaced') {
                type1Class = 'misplaced';
            }
            const type1Text = hints.type1_name || '';
            $hints.append(createHintCell(type1Text, type1Class));
        } else {
            $hints.append(createHintCell('—', 'none'));
        }
        
        // 3. Type 2
        if (hints.type2 && hints.type2 !== 'none') {
            let type2Class = 'wrong';
            let type2Text = '';
            
            if (hints.type2 === 'correct') {
                type2Class = 'correct';
                type2Text = hints.type2_name || '';
            } else if (hints.type2 === 'misplaced') {
                type2Class = 'misplaced';
                type2Text = hints.type2_name || '';
            } else if (hints.type2 === 'missing') {
                type2Class = 'missing';
                type2Text = 'None';
            } else {
                type2Text = hints.type2_name || '';
            }
            
            $hints.append(createHintCell(type2Text, type2Class));
        } else {
            $hints.append(createHintCell('None', 'none'));
        }

        // 4. Génération (uniquement en mode toutes générations)
        if (gameData.isAllGenerationsMode) {
            if (hints.generation !== undefined) {
                const genClass = hints.generation === 'correct' ? 'correct' : 'wrong';
                const genText = String(hints.generation_number || '—');
                $hints.append(createHintCell(genText, genClass));
            } else {
                $hints.append(createHintCell('—', 'none'));
            }
        }

        // 5. Stade d'évolution
        if (hints.evolution_stage !== undefined) {
            const evoClass = hints.evolution_stage === 'correct' ? 'correct' : 'wrong';
            const evoText = String(hints.evolution_stage_value || 0);
            $hints.append(createHintCell(evoText, evoClass));
        } else {
            $hints.append(createHintCell('—', 'none'));
        }

        // 6. Taille (hauteur)
        if (hints.height !== undefined) {
            const heightClass = hints.height === 'correct' ? 'correct' : 'wrong';
            const heightVal = hints.height_value !== undefined ? hints.height_value.toFixed(2) : '0';
            $hints.append(createHintCell(heightVal, heightClass));
        } else {
            $hints.append(createHintCell('—', 'none'));
        }

        // 7. Poids
        if (hints.weight !== undefined) {
            const weightClass = hints.weight === 'correct' ? 'correct' : 'wrong';
            const weightVal = hints.weight_value !== undefined ? hints.weight_value.toFixed(2) : '0';
            $hints.append(createHintCell(weightVal, weightClass));
        } else {
            $hints.append(createHintCell('—', 'none'));
        }

        $attempt.append($hints);
        
        // Ajouter au début pour afficher le plus récent en haut
        $('#pokedle-attempts').prepend($attempt);
        
        // Mettre à jour le compteur d'essais
        updateGameInfo();
    }

    /**
     * Crée une cellule d'indice avec le bon style
     */
    function createHintCell(text, className) {
        const $cell = $('<div class="pokedle-hint-cell"></div>')
            .addClass(className)
            .text(text);
        return $cell;
    }
    
    /**
     * Affiche les indices révélés après X mauvaises réponses
     */
    function showRevealedHints(revealedHints) {
        if (!revealedHints || Object.keys(revealedHints).length === 0) {
            return;
        }
        
        let $hintsContainer = $('#pokedle-revealed-hints');
        if ($hintsContainer.length === 0) {
            $hintsContainer = $('<div id="pokedle-revealed-hints" class="pokedle-revealed-hints"></div>');
            $('.pokedle-attempts').before($hintsContainer);
        }
        
        let hintsHtml = `<h4>💡 ${gameData.i18n.revealedHints}</h4><div class="pokedle-revealed-hints-list">`;
        
        if (revealedHints.type1) {
            hintsHtml += `<div class="pokedle-revealed-hint-item"><strong>${gameData.i18n.type1}:</strong> ${revealedHints.type1}</div>`;
        }
        
        if (revealedHints.type2 !== undefined) {
            hintsHtml += `<div class="pokedle-revealed-hint-item"><strong>${gameData.i18n.type2}:</strong> ${revealedHints.type2}</div>`;
        }
        
        if (revealedHints.generation) {
            hintsHtml += `<div class="pokedle-revealed-hint-item"><strong>${gameData.i18n.generation}:</strong> ${revealedHints.generation}</div>`;
        }
        
        if (revealedHints.evolution_stage) {
            hintsHtml += `<div class="pokedle-revealed-hint-item"><strong>${gameData.i18n.evolutionStage}:</strong> ${revealedHints.evolution_stage}</div>`;
        }
        
        if (revealedHints.height) {
            hintsHtml += `<div class="pokedle-revealed-hint-item"><strong>${gameData.i18n.height}:</strong> ${revealedHints.height}</div>`;
        }
        
        if (revealedHints.weight) {
            hintsHtml += `<div class="pokedle-revealed-hint-item"><strong>${gameData.i18n.weight}:</strong> ${revealedHints.weight}</div>`;
        }
        
        hintsHtml += '</div>';
        
        $hintsContainer.html(hintsHtml).slideDown();
    }

    /**
     * Termine le jeu avec succès
     */
    function completeGame(hints) {
        console.log('completeGame called', { isGameComplete, attempts: attempts.length });
        
        if (isGameComplete) {
            console.log('Game already complete, returning');
            return; // Déjà terminé
        }
        
        isGameComplete = true;
        const attemptsCount = attempts.length;
        const completionTime = Math.floor((Date.now() - startTime) / 1000);

        console.log('Looking for mystery Pokémon with ID:', gameData.dailyPokemonId);
        console.log('Available Pokémon:', gameData.allPokemon.length);
        console.log('Available Pokémon IDs:', gameData.allPokemon.map(p => p.id));
        
        // Afficher le message de succès avec le Pokémon
        // Comparer les IDs en tant qu'entiers pour éviter les problèmes de type
        let mysteryPokemon = gameData.allPokemon.find(p => parseInt(p.id) === parseInt(gameData.dailyPokemonId));
        if (!mysteryPokemon) {
            console.error('Mystery Pokémon not found. ID:', gameData.dailyPokemonId);
            console.error('Available Pokémon IDs:', gameData.allPokemon.map(p => p.id));
            console.error('Trying to fetch Pokémon directly...');
            
            // Si le Pokémon n'est pas trouvé, essayer de le récupérer directement depuis les données
            // ou utiliser les informations de base
            mysteryPokemon = {
                id: gameData.dailyPokemonId,
                name: 'Unknown Pokémon',
                slug: '',
                dex_number: 0,
                image_url: '',
            };
            console.warn('Using fallback Pokémon data');
        }
        
        console.log('Mystery Pokémon found:', mysteryPokemon.name);
        
        const mysteryImageUrl = mysteryPokemon.image_url || (gameData.imageBaseUrl ? `${gameData.imageBaseUrl}/${mysteryPokemon.slug}.png` : '');
        
        const $result = $('#pokedle-result');
        console.log('Result container found:', $result.length);
        
        if ($result.length === 0) {
            console.error('Result container not found. Creating it...');
            // Créer le conteneur s'il n'existe pas
            $result = $('<div class="pokedle-result" id="pokedle-result"></div>');
            $('.pokedle-game').after($result);
        }
        
        const i18n = gameData.i18n || {};
        
        let resultHtml = '<div class="pokedle-success">';
        resultHtml += `<h3>${i18n.congratulations || 'Congratulations!'}</h3>`;
        if (mysteryImageUrl) {
            resultHtml += `<div class="pokedle-result-pokemon-image"><img src="${mysteryImageUrl}" alt="${mysteryPokemon.name}" /></div>`;
        }
        const foundText = i18n.youFound || 'You found';
        const inText = i18n.in || 'in';
        const attemptText = attemptsCount === 1 ? (i18n.attempt || 'attempt') : (i18n.attempts || 'attempts');
        resultHtml += `<p>${foundText} <strong>${mysteryPokemon.name}</strong> ${inText} ${attemptsCount} ${attemptText}!</p>`;
        if (mysteryPokemon.dex_number) {
            resultHtml += `<p class="pokedle-result-dex">#${mysteryPokemon.dex_number.toString().padStart(3, '0')}</p>`;
        }
        const timeText = i18n.time || 'Time';
        resultHtml += `<p>${timeText}: ${formatTime(completionTime)}</p>`;
        resultHtml += '</div>';
        
        console.log('Setting result HTML and showing');
        $result.html(resultHtml).show();

        // Sauvegarder le score
        saveScore(attemptsCount, true, completionTime);

        // Recharger le classement
        loadLeaderboard();

        // Désactiver les contrôles
        $('#pokedle-pokemon-input').prop('disabled', true);
        $('#pokedle-submit').prop('disabled', true);
        $('#pokedle-show-answer').prop('disabled', true).hide();
        
        // Scroller vers le résultat
        setTimeout(function() {
            const offset = $result.offset();
            if (offset) {
                $('html, body').animate({
                    scrollTop: offset.top - 100
                }, 500);
            }
        }, 100);
    }

    /**
     * Termine le jeu sans succès
     */
    function endGameWithoutSuccess() {
        console.log('endGameWithoutSuccess called', { isGameComplete });
        
        if (isGameComplete) {
            console.log('Game already complete, returning');
            return; // Déjà terminé
        }
        
        isGameComplete = true;

        // Révéler le Pokémon mystère
        // Comparer les IDs en tant qu'entiers pour éviter les problèmes de type
        let mysteryPokemon = gameData.allPokemon.find(p => parseInt(p.id) === parseInt(gameData.dailyPokemonId));
        if (!mysteryPokemon) {
            console.error('Mystery Pokémon not found. ID:', gameData.dailyPokemonId);
            console.error('Available Pokémon IDs:', gameData.allPokemon.map(p => p.id));
            console.error('Trying to fetch Pokémon directly...');
            
            // Si le Pokémon n'est pas trouvé, essayer de le récupérer directement depuis les données
            // ou utiliser les informations de base
            mysteryPokemon = {
                id: gameData.dailyPokemonId,
                name: 'Unknown Pokémon',
                slug: '',
                dex_number: 0,
                image_url: '',
            };
            console.warn('Using fallback Pokémon data');
        }
        
        console.log('Mystery Pokémon found:', mysteryPokemon.name);
        
        const mysteryImageUrl = mysteryPokemon.image_url || (gameData.imageBaseUrl ? `${gameData.imageBaseUrl}/${mysteryPokemon.slug}.png` : '');
        
        let $result = $('#pokedle-result');
        console.log('Result container found:', $result.length);
        
        if ($result.length === 0) {
            console.error('Result container not found. Creating it...');
            // Créer le conteneur s'il n'existe pas
            $result = $('<div class="pokedle-result" id="pokedle-result"></div>');
            $('.pokedle-game').after($result);
        }
        
        const i18n = gameData.i18n || {};
        
        let resultHtml = '<div class="pokedle-failure">';
        resultHtml += `<h3>${i18n.tooBad || 'Too bad!'}</h3>`;
        if (mysteryImageUrl) {
            resultHtml += `<div class="pokedle-result-pokemon-image"><img src="${mysteryImageUrl}" alt="${mysteryPokemon.name}" /></div>`;
        }
        const wasText = i18n.theMysteryPokemonWas || 'The mystery Pokémon was';
        resultHtml += `<p>${wasText}: <strong>${mysteryPokemon.name}</strong></p>`;
        if (mysteryPokemon.dex_number) {
            resultHtml += `<p class="pokedle-result-dex">#${mysteryPokemon.dex_number.toString().padStart(3, '0')}</p>`;
        }
        const youMadeText = i18n.youMade || 'You made';
        const attemptText = attempts.length === 1 ? (i18n.attempt || 'attempt') : (i18n.attempts || 'attempts');
        resultHtml += `<p>${youMadeText} ${attempts.length} ${attemptText}.</p>`;
        resultHtml += '</div>';
        
        console.log('Setting result HTML and showing');
        $result.html(resultHtml).show();

        // Sauvegarder le score (échec)
        saveScore(attempts.length, false, Math.floor((Date.now() - startTime) / 1000));

        // Désactiver les contrôles
        $('#pokedle-pokemon-input').prop('disabled', true);
        $('#pokedle-submit').prop('disabled', true);
        $('#pokedle-show-answer').prop('disabled', true).hide();
        
        // Recharger le classement
        loadLeaderboard();
        
        // Scroller vers le résultat
        setTimeout(function() {
            const offset = $result.offset();
            if (offset) {
                $('html, body').animate({
                    scrollTop: offset.top - 100
                }, 500);
            }
        }, 100);
    }

    /**
     * Sauvegarde le score
     */
    function saveScore(attemptsCount, isSuccess, completionTime) {
        if (!gameData.isLoggedIn) {
            // Pour les utilisateurs anonymes, on peut quand même sauvegarder si nécessaire
            // Pour l'instant, on ne sauvegarde que pour les utilisateurs connectés
            return;
        }

        $.ajax({
            url: gameData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'poke_hub_pokedle_save_score',
                nonce: gameData.nonce,
                game_date: gameData.today,
                pokemon_id: gameData.dailyPokemonId,
                attempts: attemptsCount,
                is_success: isSuccess ? 1 : 0,
                completion_time: completionTime,
                score_data: {}
            },
            success: function(response) {
                if (response.success) {
                    console.log('Score sauvegardé avec succès');
                }
            }
        });
    }

    /**
     * Charge le classement
     */
    function loadLeaderboard(type = 'today') {
        $.ajax({
            url: gameData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'poke_hub_pokedle_get_leaderboard',
                nonce: gameData.nonce,
                game_date: gameData.today,
                limit: 10,
                type: type
            },
            success: function(response) {
                if (response.success) {
                    let leaderboardData = null;
                    if (response.data && response.data.leaderboard) {
                        leaderboardData = response.data.leaderboard;
                    } else if (Array.isArray(response.data)) {
                        leaderboardData = response.data;
                    }
                    if (leaderboardData) {
                        displayLeaderboard(leaderboardData, type);
                    }
                }
            }
        });
    }

    /**
     * Affiche le classement (style lolix.gg)
     */
    function displayLeaderboard(leaderboard, currentType = 'today') {
        const $content = $('#pokedle-leaderboard-content');
        
        // Créer les onglets
        let html = '<div class="pokedle-leaderboard-tabs">';
        html += `<button class="pokedle-leaderboard-tab ${currentType === 'today' ? 'active' : ''}" data-tab="today">${gameData.i18n.today}</button>`;
        html += `<button class="pokedle-leaderboard-tab ${currentType === 'global' ? 'active' : ''}" data-tab="global">${gameData.i18n.allTime}</button>`;
        html += '</div>';

        // Gestion des clics sur les onglets
        setTimeout(function() {
            $('.pokedle-leaderboard-tab').off('click').on('click', function() {
                const tabType = $(this).data('tab');
                $('.pokedle-leaderboard-tab').removeClass('active');
                $(this).addClass('active');
                loadLeaderboard(tabType === 'all' || tabType === 'global' ? 'global' : 'today');
            });
        }, 100);
        
        if (leaderboard.length === 0) {
            html += '<p class="pokedle-leaderboard-empty">' + (gameData.i18n.noScoresYet || 'No scores yet.') + '</p>';
            $content.html(html);
            return;
        }

        // Top 3 en cards
        const top3 = leaderboard.slice(0, 3);
        const rest = leaderboard.slice(3);

        if (top3.length > 0) {
            html += '<div class="pokedle-leaderboard-top3">';
            top3.forEach((entry, index) => {
                const rank = index + 1;
                const rankText = rank === 1 ? (gameData.i18n.first || '1st') : rank === 2 ? (gameData.i18n.second || '2nd') : (gameData.i18n.third || '3rd');
                const rankClass = rank === 1 ? 'rank-1' : rank === 2 ? 'rank-2' : 'rank-3';
                
                html += `<div class="me5rine-lab-card pokedle-leaderboard-card ${rankClass}">`;
                html += `<div class="pokedle-card-rank">${rankText}</div>`;
                html += `<div class="pokedle-card-avatar">${getInitials(entry.display_name || (gameData.i18n.anonymous || 'Anonymous'))}</div>`;
                html += `<div class="pokedle-card-username">${entry.display_name || (gameData.i18n.anonymous || 'Anonymous')}</div>`;
                html += `<div class="pokedle-card-score">`;
                html += `<span class="pokedle-card-score-value">${entry.attempts}</span>`;
                const attemptsText = entry.attempts === 1 ? (gameData.i18n.attempt || 'attempt') : (gameData.i18n.attempts || 'attempts');
                html += `<span class="pokedle-card-score-label"> ${attemptsText}</span>`;
                html += `<div class="pokedle-card-trophy">🏆</div>`;
                html += `</div>`;
                html += `<div class="pokedle-card-time">${formatTime(entry.completion_time)}</div>`;
                html += `<div class="pokedle-card-progress"><div class="pokedle-card-progress-bar" style="width: 100%"></div></div>`;
                html += `</div>`;
            });
            html += '</div>';
        }

        // Reste de la liste
        if (rest.length > 0) {
            html += '<div class="pokedle-leaderboard-list">';
            rest.forEach((entry, index) => {
                const rank = index + 4;
                html += `<div class="pokedle-leaderboard-item">`;
                html += `<div class="pokedle-item-rank">${rank}</div>`;
                html += `<div class="pokedle-item-avatar">${getInitials(entry.display_name || gameData.i18n.anonymous)}</div>`;
                html += `<div class="pokedle-item-username">${entry.display_name || gameData.i18n.anonymous}</div>`;
                html += `<div class="pokedle-item-score">${entry.attempts}</div>`;
                html += `<div class="pokedle-item-trophy">🏆</div>`;
                html += `<div class="pokedle-item-time">${formatTime(entry.completion_time)}</div>`;
                html += `</div>`;
            });
            html += '</div>';
        }

        $content.html(html);
        
        // Réattacher les événements des onglets après l'insertion du HTML
        $('.pokedle-leaderboard-tab').off('click').on('click', function() {
            const tabType = $(this).data('tab');
            $('.pokedle-leaderboard-tab').removeClass('active');
            $(this).addClass('active');
            loadLeaderboard(tabType === 'all' || tabType === 'global' ? 'global' : 'today');
        });
    }

    /**
     * Obtient les initiales d'un nom
     */
    function getInitials(name) {
        if (!name || name === gameData.i18n.anonymous) {
            return '?';
        }
        const parts = name.trim().split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }

    /**
     * Affiche un message de complétion si l'utilisateur a déjà joué
     */
    function showCompletedMessage() {
        // Le message est déjà affiché dans le HTML
        // On peut ajouter des fonctionnalités supplémentaires ici
    }

    /**
     * Affiche le résultat du Pokémon (pour le bouton "Voir le résultat")
     */
    function showPokemonResult(pokemonId) {
        const pokemon = gameData.allPokemon.find(p => p.id === pokemonId);
        if (!pokemon) {
            return;
        }

        const imageUrl = pokemon.image_url || (gameData.imageBaseUrl ? `${gameData.imageBaseUrl}/${pokemon.slug}.png` : '');
        
        let resultHtml = '<div class="pokedle-success">';
        resultHtml += '<h3>Pokémon du jour</h3>';
        if (imageUrl) {
            resultHtml += `<div class="pokedle-result-pokemon-image"><img src="${imageUrl}" alt="${pokemon.name}" /></div>`;
        }
        resultHtml += `<p><strong>${pokemon.name}</strong></p>`;
        if (pokemon.dex_number) {
            resultHtml += `<p class="pokedle-result-dex">#${pokemon.dex_number.toString().padStart(3, '0')}</p>`;
        }
        resultHtml += '</div>';

        // Créer ou utiliser une zone de résultat
        let $result = $('#pokedle-result');
        if ($result.length === 0) {
            $result = $('<div id="pokedle-result" class="pokedle-result"></div>');
            $('.pokedle-game').after($result);
        }
        
        $result.html(resultHtml).show();
        
        // Scroller vers le résultat
        $('html, body').animate({
            scrollTop: $result.offset().top - 100
        }, 500);
    }

    /**
     * Formate le temps en minutes:secondes
     */
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    // Initialisation au chargement du DOM
    $(document).ready(function() {
        if ($('#poke-hub-pokedle').length) {
            initPokedle();
        }
    });

})(jQuery);

