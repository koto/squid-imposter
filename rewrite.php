#!/usr/bin/php
<?php
/**
 * Redirecting script - this file checks for URL matching config.ini entries
 * and redirects those URLs to Imposter server
 *
 * This file is a part of squid-imposter project.
 * @author Krzysztof Kotowicz <kkotowicz at gmail dot com>
 */
ini_set('display_errors', false);
set_error_handler('error_handler');

$cfg = read_config_file();

while ($input = fgets(STDIN)) {

    if (!empty($cfg['test_mode'])) // refresh config file at each request
        $cfg = read_config_file();

    // Split the output (space delimited) from squid into an array.
    $line = explode(' ', $input);

    $url = trim($line[0]);

    if (count($line) >= 4) {
        $ip_fqdn = $line[1];
        $user = $line[2];
        $method = $line[3];
    }

    $elements = array('payload', 'manifest');

    foreach ($cfg as $site_id => $site) {
        foreach ($elements as $element) {
            if ($match = url_matches($url, $site, $element)) {
                $params = array(
                  'site' => $site_id,
                  'url' => $url,
                  'match' => $match,
                  'payload' => $site['payload'],
                  'manifest' => $site['manifest'],
                  'show' => $element,
                );

                $url = $cfg['config']['imposter'] . '?' . http_build_query($params, '', '&');
                break 2;
            }
        }
    }

    echo  $url . "\n";
}

/**
 * In case of any error, redirect to a dummy page with error details
 */
function error_handler($errno, $errstr, $errfile, $errline) {
    echo '301:http://error-at-line-' . (int) $errline . '/' . urlencode($errstr) . "\n";
    die();
}

function read_config_file() {
    return parse_ini_file(dirname(__FILE__) . '/config.ini', true);
}

/**
 * Checks if URL matches given site element
 *
 * @param string $url
 * @param array $site
 * @param string $element
 */
function url_matches($url, $site, $element) {
    if (empty($site[$element]))
        return false;

    if (!empty($site[$element . '_match'])) {
        // match URL to a regular expression
        // Caution: be sure to test the regex!
        $pattern = $site[$element . '_match'];

        $match = array();
        if (preg_match($pattern, $url, $match)) {
            return $match;
        }
        return false;
    }

    return ($url == $site[$element]) ? array($url) : false; // simple string matching
}
