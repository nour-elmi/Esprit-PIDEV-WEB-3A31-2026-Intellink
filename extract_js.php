<?php
$content = file_get_contents('templates/base.html.twig');
preg_match_all('/<script>(.*?)<\/script>/s', $content, $matches);
$js = "";
foreach($matches[1] as $match) {
    if (strpos($match, 'var currentUserId') !== false || strpos($match, 'function slideIntoDiscussion') !== false || strpos($match, 'isSending') !== false) {
        $js .= "\n" . $match;
    }
}
// just replace twig tags temporarily
$js = preg_replace('/{{.*?}}/', '1', $js);
$js = preg_replace('/{%.*?%}/', '', $js);
file_put_contents('test.js', $js);
