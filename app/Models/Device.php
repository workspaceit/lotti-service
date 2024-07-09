<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Device extends Model
{
    use HasFactory;
    protected $appends = [
        'icon','icon_map','alerts','floor_map_name','level','location_x','location_y','room_name','sensor_details','lang','map_hover','odo_meter'
    ];
    protected $fillable = [
        'device_name',
        'serial_number',
        'device_type_id',
        'active',
        'project_id',
        'device_status',
        'region_code',
    ];
    public function project(){
        return $this->belongsTo(Project::class);
    }
    public function deviceType(){
        return $this->belongsTo(DeviceType::class);
    }
    public function getLevelAttribute(){
        $dType = $this->deviceType->device_type;
        if(!$dType){
            return null;
        }
        $dTypeObj = $dType::where("serial",$this->serial_number)->first();
        if(!$dTypeObj){
            return null;
        }
        return $dTypeObj->level;
    }
    public function getLocationXAttribute(){
        $dType = $this->deviceType->device_type;
        if(!$dType){
            return null;
        }
        $dTypeObj = $dType::where("serial",$this->serial_number)->first();
        if(!$dTypeObj){
            return null;
        }
        return $dTypeObj->locationX;
    }
    public function getLocationYAttribute(){
        $dType = $this->deviceType->device_type;
        if(!$dType){
            return null;
        }
        $dTypeObj = $dType::where("serial",$this->serial_number)->first();
        if(!$dTypeObj){
            return null;
        }
        return $dTypeObj->locationY;
    }
    public function getRoomNameAttribute(){
        $dType = $this->deviceType->device_type;
        if(!$dType){
            return null;
        }
        $dTypeObj = $dType::where("serial",$this->serial_number)->first();
        if(!$dTypeObj){
            return null;
        }
        return $dTypeObj->room_name;
    }
    public function getFloorMapNameAttribute(){
        $dType = $this->deviceType->device_type;
        if(!$dType){
            return null;
        }
        $dTypeObj = $dType::where("serial",$this->serial_number)->first();
        if(!$dTypeObj){
            return null;
        }
        $ff = FloorMap::select('level_name')->where("level",$dTypeObj->level)->where("project_id",$this->project_id)->first();
        if($ff){
            return $ff->level_name;
        }else{
            return null;
        }
    }
    public function getAlertsAttribute(){
        $message = UiText::where("device_type_id",$this->device_type_id)->get()->keyBy('text_key');
        $deviceType = $this->deviceType->device_type;
        if(!$deviceType){
            return null;
        }
        $deviceDetails = "";
        if(class_exists($deviceType)){
            $deviceDetails = $deviceType::where("serial",$this->serial_number)->first();
        }

        if($deviceDetails){
            return check_dashboard_alert($deviceDetails, $this, $message, (new $deviceType)->checkKey());
        }else{
            return array();
        }
    }
    public function getIconAttribute(){
        $deviceType = $this->deviceType->device_type;
        if(!$deviceType){
            return null;
        }
        $deviceDetails = "";
        if(class_exists($deviceType)){
            $deviceDetails = $deviceType::where("serial",$this->serial_number)->first();
        }
        if($deviceDetails){
            return $deviceDetails->icon;
        }else{
            return null;
        }
    }
    public function getLangAttribute(){
        $deviceTypeId = $this->device_type_id;
        if($deviceTypeId){
            return UiText::where('device_type_id',$deviceTypeId)->get();
        }else{
            return null;
        }
    }
    public function getIconMapAttribute(){
        $deviceType = $this->deviceType->device_type;
        if(!$deviceType){
            return null;
        }
        $deviceDetails = "";
        if(class_exists($deviceType)){
            $deviceDetails = $deviceType::where("serial",$this->serial_number)->first();
        }
        if($deviceDetails){
            return $deviceDetails->icon_map;
        }else{
            return null;
        }
    }
    public function getMapHoverAttribute(){
        $deviceType = $this->deviceType->device_type;
        if(!$deviceType){
            return null;
        }
        $deviceDetails = "";
        if(class_exists($deviceType)){
            $deviceDetails = $deviceType::where("serial",$this->serial_number)->first();
        }
        if($deviceDetails){
            if (method_exists($deviceDetails, 'mapHover')){
                $mapHoverArr = $deviceDetails->mapHover();
                $key = $mapHoverArr['key'];
                $min = ($mapHoverArr['lower_case']) ? strtolower($key).'_min' : $key.'_min';
                $max = ($mapHoverArr['lower_case']) ? strtolower($key).'_max' : $key.'_max';
                $data['min_value'] = $deviceDetails->$min;
                $data['max_value'] = $deviceDetails->$max;
                $data['value'] = $deviceDetails->$key;
                $data['type'] = $mapHoverArr['type'];
                $data['name'] = $this->device_name;

                return $data;
            }
        }
        $data['type'] = 0;
        $data['name'] = $this->device_name;
        return $data;
    }
    public function getSensorDetailsAttribute(){
        $deviceType = $this->deviceType->device_type;
        if(!$deviceType){
            return null;
        }
        $deviceDetails = "";
        if(class_exists($deviceType)){
            $deviceDetails = $deviceType::where("serial",$this->serial_number)->first();
        }
        if($deviceDetails){
            $sensorDetails = sensor_details();
            $deviceSensorDetails = $deviceDetails->sensor_details;
            if($deviceSensorDetails){
                foreach($sensorDetails as $key => $sd){
                    if(in_array($key, $deviceSensorDetails)){
                        $newSensorDetails[$key] = $sd;
                    }
                }

                return $newSensorDetails;
            }
        }
        return null;
    }
    public function getOdoMeterAttribute(){
        $deviceType = $this->deviceType->device_type;
        if(!$deviceType){
            return null;
        }
        $deviceDetails = "";
        if(class_exists($deviceType)){
            $deviceDetails = $deviceType::where("serial",$this->serial_number)->first();
            if (method_exists($deviceType, 'getOdoMeterState') && $deviceDetails) {
                $deviceDetails['information'] = (new $deviceType)->getOdoMeterState($this->serial_number);
                // $deviceDetails['data'] = $deviceDetails;
            }
        }
        if($deviceDetails){
            return $deviceDetails;
        }else{
            return null;
        }
    }
}
