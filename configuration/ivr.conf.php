<?php
/**
 * @package    FS_CURL
 * @subpackage FS_CURL_Configuration
 * ivr.conf.php
 */

/**
 * @package    FS_CURL
 * @subpackage FS_CURL_Configuration
 * @license
 * @author     Raymond Chandler (intralanman) <intralanman@gmail.com>
 * @version    0.1
 * Write XML for ivr.conf
 */
class ivr_conf extends fs_configuration
{
    /**
     * This method will run all of the methods necessary to return
     * the XML for the ivr.conf
     * @return void
     */
    public function main()
    {
        $name = $this->request['Menu-Name'];
        $ivrs = $this->get_ivr_array($name);
        $this->write_config($ivrs);
    }

    /**
     * This method will fetch all of the ivr menus from the database
     * using the MDB2 pear class
     * @return array
     */
    private function get_ivr_array($name)
    {
        $query = "SELECT * FROM ivr_conf WHERE name='$name'";
        $res = $this->db->query($query);
        $res = $res->fetchAll();

        return $res;
    }

    /**
     * This method will write all of the entry elements with
     * their corresponding attributes
     * @return void
     */
    private function write_entries($ivr_id)
    {
        $query = "SELECT * FROM ivr_entries WHERE ivr_id=$ivr_id ORDER BY digits";
        $res = $this->db->query($query);
        $entries_array = $res->fetchAll();

        $entries_count = count($entries_array);
        if ($entries_count < 1) {
            return;
        }
        $entries_count = count($entries_array);
        for ($i = 0; $i < $entries_count; $i++) {
            //$this -> comment_array($entries_array[$i]);
            $this->xmlw->startElement('entry');
            $this->xmlw->writeAttribute('action', $entries_array[$i]['action']);
            $this->xmlw->writeAttribute('digits', $entries_array[$i]['digits']);
            if (!empty($entries_array[$i]['param'])) {
                $this->xmlw->writeAttribute('param', $entries_array[$i]['param']);
            }
            $this->xmlw->endElement();//</param>
        }
    }

    /**
     * This method will evaluate the data from the db and
     * write attributes that need written
     * @return void
     */
    private function write_menu_attributes($data)
    {
        $this->xmlw->writeAttribute('name', $data['name']);
        $this->xmlw->writeAttribute('greet-long', $data['greet_long']);
        if ($data['greet_short'] !== null) {
            $this->xmlw->writeAttribute('greet-short', $data['greet_short']);
        }
        if ($data['invalid_sound'] !== null) {
            $this->xmlw->writeAttribute('invalid-sound', $data['invalid_sound']);
        }
        if ($data['exit_sound'] !== null) {
            $this->xmlw->writeAttribute('exit-sound', $data['exit_sound']);
        }
        $this->xmlw->writeAttribute('timeout', $data['timeout']);
        $this->xmlw->writeAttribute('inter-digit-timeout', $data['inter_digit_timeout']);
        $this->xmlw->writeAttribute('digit-len', $data['digit_len']);
        if ($data['max_failures'] !== null) {
            $this->xmlw->writeAttribute('max-failures', $data['max_failures']);
        }
        if ($data['max_timeouts'] !== null) {
            $this->xmlw->writeAttribute('max-timeouts', $data['max_timeouts']);
        }
        if ($data['exec_on_max_failures'] !== null) {
            $this->xmlw->writeAttribute('exec-on-max-failures', $data['exec_on_max_failures']);
        }
        if ($data['exec_on_max_timeouts'] !== null) {
            $this->xmlw->writeAttribute('exec-on-max-timeouts', $data['exec_on_max_timeouts']);
        }

        if (!empty($data['tts_engine'])) {
            $this->xmlw->writeAttribute('tts-engine', $data['tts_engine']);
        }
        if (!empty($data['tts_voice'])) {
            $this->xmlw->writeAttribute('tts-voice', $data['tts_voice']);
        }
    }

    /**
     * This method will do the writing of the "menu" elements
     * and call the write_entries method to do the writing of
     * individual menu's "entry" elements
     * @return void
     */
    private function write_config($menus)
    {
        $menu_count = count($menus);
        $this->xmlw->startElement('configuration');
        $this->xmlw->writeAttribute('name', basename(__FILE__, '.php'));
        $this->xmlw->writeAttribute('description', 'Sofia SIP Endpoint');
        $this->xmlw->startElement('menus');
        for ($i = 0; $i < $menu_count; $i++) {
            $this->xmlw->startElement('menu');
            $this->write_menu_attributes($menus[$i]);
            $this->write_entries($menus[$i]['id']);
            $this->xmlw->endElement();
        }
        $this->xmlw->endElement();
        $this->xmlw->endElement();
    }
}
