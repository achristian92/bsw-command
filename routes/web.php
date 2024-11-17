<?php

use App\Http\Controllers\ApiCommandController;
use App\Http\Controllers\TestController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Spatie\WebhookClient\WebhookProcessor;
use Warrior\Ticketer\Ticketer;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/token', function () {
    $centro = 'mz2eouwiZqShRGjSbxIYtl0zurpCmxet4PKZ8SCa3fSgYWXU';
    $tavera = 'w2cVUAI35H6dbBduOcldRAWzKQZEblgp0CeeEKT1vf2mbI6a';


//    APP_URL=https://bsw-command.test
//    COMPANY_TOKEN=w2cVUAI35H6dbBduOcldRAWzKQZEblgp0CeeEKT1vf2mbI6a
//    API_URL_PROD=https://brainsware.pe/
//    API_URL_DEV=https://brainsware.test/

});

Route::get('api/command', ApiCommandController::class)->withoutMiddleware('auth:web');

Route::webhooks('webhook-receiving-url');

