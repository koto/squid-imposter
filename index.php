<?php
/**
 * Decorating script - this file serves given .payload and .manifest files
 * for requested site.
 * The results are stored in HTML5 offline cache by client's browser
 *
 * This file is a part of squid-imposter project.
 * @author Krzysztof Kotowicz <kkotowicz at gmail dot com>
 */
if (empty($_GET['site'])) {
    echo 'This file should not be requested directly';
    die();
}
$_GET['site'] = preg_replace('/[^a-z0-9_\.-]/i', '', $_GET['site']);

$standard_prefix = dirname(__FILE__) . '/payloads/' . $_GET['site'];
$default_prefix = dirname(__FILE__) . '/payloads/default';

$output = "Squid-imposter could not find files for site: {$_GET['site']}!";

// try site_id.{manifest|payload|append} files first,
// default.{manifest|payload|append} then
foreach (array($standard_prefix, $default_prefix) as $prefix) {
    switch ($_GET['show']) {
        case 'manifest':
            if (file_exists($prefix.'.manifest')) {
                header('Content-Type: text/cache-manifest');
                $output = file_get_contents($prefix.'.manifest');
                break 2;
            }
        break;
        case 'payload':
            if (file_exists($prefix.'.payload')) {
                $output = file_get_contents($prefix.'.payload');
                break 2;
            }

            if (file_exists($prefix.'.append')) {
                $output = append_file_to_url($_GET['url'], $prefix.'.append');
                break 2;
            }
        break;
    }
}

// set up standard HTTP caching
$ten_years = 315569260;
header('Cache-Control: max-age=' . $ten_years); // ten years
header('Last-Modified: Mon, 29 Jun 1998 02:28:12 GMT'); // it was modifed long ago, so is most likely fresh
header("Expires: " . gmdate("D, d M Y H:i:s", time() + $ten_years) . " GMT");

echo decorate($output, $_GET);
exit();

function append_file_to_url($url, $file) {
    $output = get_url($url);
    return str_ireplace('</body>', file_get_contents($file) . '</body>', $output);
}

/**
 * Decorates resulting HTML file, replacing %%variables%% with actual values and appending manifest URL to <html> tag.
 * @param string $text
 * @param array $variables
 * @return string
 */
function decorate($text, $variables = array()) {
    $text = str_ireplace('<html', '<html manifest="%%manifest%%" ', $text);
    $search = $replace = array();
    foreach ($variables as $k => $v) {
        $search[] = '%%' . $k . '%%';
        $replace[] = $v;
    }
    return str_replace($search, $replace, $text);
}

/**
 * Retrieves given URL (GET method), forwarding current client headers to target host
 * @param string $url
 * @return string response body
 */
function get_url($url) {
    // Ensure library/ is on include_path
    set_include_path(implode(PATH_SEPARATOR, array(
      realpath(dirname(__FILE__) . '.'),
      get_include_path(),
    )));

    require_once 'Zend/Loader/Autoloader.php';
    $autoloader = Zend_Loader_Autoloader::getInstance();
    $autoloader->setFallbackAutoloader(true);

    $headers = array();

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
    }

    unset($headers['Via']);
    unset($headers['X-Forwarded-For']);
    unset($headers['Host']);

    $client = new Zend_Http_Client();
    $client->setUri($url);
    $client->setHeaders($headers);
    $client->request();

    $response = $client->getLastResponse();

    $body = $response->getBody();
    return $body;
}

?>
