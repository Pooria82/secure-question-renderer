<?php
function getDirection($text) {
    $clean = strip_tags($text);
    $clean = preg_replace('/^[\d\s\p{P}\p{S}]+/u', '', $clean);
    return preg_match('/^[\p{Arabic}]/u', $clean) ? 'rtl' : 'ltr';
}
var_dump(getDirection('37. Which sentence has the word with a different vowel sound than the others?'));
