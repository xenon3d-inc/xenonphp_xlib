<?php
function X_asset($path) {
    return BASE_URL.$path.'?'.@filemtime(DOCUMENT_ROOT.BASE_URL.$path);
}
function X_asset_js($path, $forceIncludeAlsoInDev = false) {
    if (!DEV || PROD || $forceIncludeAlsoInDev) {
        echo '<script>';
        include DOCUMENT_ROOT.BASE_URL.$path;
        echo '</script>';
    } else {
        echo '<script src="'.X_asset($path).'"></script>';
    }
}
function X_asset_css($path, $forceIncludeAlsoInDev = false) {
    if (!DEV || PROD || $forceIncludeAlsoInDev) {
        echo '<style>';
        include DOCUMENT_ROOT.BASE_URL.$path;
        echo '</style>';
    } else {
        echo '<link rel="stylesheet" href="'.X_asset($path).'">';
    }
}
