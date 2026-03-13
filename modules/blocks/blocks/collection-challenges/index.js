/**
 * Enregistrement côté client du bloc "Défis de Collection"
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
    var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps;

    // Enregistrer le bloc (le rendu est géré par render.php)
    registerBlockType('pokehub/collection-challenges', {
        edit: function() {
            var props = useBlockProps ? useBlockProps({ className: 'pokehub-block-placeholder' }) : { className: 'pokehub-block-placeholder' };

            return el(
                'div',
                props,
                el('p', {}, __('Collection Challenges', 'poke-hub')),
                el('small', {}, __('This block automatically displays collection challenges from the event.', 'poke-hub'))
            );
        },
        save: function() {
            // Bloc dynamique, pas de save
            return null;
        }
    });
})();

