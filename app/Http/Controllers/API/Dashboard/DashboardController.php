<?php

namespace App\Http\Controllers\API\Dashboard;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Device;
use App\Models\FloorMap;
use App\Models\Project;
use App\Models\UiText;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Imagick;

class DashboardController extends BaseController
{
    public function __construct()
    {
        $this->middleware('api.auth');
    }
    protected function uploadFloorMapValidator(array $data)
    {
        return Validator::make($data, [
            'map_file'      => 'required|mimes:png,jpg,jpeg,pdf',
        ]);
    }
    protected function setSensorLocationValidator(array $data)
    {
        return Validator::make($data, [
            'room_name'     => 'required',
            'location_x'    => 'required',
            'location_y'    => 'required',
            'level'         => 'required',
        ]);
    }
    public function index($project_id){
        if(!check_dashboard_read_permission($project_id)){
            return response()->json([
                'status' => 'error',
                'message' => "you don't have permission to access.",
                'data' => []
            ], Response::HTTP_FORBIDDEN);
        }
        $project = Project::find($project_id);
        $deviceTypeIds = $project->devices()->where('active',1)->pluck('device_type_id')->toArray();
        if($project){
            $success['lang'] = UiText::where(function ($query) use ($deviceTypeIds){
                $query->whereNull('device_type_id')->orWhereIn('device_type_id',$deviceTypeIds);
            })->orderBy('device_type_id')->get();
            $success['floor_map'] = FloorMap::where("project_id",$project_id)->orderBy('level', 'asc')->get();
            $success['devices'] = Device::where("project_id",$project_id)->where('active',1)->orderBy('device_name', 'asc')->get();

            return $this->sendResponse($success, 'Dashboard Content');
        }
        return $this->sendError('Not Found', ['error'=>'not found']);
    }
    public function uploadFloorMap($project_id,$level, Request $request){
        $floorMap = FloorMap::where('project_id',$project_id)->where('level',$level)->first();
        if(!check_dashboard_write_permission($project_id) || !$floorMap){
            return response()->json([
                'status' => 'error',
                'message' => "you don't have permission to access.",
                'data' => []
            ], Response::HTTP_FORBIDDEN);
        }
        $validator = $this->uploadFloorMapvalidator($request->all());
        if ($validator->fails()) {
            return $this->sendError('Error', ['error'=>$validator->messages()->toArray()],422);
        }



        $file_map = $request->file('map_file');
        $extension = $file_map->getClientOriginalExtension();
        if($extension == 'pdf'){
            $location = $file_map->store('', 'map-images');
            $location = public_path('/images/plans/'.$location);
            $im = new Imagick();
            //dd($location);
            $im->setResolution(200, 200);
            $im->readImage($location);
            $im->setImageBackgroundColor('white');
            $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $im->setImageFormat('jpeg');
            $uploaded_path = pathinfo($location, PATHINFO_FILENAME).'.jpg';
            $newLocation = public_path('/images/plans/'.$uploaded_path);
            $im->writeImages($newLocation, true);
            //file_put_contents($new_location, $im);
            $im->clear();
            $im->destroy();
        }else{
            $uploaded_path = $file_map->store('', 'map-images');
        }
        if($floorMap->file_url){
            $fileName = pathinfo($floorMap->file_url, PATHINFO_FILENAME).'.pdf';
            if(Storage::disk('map-images')->exists($fileName)){
                Storage::disk('map-images')->delete($fileName);
            }
            //dd($floorMap->file_url);
            Storage::disk('map-images')->delete($floorMap->file_url);
        }
        $floorMap->file_url = $uploaded_path;
        $floorMap->save();
        $success['floor_maps'] = FloorMap::select('level','level_name','project_id','file_url')->where("project_id",$project_id)->get();
        return $this->sendResponse($success, 'Successfuly uploaded');
    }
    public function setSensorLocation($sensor_number, Request $request){
        $deviceInfo = Device::where("serial_number",$sensor_number)->first();

        if(!$deviceInfo){
            return response()->json([
                'status' => 'error',
                'message' => "you don't have permission to access.",
                'data' => []
            ], Response::HTTP_FORBIDDEN);
        }
        if($deviceInfo && !check_dashboard_write_permission($deviceInfo->project_id)){
            return response()->json([
                'status' => 'error',
                'message' => "you don't have permission to access.",
                'data' => []
            ], Response::HTTP_FORBIDDEN);
        }
        $validator = $this->setSensorLocationValidator($request->all());
        if ($validator->fails()) {
            return $this->sendError('Error', ['error'=>$validator->messages()->toArray()],422);
        }

        $requiredLevel = $deviceInfo->project->no_of_level ?? 0;
        if($request->level > $requiredLevel){
            return response()->json([
                'status' => 'error',
                'message' => "you don't have permission to access.",
                'data' => []
            ], Response::HTTP_FORBIDDEN);
        }
        $dType = $deviceInfo->deviceType->device_type;

        $dTypeObj = $dType::where("serial",$deviceInfo->serial_number)->first();

        if(!$dTypeObj){
            return response()->json([
                'status' => 'error',
                'message' => "This Type Device information is missing.",
                'data' => []
            ], Response::HTTP_FORBIDDEN);
        }
        $dTypeObj->room_name = $request->room_name;
        $dTypeObj->locationX = $request->location_x;
        $dTypeObj->locationY = $request->location_y;
        $dTypeObj->level = $request->level;
        $dTypeObj->save();
        $deviceInfo = Device::where("serial_number",$sensor_number)->first();
        $success['device'] = $deviceInfo;
        return $this->sendResponse($success, 'Successfuly Set Room Information');
    }
    public function resetSensorLocation($sensor_number){
        $deviceInfo = Device::where("serial_number",$sensor_number)->first();
        if(!$deviceInfo){
            return response()->json([
                'status' => 'error',
                'message' => "you don't have permission to access.",
                'data' => []
            ], Response::HTTP_FORBIDDEN);
        }
        if($deviceInfo && !check_dashboard_write_permission($deviceInfo->project_id)){
            return response()->json([
                'status' => 'error',
                'message' => "you don't have permission to access.",
                'data' => []
            ], Response::HTTP_FORBIDDEN);
        }
        $dType = $deviceInfo->deviceType->device_type;

        $dTypeObj = $dType::where("serial",$deviceInfo->serial_number)->first();

        if(!$dTypeObj){
            return response()->json([
                'status' => 'error',
                'message' => "This Type Device information is missing.",
                'data' => []
            ], Response::HTTP_FORBIDDEN);
        }
        $dTypeObj->room_name = null;
        $dTypeObj->locationX = -1;
        $dTypeObj->locationY = -1;
        $dTypeObj->level = null;
        $dTypeObj->save();

        $success['device'] = $dTypeObj;
        return $this->sendResponse($success, 'Successfuly Reset Room Information');

    }
}
