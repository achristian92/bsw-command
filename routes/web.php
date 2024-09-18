<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
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

Route::get('/test', function () {

//    $api_url = App::environment('production') ?  env('API_URL_PROD') :  env('API_URL_DEV');
//
//    $response = Http::withHeaders([
//        'accept' => 'application/json'
//    ])->get($api_url.'api/v1/command',[
//        'token' => env('COMPANY_TOKEN')
//    ]);
//
//    if (!$response->successful())
//        return response()->json(['error' => $response->json()['errors'][0]['detail'][0]], $response->status());
//
//    $resp = $response->json()['data'];
//
//    foreach ($resp as $rep) {
//        $data = json_decode($rep['data']);
//
//        foreach ($data->printers as  $zn) {
//            try {
//                $connector = new NetworkPrintConnector($zn->pr_ip);
//                $printer = new Printer($connector);
//                $printer -> selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
//                $printer -> setJustification(Printer::JUSTIFY_CENTER);
//                $printer -> setTextSize(1,1);
//                $printer -> setJustification(Printer::JUSTIFY_CENTER);
//                $printer -> text($data->company."\n");
//                $printer -> text("#:".$data->num."\n");
//                $printer -> setJustification(Printer::JUSTIFY_LEFT);
//                $printer -> text("AREA:".$zn->area."\n");
//                $printer -> text("HORA:".$data->date.' '.$data->time."\n");
//                $printer -> text("MOZO:".$data->waiter."\n");
//                $printer -> text("MESA:".$data->table."\n");
//                $printer -> feed();
//                $printer -> text("CANT  DETALLE                    \n");
//                $printer -> text("---------------------------------\n");
//                foreach ($zn->items as $item) {
//                    $printer -> text("  ".$item->qty."    ".$item->name."\n");
//                    if($item->notes)
//                        $printer -> text("  "."    "."(Nota:".$item->notes.')'."\n");
//
//                    $printer -> text("-----------------------------------\n");
//                }
//                $printer -> selectPrintMode();
//                $printer -> feed();
//                $printer -> feed();
//                $printer -> cut();
//                $printer ->close();
//
//                $notify = Http::withHeaders([
//                    'accept' => 'application/json'
//                ])->put($api_url."api/v1/command/".$rep['uuid'],[
//                    'token' => env('COMPANY_TOKEN')
//                ]);
//
//                if (!$notify->successful())
//                    Log::error("Error para actualizar envio de comanda");
//
//            } catch (Exception $e) {
//                return ['success'=>0,'message'=>$e->getMessage()];
//            }
//
//
//        }
//
//
//    }

});
