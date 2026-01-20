/**
 * Gestion du collapse/expand et de l'état actif des quêtes d'événements
 */
(function($){
    'use strict';
    
    // Initialiser au chargement du document
    $(document).ready(function(){
        // Gérer le collapse/expand au clic sur le toggle OU sur toute la quête
        $(document).on('click', '.event-field-research-list .quest-toggle, .event-field-research-list .quest-header, .event-field-research-list .task', function(e){
            e.stopPropagation();
            e.preventDefault();
            
            var $li = $(this).closest('li');
            var $toggle = $li.find('.quest-toggle');
            
            // Toggle la classe expanded
            $li.toggleClass('expanded');
            var isExpanded = $li.hasClass('expanded');
            $toggle.attr('aria-expanded', isExpanded ? 'true' : 'false');
            
            return false;
        });
        
        // Gérer l'état actif au clic sur le li (mais pas sur le header/toggle)
        $(document).on('click', '.event-field-research-list li', function(e){
            // Ne pas déclencher si on clique sur le header, toggle ou task
            if ($(e.target).closest('.quest-toggle, .quest-header, .task').length) {
                return;
            }
            // Si on clique sur le li directement, toggle expanded aussi
            if ($(e.target).closest('.reward-list').length === 0) {
                var $toggle = $(this).find('.quest-toggle');
                $(this).toggleClass('expanded');
                var isExpanded = $(this).hasClass('expanded');
                $toggle.attr('aria-expanded', isExpanded ? 'true' : 'false');
            }
            $(this).siblings().removeClass('active');
            $(this).toggleClass('active');
        });
    });
})(jQuery);

