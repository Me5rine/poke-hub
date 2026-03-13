/**
 * Gestion du collapse/expand et de l'état actif des quêtes d'événements
 */
(function($){
    'use strict';
    
    if (typeof $ === 'undefined') {
        console.error('PokeHub Quests: jQuery is required but not loaded');
        return;
    }
    
    function initQuestToggles() {
        // Nouveau système de quêtes (pokehub-event-quests-list)
        // Gérer le clic sur la zone principale de la quête
        $(document).off('click', '.pokehub-quest-main').on('click', '.pokehub-quest-main', function(e){
            // Ne pas déclencher si on clique sur un lien ou un élément interactif
            if ($(e.target).closest('a, button, input, select, .pokehub-quest-toggle').length) {
                return;
            }
            
            var $item = $(this).closest('.pokehub-quest-item');
            var $toggle = $item.find('.pokehub-quest-toggle');
            
            // Toggle la classe expanded
            $item.toggleClass('expanded');
            var isExpanded = $item.hasClass('expanded');
            $toggle.attr('aria-expanded', isExpanded ? 'true' : 'false');
        });
        
        // Gérer aussi le clic directement sur le toggle
        $(document).off('click', '.pokehub-quest-toggle').on('click', '.pokehub-quest-toggle', function(e){
            e.stopPropagation();
            e.preventDefault();
            var $item = $(this).closest('.pokehub-quest-item');
            $item.toggleClass('expanded');
            var isExpanded = $item.hasClass('expanded');
            $(this).attr('aria-expanded', isExpanded ? 'true' : 'false');
        });
    }
    
    // Initialiser au chargement du document
    $(document).ready(function(){
        initQuestToggles();
        
        // Ancien système (event-field-research-list) - compatibilité
        $(document).on('click', '.event-field-research-list li', function(e){
            if ($(e.target).closest('a, button, input, select').length) {
                return;
            }
            
            var $li = $(this);
            var $toggle = $li.find('.quest-toggle');
            
            $li.toggleClass('expanded');
            var isExpanded = $li.hasClass('expanded');
            $toggle.attr('aria-expanded', isExpanded ? 'true' : 'false');
            
            $li.siblings().removeClass('active');
            $li.toggleClass('active');
        });
        
        $(document).on('click', '.event-field-research-list .quest-toggle', function(e){
            e.stopPropagation();
        });
    });
    
    // Réinitialiser après le chargement complet de la page (pour contenu chargé dynamiquement)
    $(window).on('load', function(){
        initQuestToggles();
    });
})(jQuery);
