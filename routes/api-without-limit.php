<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Sensor\SensorController;

/*** QR Page ***/
Route::namespace('Api')->prefix('qr')->name('api.qr.')->group(function () {
    Route::get('/information/{sn}', [SensorController::class, 'qrinfo'])->name('qrinfo');
});
/*** QR Page END ***/
