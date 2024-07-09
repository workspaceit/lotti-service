<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Dashboard\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*** Auth ***/
Route::namespace('Api')->prefix('auth')->name('api.auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('api.auth');
});
/*** Auth END ***/
/*** Dashboard ***/
Route::namespace('Api')->prefix('dashboard')->name('api.dashboard.')->group(function () {
    Route::get('/{project_id}', [DashboardController::class, 'index'])->name('index');
    Route::post('/upload-floor-map/{project_id}/{level}', [DashboardController::class, 'uploadFloorMap'])->name('upload.floormap');
    Route::post('/set-sensor-location/{sensor_number}', [DashboardController::class, 'setSensorLocation'])->name('set.sensor.location');
    Route::get('/reset-sensor-location/{sensor_number}', [DashboardController::class, 'resetSensorLocation'])->name('reset.sensor.location');
});
/*** Dashboard END ***/
