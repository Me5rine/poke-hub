<?php
// modules/user-profiles/public/user-profiles-friend-codes-header.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Header adaptatif pour les pages de codes amis
 * 
 * @param string $context Context ('friend_codes' ou 'vivillon')
 */
function poke_hub_friend_codes_render_header($context = 'friend_codes') {
    ?>
    <div class="me5rine-lab-form-block">
        <?php if ($context === 'friend_codes') : ?>
            <h2 class="me5rine-lab-title-large"><?php esc_html_e('Pokémon GO Friend Codes', 'poke-hub'); ?></h2>
            <p class="me5rine-lab-form-description">
                <?php esc_html_e('Find and share Pokémon GO friend codes. Filter by country, team, or reason to find players to interact with.', 'poke-hub'); ?>
            </p>
            <div class="me5rine-lab-form-row me5rine-lab-form-col-gap">
                <div class="me5rine-lab-form-col">
                    <strong><?php esc_html_e('🔍 Search', 'poke-hub'); ?></strong>
                    <p class="me5rine-lab-form-description">
                        <?php esc_html_e('Filter by country, team, or reason to find friend codes that match your needs.', 'poke-hub'); ?>
                    </p>
                </div>
                <div class="me5rine-lab-form-col">
                    <strong><?php esc_html_e('📋 Share', 'poke-hub'); ?></strong>
                    <p class="me5rine-lab-form-description">
                        <?php esc_html_e('Add your friend code to allow other players to find you easily.', 'poke-hub'); ?>
                    </p>
                </div>
                <div class="me5rine-lab-form-col">
                    <strong><?php esc_html_e('⚡ Quick', 'poke-hub'); ?></strong>
                    <p class="me5rine-lab-form-description">
                        <?php esc_html_e('Copy a code with one click or scan the QR code directly from Pokémon GO.', 'poke-hub'); ?>
                    </p>
                </div>
            </div>
        <?php elseif ($context === 'vivillon') : ?>
            <h2 class="me5rine-lab-title-large"><?php esc_html_e('Vivillon Patterns', 'poke-hub'); ?></h2>
            <p class="me5rine-lab-form-description">
                <?php esc_html_e('Find friend codes by Vivillon pattern. Each region has a unique pattern to collect!', 'poke-hub'); ?>
            </p>
            <div class="me5rine-lab-form-row me5rine-lab-form-col-gap">
                <div class="me5rine-lab-form-col">
                    <strong><?php esc_html_e('🦋 Collection', 'poke-hub'); ?></strong>
                    <p class="me5rine-lab-form-description">
                        <?php esc_html_e('Find players with the Vivillon pattern you are looking for to complete your collection.', 'poke-hub'); ?>
                    </p>
                </div>
                <div class="me5rine-lab-form-col">
                    <strong><?php esc_html_e('🌍 By Region', 'poke-hub'); ?></strong>
                    <p class="me5rine-lab-form-description">
                        <?php esc_html_e('Filter by country to find Vivillon patterns specific to each region.', 'poke-hub'); ?>
                    </p>
                </div>
                <div class="me5rine-lab-form-col">
                    <strong><?php esc_html_e('📨 Gift Exchange', 'poke-hub'); ?></strong>
                    <p class="me5rine-lab-form-description">
                        <?php esc_html_e('Exchange gifts to get postcards and unlock patterns.', 'poke-hub'); ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// Hook pour permettre aux thèmes de surcharger
add_action('poke_hub_friend_codes_header', 'poke_hub_friend_codes_render_header', 10, 1);

