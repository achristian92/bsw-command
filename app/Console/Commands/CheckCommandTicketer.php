<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Warrior\Ticketer\Ticketer;

class CheckCommandTicketer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:checkCommandTicketer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'consult api to command ticketer';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Iniciando escuchador para consultar la API cada 20 segundos...');
        Log::info("Consultando api ticketer");
        while (true) {

            $api_url = App::environment('production') ?  env('API_URL_PROD') :  env('API_URL_DEV');
            $token = env('COMPANY_TOKEN');

            Log::info("api base:".$api_url);
            Log::info("token:".$token);

            $response = Http::withHeaders([
                'accept' => 'application/json'
            ])->get($api_url.'api/v1/command',[
                'token' => $token
            ]);

            if (!$response->successful())
                Log::error($response->json()['errors'][0]['detail'][0]);

            $respApi = $response->json()['data'];

            foreach ($respApi as $rep) {
                if($rep['model_type'] === 'command')
                    $this->command($rep,$api_url,$token);
                if($rep['model_type'] === 'precuenta')
                    $this->preCuenta($rep,$api_url,$token);
                if($rep['model_type'] === 'invoice')
                    $this->invoice($rep,$api_url,$token);
            }

            // Espera 20 segundos antes de la siguiente iteración
            sleep(20);
        }

        return 0;
    }

    private function command($rep,$api_url,$token)
    {
        $data = json_decode($rep['data']);

        foreach ($data->printers as  $zn) {
            try {
                $connector = new NetworkPrintConnector($zn->pr_ip);
                $printer = new Printer($connector);
                $printer -> selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
                $printer -> setJustification(Printer::JUSTIFY_CENTER);
                $printer -> setTextSize(1,1);
                $printer -> setJustification(Printer::JUSTIFY_CENTER);
                $printer -> text($data->company."\n");
                $printer -> text("#:".$data->num."\n");
                $printer -> setJustification(Printer::JUSTIFY_LEFT);
                $printer -> text("AREA:".$zn->area."\n");
                $printer -> text("HORA:".$data->date.' '.$data->time."\n");
                $printer -> text("MOZO:".$data->waiter."\n");
                $printer -> text("MESA:".$data->table."\n");
                $printer -> feed();
                $printer -> text("CANT  DETALLE                    \n");
                $printer -> text("---------------------------------\n");
                foreach ($zn->items as $item) {
                    $printer -> text("  ".$item->qty."    ".$item->name."\n");
                    if($item->notes)
                        $printer -> text("  "."    "."(Nota:".$item->notes.')'."\n");

                    $printer -> text("-----------------------------------\n");
                }
                $printer -> selectPrintMode();
                $printer -> feed();
                $printer -> feed();
                $printer -> cut();
                $printer ->close();

                $notify = Http::withHeaders([
                    'accept' => 'application/json'
                ])->put($api_url."api/v1/command/".$rep['uuid'],[
                    'token' => $token
                ]);

                if (!$notify->successful())
                    Log::error("Error para actualizar envio de comanda");

            } catch (Exception $e) {
                Log::error($e->getMessage());
            }

        }
    }
    private function preCuenta($rep,$api_url,$token)
    {
        $data = json_decode($rep['data']);

        try {
            $connector = new NetworkPrintConnector($data->printer->pr_ip);
            $printer = new Printer($connector);
            $printer -> selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> setTextSize(1,1);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> text("PRE-CUENTA"."\n");
            $printer -> text("#:".$data->order_num."\n");
//            $printer -> text($data->invoice_name.":".$data->serie_num."\n");
            $printer -> setJustification(Printer::JUSTIFY_LEFT);
            $printer -> text("DNI:".$data->cli_nro_document."\n");
            $printer -> text("CLIENTE:".$data->cli_name."\n");
            $printer -> text("FECHA:".$data->issue_date."\n");
            $printer -> text("MOZO:".$data->waiter."\n");
            $printer -> text("MESA:".$data->table."\n");
            $printer -> feed();
            $printer -> text("DETALLE                PRECIO      TOTAL   \n");
            $printer -> text("-----------------------------------\n");
            foreach ($data->items as $item) {
                $printer -> text($item->name."   ".$item->quantity."   ".$item->total."\n");
                $printer -> text("-----------------------------------\n");
            }
            $printer -> selectPrintMode();
            $printer -> feed();
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
//            $printer -> text("DESCUENTO:".$data->discount_amount);
//            $printer -> text("SUBTOTAL:".$data->subtotal);
//            $printer -> text("IGV:".$data->igv);
            $printer -> text("IMPORTE TOTAL:".$data->total_incl);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> feed();
            $printer -> cut();
            $printer ->close();

            $notify = Http::withHeaders([
                'accept' => 'application/json'
            ])->put($api_url."api/v1/command/".$rep['uuid'],[
                'token' => $token
            ]);

            if (!$notify->successful())
                Log::error("Error para actualizar envio de comanda");

        } catch (Exception $e) {
            Log::error($e->getMessage());
        }



    }
    private function invoice($rep,$api_url,$token)
    {
        $data = json_decode($rep['data']);
        //dd($data);
        foreach ($data->printers as  $zn) {
            try {
                $connector = new NetworkPrintConnector($zn->pr_ip);
                $printer = new Printer($connector);
                $printer -> selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
                $printer -> setJustification(Printer::JUSTIFY_CENTER);
                $printer -> setTextSize(1,1);
                $printer -> setJustification(Printer::JUSTIFY_CENTER);
                $printer -> text($data->company."\n");
                $printer -> text("#:".$data->num."\n");
                $printer -> setJustification(Printer::JUSTIFY_LEFT);
                $printer -> text("AREA:".$zn->area."\n");
                $printer -> text("HORA:".$data->date.' '.$data->time."\n");
                $printer -> text("MOZO:".$data->waiter."\n");
                $printer -> text("MESA:".$data->table."\n");
                $printer -> feed();
                $printer -> text("CANT  DETALLE                    \n");
                $printer -> text("---------------------------------\n");
                foreach ($zn->items as $item) {
                    $printer -> text("  ".$item->qty."    ".$item->name."\n");
                    if($item->notes)
                        $printer -> text("  "."    "."(Nota:".$item->notes.')'."\n");

                    $printer -> text("-----------------------------------\n");
                }
                $printer -> selectPrintMode();
                $printer -> feed();
                $printer -> feed();
                $printer -> cut();
                $printer ->close();

                $notify = Http::withHeaders([
                    'accept' => 'application/json'
                ])->put($api_url."api/v1/command/".$rep['uuid'],[
                    'token' => $token
                ]);

                if (!$notify->successful())
                    Log::error("Error para actualizar envio de comanda");

            } catch (Exception $e) {
                Log::error($e->getMessage());
            }

        }
    }
}
