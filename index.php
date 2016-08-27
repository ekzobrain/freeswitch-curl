<?php
/**
 * @package FS_CURL
 * @license BSD
 * @author  Raymond Chandler (intralanman) <intralanman@gmail.com>
 * @version 0.1
 * initial page hit in all curl requests
 */

/**
 * define for the time that execution of the script started
 */
define('START_TIME', preg_replace('/^0\.([0-9]+) ([0-9]+)$/', '\2.\1', microtime()));

/**
 * Pre-Class initialization die function
 * This function should be called on any
 * critical error condition before the fs_curl
 * class is successfully instantiated.
 * @return void
 */

function file_not_found($no = false, $str = false, $file = false, $line = false)
{
    if ($no == E_STRICT) {
        return;
    }
    header('Content-Type: text/xml');
    printf("<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\n");
    printf("<document type=\"freeswitch/xml\">\n");
    printf("  <section name=\"result\">\n");
    printf("    <result status=\"not found\"/>\n");
    printf("  </section>\n");
    if (!empty($no) && !empty($str) && !empty($file) && !empty($line)) {
        printf("  <!-- ERROR: $no - ($str) on line $line of $file -->\n");
    }
    printf("</document>\n");
    exit();
}

set_error_handler('file_not_found');

if (!(@include_once('fs_curl.php'))
    || !(@include_once('global_defines.php'))
) {
    trigger_error(
        'could not include fs_curl.php or global_defines.php', E_USER_ERROR
    );
}

if (isset($_REQUEST['cdr'])) {
    $section = 'cdr';
} else {
    $section = $_REQUEST['section'];
}
$section_file = sprintf('fs_%s.php', $section);
include_once($section_file);

switch ($section) {
    case 'configuration':
        $config = $_REQUEST['key_value'];
        $processor = sprintf('configuration/%s.php', $config);
        $class = str_replace('.', '_', $config);
        if (!(@include_once($processor))) {
            trigger_error("unable to include $processor");
        }

        /**
         * @var $conf fs_configuration
         */
        $conf = new $class;
        $conf->comment("class name is $class");
        break;
    case 'dialplan':
        $conf = new fs_dialplan();
        break;
    case 'directory':
        $conf = new fs_directory();
        break;
    case 'cdr':
        $conf = new fs_cdr();
        break;
    case 'chatplan':
        $conf = new fs_chatplan();
        break;
}

$conf->debug('---- Start _REQUEST ----');
$conf->debug($_REQUEST);
$conf->debug('---- End _REQUEST ----');
$conf->main();
$conf->output_xml();
