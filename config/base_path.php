<?php
// Base path configuration
// Auto-detect environment: Docker (root) vs Local XAMPP (subdirectory)
if (getenv('DOCKER_ENV') === 'true') {
    // Running in Docker - app is at root
    define('BASE_PATH', '');
} else {
    // Running locally (XAMPP) - app is in subdirectory
    define('BASE_PATH', '/BARGAIN_SPORT');
}

// Helper function to generate URLs
function url($path = '') {
    return BASE_PATH . $path;
}
