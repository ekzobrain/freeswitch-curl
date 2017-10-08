<?php

/**
 * @package FS_CURL
 * @license BSD
 * @author  Raymond Chandler (intralanman) <intralanman@gmail.com>
 * @version 0.1
 * Class for XML dialplan
 */
class fs_dialplan extends fs_curl
{
    private $special_class_file;

    /**
     * This is the method that determines the XML output. Customized dialplans can
     * be easily created by adding a record to the dialplan_special table with the
     * appropriate values. The php class MUST contain a "main()" method. The method
     * should write directly to the xmlw obj that's pased or take care of writing
     * out the xml itself and exiting as to not return.
     *
     */
    public function main()
    {
        $this->comment($this->request);
        $context = $this->request['Hunt-Context'];
        if ($this->is_specialized_dialplan($context)) {
            $this->debug("$context should be handled in a specialized dialplan class file");
            if (!include_once($this->special_class_file)) {
                $this->file_not_found();
            }
            $class = sprintf('dialplan_%s', $context);
            if (!class_exists($class)) {
                $this->comment("No Class of name $class");
                $this->file_not_found();
            }
            $obj = new $class;
            /**
             * recieving method should take incoming parameter as &$something
             */
            $obj->main($this);
        } else {
            $dp_array = $this->get_dialplan($context);
            $this->writeDialplan($dp_array);
        }
        $this->output_xml();
    }

    public function is_specialized_dialplan($context)
    {
        $query = sprintf(
            "SELECT * FROM dialplan_special WHERE context='%s'", $context
        );
        $this->debug($query);
        $res = $this->db->query($query);

        if ($res->rowCount() == 1) {
            $this->debug("numRows() == 1");
            $row = $res->fetch();
            $this->debug($row);
            $this->special_class_file = sprintf('dialplans/%s', $row['class_file']);
            return true;
        } else {
            return false;
        }
    }

    /**
     * This method will pull dialplan from database
     *
     * @param string $context context name for XML dialplan
     *
     * @return array
     */
    private function get_dialplan($context)
    {
        $query = sprintf('SELECT
			dialplan_context.%1$sname%1$s AS context_name,
			dialplan_extension.%1$sname%1$s AS extension_name,
			%1$scontinue%1$s AS extension_continue,
			%1$sfield%1$s AS condition_field,
			%1$sexpression%1$s AS condition_expression,
			%1$sbreak%1$s AS condition_break, dialplan_condition.condition_id,
			%1$sapplication%1$s AS action_application,
			%1$sdata%1$s AS action_data,	
			%1$santi_action%1$s AS action_anti_action,
			%1$sinline%1$s AS action_inline
		FROM dialplan_context
			INNER JOIN dialplan_extension USING(context_id)
			INNER JOIN dialplan_condition USING(extension_id)
			LEFT JOIN dialplan_action USING(condition_id)
		WHERE dialplan_context.%1$sname%1$s = \'%2$s\'
		ORDER BY dialplan_context.weight,
                 dialplan_extension.weight,
                 dialplan_condition.weight,
                 dialplan_action.weight'
            , DB_FIELD_QUOTE, $context
        );
        $this->debug($query);
        $res = $this->db->query($query);

        if ($res->rowCount() < 1) {
            $this->debug("nothing to do, let's just return not found");
            $this->file_not_found();
        }

        $dp_array = [];
        while ($row = $res->fetch()) {
            $ct = $row['context_name'];
            $en = $row['extension_name'];
            $ec = $row['extension_continue'] === null ? '' : ($row['extension_continue'] ? 'true' : 'false');
            $cf = $row['condition_field'];
            $ce = $row['condition_expression'];
            $cb = $row['condition_break'];
            $ci = $row['condition_id'];

            $dp_array[$ct]["$en;$ec"]["$cf;$ce;$cb;$ci"][] = [
                'anti_action' => $row['action_anti_action'],
                'application' => $row['action_application'],
                'data'        => $row['action_data'],
                'inline'      => $row['action_inline'],
            ];
        }
        $this->debug($dp_array);

        return $dp_array;
    }

    /**
     * Write XML dialplan from the array returned by get_dialplan
     * @see  fs_dialplan::get_dialplan
     *
     * @param array $dpArray Multi-dimentional array from which we write the XML
     *
     * @todo this method should REALLY be broken down into several smaller methods
     *
     */
    private function writeDialplan($dpArray)
    {

        $this->xmlw->startElement('section');
        $this->xmlw->writeAttribute('name', 'dialplan');
        $this->xmlw->writeAttribute('description', 'FreeSWITCH Dialplan');

        foreach ($dpArray as $context => $extensions) {
            $this->xmlw->startElement('context');
            $this->xmlw->writeAttribute('name', $context);

            foreach ($extensions as $extension => $conditions) {
                $this->xmlw->startElement('extension');

                $ex_split = explode(';', $extension);
                $this->xmlw->writeAttribute('name', $ex_split[0]);
                if ($ex_split[1]) {
                    $this->xmlw->writeAttribute('continue', $ex_split[1]);
                }

                foreach ($conditions as $condition => $actions) {
                    $this->xmlw->startElement('condition');

                    $c_split = explode(';', $condition);
                    if ($c_split[0]) {
                        if (in_array($c_split[0], ['date_time', 'time_of_day', 'wday'])) {
                            $this->xmlw->writeAttribute($c_split[0], $c_split[1]);
                        } else {
                            $this->xmlw->writeAttribute('field', $c_split[0]);
                            $this->xmlw->writeAttribute('expression', $c_split[1]);
                        }
                    }
                    if ($c_split[2]) {
                        $this->xmlw->writeAttribute('break', $c_split[2]);
                    }

                    foreach ($actions as $action) {
                        if (empty($action['application'])) {
                            continue;
                        }
                        $this->xmlw->startElement($action['anti_action'] ? 'anti-action' : 'action');
                        $this->xmlw->writeAttribute('application', $action['application']);
                        if ($action['data'] !== null) {
                            $this->xmlw->writeAttribute('data', $action['data']);
                        }
                        if ($action['inline'] !== null) {
                            $this->xmlw->writeAttribute('inline', $action['inline'] ? 'true' : 'false');
                        }
                        $this->xmlw->endElement();
                    }
                    //</condition>
                    $this->xmlw->endElement();
                }
                // </extension>
                $this->xmlw->endElement();
            }
            // </context>
            $this->xmlw->endElement();
        }
        // </section>
        $this->xmlw->endElement();
    }
}
