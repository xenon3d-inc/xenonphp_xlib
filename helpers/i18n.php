<?php
/**
 * This is the internationalisation function (i18n)
 * 
 * @param associative array of string ['fr' => "texte", 'en' => "text"]
 * @param lang string Override current language
 * @return string the text in the current language
 */
function __(array $texts, $lang = null) {
    if ($lang === null) $lang = LANG;
    if (isset($texts[$lang])) return $texts[$lang];
    if (isset($texts[DEFAULT_LANG])) return $texts[DEFAULT_LANG];
    if (count($texts) > 0) return current($texts);
}
