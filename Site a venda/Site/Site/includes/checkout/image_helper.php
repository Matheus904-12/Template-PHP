<?php
/**
 * Image Helper - Helps find and validate cart item images
 * This file resolves image path issues in the checkout process
 */

function getValidImagePath($imagePath) {
    // Define possible base directories to look for images
    $possiblePaths = [
        '', // Current directory
        '../adminView/uploads/produtos/', // Standard product upload directory
        'img/produtos/', // Alternative product image directory
        '../img/produtos/', // Another possible path
        '../adminView/assets/images/produtos/' // Another possible path
    ];
    
    // First check if the provided image path already exists and is valid
    if (file_exists($imagePath) && is_file($imagePath)) {
        return $imagePath;
    }
    
    // Extract filename from path
    $filename = basename($imagePath);
    
    // Check each possible base directory
    foreach ($possiblePaths as $basePath) {
        $testPath = $basePath . $filename;
        if (file_exists($testPath) && is_file($testPath)) {
            return $testPath;
        }
    }
    
    // If we have a path with 'uploads/produtos' but can't find the file,
    // try without that part of the path
    if (strpos($imagePath, 'uploads/produtos/') !== false) {
        $simpleFilename = basename($imagePath);
        foreach ($possiblePaths as $basePath) {
            $testPath = $basePath . $simpleFilename;
            if (file_exists($testPath) && is_file($testPath)) {
                return $testPath;
            }
        }
    }
    
    // If all else fails, return a default placeholder image
    if (file_exists('../adminView/uploads/produtos/placeholder.jpeg')) {
        return '../adminView/uploads/produtos/placeholder.jpeg';
    } elseif (file_exists('img/placeholder.png')) {
        return 'img/placeholder.png';
    } else {
        return 'https://via.placeholder.com/150';  // Fallback to an external placeholder
    }
}

/**
 * Helper function to fix image paths in cart items
 * @param array $cartItems Array of cart items to fix image paths
 * @return array Updated cart items with valid image paths
 */
function fixCartItemImagePaths($cartItems) {
    if (!is_array($cartItems)) {
        return $cartItems;
    }
    
    foreach ($cartItems as &$item) {
        if (isset($item['imagem']) && !empty($item['imagem'])) {
            $item['imagem'] = getValidImagePath($item['imagem']);
        }

        // Also check for alternate image fields that might be used
        if (isset($item['imagem_path']) && !empty($item['imagem_path'])) {
            $item['imagem_path'] = getValidImagePath($item['imagem_path']);
        }
        
        if (isset($item['image']) && !empty($item['image'])) {
            $item['image'] = getValidImagePath($item['image']);
        }
    }
    
    return $cartItems;
}

/**
 * Debug function to display image details
 * @param string $imagePath Path to check
 * @return array Information about the image path
 */
function debugImagePath($imagePath) {
    $info = [
        'original_path' => $imagePath,
        'path_exists' => file_exists($imagePath),
        'is_file' => is_file($imagePath),
        'realpath' => realpath($imagePath) ?: 'Not found',
        'dirname' => dirname($imagePath),
        'basename' => basename($imagePath),
        'corrected_path' => getValidImagePath($imagePath)
    ];
    
    return $info;
}

/**
 * Generate HTML img tag with proper image path
 * @param string $imagePath Original image path
 * @param string $altText Alt text for the image
 * @param string $cssClass CSS class for the image
 * @return string HTML img tag with correct path
 */
function getImageTag($imagePath, $altText = '', $cssClass = '') {
    $validPath = getValidImagePath($imagePath);
    $class = !empty($cssClass) ? "class=\"$cssClass\"" : '';
    return "<img src=\"$validPath\" alt=\"$altText\" $class>";
}