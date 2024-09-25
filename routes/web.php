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
    $api_url = App::environment('production') ?  env('API_URL_PROD') :  env('API_URL_DEV');

    $api_url = App::environment('production') ?  env('API_URL_PROD') :  env('API_URL_DEV');

    $response = Http::withHeaders([
        'accept' => 'application/json'
    ])->get($api_url.'api/v1/command',[
        'token' => env('COMPANY_TOKEN')
    ]);

    if (!$response->successful())
        return response()->json(['error' => $response->json()['errors'][0]['detail'][0]], $response->status());

    $resp = $response->json()['data'];

    foreach ($resp as $rep) {
        if($rep['model_type'] === 'invoice') {
            $data = json_decode($rep['data']);

            try {
                $connector = new NetworkPrintConnector($data->printer->pr_ip);
                $printer = new Printer($connector);
                $printer -> selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
                $printer -> setJustification(Printer::JUSTIFY_CENTER);
                $printer -> setTextSize(1,1);
                $printer -> setJustification(Printer::JUSTIFY_CENTER);
                $printer -> text($data->company_name."\n");
                $printer -> text($data->invoice_name.":".$data->serie_num."\n");
                $printer -> setJustification(Printer::JUSTIFY_LEFT);
                $printer -> text("DNI:".$data->cli_nro_document."\n");
                $printer -> text("CLIENTE:".$data->cli_name);
                $printer -> text("FECHA:".$data->issue_date."\n");
//            $printer -> text("MESA:".$data->table."\n");
                $printer -> feed();
                $printer -> text("DETALLE  PRECIO  TOTAL             \n");
                $printer -> text("-----------------------------------\n");
                foreach ($data->items as $item) {
                    $printer -> text($item->name."   ".$item->quantity."   ".$item->total."\n");
                    $printer -> text("-----------------------------------\n");
                }
                $printer -> selectPrintMode();
                $printer -> feed();
                $printer -> setJustification(Printer::JUSTIFY_RIGHT);
                $printer -> text("DESCUENTO:".$data->discount_amount);
                $printer -> text("SUBTOTAL:".$data->subtotal);
                $printer -> text("IGV:".$data->igv);
                $printer -> text("IMPORTE TOTAL:".$data->total_incl);
                $printer -> setJustification(Printer::JUSTIFY_CENTER);
                $printer -> text("Representación impresa del comprobante electrónico. Para consultar ingrese a www.sunat.gob.pe");
                $printer -> feed();
                $printer -> cut();
                $printer ->close();

                $notify = Http::withHeaders([
                    'accept' => 'application/json'
                ])->put($api_url."api/v1/command/".$rep['uuid'],[
                    'token' => env('COMPANY_TOKEN')
                ]);

                if (!$notify->successful())
                    Log::error("Error para actualizar envio de comanda");

            } catch (Exception $e) {
                return ['success'=>0,'message'=>$e->getMessage()];
            }
        }



    }


});
