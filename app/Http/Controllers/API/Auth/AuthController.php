<?php

namespace App\Http\Controllers\API\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Device;
use App\Models\FloorMap;
use App\Models\UiText;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Validator;
use Laravel\Passport\Client as OClient;

class AuthController extends BaseController
{

    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        if(Auth::attempt(['username' => $request->username, 'password' => $request->password])){
            $user = Auth::user();

            $oClient = OClient::where('password_client', 1)->latest()->first();
            $success['token'] =  $this->getTokenAndRefreshToken($oClient, request('username'), request('password'));
            $success['full_name'] =   $user->full_name;
            $success['type'] =  $user->type;
            $success['project_id'] =  $user->project_id;
            $success['username'] =  $user->username;
            $success['profile_image'] =  $user->profile_image;
            $success['address'] =  $user->address;
            $success['lang'] =  $user->lang;


            return $this->sendResponse($success, 'User login successfully.');
        }
        else{
            return $this->sendError('Invalid credentials.', ['error'=>'Unauthorised'], 401);
        }
    }
    public function logout()
    {

        Auth::guard('api')->user()->token()->revoke();

        $success['logout'] = 'Logout';
        return $this->sendResponse($success, 'User logout successfully.');

        // TODO:: Also need to revoke refresh token. But I can't get refresh token. So, I am skipping that for now.
    }
    public function getTokenAndRefreshToken(OClient $oClient, $username, $password): JsonResponse
    {
        $http    = new Client(['verify' => false]);
        $oClient = OClient::where('password_client', 1)->latest()->first();
        try {
            $response = $http->request('POST', route('passport.token'), [
                'form_params' => [
                    'grant_type'    => 'password',
                    'client_id'     => $oClient->id,
                    'client_secret' => $oClient->secret,
                    'username'      => $username,
                    'password'      => $password,
                    'scope'         => '*',
                ]
            ]);
        } catch (\Throwable $throwable) {
            return response()->json(['status' => 'error', 'errors' => ['messages' => $throwable->getMessage()]], 200);
        }

        return response()->json(json_decode((string) $response->getBody(), true), 200);
    }
}
