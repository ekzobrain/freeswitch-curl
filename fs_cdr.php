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
            'username'           => (string)$caller_profile->username ?: null,
            'caller_id_name'     => urldecode((string)$variables->effective_caller_id_name) ?: null,
            'caller_id_number'   => (string)$variables->effective_caller_id_number ?: null,
            'destination_number' => (string)$caller_profile->destination_number,
            'context'            => (string)$caller_profile->context,
            'start_stamp'        => urldecode((string)$variables->start_stamp),
            'answer_stamp'       => urldecode((string)$variables->answer_stamp) ?: null,
            'end_stamp'          => urldecode((string)$variables->end_stamp),
            'duration'           => (int)$variables->duration,
            'billsec'            => (int)$variables->billsec,
            'hangup_cause'       => (string)$variables->hangup_cause,
            'uuid'               => (string)$caller_profile->uuid,
            'accountcode'        => (string)$variables->accountcode ?: null,
            'read_codec'         => (string)$variables->read_codec ?: null,
            'write_codec'        => (string)$variables->write_codec ?: null,
        ];
        $this->debug($this->values);
    }

    /**
     * finally do the insert of the CDR
     */
    public function insert_cdr()
    {
        $keys = array_keys($this->values);

        $query = sprintf("INSERT INTO cdr (%s) VALUES (:%s)", implode(', ', $keys), implode(', :', $keys));
        $this->debug($query);

        $statement = $this->db->prepare($query);
        $statement->execute($this->values);
        $this->db->counter += 1;
    }
}
