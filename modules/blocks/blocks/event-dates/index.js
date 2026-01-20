/**
 * Enregistrement côté client du bloc "Dates d'événement"
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

    // Enregistrer le bloc (le rendu est géré par render.php)
    registerBlockType('pokehub/event-dates', {
        edit: function() {
            return wp.element.createElement(
                'div',
                { className: 'pokehub-block-placeholder' },
                wp.element.createElement('p', {}, __('Dates d\'événement', 'poke-hub')),
                wp.element.createElement('small', {}, __('Ce bloc affiche automatiquement les dates de l\'événement.', 'poke-hub'))
            );
        },
        save: function() {
            // Bloc dynamique, pas de save
            return null;
        }
    });
})();




