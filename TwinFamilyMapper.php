<?php
namespace Stanford\TwinFamilyMapper;

require_once "emLoggerTrait.php";

use \REDCap;

class TwinFamilyMapper extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    private $settings;


    /**
     * @param int $project_id
     * @param $record
     * @param string $instrument
     * @param int $event_id
     * @param $group_id
     * @param $survey_hash
     * @param $response_id
     * @param $repeat_instance
     * @return void
     * @throws \Exception
     */
    public function redcap_save_record( int $project_id, $record, string $instrument, int $event_id, $group_id, $survey_hash, $response_id, $repeat_instance ) {
        $email_primary_field    = $this->getSetting('email-primary-field');
        if (empty($email_primary_field)) $this->throwError("Missing required primary email field in EM Config!");
        $email_secondary_field  = $this->getSetting('email-secondary-field');
        if (empty($email_secondary_field)) $this->throwError("Missing required secondary email field in EM Config!");

        # Determine if we are on the right form by checking if variables being saved contain either email field
        if (!isset($_POST[$email_primary_field]) && !isset($_POST[$email_secondary_field])) {
            # Both are missing - wrong instrument
            $this->emDebug("Save doesn't include an email field");
            return false;
        }

        $this->twinCheck($record, $event_id);
    }


    /**
     * @param $record
     * @param $event_id
     * @return boolean
     * @throws \Exception
     */
    public function twinCheck($record, $event_id) {

        # See if the required config fields are set
        $email_primary_field    = $this->getSetting('email-primary-field');
        if (empty($email_primary_field)) $this->throwError("Missing required primary email field in EM Config!");
        $email_secondary_field  = $this->getSetting('email-secondary-field');
        if (empty($email_secondary_field)) $this->throwError("Missing required secondary email field in EM Config!");
        $family_id_field = $this->getSetting('family-id-field');
        if (empty($family_id_field)) $this->throwError("Missing required Family ID Field in EM Config!");

        # Check if family id is already set for current record
        $family_id = isset($_POST[$family_id_field]) ? $_POST[$family_id_field] : $this->getFieldValue($family_id_field, $event_id, $record);
        //$this->emDebug("Family ID for Record $record on save: $family_id");
        if (!empty($family_id)) {
            $this->emDebug("[$record] Family ID has already been set to $family_id - so nothing to do");
            return false;
        }


        # Check for matching twin -- starting with saved record's secondary email from post or database
        $email_secondary = isset($_POST[$email_secondary_field]) ? $_POST[$email_secondary_field] : $this->getFieldValue($email_secondary_field, $event_id, $record);
        if (!empty($email_secondary)) {
            // Look for matching twin
            if ($this->findMatchingTwin($record, $event_id, $email_primary_field, $email_secondary)) {
                // Found and set family using secondary search
                // $this->emDebug("[$record] Found match with secondary email");
                return true;
            }
        }

        # Check for matching twin -- starting with saved record's secondary email
        $email_primary = isset($_POST[$email_primary_field]) ? $_POST[$email_primary_field] : $this->getFieldValue($email_primary_field, $event_id, $record);
        if (!empty($email_primary)) {
            if ($this->findMatchingTwin($record, $event_id, $email_secondary_field, $email_primary)) {
                // Found and set family using primary search
                // $this->emDebug("Found match with primary email");
                return true;
            }
        }
        return false;
    }


    /**
     * Lazy setting capture tool
     * @param $setting
     * @return mixed
     */
    public function getSetting($setting) {
        if (!isset($settings[$setting])) {
            $val = $this->getProjectSetting($setting);
            $settings[$setting] = $val;
        }
        return $settings[$setting];
    }


    /**
     * @param $field_name
     * @param $event_id
     * @param $record
     * @return mixed
     */
    public function getFieldValue($field_name, $event_id, $record) {
        $params = [
            REDCap::getRecordIdField() => $record,
            "events"    => [ $event_id ],
            "fields"    => [ $field_name ],
            "record"    => [ $record ]
        ];
        $data = REDCap::getData($params);
        // $this->emDebug($data);
        return $data[$record][$event_id][$field_name];
    }


    /**
     * @param $message
     * @return mixed
     * @throws \Exception
     */
    private function throwError($message) {
        REDCap::logEvent("Error",$message);
        throw new \Exception($message);
    }


    /**
     * @param $current_record
     * @param $current_event
     * @param $search_field
     * @param $search_value
     * @return mixed|void
     */
    private function findMatchingTwin($current_record, $current_event, $search_field, $search_value)
    {
        $family_id_field = $this->getSetting('family-field');
        $params = [
            "events" => [$current_event],
            "fields" => [REDCap::getRecordIdField(), $family_id_field],
            "filterLogic" => "[$search_field] = '$search_value'"
        ];
        $results = REDCap::getData($params);

        if (empty($results)) {
            # No matches found
            $this->emDebug("[$current_record] No matches for for $search_field = $search_value");
            return false;
        }

        # We found at least one match
        $count = count($results);

        if ($count > 1) {
            REDCap::logEvent("More than 1 Twin Match", "Record $current_record matched multiple twins using $search_value.  Only linking one record.  \nDetails: " . json_encode($results));
            $this->emError("[$current_record] Found more than one match for requested field -- taking first", $results);
        }

        $result = array_shift($results);

        // Match Found
        $twin_record_id = $result[$current_event][REDCap::getRecordIdField()];

        // Check if match family is set
        if (!empty($result[$current_event][$family_id_field])) {
            // Family id is already set for match
            $twin_family_id = $result[$current_event][$family_id_field];
            $this->emDebug("[$current_record] Found twin in record $twin_record_id - using their family id of $twin_family_id");
            $new_family_id = $twin_family_id;
        } else {
            // Neither main or twin have family id yet
            $new_family_id = $this->getNextFamilyId();
            // Save the new family ID to twin first
            $this->emDebug("[$current_record] Saving new family id $new_family_id to twin record $twin_record_id");
            $this->setFamilyID($twin_record_id, $current_event, $new_family_id);
        }

        // Save the new family ID to the current record
        $this->emDebug("[$current_record] Saving new family id $new_family_id");
        $this->setFamilyID($current_record, $current_event, $new_family_id);
        // At this point we are done as we identified the family
        return true;
    }


    /**
     * @param $record
     * @param $event_id
     * @param $family_value
     * @return void
     * @throws \Exception
     */
    private function setFamilyID($record, $event_id, $family_value) {
        $family_id_field = $this->getSetting("family-id-field");
        $data = [
            $record => [
                $event_id => [
                    $family_id_field => $family_value
                ]
            ]
        ];
        $params = [
            "data"=>$data
        ];
        $result = REDCap::saveData($params);
        //$this->emDebug("Save Data Results", $result);
        if (!empty($result['errors'])) {
            $this->throwError(implode("\n", $result['errors']));
        }
    }


    /**
     * @return string
     */
    private function getNextFamilyId() {
        $family_id_field = $this->getSetting('family-id-field');
        $family_field_prefix = $this->getSetting('family-id-field-prefix') ?? "";
        $prefix_length = strlen($family_field_prefix);

        # Load all records
        $params = [
            "fields"    => [ REDCap::getRecordIdField(), $family_id_field ],
        ];
        $data = REDCap::getData($params);
        //todo get prefix
        $max_id = 1;
        foreach ($data as $record_id => $events) {
            foreach ($events as $event_id => $event_data) {
                if (!empty($event_data[$family_id_field])) {
                    $this_id = $event_data[$family_id_field];
                    $suffix=0;
                    if ($prefix_length) {
                        // We are using a family_field_prefix
                        $k_prefix = substr($this_id, 0, $prefix_length);
                            if( strcasecmp($k_prefix, $family_field_prefix) == 0 ) {
                                # Case-insensitive match
                                $suffix = substr($this_id,$prefix_length);
                            }
                    } else {
                        $suffix = $this_id;
                    }
                    if (is_numeric($suffix) && intval($suffix) >= $max_id) {
                        $max_id = intval($suffix) + 1;
                    }
                }
            }
        }
        return $family_field_prefix . ($max_id);
    }


    public function getRecordTable($filter_out_records_with_family_ids = false) {
        $family_id_field        = $this->getSetting('family-id-field');
        $email_primary_field    = $this->getSetting('email-primary-field');
        $email_secondary_field  = $this->getSetting('email-secondary-field');

        $params = [
            "fields"    => [ REDCap::getRecordIdField(), $family_id_field, $email_primary_field, $email_secondary_field ],
        ];
        $data = REDCap::getData($params);

        $summary = [];
        foreach ($data as $record_id => $events) {
            $this_family_id = '';
            $this_email_primary = '';
            $this_email_secondary = '';
            foreach ($events as $event_id => $event_data) {
                $family_id = $event_data[$family_id_field];
                $email_primary = $event_data[$email_primary_field];
                $email_secondary = $event_data[$email_secondary_field];
                if (!empty($family_id) || !empty($email_primary) || !empty($email_secondary)) {
                    $this_event_id        = $event_id;
                    $this_family_id       = $family_id;
                    $this_email_primary   = $email_primary;
                    $this_email_secondary = $email_secondary;
                    break;
                }
            }
            if (!empty($this_family_id) && $filter_out_records_with_family_ids) {
                // Skip
            } else {
                $summary[] = [
                    $record_id,
                    $this_event_id,
                    $this_family_id,
                    $this_email_primary,
                    $this_email_secondary
                ];
            }
        }
        return $summary;
    }


    /**
     * @param $data
     * @param $init_method
     * @return void
     */
    public function injectJSMO($data = null, $init_method = null) {
        echo $this->initializeJavascriptModuleObject();
        ?><script src="<?=$this->getUrl("assets/jsmo.js",true)?>"></script>
        <script>
            (function() {
                const module = <?php echo $this->getJavascriptModuleObjectName() ?>;
                <?php if (!empty($data)) echo "module.data = " . json_encode($data) . ";\n"; ?>
                <?php if (!empty($init_method)) echo "module.afterRender(module." . $init_method . ");\n"; ?>
                console.log(module);
            })();
        </script>
        <?php
    }


    /**
     * @param $action
     * @param $payload
     * @param $project_id
     * @param $record
     * @param $instrument
     * @param $event_id
     * @param $repeat_instance
     * @param $survey_hash
     * @param $response_id
     * @param $survey_queue_hash
     * @param $page
     * @param $page_full
     * @param $user_id
     * @param $group_id
     * @return array
     * @throws \Exception
     */
    public function redcap_module_ajax($action, $payload, $project_id, $record, $instrument, $event_id, $repeat_instance,
        $survey_hash, $response_id, $survey_queue_hash, $page, $page_full, $user_id, $group_id)
    {
        $this->emDebug("Ajax: $action with payload: " . json_encode($payload));
        switch($action) {
            case "TestAction":
                \REDCap::logEvent("Test Action Received");
                $result = [
                    "success"=>true,
                    "user_id"=>$user_id
                ];
                break;
            case "getRecordTable":
                $result = $this->getRecordTable();
                break;
            case "processAllMissingFamilyIds":
                // TODO process record...
                $data = $this->getRecordTable(true);
                foreach ($data as $row) {
                    list($record_id,$event_id,$family_id,$email_primary,$email_secondary) = $row;
                    $result = $this->twinCheck($record_id,$event_id);
                    // $this->emDebug("[$record_id] TwinCheck result: " . json_encode($result));
                }
                $result = $this->getRecordTable();
                break;
            default:
                // Action not defined
                throw new \Exception ("Action $action is not defined");
        }

        // Return is left as php object, is converted to json automatically
        return $result;
    }

}
