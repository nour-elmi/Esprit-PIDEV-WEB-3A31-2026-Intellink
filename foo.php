<?php
$h = "C:\\Users\\Kaaboura69\\AppData\\Roaming\\Code\\User\\History\\-2376e7d4\\";
$files = glob($h . "*.twig");
usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
foreach($files as $f) {
    if (strpos(file_get_contents($f), 'friend-request-received') !== false) {
        echo basename($f) . ": " . filemtime($f) . "\n";
    }
}
