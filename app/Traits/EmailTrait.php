<?php

namespace App\Traits;

use App\Mail\SendAlertEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

trait EmailTrait {
    public function send_email($obj, $key_to_check, $serial, $alert_state) {
        Log::info("send email started | Alert State:".$alert_state." | Serial:".$serial);
        Log::info("Email Obj | ".json_encode($obj));

        $email_key = $key_to_check . "_email";
        $message_key = $key_to_check . "_email_text_state_" . $alert_state;
        $prell_key = $key_to_check . "_prell_" . $alert_state;
    // if (str_starts_with($obj->$message_key, 'SC541: Check value of')) {
        if(!$obj->$email_key){
            Log::error("Email required");
            return false;
        }
        if(!$obj->$message_key){
            Log::error("Msg required");
            return false;
        }
        $address_text = $obj->$email_key;
        Log::info("emails | " . $address_text);
        if(strpos($address_text, ";") !== false){
            $address_text = str_replace(' ', '', $address_text);
            $email_addresses = explode(";", $address_text);
        }else{
            $email_addresses = [$address_text];
        }
        foreach ($email_addresses as $email_address){
            Mail::to($email_address)->send(new SendAlertEmail($obj->$message_key));
        }

        $prell_updated_data = ($obj->$prell_key) ? $obj->$prell_key + 1 : 1;
        $obj->update([$prell_key => $prell_updated_data]);
    // }
    }
}
