<?php

/**
 * @package FS_CURL
 * @license BSD
 * @author  Raymond Chandler (intralanman) <intralanman@gmail.com>
 * @version 0.1
 *          FreeSWITCH CURL base class
 *          Base class for all curl XML output, contains methods for XML output and
 *          connecting to a database
 * @return void
 */
class fs_curl
{
    /**
     * @var PDO
     */
    public $db;

    /**
     * Array of _REQUEST parameters passed
     * @var array
     */
    public $request;

    /**
     * @var XMLWriter
     */
    public $xmlw;

    /**
     * Array of comments to be output in the XML
     * @see fs_curl::comment
     * @var array
     */
    private $comments = [];

    public function __construct()
    {
        set_error_handler([$this, 'error_handler']);
        set_exception_handler([$this, 'exception_handler']);

        $this->generate_request_array();

        openlog('fs_curl', LOG_NDELAY | LOG_PID, LOG_USER);
        header('Content-Type: text/xml');
        $this->xmlw = $this->open_xml();

        $this->connect_db(DEFAULT_DSN, DEFAULT_DSN_LOGIN, DEFAULT_DSN_PASSWORD);
    }

    /**
     * Connect to a database via FS_PDO
     *
     * @param mixed $dsn data source for database connection (array or string)
     * @param       $login
     * @param       $password
     */
    public function connect_db($dsn, $login, $password)
    {
        try {
            $this->db = new PDO($dsn, $login, $password);
        } catch (PDOException $e) {
            $this->debug($e->getMessage());
            $this->file_not_found(); //program terminates in function file_not_found()
        }
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->debug("our driver is $driver");

        switch ($driver) {
            case 'mysql':
                $quoter = '`';
                break;
            case 'pgsql':
                if (DEFAULT_DSN_SCHEMA) {
                    $this->db->exec('SET SEARCH_PATH=' . DEFAULT_DSN_SCHEMA);
                }
                $quoter = '"';
                break;
            default:
                $quoter = '';
                break;
        }
        define('DB_FIELD_QUOTE', $quoter);
    }

    /**
     * Method to add comments to XML
     * Adds a comment to be displayed in the final XML
     *
     * @param string $comment comment string to be output in XML
     *
     * @return void
     */
    public function comment($comment)
    {
        $this->comments[] = $comment;
    }

    /**
     * Generate a globally accesible array of the _REQUEST parameters passed
     * Generates an array from the _REQUEST parameters that were passed, keeping
     * all key => value combinations intact
     * @return void
     */
    private function generate_request_array()
    {
        while (list($req_key, $req_val) = each($_REQUEST)) {
            if (!defined('FS_CURL_DEBUG') && $req_key == 'fs_curl_debug') {
                define('FS_CURL_DEBUG', $req_val);
            }
            //$this -> comment("$req_key => $req_val");
            $this->request[$req_key] = $req_val;
        }
    }

    /**
     * Actual Instantiation of XMLWriter Object
     * This method creates an XMLWriter Object and sets some needed options
     *
     * @return XMLWriter
     */
    private function open_xml()
    {
        $xmlw = new XMLWriter();
        $xmlw->openMemory();

        if (isset($this->request['fs_curl_debug']) && $this->request['fs_curl_debug'] > 0) {
            $indent = true;
        } else {
            $indent = false;
        }
        $xmlw->setIndent($indent);
        $xmlw->setIndentString('  ');

        $xmlw->startDocument('1.0', 'UTF-8', 'no');
        $xmlw->startElement('document');
        $xmlw->writeAttribute('type', 'freeswitch/xml');

        return $xmlw;
    }

