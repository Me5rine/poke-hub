<?php
// File: includes/functions/pokehub-encryption.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chiffre une valeur sensible en utilisant les salts WordPress.
 *
 * @param string $value Valeur à chiffrer
 * @return string|false Valeur chiffrée en base64 ou false en cas d'erreur
 */
function poke_hub_encrypt_value($value) {
    if (empty($value)) {
        return '';
    }

    // Utiliser les salts WordPress pour générer une clé de chiffrement
    $key = wp_salt('nonce');
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    
    if ($iv_length === false) {
        // Fallback si OpenSSL n'est pas disponible
        return base64_encode($value);
    }

    $iv = openssl_random_pseudo_bytes($iv_length);
    if ($iv === false) {
        return false;
    }

    $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
    if ($encrypted === false) {
        return false;
    }

    // Combiner IV et données chiffrées, puis encoder en base64
    return base64_encode($iv . $encrypted);
}

/**
 * Déchiffre une valeur précédemment chiffrée.
 *
 * @param string $encrypted_value Valeur chiffrée en base64
 * @return string|false Valeur déchiffrée ou false en cas d'erreur
 */
function poke_hub_decrypt_value($encrypted_value) {
    if (empty($encrypted_value)) {
        return '';
    }

    // Décoder depuis base64
    $data = base64_decode($encrypted_value, true);
    if ($data === false) {
        // Peut-être une ancienne valeur non chiffrée, retourner telle quelle
        return $encrypted_value;
    }

    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    if ($iv_length === false) {
        // Fallback si OpenSSL n'est pas disponible
        return base64_decode($encrypted_value);
    }

    // Extraire l'IV et les données chiffrées
    if (strlen($data) < $iv_length) {
        // Format invalide, peut-être une ancienne valeur non chiffrée
        return $encrypted_value;
    }

    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);

    // Utiliser les salts WordPress pour générer la même clé
    $key = wp_salt('nonce');

    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    if ($decrypted === false) {
        // Échec du déchiffrement, peut-être une ancienne valeur
        return $encrypted_value;
    }

    return $decrypted;
}






