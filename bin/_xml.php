<?php
// Hacky functions to read the version/release notes out of XML files.

function _pw_get_version()
{
    $xmlFile = __DIR__ . '/../package.template.xml';

    // where i'm from we parse xml with regexes and we like it
    preg_match(
        '/<version>(.*?)<\/version>/',
        file_get_contents($xmlFile),
        $m
    );

    return $m[1];
}

function _pw_get_release_notes()
{
    $xmlFile = __DIR__ . '/../package.template.xml';

    // where i'm from we parse xml with regexes and we like it
    preg_match(
        '/<notes>(.*?)<\/notes>/',
        file_get_contents($xmlFile),
        $m
    );

    return $m[1];
}