    /**
     * Method to call on any error that can not be revovered from
     * This method was written to return a valid XML response to FreeSWITCH
     * in the event that we are unable to generate a valid configuration file
     * from the passed information
     * @return void
     */
    public function file_not_found()
    {
        $not_found = $this->open_xml();
        $not_found->startElement('section');
        $not_found->writeAttribute('name', 'result');
        $not_found->startElement('result');
        $not_found->writeAttribute('status', 'not found');
        $not_found->endElement();
        $not_found->endElement();
        /* we put the comments inside the root element so we don't
         * get complaints about markup outside of it */
        $this->comments2xml($not_found, $this->comments);
        $not_found->endElement();

        echo $not_found->outputMemory();
        exit;
    }

    /**
     * Generate XML comments from comments array
     * This [recursive] method will iterate over the passed array, writing XML
     * comments and calling itself in the event that the "comment" is an array
     *
     * @param object  $xml_obj   Already instantiated XMLWriter object
     * @param array   $comments  [Multi-dementional] Array of comments to be added
     * @param integer $space_pad Number of spaces to indent the comments
     *
     * @return void
     */
    private function comments2xml($xml_obj, $comments, $space_pad = 0)
    {
        $comment_count = count($comments);
        for ($i = 0; $i < $comment_count; $i++) {
            if (array_key_exists($i, $comments)) {
                if (!is_array($comments[$i])) {
                    $xml_obj->writeComment(" " . $comments[$i] . " ");
                } else {
                    $this->comments2xml($xml_obj, $comments[$i], $space_pad + 2);
                }
            }
        }
    }

    /**
     * End open XML elments in XMLWriter object
     * @return void
     */
    private function close_xml()
    {
        $this->xmlw->endElement();
        $this->xmlw->endElement();
        $this->xmlw->endElement();
    }

    /**
     * Close and Output XML and stop script execution
     * @return void
     */
    public function output_xml()
    {
        $this->comment(sprintf("Estimated Execution Time Is: %s"
            , (preg_replace(
                    '/^0\.(\d+) (\d+)$/', '\2.\1', microtime()) - START_TIME)
        ));

        $this->comments2xml($this->xmlw, $this->comments);
        $this->close_xml();
        $xml_out = $this->xmlw->outputMemory();
        $this->debug('---- Start XML Output ----');
        $this->debug(explode("\n", $xml_out));
        $this->debug('---- End XML Output ----');
        echo $xml_out;
        exit();
    }

    /**
     * Recursive method to add an array of comments
     *
     * @param     $array
     * @param int $spacepad
     */
    public function comment_array($array, $spacepad = 0)
    {
        $spaces = str_repeat(' ', $spacepad);
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $this->comment("$spaces$key => Array");
                $this->comment_array($val, $spacepad + 2);
            } else {
                $this->comment("$spaces$key => $val");
            }
        }
    }


    /**
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     *
     * @throws ErrorException
     */
    public function error_handler($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            return;
        }

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * @param Exception $e
     */
    public function exception_handler(Exception $e)
    {
        $this->comment($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $this->file_not_found();
    }

    /**
     * Function to print out debugging info
     * This method will recieve arbitrary data and send it using your method of
     * choice.... enable/disable by defining FS_CURL_DEBUG to and arbitrary integer
     *
     * @param mixed   $input       what to debug, arrays and strings tested, objects MAY work
     * @param integer $debug_level debug if $debug_level <= FS_CURL_DEBUG
     * @param integer $spaces
     */
    public function debug($input, $debug_level = -1, $spaces = 0)
    {
        if (defined('FS_CURL_DEBUG') && $debug_level <= FS_CURL_DEBUG) {
            if (is_array($input)) {
                $input = print_r($input, true);
            }

            $debug_str = sprintf("%s%s", str_repeat(' ', $spaces), $input);
            switch (FS_DEBUG_TYPE) {
                case 0:
                    syslog(LOG_NOTICE, $debug_str);
                    break;
                case 1:
                    $debug_str = preg_replace('/--/', '- - ', $debug_str);
                    $this->comment($debug_str);
                    break;
                case 2:
                    file_put_contents(FS_DEBUG_FILE, "$debug_str\n", FILE_APPEND);
                    break;
                default:
                    return;
            }
        }
    }
}
