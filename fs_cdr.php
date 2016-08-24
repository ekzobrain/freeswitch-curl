<?php

/**
 * @package FS_CURL
 * @license BSD
 * @author  Raymond Chandler (intralanman) <intralanman@gmail.com>
 * @version 0.1
 * Class for inserting xml CDR records
 * @return object
 */
class fs_cdr extends fs_curl
{
    /**
     * This object is the objectified representation of the XML CDR
     * @var SimpleXMLElement
     */
    public $xml_cdr;

    /**
     * This array will hold the db field and their corresponding value
     * @var array
     */
    public $values = [];

    /**
     * This is where we instantiate our parent and set up our CDR object
     */
    public function __construct()
    {
        parent::__construct();

        $cdr = stripslashes($this->request['cdr']);
        $this->debug($cdr);

        $this->xml_cdr = new SimpleXMLElement($cdr);
    }

    /**
     * This is where we run the bulk of our logic through other methods
     */
    public function main()
    {
        $this->set_record_values();
        $this->insert_cdr();
    }

    /**
     * This method will take the db fields and paths defined above and
     * set the values array to be used for the insert
     */
    public function set_record_values()
    {
        /**
         * @var stdClass $xml
         */
        $xml = $this->xml_cdr;
        $callflow = is_array($xml->callflow) ? $xml->callflow[0] : $xml->callflow;
        $caller_profile = $callflow->caller_profile;
        $variables = $xml->variables;

        $this->values = [
            'username'           => $caller_profile->username,
            'caller_id_name'     => $variables->effective_caller_id_name,
            'caller_id_number'   => $variables->effective_caller_id_number,
            'destination_number' => $caller_profile->destination_number,
            'context'            => $caller_profile->context,
            'start_stamp'        => urldecode($variables->start_stamp),
            'answer_stamp'       => urldecode($variables->answer_stamp),
            'end_stamp'          => urldecode($variables->end_stamp),
            'duration'           => $variables->duration,
            'billsec'            => $variables->billsec,
            'hangup_cause'       => $variables->hangup_cause,
            'uuid'               => $caller_profile->uuid,
            'accountcode'        => $variables->accountcode,
            'read_codec'         => $variables->read_codec,
            'write_codec'        => $variables->write_codec,
        ];
        $this->debug($this->values);
    }

    /**
     * finally do the insert of the CDR
     */
    public function insert_cdr()
    {
        $query = sprintf(
            "INSERT INTO cdr (%s) VALUES (%s)",
            join(',', array_keys($this->values)), join(',', $this->values)
        );
        $this->debug($query);
        $this->db->exec($query);
    }
}
