<?php
/**
 * This is the internationalisation function (i18n)
 * 
 * @param associative array of string ['fr' => "texte", 'en' => "text"] OR string key from config files
 * @param lang string Override current language
 * @return string the text in the current language
 */
function __($texts, $lang = null) {
    global $X_CONFIG;
    if ($lang === null) $lang = LANG;
    if (is_string($texts)) {
        if (isset($X_CONFIG['texts_'.$lang][$texts])) return $X_CONFIG['texts_'.$lang][$texts];
        if (isset($X_CONFIG['texts_'.DEFAULT_LANG][$texts])) return $X_CONFIG['texts_'.DEFAULT_LANG][$texts];
        return "{".$texts."}";
    } else if (is_array($texts)) {
        if (isset($texts[$lang])) return $texts[$lang];
        if (isset($texts[DEFAULT_LANG])) return $texts[DEFAULT_LANG];
        if (count($texts) > 0) return current($texts);
    }
}
