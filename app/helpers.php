<?php

use App\Models\Device;
use App\Models\NkePowerplug;
use App\Models\NkeSerialMapping;
use App\Models\UiText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use Illuminate\Support\Str;
// use Exception;

if(!function_exists('isNowNearThisTime'))
{
    function isNowNearThisTime($time, $around) {
        $start = strtotime(date('Y-m-d').$time);
        $end = strtotime(date('Y-m-d').$time . '+ ' . $around);

        $result = false;
        if( time() >= $start && time() <= $end ) {
            $result = true;
        }
        return $result;
    }
}
if (!function_exists('sendDownlinkToTTNForSmartPlug')) {
    /**
     * Sends Downlink command to TTN
     * Returns true/false
     * @param $sn Srting Example: 70B3D5E75E00CE2A
     * @param $command String Options: off, on, reset Example: on;
     * @param $flag String Default: auto; Options: auto, manual
     */
    function sendDownlinkToTTNForSmartPlug ($sn, $command, $flag='auto') {

        if ($command === 'on') {
            $state = 1; /* DB value */
            $payloadMsg = "EVAABgE="; /* On Command */
        } else if ($command === 'off') {
            $state = 0; /* DB value */
            $payloadMsg = "EVAABgA="; /* Off Command */
        } else if ($command === 'reset') {
            $payloadMsg = "EVAAUgA="; /* Reset Command */
        } else {
            $payloadMsg = "";
        }
        /* $payloadMsg = ($state == 2 ? "EVAAUgA=" : ($state == 1 ? "EVAABgE=" : "EVAABgA=")); ***ignored this short-code for clear understanding */

        // Only for On/Off command
        if ( Str::contains($command, ['on', 'off']) ) {
            // Step3: Before downlink need to update DB so that it does not repeat for AutoSwitch Feature
            // Update on/off switch flags in DB
            if ($flag === 'auto') {
                NkePowerplug::where('serial',$sn)->update(['on_switched' => $state, 'off_switched' => !$state]);
            } else {
                NkePowerplug::where('serial',$sn)->update(['switch_status' => $state]);
            }
        }

        $foundSensorInfo = NkeSerialMapping::where("serial",$sn)->first();
        $deviceID = ($foundSensorInfo) ? $foundSensorInfo->device_id : strtolower($sn); /* compatible */

        $payload = '{"downlinks":[{"frm_payload":"' . $payloadMsg . '","f_port":125,"priority":"NORMAL"}]}';
        $apiKey = env('SMARTPLUG_DOWNLINK_API_KEY');
        $url = env('SMARTPLUG_DOWNLINK').$deviceID.'/down/push';

        Log::info("NKEPPDownlink URL: ".$url." | Key: ".$apiKey." | Command: ".$command." | PayloadMsg: ".$payloadMsg);

        // IF API key not set, then exit
        if (!$apiKey) {
            return false;
        }

        $client = new \GuzzleHttp\Client();
        $res = $client->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization'=> 'Bearer ' . $apiKey
            ],
            'body' => $payload,
        ]);
        return $res->getBody()->getContents();
    }
}
if(!function_exists('publishSensorData')){
    function publishSensorData($device_sn, $data, $type="data"){
        // Step 1: Check Mqtt enable or not
        $device_info = Device::where('serial_number',$device_sn)->where('active',1)->first();
        if($device_info && $device_info->mqtt){
            $gName = $device_info->project->group_name;
            $dTypeChannel = $device_info->deviceType->mqtt_channel;
            $pId = $device_info->project_id;

            /* TODO: dynamic user:pass in future */
            $username = $gName;
            $password = $gName.".".$pId;
            /* TODO: dynamic user:pass in future */

            $channel = $gName."/".$dTypeChannel."/".$device_sn."/".$type;
            Log::info("mqtt_pub: ".$channel." | Serial: ".$device_sn." start");
            try{
                //config(['mqtt-client.connections.default.connection_settings.auth.username'=>$username]);
                //config(['mqtt-client.connections.default.connection_settings.auth.password'=>$password]);
                $mqtt = MQTT::connection();
                $mqtt->publish($channel, $data, 0);

            }catch(Exception $e){
                Log::error("MQTT | Error: ".$e->getMessage());
            }

            return "Published";
        }
    }
}
if(!function_exists('limit_usage')){
    function limit_usage(){
        return [
            "correct_zone" =>  0,
            "traffic_light" => 1,
            "reverse_traffic_light" => 2,
            "correct_zone_extend" => 3
        ];
    }
}
if(!function_exists('correct_zone')){
    function correct_zone(){
        return [
            "in_zone" =>  0,
            "out_zone" => 1
        ];
    }
}
if(!function_exists('traffic_light_states')){
    function traffic_light_states(){
        return [
            "green" =>  0,
            "yellow" => 1,
            "red" => 2
        ];
    }
}
if(!function_exists('reverse_traffic_light_states')){
    function reverse_traffic_light_states(){
        return [
            "red" =>  0,
            "yellow" => 1,
            "green" => 2
        ];
    }
}
if(!function_exists('setting_lebels')){
    function setting_lebels()
    {
        return array(
            array(
                "basic" => array(
                    "device_name" => array(
                        "text_key" => "device_name",
                        "field_name" => "device_name"
                    ),
                    "room" => array(
                        "text_key" => "room",
                        "field_name" => "room_name"
                    )
                ),
                "key_item" => array(
                    "alert" => array(
                        "min" => array(
                            "field_name" => "[key]_min",
                            "text_key" => array(
                                array(
                                    "limit_usage" => 0,
                                    "text_key" => "threshold_min",
                                ),
                                array(
                                    "limit_usage" => 1,
                                    "text_key" => "threshold_warning",
                                ),
                                array(
                                    "limit_usage" => 2,
                                    "text_key" => "threshold_alarm",
                                ),
                                array(
                                    "limit_usage" => 3,
                                    "text_key" => "threshold_min",
                                ),
                            ),
                            "type" => "text",
                            "apply_all_flag" => 1
                        ),
                        "max" => array(
                            "field_name" => "[key]_max",
                            "text_key" => array(
                                array(
                                    "limit_usage" => 0,
                                    "text_key" => "threshold_max",
                                ),
                                array(
                                    "limit_usage" => 1,
                                    "text_key" => "threshold_alarm",
                                ),
                                array(
                                    "limit_usage" => 2,
                                    "text_key" => "threshold_warning",
                                ),
                                array(
                                    "limit_usage" => 3,
                                    "text_key" => "threshold_max",
                                )
                            ),
                            "type" => "text",
                            "apply_all_flag" => 1
                        ),
                        "active_alerts" => array(
                            "text_key" => "activate_alerts",
                            "field_name" => "[key]_alerts_active",
                            "type" => "checkbox",
                            "apply_all_flag" => 1
                        ),
                    ),
                    "sms" => array(
                        "activate_sms" => array(
                            "text_key" => "activate_sms_notification",
                            "field_name" => "[key]_sms_active",
                            "type" => "checkbox",
                            "apply_all_flag" => 1
                        ),
                        "phone" => array(
                            "text_key" => "mobile_number_of_recipient",
                            "field_name" => "[key]_phone",
                            "type" => "text",
                            "apply_all_flag" => 1
                        ),
                        "phone_warning_text" => array(
                            "text_key" => "warning_text",
                            "field_name" => "[key]_sms_text_state_1",
                            "type" => "text"
                        ),
                        "phone_alarm_text" => array(
                            "text_key" => "alarm_text",
                            "field_name" => "[key]_sms_text_state_2",
                            "type" => "text"
                        ),
                    ),
                    "email" => array(
                        "activate_email" => array(
                            "text_key" => "activate_email_notification",
                            "field_name" => "[key]_email_active",
                            "type" => "checkbox",
                            "apply_all_flag" => 1
                        ),
                        "email" => array(
                            "text_key" => "email_of_recipient",
                            "field_name" => "[key]_email",
                            "type" => "text",
                            "apply_all_flag" => 1
                        ),
                        "email_warning_text" => array(
                            "text_key" => "warning_text",
                            "field_name" => "[key]_email_text_state_1",
                            "type" => "text"
                        ),
                        "email_alarm_text" => array(
                            "text_key" => "alarm_text",
                            "field_name" => "[key]_email_text_state_2",
                            "type" => "text"
                        ),
                    ),
                    "apply_settings_for_all_devices" => array(
                        "text_key" => "apply_settings_for_all_devices_of_this_type",
                        "field_name" => "apply_all_device"
                    )
                )
            ),
        );
    }
}
if(!function_exists('sensor_details')){
    function sensor_details(){
        return array(
            "information" => array(
                "text_key" => "sensor.information"
            ),
            "history" => array(
                "text_key" => "sensor.history"
            ),
            "event" => array(
                "text_key" => "sensor.event"
            ),
            "daily_summary" => array(
                "text_key" => "sensor.daily_summary"
            ),
            "sensing_data" => array(
                "text_key" => "sensor.sensing_data"
            ),
        );
    }
}
if(!function_exists('reduce_prell')){
    function reduce_prell($obj, $prell_value_1, $prell_value_2, $key_to_check)
    {
        if ($prell_value_1 > 0) {
            $obj->update([$key_to_check . "_prell_1" => 0]);
        }
        if ($prell_value_2 > 0) {
            $obj->update([$key_to_check . "_prell_2" => 0]);
        }
    }
}
if(!function_exists('check_dashboard_read_permission')){
    function check_dashboard_read_permission($project_id){
        if(!Auth::guard('api')->user()->isAdmin() && Auth::guard('api')->user()->project_id != $project_id){
            return false;
        }
        return true;
    }
}
if(!function_exists('check_dashboard_write_permission')){
    function check_dashboard_write_permission($project_id){
        if(Auth::guard('api')->user()->isUser()){
            return false;
        }
        if(Auth::guard('api')->user()->isUseradmin() && Auth::guard('api')->user()->project_id != $project_id){
            return false;
        }
        return true;
    }
}
if(!function_exists('check_dashboard_alert')){
    function check_dashboard_alert($obj, $device_info, $message, $keyArray = array()){
        // Checks whether alarms/warnings are activated
        $response = array(
            "warning_count" => 0,
            "alarm_count" => 0,
            "unreachable_count" => 0
        );
        $limitUsageEnum = limit_usage();
        $correct_zone = correct_zone();
        $traffic_light = traffic_light_states();
        $reverse_traffic_light = reverse_traffic_light_states();
        Log::info("Dashboard Alerts | Serial: ".$device_info->serial_number." start");
        if(!$obj->reachable){
            $response['unreachable'][$obj->serial] = array(
                "name" => $device_info->device_name,
                "room_name" => $device_info->room_name,
                "last_update" => $obj->insert_time
            );
            $response["unreachable_count"]++;
        }
        foreach($keyArray as $key){
            $limit_key = "limit_usage_" . $key;
            $active_key = $key. "_alerts_active";
            $state_key = "analog_state_" . $key;
            $usage = $obj->$limit_key;
            $state = $obj->$state_key;
            $active = $obj->$active_key;
            if ($active){
                // Interprets passed usage
                if ($usage == $limitUsageEnum['correct_zone'] || $usage == $limitUsageEnum['correct_zone_extend']) {

                    //let index = window.app.alarms.findIndex(alarm => alarm.unique == name+'-'+type);

                    // Interprets passed status
                    if ($state == $correct_zone['in_zone']) {

                        // Removes the alarm from the associated array, if it exists

                    } else if ($state == $correct_zone['out_zone']) {
                        $response['alarms'][$obj->serial][$key] = array(
                            "name" => $device_info->device_name,
                            "key_name" => $key,
                            "room_name" => $device_info->room_name,
                            "message_en" => (isset($message[$key."_alarm"])) ? $message[$key."_alarm"]->text_en : "",
                            "message_de" => (isset($message[$key."_alarm"])) ? $message[$key."_alarm"]->text_de : "",
                            "last_update" => $obj->insert_time
                        );
                        $response['alarm_count']++;
                    }
                } else if ($usage == $limitUsageEnum['traffic_light']) {
                    // Interprets passed status
                    if ($state == $traffic_light['green']) {

                    } else if ($state == $traffic_light['yellow']) {
                        $response['warnings'][$obj->serial][$key] = array(
                            "name" => $device_info->device_name,
                            "key_name" => $key,
                            "room_name" => $device_info->room_name,
                            "message_en" => (isset($message[$key."_warning"])) ? $message[$key."_warning"]->text_en : "",
                            "message_de" => (isset($message[$key."_warning"])) ? $message[$key."_warning"]->text_de : "",
                            "last_update" => $obj->insert_time
                        );
                        $response['warning_count']++;
                    } else if ($state == $traffic_light['red']) {

                        $response['alarms'][$obj->serial][$key] = array(
                            "name" => $device_info->device_name,
                            "key_name" => $key,
                            "room_name" => $device_info->room_name,
                            "message_en" => (isset($message[$key."_alarm"])) ? $message[$key."_alarm"]->text_en : "",
                            "message_de" => (isset($message[$key."_alarm"])) ? $message[$key."_alarm"]->text_de : "",
                            "last_update" => $obj->insert_time
                        );

                        $response['alarm_count']++;
                    }
                } else if ($usage == $limitUsageEnum['reverse_traffic_light']) {

                    if ($state == $reverse_traffic_light['green']) {

                    } else if ($state == $reverse_traffic_light['yellow']) {
                        $response['warnings'][$obj->serial][$key] = array(
                            "name" => $device_info->device_name,
                            "key_name" => $key,
                            "room_name" => $device_info->room_name,
                            "message_en" => (isset($message[$key."_warning"])) ? $message[$key."_warning"]->text_en:"",
                            "message_de" => (isset($message[$key."_warning"])) ? $message[$key."_warning"]->text_de  : "",
                            "last_update" => $obj->insert_time
                        );
                        $response['warning_count']++;
                    } else if ($state == $reverse_traffic_light['red']) {
                        $response['alarms'][$obj->serial][$key] = array(
                            "name" => $device_info->device_name,
                            "key_name" => $key,
                            "room_name" => $device_info->room_name,
                            "message_en" => (isset($message[$key."_alarm"])) ? $message[$key."_alarm"]->text_en : "",
                            "message_de" => (isset($message[$key."_alarm"])) ? $message[$key."_alarm"]->text_de : "",
                            "last_update" => $obj->insert_time
                        );
                        $response['alarm_count']++;
                    }
                }
            }else{
                Log::info("Serial: ".$device_info->serial_number." | Key: ".$key." | Alert not active");
            }
        }
        Log::info("Serial: ".$device_info->serial_number." | Alerts".json_encode($response));
        return $response;
    }
}
if(!function_exists('get_microtime_string')){
    function get_microtime_string(){
        list($usec, $sec) = explode(' ', microtime());
        return bcadd($sec, $usec, 8);
    }
}
if(!function_exists('get_duration')){
    function get_duration($s='',$e=''){
        if($e === '')$e = get_microtime_string();
        return bcsub($e, $s, 8);
    }
}
if(!function_exists('events_co2')){
    function events_co2($sn, $since, $till, $data){
        //Start timing total
        $timing_start = get_microtime_string();
        //Pufferobject for remembering the last read values in order to be able to form
        //to be able to form a delta.
        $last_state = [
            "analog_state_co2" => (object)[
                //The language label that will be displayed on the client as a clickable tab or similar.
                "lbl" => "CO2",

                //limit_usage must be considered individually for each field to be observed.
                //The corresponding DB field name is also required to be able to read out the value automatically.
                "limit_fieldname" => "limit_usage_co2",
                "limit_usage" => 0,

                //The last value of the observed DB field
                "value" => 0,

                "start_date" => "",
                "end_date" => ""
            ],
            "analog_state_motion" => (object)[
                "lbl" => "Motion",
                "limit_fieldname" => "limit_usage_motion",
                "limit_usage" => 0,
                "value" => 0,
                "start_date" => "",
                "end_date" => ""
            ],
            "analog_state_humidity" => (object)[
                "lbl" => "Humidity",
                "limit_fieldname" => "limit_usage_humidity",
                "limit_usage" => 0,
                "value" => 0,
                "start_date" => "",
                "end_date" => ""
            ],
            "analog_state_temperature" => (object)[
                "lbl" => "Temperature",
                "limit_fieldname" => "limit_usage_temperature",
                "limit_usage" => 0,
                "value" => 0,
                "start_date" => "",
                "end_date" => ""
            ],
            "analog_state_battery" => (object)[
                "lbl" => "Battery",
                "limit_fieldname" => "limit_usage_battery",
                "limit_usage" => 0,
                "value" => 0,
                "start_date" => "",
                "end_date" => ""
            ]
        ];

        //--- Message object
        $msg = (object)[
            "duration" => 0,
            "sn" => $sn,
            "since" => $since,
            "till" => $till,

            //Control counter of all passed records from the DB in the requested period.
            "rtotal" => 0,

            "data" => null
        ];
        //assoc Collection array of all status changes in chronological order.
        //NOTE: this is cast into an object at the end for JSON.
        //The filtered analog outputs are then the keys, the grouped data the values (object).
        $ala = [];
        //For each database field in last_state[] that is to be monitored automatically, //the corresponding field must also be queried in the SQL statement.
        //the corresponding field in the SQL statement must also be queried.
        //nur wenn since und til nicht leer sind. Es wird aber nicht geprüft, ob beide korrekte Datumsstrings sind.
        if($since !== "" && $till !== ""){
            foreach($data as $r){

                //Control counter
                $msg->rtotal++;

                //The time of the history entry, is the same for all fields of this record.
                $act_insert_time = $r->insert_time;

                //All database fields to watch for changes for each record.
                //compared to the last while loop.
                foreach($last_state as $fld => $o){
                    //The value of this specially named field
                    $act_value = $r->$fld;
                    //The corresponding limit usage for this DB field. The field name is noted in the control object
                    $lifld = $o->limit_fieldname;
                    $act_limit_usage = $r->$lifld;


                    $last_value = $o->value;

                    /*
                    Each status change results in a separate entry, or completes a running entry.
                    There are the stati
                        0 idle
                        1 waring
                        2 alarm

                    When jumping from 0 to 1 to two, a running entry for idle|warn is closed and a new alarm entry is started.
                    and then a new alarm entry is started.

                    The status can then also first fall back from 2 to 1 stat to 0.
                    Either way, descending values from 2 to or 1 to 0 will always terminate the running entry.
                    */
                    if($act_value > $last_value){
                        $o->end_date = $act_insert_time;
                        //Collect Start
                        $sd = trim($o->start_date);
                        $ed = trim($o->end_date);
                        if($sd === "")$sd = $since;
                        if($ed === "")$ed = $till;

                        $diff_sec = 0;
                        $diff_str = "";
                        if($sd !== "" && $ed !== ""){
                            $sdate = date_create($sd);
                            $edate = date_create($ed);
                            //Gives an Interval Object
                            $interval = date_diff($sdate, $edate);
                            //difference of the underlying timestamps
                            $sts = $sdate->getTimestamp();
                            $ets = $edate->getTimestamp();
                            //Formatted as string hours:minutes:seconds without "0" padLeft
                            $diff_str = $interval->format("%a:%h:%i:%s");

                            //Entries with 0 seconds are skipped. see below.
                            $diff_sec = $ets - $sts;
                        }

                        //The entries are already grouped at the top according to the underlying field names.
                        //So it is possible to list e.g. all detected co2 warnings separately from the
                        //the motion warnings.
                        //There is only a key created in the assoc array ala when it is actually used for the first time.
                        if(!isset($ala[$fld])){
                            //add control entry to collection array
                            $ala[$fld] = (object)[
                                //original field name
                                "fld" => $fld,
                                //Visible label for client output
                                "lbl" => $o->lbl,
                                //Auxiliary flag for group switching in the client.
                                "sel" => 0,
                                //original limit usage rule
                                "limit_usage" => $o->limit_usage,
                                //total number of all time spans found > 0
                                "icount" => 0,
                                //Array with the found time spans
                                "items" => []
                            ];
                        }

                        /*What alarm type this entry is depends on the corresponding limit_usage.

                        If limit usage = 0 then
                        0 idle
                        2 alarm

                        If limit usage = 1 or 2 then
                        0 idle
                        1 warn
                        2 alarm

                        We immediately transfer an auxiliary flag, which we can evaluate on the client side.
                        */
                        $stype = 0;
                        if($o->value === 0){
                            //Actual idle span
                        }
                        else{
                            if($o->limit_usage === 0){
                                //Simple idle / alert
                                $stype = 2;
                            }
                            else{
                                //multi-level idel / warn / alert
                                $stype = $o->value === 1 ? 1 : 2;
                            }
                        }

                        //The group
                        $g = $ala[$fld];

                        //The new entry
                        $x = (object)[
                            //The raw state from the DB
                            "v" => $o->value,
                            //The pre-interpreted alarm type for client-side display.
                            "a" => $stype,

                            //Start and end date of the event span.
                            //NOTE: here is taken for control of the possibly on since | till corrected values.
                            //The first entry can be idle from the requested since date.
                            //"sd" => $o->start_date,
                            //"ed" => $o->end_date,
                            "sd" => $sd,
                            "ed" => $ed,

                            //The duration of the warning/alert interval for control
                            "d" => $diff_str
                        ];


                        //Collect - only if the time span is not 0.
                        //This occurs if directly the first queried database entry had a value of > 0 (warn, alert).
                        if($diff_sec !== 0){
                            $g->items[] = $x;
                        }
                        //Collect End
                        //New entry in the corresponding buffer
                        $o->limit_usage = $act_limit_usage;
                        $o->value = $act_value;
                        $o->start_date = $act_insert_time;
                        $o->end_date = "";
                    }
                    else if($act_value < $last_value){
                        //Close running entry, collect
                        $o->end_date = $act_insert_time;
                        //Collect Start
                        $sd = trim($o->start_date);
                        $ed = trim($o->end_date);
                        if($sd === "")$sd = $since;
                        if($ed === "")$ed = $till;

                        $diff_sec = 0;
                        $diff_str = "";
                        if($sd !== "" && $ed !== ""){
                            $sdate = date_create($sd);
                            $edate = date_create($ed);
                            //Gives an Interval Object
                            $interval = date_diff($sdate, $edate);
                            //difference of the underlying timestamps
                            $sts = $sdate->getTimestamp();
                            $ets = $edate->getTimestamp();
                            //Formatted as string hours:minutes:seconds without "0" padLeft
                            $diff_str = $interval->format("%a:%h:%i:%s");

                            //Entries with 0 seconds are skipped. see below.
                            $diff_sec = $ets - $sts;
                        }

                        //The entries are already grouped at the top according to the underlying field names.
                        //So it is possible to list e.g. all detected co2 warnings separately from the
                        //the motion warnings.
                        //There is only a key created in the assoc array ala when it is actually used for the first time.
                        if(!isset($ala[$fld])){
                            //add control entry to collection array
                            $ala[$fld] = (object)[
                                //original field name
                                "fld" => $fld,
                                //Visible label for client output
                                "lbl" => $o->lbl,
                                //Auxiliary flag for group switching in the client.
                                "sel" => 0,
                                //original limit usage rule
                                "limit_usage" => $o->limit_usage,
                                //total number of all time spans found > 0
                                "icount" => 0,
                                //Array with the found time spans
                                "items" => []
                            ];
                        }

                        /*What alarm type this entry is depends on the corresponding limit_usage.

                        If limit usage = 0 then
                        0 idle
                        2 alarm

                        If limit usage = 1 or 2 then
                        0 idle
                        1 warn
                        2 alarm

                        We immediately transfer an auxiliary flag, which we can evaluate on the client side.
                        */
                        $stype = 0;
                        if($o->value === 0){
                            //Actual idle span
                        }
                        else{
                            if($o->limit_usage === 0){
                                //Simple idle / alert
                                $stype = 2;
                            }
                            else{
                                //multi-level idel / warn / alert
                                $stype = $o->value === 1 ? 1 : 2;
                            }
                        }

                        //The group
                        $g = $ala[$fld];

                        //The new entry
                        $x = (object)[
                            //The raw state from the DB
                            "v" => $o->value,
                            //The pre-interpreted alarm type for client-side display.
                            "a" => $stype,

                            //Start and end date of the event span.
                            //NOTE: here is taken for control of the possibly on since | till corrected values.
                            //The first entry can be idle from the requested since date.
                            //"sd" => $o->start_date,
                            //"ed" => $o->end_date,
                            "sd" => $sd,
                            "ed" => $ed,

                            //The duration of the warning/alert interval for control
                            "d" => $diff_str
                        ];


                        //Collect - only if the time span is not 0.
                        //This occurs if directly the first queried database entry had a value of > 0 (warn, alert).
                        if($diff_sec !== 0){
                            $g->items[] = $x;
                        }
                        //Collect End

                        //If it has not yet fallen back to 0, then the current
                        //Date must be noted as the start date for the subsequent entry.
                        $o->start_date = $act_insert_time;
                        $o->end_date = "";

                    }



                    //Note for next run
                    $o->value = $act_value;
                }

            }
            //end while db record

            //Close all control entries that may still be open.
            //If they were not reset to 0 by a database record from the previous loop,
            //they could still be set to 1 or 2.
            foreach($last_state as $fld => $o){
                if($o->value > 0){
                    //Since the end date is not known, we leave it blank and can address it in the frontend in the display.
                    $o->end_date = "";
                    //Collect Start
                    $sd = trim($o->start_date);
                    $ed = trim($o->end_date);
                    if($sd === "")$sd = $since;
                    if($ed === "")$ed = $till;

                    $diff_sec = 0;
                    $diff_str = "";
                    if($sd !== "" && $ed !== ""){
                        $sdate = date_create($sd);
                        $edate = date_create($ed);
                        //Gives an Interval Object
                        $interval = date_diff($sdate, $edate);
                        //difference of the underlying timestamps
                        $sts = $sdate->getTimestamp();
                        $ets = $edate->getTimestamp();
                        //Formatted as string hours:minutes:seconds without "0" padLeft
                        $diff_str = $interval->format("%a:%h:%i:%s");

                        //Entries with 0 seconds are skipped. see below.
                        $diff_sec = $ets - $sts;
                    }

                    //The entries are already grouped at the top according to the underlying field names.
                    //So it is possible to list e.g. all detected co2 warnings separately from the
                    //the motion warnings.
                    //There is only a key created in the assoc array ala when it is actually used for the first time.
                    if(!isset($ala[$fld])){
                        //add control entry to collection array
                        $ala[$fld] = (object)[
                            //original field name
                            "fld" => $fld,
                            //Visible label for client output
                            "lbl" => $o->lbl,
                            //Auxiliary flag for group switching in the client.
                            "sel" => 0,
                            //original limit usage rule
                            "limit_usage" => $o->limit_usage,
                            //total number of all time spans found > 0
                            "icount" => 0,
                            //Array with the found time spans
                            "items" => []
                        ];
                    }

                    /*What alarm type this entry is depends on the corresponding limit_usage.

                    If limit usage = 0 then
                    0 idle
                    2 alarm

                    If limit usage = 1 or 2 then
                    0 idle
                    1 warn
                    2 alarm

                    We immediately transfer an auxiliary flag, which we can evaluate on the client side.
                    */
                    $stype = 0;
                    if($o->value === 0){
                        //Actual idle span
                    }
                    else{
                        if($o->limit_usage === 0){
                            //Simple idle / alert
                            $stype = 2;
                        }
                        else{
                            //multi-level idel / warn / alert
                            $stype = $o->value === 1 ? 1 : 2;
                        }
                    }

                    //The group
                    $g = $ala[$fld];

                    //The new entry
                    $x = (object)[
                        //The raw state from the DB
                        "v" => $o->value,
                        //The pre-interpreted alarm type for client-side display.
                        "a" => $stype,

                        //Start and end date of the event span.
                        //NOTE: here is taken for control of the possibly on since | till corrected values.
                        //The first entry can be idle from the requested since date.
                        //"sd" => $o->start_date,
                        //"ed" => $o->end_date,
                        "sd" => $sd,
                        "ed" => $ed,

                        //The duration of the warning/alert interval for control
                        "d" => $diff_str
                    ];


                    //Collect - only if the time span is not 0.
                    //This occurs if directly the first queried database entry had a value of > 0 (warn, alert).
                    if($diff_sec !== 0){
                        $g->items[] = $x;
                    }
                    //Collect End
                }
            }
            //invert collected items per observed field so that the newest warnings and alarms are at the top.
            foreach($ala as $fld => $o){
                $o->items = array_reverse($o->items);
                //Anzhal as control note
                $o->icount = count($o->items);
            }

            //Sort the collection array upwards according to the keys.
            //This is for clarity, since the collected field names - if any - will always be sent to the client in the same order.
            //ksort() sorts the array passed byref in place.
            ksort($ala);

        }


        //NOTE: it may be that no warnings / alarms were found for the requested time period.
        //In this case ala[] is still an empty assoc array.
        //To make things clear on the client side, data=null is returned here instead of an empty array.
        //If items have been collected, casting of the assoc array to a JSON object takes place.
        if(count($ala) === 0){
            $msg->data = null;
        }
        else{
            $msg->data = (object)$ala;
        }
        $msg->duration = get_duration($timing_start);
        //JSON return
        return $msg;
    }
}
if(!function_exists('events_contact')){
    function events_contact($sn, $since, $till, $data){
        //Start timing total
        $timing_start = get_microtime_string();

        $last_state = [
            "analog_state_digital" => (object)[
                "lbl" => "Status",
                "value" => 0,
                "start_date" => "",
                "end_date" => "",
                "limit_fieldname" => "limit_usage_digital",
                "limit_usage" => 0,
            ],
        ];

        $msg = (object)[
            "duration" => 0,
            "sn" => $sn,
            "since" => $since,
            "till" => $till,
            "rtotal" => 0,
            "data" => null
        ];
        $ala = [];
        if($since !== "" && $till !== ""){
            foreach($data as $r){
                $msg->rtotal++;

                $act_insert_time = $r->insert_time;

                foreach($last_state as $fld => $o){
                    $act_value = $r->$fld;
                    $lifld = $o->limit_fieldname;
                    $act_limit_usage = $r->$lifld;

                    $last_value = $o->value;

                    if($act_value > $last_value){
                        $o->end_date = $act_insert_time;
                        //Sammeln
                        $sd = trim($o->start_date);
                        $ed = trim($o->end_date);
                        if($sd === "")$sd = $since;
                        if($ed === "")$ed = $till;

                        $diff_sec = 0;
                        $diff_str = "";
                        if($sd !== "" && $ed !== ""){
                            $sdate = date_create($sd);
                            $edate = date_create($ed);
                            $interval = date_diff($sdate, $edate);
                            $sts = $sdate->getTimestamp();
                            $ets = $edate->getTimestamp();
                            $diff_str = $interval->format("%a:%h:%i:%s");
                            $diff_sec = $ets - $sts;
                        }
                        if(! isset($ala[$fld])){
                            $ala[$fld] = (object)[
                                "fld" => $fld,
                                "lbl" => $o->lbl,
                                "sel" => 0,
                                "limit_usage" => $o->limit_usage,
                                "icount" => 0,
                                "items" => []
                            ];
                        }
                        $stype = 0;
                        if($o->value === 0){
                        }
                        else{
                            if($o->limit_usage === 0){
                                $stype = 2;
                            }
                            else{
                                $stype = $o->value === 1 ? 1 : 2;
                            }
                        }
                        $g = $ala[$fld];
                        $x = (object)[
                            "v" => $o->value,
                            "a" => $stype,
                            "sd" => $sd,
                            "ed" => $ed,
                            "d" => $diff_str
                        ];
                        if($diff_sec !== 0){
                            $g->items[] = $x;
                        }
                        $o->limit_usage = $act_limit_usage;
                        $o->value = $act_value;
                        $o->start_date = $act_insert_time;
                        $o->end_date = "";
                    }
                    else if($act_value < $last_value){
                        $o->end_date = $act_insert_time;
                        $sd = trim($o->start_date);
                        $ed = trim($o->end_date);
                        if($sd === "")$sd = $since;
                        if($ed === "")$ed = $till;

                        $diff_sec = 0;
                        $diff_str = "";
                        if($sd !== "" && $ed !== ""){
                            $sdate = date_create($sd);
                            $edate = date_create($ed);
                            $interval = date_diff($sdate, $edate);
                            $sts = $sdate->getTimestamp();
                            $ets = $edate->getTimestamp();
                            $diff_str = $interval->format("%a:%h:%i:%s");
                            $diff_sec = $ets - $sts;
                        }
                        if(! isset($ala[$fld])){
                            $ala[$fld] = (object)[
                                "fld" => $fld,
                                "lbl" => $o->lbl,
                                "sel" => 0,
                                "limit_usage" => $o->limit_usage,
                                "icount" => 0,
                                "items" => []
                            ];
                        }
                        $stype = 0;
                        if($o->value === 0){
                        }
                        else{
                            if($o->limit_usage === 0){
                                $stype = 2;
                            }
                            else{
                                $stype = $o->value === 1 ? 1 : 2;
                            }
                        }
                        $g = $ala[$fld];
                        $x = (object)[
                            "v" => $o->value,
                            "a" => $stype,
                            "sd" => $sd,
                            "ed" => $ed,
                            "d" => $diff_str
                        ];
                        if($diff_sec !== 0){
                            $g->items[] = $x;
                        }
                        $o->start_date = $act_insert_time;
                        $o->end_date = "";
                    }
                    $o->value = $act_value;
                }
            }

            foreach($last_state as $fld => $o){
                if($o->value > 0){
                    $o->end_date = "";
                    $sd = trim($o->start_date);
                    $ed = trim($o->end_date);
                    if($sd === "")$sd = $since;
                    if($ed === "")$ed = $till;

                    $diff_sec = 0;
                    $diff_str = "";
                    if($sd !== "" && $ed !== ""){
                        $sdate = date_create($sd);
                        $edate = date_create($ed);
                        $interval = date_diff($sdate, $edate);
                        $sts = $sdate->getTimestamp();
                        $ets = $edate->getTimestamp();
                        $diff_str = $interval->format("%a:%h:%i:%s");
                        $diff_sec = $ets - $sts;
                    }
                    if(! isset($ala[$fld])){
                        $ala[$fld] = (object)[
                            "fld" => $fld,
                            "lbl" => $o->lbl,
                            "sel" => 0,
                            "limit_usage" => $o->limit_usage,
                            "icount" => 0,
                            "items" => []
                        ];
                    }
                    $stype = 0;
                    if($o->value === 0){
                    }
                    else{
                        if($o->limit_usage === 0){
                            $stype = 2;
                        }
                        else{
                            $stype = $o->value === 1 ? 1 : 2;
                        }
                    }
                    $g = $ala[$fld];
                    $x = (object)[
                        "v" => $o->value,
                        "a" => $stype,
                        "sd" => $sd,
                        "ed" => $ed,
                        "d" => $diff_str
                    ];
                    if($diff_sec !== 0){
                        $g->items[] = $x;
                    }
                }
            }
            foreach($ala as $fld => $o){
                $o->items = array_reverse($o->items);
                $o->icount = count($o->items);
            }
            ksort($ala);
        }
        if(count($ala) === 0){
            $msg->data = null;
        }
        else{
            $msg->data = (object)$ala;
        }
        $msg->duration = get_duration($timing_start);
        return $msg;
    }
}
if(!function_exists('events_door')){
    function events_door($sn, $since, $till, $data){
        $timing_start = get_microtime_string();
        $last_state = [
            "analog_state_status" => (object)[
                "lbl" => "Status",
                "limit_fieldname" => "limit_usage_status",
                "limit_usage" => 0,
                "value" => 0,
                "start_date" => "",
                "end_date" => ""
            ],
        ];
        $msg = (object)[
            "duration" => 0,
            "sn" => $sn,
            "since" => $since,
            "till" => $till,
            "rtotal" => 0,
            "data" => null
        ];
        $ala = [];
        if($since !== "" && $till !== ""){
            foreach($data as $r){
                $msg->rtotal++;

                $act_insert_time = $r->insert_time;

                foreach($last_state as $fld => $o){
                    $act_value = $r->$fld;
                    $lifld = $o->limit_fieldname;
                    $act_limit_usage = $r->$lifld;


                    $last_value = $o->value;

                    if($act_value > $last_value){
                        $o->end_date = $act_insert_time;
                        $sd = trim($o->start_date);
                        $ed = trim($o->end_date);
                        if($sd === "")$sd = $since;
                        if($ed === "")$ed = $till;

                        $diff_sec = 0;
                        $diff_str = "";
                        if($sd !== "" && $ed !== ""){
                            $sdate = date_create($sd);
                            $edate = date_create($ed);
                            $interval = date_diff($sdate, $edate);
                            $sts = $sdate->getTimestamp();
                            $ets = $edate->getTimestamp();
                            $diff_str = $interval->format("%a:%h:%i:%s");

                            $diff_sec = $ets - $sts;
                        }
                        if(! isset($ala[$fld])){
                            $ala[$fld] = (object)[
                                "fld" => $fld,
                                "lbl" => $o->lbl,
                                "sel" => 0,
                                "limit_usage" => $o->limit_usage,
                                "icount" => 0,
                                "items" => []
                            ];
                        }
                        $stype = 0;
                        if($o->value === 0){
                        }
                        else{
                            if($o->limit_usage === 0){
                                $stype = 2;
                            }
                            else{
                                $stype = $o->value === 1 ? 1 : 2;
                            }
                        }
                        $g = $ala[$fld];
                        $x = (object)[
                            "v" => $o->value,
                            "a" => $stype,
                            "sd" => $sd,
                            "ed" => $ed,
                            "d" => $diff_str
                        ];
                        if($diff_sec !== 0){
                            $g->items[] = $x;
                        }

                        $o->limit_usage = $act_limit_usage;
                        $o->value = $act_value;
                        $o->start_date = $act_insert_time;
                        $o->end_date = "";
                    }
                    else if($act_value < $last_value){
                        $o->end_date = $act_insert_time;
                        $sd = trim($o->start_date);
                        $ed = trim($o->end_date);
                        if($sd === "")$sd = $since;
                        if($ed === "")$ed = $till;

                        $diff_sec = 0;
                        $diff_str = "";
                        if($sd !== "" && $ed !== ""){
                            $sdate = date_create($sd);
                            $edate = date_create($ed);
                            $interval = date_diff($sdate, $edate);
                            $sts = $sdate->getTimestamp();
                            $ets = $edate->getTimestamp();
                            $diff_str = $interval->format("%a:%h:%i:%s");

                            $diff_sec = $ets - $sts;
                        }
                        if(! isset($ala[$fld])){
                            $ala[$fld] = (object)[
                                "fld" => $fld,
                                "lbl" => $o->lbl,
                                "sel" => 0,
                                "limit_usage" => $o->limit_usage,
                                "icount" => 0,
                                "items" => []
                            ];
                        }
                        $stype = 0;
                        if($o->value === 0){
                        }
                        else{
                            if($o->limit_usage === 0){
                                $stype = 2;
                            }
                            else{
                                $stype = $o->value === 1 ? 1 : 2;
                            }
                        }
                        $g = $ala[$fld];
                        $x = (object)[
                            "v" => $o->value,
                            "a" => $stype,
                            "sd" => $sd,
                            "ed" => $ed,
                            "d" => $diff_str
                        ];
                        if($diff_sec !== 0){
                            $g->items[] = $x;
                        }

                        $o->start_date = $act_insert_time;
                        $o->end_date = "";

                    }
                    $o->value = $act_value;
                }

            }
            foreach($last_state as $fld => $o){
                if($o->value > 0){
                    $o->end_date = "";
                    $sd = trim($o->start_date);
                    $ed = trim($o->end_date);
                    if($sd === "")$sd = $since;
                    if($ed === "")$ed = $till;

                    $diff_sec = 0;
                    $diff_str = "";
                    if($sd !== "" && $ed !== ""){
                        $sdate = date_create($sd);
                        $edate = date_create($ed);
                        $interval = date_diff($sdate, $edate);
                        $sts = $sdate->getTimestamp();
                        $ets = $edate->getTimestamp();
                        $diff_str = $interval->format("%a:%h:%i:%s");

                        $diff_sec = $ets - $sts;
                    }
                    if(! isset($ala[$fld])){
                        $ala[$fld] = (object)[
                            "fld" => $fld,
                            "lbl" => $o->lbl,
                            "sel" => 0,
                            "limit_usage" => $o->limit_usage,
                            "icount" => 0,
                            "items" => []
                        ];
                    }
                    $stype = 0;
                    if($o->value === 0){
                    }
                    else{
                        if($o->limit_usage === 0){
                            $stype = 2;
                        }
                        else{
                            $stype = $o->value === 1 ? 1 : 2;
                        }
                    }
                    $g = $ala[$fld];
                    $x = (object)[
                        "v" => $o->value,
                        "a" => $stype,
                        "sd" => $sd,
                        "ed" => $ed,
                        "d" => $diff_str
                    ];
                    if($diff_sec !== 0){
                        $g->items[] = $x;
                    }
                }
            }

            foreach($ala as $fld => $o){
                $o->items = array_reverse($o->items);
                $o->icount = count($o->items);
            }
            ksort($ala);
        }
        if(count($ala) === 0){
            $msg->data = null;
        }
        else{
            $msg->data = (object)$ala;
        }
        $msg->duration = get_duration($timing_start);

        return $msg;
    }
}
if(!function_exists('events_voc')){
    function events_voc($sn, $since, $till, $data){
        $timing_start = get_microtime_string();
        $last_state = [
            "analog_state_voc" => (object)[
                "lbl" => "VOC",
                "limit_fieldname" => "limit_usage_voc",
                "limit_usage" => 0,
                "value" => 0,
                "start_date" => "",
                "end_date" => ""
            ],

            "analog_state_motion" => (object)[
                "lbl" => "Motion",
                "limit_fieldname" => "limit_usage_motion",
                "limit_usage" => 0,
                "value" => 0,
                "start_date" => "",
                "end_date" => ""
            ],

            "analog_state_humidity" => (object)[
                "lbl" => "Humidity",
                "limit_fieldname" => "limit_usage_humidity",
                "limit_usage" => 0,
                "value" => 0,
                "start_date" => "",
                "end_date" => ""
            ],

            "analog_state_temperature" => (object)[
                "lbl" => "Temperature",
                "limit_fieldname" => "limit_usage_temperature",
                "limit_usage" => 0,
                "value" => 0,
                "start_date" => "",
                "end_date" => ""
            ],
            "analog_state_battery" => (object)[
                "lbl" => "Battery",
                "limit_fieldname" => "limit_usage_battery",
                "limit_usage" => 0,
                "value" => 0,
                "start_date" => "",
                "end_date" => ""
            ]
        ];
        $msg = (object)[
            "duration" => 0,
            "sn" => $sn,
            "since" => $since,
            "till" => $till,
            "rtotal" => 0,
            "data" => null
        ];
        $ala = [];
        function collect_record($fld="", $o=null){

        }
        if($since !== "" && $till !== ""){
            foreach($data as $r){
                $msg->rtotal++;

                $act_insert_time = $r->insert_time;

                foreach($last_state as $fld => $o){
                    $act_value = $r->$fld;
                    $lifld = $o->limit_fieldname;
                    $act_limit_usage = $r->$lifld;


                    $last_value = $o->value;

                    if($act_value > $last_value){
                        $o->end_date = $act_insert_time;
                        $sd = trim($o->start_date);
                        $ed = trim($o->end_date);
                        if($sd === "")$sd = $since;
                        if($ed === "")$ed = $till;

                        $diff_sec = 0;
                        $diff_str = "";
                        if($sd !== "" && $ed !== ""){
                            $sdate = date_create($sd);
                            $edate = date_create($ed);
                            $interval = date_diff($sdate, $edate);
                            $sts = $sdate->getTimestamp();
                            $ets = $edate->getTimestamp();
                            $diff_str = $interval->format("%a:%h:%i:%s");
                            $diff_sec = $ets - $sts;
                        }
                        if(! isset($ala[$fld])){
                            $ala[$fld] = (object)[
                                "fld" => $fld,
                                "lbl" => $o->lbl,
                                "sel" => 0,
                                "limit_usage" => $o->limit_usage,
                                "icount" => 0,
                                "items" => []
                            ];
                        }
                        $stype = 0;
                        if($o->value === 0){
                        }
                        else{
                            if($o->limit_usage === 0){
                                $stype = 2;
                            }
                            else{
                                $stype = $o->value === 1 ? 1 : 2;
                            }
                        }
                        $g = $ala[$fld];
                        $x = (object)[
                            "v" => $o->value,
                            "a" => $stype,
                            "sd" => $sd,
                            "ed" => $ed,
                            "d" => $diff_str
                        ];
                        if($diff_sec !== 0){
                            $g->items[] = $x;
                        }

                        $o->limit_usage = $act_limit_usage;
                        $o->value = $act_value;
                        $o->start_date = $act_insert_time;
                        $o->end_date = "";
                    }
                    else if($act_value < $last_value){
                        $o->end_date = $act_insert_time;
                        $sd = trim($o->start_date);
                        $ed = trim($o->end_date);
                        if($sd === "")$sd = $since;
                        if($ed === "")$ed = $till;

                        $diff_sec = 0;
                        $diff_str = "";
                        if($sd !== "" && $ed !== ""){
                            $sdate = date_create($sd);
                            $edate = date_create($ed);
                            $interval = date_diff($sdate, $edate);
                            $sts = $sdate->getTimestamp();
                            $ets = $edate->getTimestamp();
                            $diff_str = $interval->format("%a:%h:%i:%s");
                            $diff_sec = $ets - $sts;
                        }
                        if(! isset($ala[$fld])){
                            $ala[$fld] = (object)[
                                "fld" => $fld,
                                "lbl" => $o->lbl,
                                "sel" => 0,
                                "limit_usage" => $o->limit_usage,
                                "icount" => 0,
                                "items" => []
                            ];
                        }
                        $stype = 0;
                        if($o->value === 0){
                        }
                        else{
                            if($o->limit_usage === 0){
                                $stype = 2;
                            }
                            else{
                                $stype = $o->value === 1 ? 1 : 2;
                            }
                        }
                        $g = $ala[$fld];
                        $x = (object)[
                            "v" => $o->value,
                            "a" => $stype,
                            "sd" => $sd,
                            "ed" => $ed,
                            "d" => $diff_str
                        ];
                        if($diff_sec !== 0){
                            $g->items[] = $x;
                        }
                        $o->start_date = $act_insert_time;
                        $o->end_date = "";

                    }
                    $o->value = $act_value;
                }
            }
            foreach($last_state as $fld => $o){
                if($o->value > 0){
                    $o->end_date = "";
                    $sd = trim($o->start_date);
                    $ed = trim($o->end_date);
                    if($sd === "")$sd = $since;
                    if($ed === "")$ed = $till;

                    $diff_sec = 0;
                    $diff_str = "";
                    if($sd !== "" && $ed !== ""){
                        $sdate = date_create($sd);
                        $edate = date_create($ed);
                        $interval = date_diff($sdate, $edate);
                        $sts = $sdate->getTimestamp();
                        $ets = $edate->getTimestamp();
                        $diff_str = $interval->format("%a:%h:%i:%s");
                        $diff_sec = $ets - $sts;
                    }
                    if(! isset($ala[$fld])){
                        $ala[$fld] = (object)[
                            "fld" => $fld,
                            "lbl" => $o->lbl,
                            "sel" => 0,
                            "limit_usage" => $o->limit_usage,
                            "icount" => 0,
                            "items" => []
                        ];
                    }
                    $stype = 0;
                    if($o->value === 0){
                    }
                    else{
                        if($o->limit_usage === 0){
                            $stype = 2;
                        }
                        else{
                            $stype = $o->value === 1 ? 1 : 2;
                        }
                    }
                    $g = $ala[$fld];
                    $x = (object)[
                        "v" => $o->value,
                        "a" => $stype,
                        "sd" => $sd,
                        "ed" => $ed,
                        "d" => $diff_str
                    ];
                    if($diff_sec !== 0){
                        $g->items[] = $x;
                    }
                }
            }
            foreach($ala as $fld => $o){
                $o->items = array_reverse($o->items);
                $o->icount = count($o->items);
            }
            ksort($ala);

        }
        if(count($ala) === 0){
            $msg->data = null;
        }
        else{
            $msg->data = (object)$ala;
        }
        $msg->duration = get_duration($timing_start);
        return $msg;
    }
}
if(!function_exists('sc541_data_check')){
    function sc541_data_check ($value, $zone, $oldRecords) {
        $alert_state = 0;
        // Fetch previous data
        if ( $oldRecords && count($oldRecords) > 1 ) {
            $prev0_value = $oldRecords[0]->$zone;
            $prev_digits = strlen($prev0_value);
            $value_digits = strlen($value);

            if ( $prev_digits !== $value_digits ) {
                // Test1: check number of digits: if NOK ==>> notification to send or if OK: go to test2
                $alert_state = "alert";
            } else if ( $value < $prev0_value ) {
                // Test2:  check whether the new measurement value is under previous measurement
                // is timeX+1 value < timeX value ==>> YES = notification to send or NO = go to test3
                $alert_state = "alert";
            } else {
                // Test3:  is new measurement not too big/not realistic?
                //  acceptable variiation	min	max
                //          meter	delta	50%	   delta-50%	delta+50%
                // timeX	58754
                // timeX+1	58763	9	4.5	4.5	13.5
                // timeX+2	58773	10	5	5	15	    (10)>=4,5 and (10)=<13,5 so no notification
                // timeX+3	58780	7	3.5	3.5	10.5	(7)>=5 and 7<=15 so no notification
                // timeX+4	58793	13	6.5	6.5	19.5	13>=3,5 and 13=<10,9 ==>> notification sent to check value (reason could be significant increase in consumption)
                // timeX+5	58903	110	55	55	165	    110>=6,5 and 110<=19,5 ==>> notification to check value (reason is erroneous reading by AI platform)
                // timeX+6	58813	-90	-45	-45	-135	TEST1 will send already a notification or '-90 >=55 and -90 <=165 ==>> notification to check value (if previous value was not corrected than again error flag)
                // timeX+7	58823	10	5	5	15	    10>=-45 and 10<=-135 ==>> notification to check value (if previous value was not corrected than again error flag)
                // timeX+8	58834	11	5.5	5.5	16.5	11>=5 and 11<=15 ==>> no notification
                // timeX+9	58843	9	4.5	4.5	13.5	9>=5,5 and 9<=16,5 ==>> no notification
                $delta0 = $value - $prev0_value;
                $delta0_fiftyPercent = $delta0 * 0.5;
                $delta0_min = $delta0 - $delta0_fiftyPercent;
                $delta0_max = $delta0 + $delta0_fiftyPercent;
                $cumulative = $delta0_max - $delta0_min;

                if ( count($oldRecords) > 2 ) {
                    $prev1_value = $oldRecords[1]->$zone;
                    $delta1 = $prev0_value - $prev1_value;
                    $delta1_fiftyPercent = $delta1 * 0.5;
                    $delta1_min = $delta1 - $delta1_fiftyPercent;
                    $delta1_max = $delta1 + $delta1_fiftyPercent;

                    if ( $cumulative > $delta1_max || $cumulative < $delta1_min ) {
                        $alert_state = "alert";
                    }
                }
            }
        }
        return $alert_state;
    }
}
