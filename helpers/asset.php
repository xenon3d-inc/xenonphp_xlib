<?php

function X_asset($path) {
    return BASE_URL.$path.'?'.@filemtime(DOCUMENT_ROOT.BASE_URL.$path);
}

function X_asset_js($path, $forceIncludeAlsoInDev = false) {
    if (!DEV || PROD || $forceIncludeAlsoInDev) {
        echo '<script>';
        if (ASSETS_MINIFY_AND_CACHE_PATH) {
            X_minified_js(DOCUMENT_ROOT . BASE_URL . $path, ASSETS_MINIFY_AND_CACHE_PATH . BASE_URL . $path);
        } else {
            include DOCUMENT_ROOT . BASE_URL . $path;
        }
        echo '</script>';
    } else {
        echo '<script src="'.X_asset($path).'"></script>';
    }
}

function X_asset_css($path, $forceIncludeAlsoInDev = false) {
    if (!DEV || PROD || $forceIncludeAlsoInDev) {
        echo '<style>';
        if (ASSETS_MINIFY_AND_CACHE_PATH) {
            X_minified_css(DOCUMENT_ROOT . BASE_URL . $path, ASSETS_MINIFY_AND_CACHE_PATH . BASE_URL . $path);
        } else {
            include DOCUMENT_ROOT . BASE_URL . $path;
        }
        echo '</style>';
    } else {
        echo '<link rel="stylesheet" href="'.X_asset($path).'">';
    }
}

function X_js($path, $forceMinify = false) {
    echo '<script>';
    if (ASSETS_MINIFY_AND_CACHE_PATH && (!DEV || PROD || $forceMinify)) {
        X_minified_js($path, str_replace(APPLICATION_PATH, ASSETS_MINIFY_AND_CACHE_PATH, $path));
    } else {
        include $path;
    }
    echo '</script>';
}

function X_css($path, $forceMinify = false) {
    echo '<style>';
    if (ASSETS_MINIFY_AND_CACHE_PATH && (!DEV || PROD || $forceMinify)) {
        X_minified_css($path, str_replace(APPLICATION_PATH, ASSETS_MINIFY_AND_CACHE_PATH, $path));
    } else {
        include $path;
    }
    echo '</style>';
}

function X_minified_js($fromPath, $toPath) {
    if (!is_file($toPath)) {
        X_minify_js($fromPath, $toPath);
    }
    include $toPath;
}

function X_minified_css($fromPath, $toPath) {
    if (!is_file($toPath)) {
        X_minify_css($fromPath, $toPath);
    }
    include $toPath;
}

function X_minify_js($fromPath, $toPath) {
    $js = file_get_contents($fromPath);
    $jsminified = \External\JSMin::minify($js);
    if (!is_dir(dirname($toPath))) mkdir(dirname($toPath), 0770, true);
    if ($jsminified) file_put_contents($toPath, $jsminified);
}

function X_minify_css($fromPath, $toPath) {
    $css = file_get_contents($fromPath);
    $cssmin = new \External\CSSmin();
    $cssminified = $cssmin->run($css);
    if (!is_dir(dirname($toPath))) mkdir(dirname($toPath), 0770, true);
    if ($cssminified) file_put_contents($toPath, $cssminified);
}