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
		openlog('fs_curl', LOG_NDELAY | LOG_PID, LOG_USER);
		header('Content-Type: text/xml');
		$this->generate_request_array();
        $this->xmlw = $this->open_xml();
		$inc
			= ['required' => 'libs/fs_pdo.php']; // include an external file. i.e. 'required'=>'important_file.php'
		$this->include_files($inc);
		$this->connect_db(DEFAULT_DSN, DEFAULT_DSN_LOGIN, DEFAULT_DSN_PASSWORD);
		set_error_handler([$this, 'error_handler']);
		//trigger_error('blah', E_USER_ERROR);
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
			$this->comment($e->getMessage());
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
		$this->comment('Include Path = ' . ini_get('include_path'));

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
	 * Include files for child classes
	 * This method will include the files needed by child classes.
	 * Expects an associative array of type => path
	 * where type = [required|any other string]
	 *
	 * @param array $file_array associative array of files to include
	 *
	 * @todo add other types for different levels of errors
	 * @return int
	 */
	public function include_files($file_array)
	{
		$return = FS_CURL_SUCCESS;
		while (list($type, $file) = each($file_array)) {
			$inc = @include_once($file);
			if (!$inc) {
				$comment = sprintf(
					'Unable To Include %s File %s', $type, $file
				);
				$this->comment($comment);
				if ($type == 'required') {
					$return = FS_CURL_CRITICAL;
				} else {
					if ($return != FS_CURL_CRITICAL) {
						$return = FS_CURL_WARNING;
					}
				}
			}
		}
		if ($return == FS_CURL_CRITICAL) {
			$this->file_not_found();
		}

		return $return;
	}

	/**
	 * Class-wide error handler
	 * This method should be called whenever there is an error in any child
	 * classes, script execution and returning is pariatlly determined by
	 * defines
	 * @see  RETURN_ON_WARN
	 * @todo add other defines that control what, if any, comments gets output
	 * @return boolean
	 */
	public function error_handler($no, $str, $file, $line)
	{
		if ($no == E_STRICT) {
			return TRUE;
		}
		$file = preg_replace('/\.(inc|php)$/', '', $file);
		$this->comment(basename($file) . ":$line - $no:$str");

		switch ($no) {
			case E_USER_NOTICE:
			case E_NOTICE:
				break;
			case E_USER_WARNING:
			case E_WARNING:
				if (defined('RETURN_ON_WARN') && RETURN_ON_WARN == TRUE) {
					break;
				}
			case E_ERROR:
			case E_USER_ERROR:
			default:
				$this->file_not_found();
		}

		return TRUE;
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
