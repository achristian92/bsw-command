<?php

namespace App\Http\Controllers;

use App\Traits\CommandTraits;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class ApiCommandController extends Controller
{
    use CommandTraits;
    public function __invoke(Request $request)
    {
        $isSuccessful = false;

        $api_url = App::isProduction() ?  env('API_URL_PROD') :  env('API_URL_DEV');
        $token = env('COMPANY_TOKEN');

        if($request->input('uuid'))
            Log::info("*****Command from PC: " .$request->input('uuid'));

        $response = Http::withHeaders([
            'accept' => 'application/json'
        ])->get($api_url.'api/v1/command',[
            'token' => $token,
            'uuid' => $request->input('uuid')
        ]);

        if (!$response->successful()) {
            Log::error($response->json()['errors'][0]['detail'][0]);
            return 'bad';
        }

        $respApi = $response->json()['data'];

        foreach ($respApi as $rep) {
            if($rep['model_type'] === 'command')
                $isSuccessful = $this->command($rep);
            if($rep['model_type'] === 'precuenta')
                $isSuccessful = $this->preCuenta($rep);
            if($rep['model_type'] === 'invoice')
                $isSuccessful = $this->invoice($rep);
            if($rep['model_type'] === 'cashRegister')
                $isSuccessful = $this->cashRegister($rep);
        }

        return $isSuccessful ? 'ok' : 'bad';
    }




}
