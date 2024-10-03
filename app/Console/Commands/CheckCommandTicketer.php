<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

            if (!$response->successful()) {
                Log::error($response->json()['errors'][0]['detail'][0]);
                return 0;
            }


            $respApi = $response->json()['data'];

            foreach ($respApi as $rep) {
                if($rep['model_type'] === 'command')
                    $this->command($rep,$api_url,$token);
                if($rep['model_type'] === 'precuenta')
                    $this->preCuenta($rep,$api_url,$token);
                if($rep['model_type'] === 'invoice')
                    $this->invoice($rep,$api_url,$token);
                if($rep['model_type'] === 'cashRegister')
                    $this->cashRegister($rep,$api_url,$token);
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
            $printer -> text($data->company_name."\n");
            $printer -> text($data->company_ruc."\n");
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
            $printer -> text("CANT  PRODUCTO        SUBTOTAL \n");
            $printer -> text("----------------------------------------\n");
            foreach ($data->items as $item) {
                $printer -> text(" ".$item->quantity."    ".$item->name."    ".$item->total."\n");
                $printer -> text("--------------------------------------\n");
            }
            $printer -> selectPrintMode();
            $printer -> feed();
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> text("TOTAL CONSUMO:"."    S/".round($data->total_amount_items,2)."\n");
            $printer -> text("DESCUENTO:"."        S/".$data->discount_amount."\n");
            $printer -> text("TOTAL A PAGAR:"."    S/".$data->total_incl."\n");
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> feed();
            $printer -> text("DNI/RUC:___________________________________________________\n");
            $printer -> text("NOMBRE/R.SOCIAL:___________________________________________\n");
            $printer -> feed();
            $printer -> text("FECHA:".now()->format('d/m/Y H:i')."\n");
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

        for ($i = 1; $i <= 2; $i++) {
            try {
                $connector = new NetworkPrintConnector($data->printer->pr_ip);
                $printer = new Printer($connector);
                $printer -> selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
                $printer -> setJustification(Printer::JUSTIFY_CENTER);
                $printer -> setTextSize(1,1);
                $printer -> setJustification(Printer::JUSTIFY_CENTER);
                $printer -> text($data->company_name."\n");
                $printer -> text($data->company_ruc."\n");
                $printer -> setTextSize(2,1);
                $printer -> text($data->invoice_name."\n");
                $printer -> text($data->serie_num."\n");
                $printer -> setTextSize(1,1);
                $printer -> setJustification(Printer::JUSTIFY_LEFT);
                $printer -> text("DNI:".$data->cli_nro_document."\n");
                $printer -> text("CLIENTE:".$data->cli_name."\n");
                $printer -> text("F.EMISION:".$data->issue_date."\n");
                $printer -> text($data->usu_ref."\n");
                $printer -> feed();
                $printer -> text("CANT  PRODUCTO        SUBTOTAL \n");
                $printer -> text("----------------------------------------\n");
                foreach ($data->items as $item) {
                    $printer -> text(" ".$item->quantity."    ".$item->name."    ".$item->total."\n");
                    $printer -> text("--------------------------------------\n");
                }
                $printer -> selectPrintMode();
                $printer -> feed();
                $printer -> setJustification(Printer::JUSTIFY_CENTER);
                $printer -> text("DESCUENTO:"."        S/".$data->discount_amount."\n");
                $printer -> text("SUBTOTAL:"."        S/".$data->total_tax_excl."\n");
                $printer -> text("IGV:"."             S/".$data->total_tax."\n");
                $printer -> text("TOTAL A PAGAR:"."    S/".$data->total_tax_incl."\n");
                $printer -> setJustification(Printer::JUSTIFY_CENTER);
                $printer -> feed();
                $printer -> setJustification(Printer::JUSTIFY_LEFT);
                if($data->payments) {
                    $printer -> text("PAGOS:"."\n");
                    foreach (json_decode($data->payments) as $payment) {
                        $printer -> text(" ".$payment->name."($payment->ref):S/".$payment->amount."\n");
                    }
                    $printer -> feed();
                }

                if($data->tips) {
                    $printer -> text("PROPINAS:"."\n");
                    foreach (json_decode($data->tips) as $tip) {
                        $printer -> text(" ".$tip->name."($tip->ref):S/".$tip->amount."\n");
                    }
                    $printer -> feed();
                }
                $printer -> setJustification(Printer::JUSTIFY_CENTER);
                $printer -> text("Representación impresa del comprobante electrónico. Para consultar ingrese a www.sunat.gob.pe:"."\n");
                $printer -> text("FECHA:".now()->format('d/m/Y H:i')."\n");
                $printer -> feed();
                $printer -> cut();
                $printer ->close();

                if($i == '1') {
                    $notify = Http::withHeaders([
                        'accept' => 'application/json'
                    ])->put($api_url."api/v1/command/".$rep['uuid'],[
                        'token' => $token
                    ]);

                    if (!$notify->successful())
                        Log::error("Error para actualizar envio de comanda");
                }


            } catch (Exception $e) {
                Log::error($e->getMessage());
            }
        }

    }
    private function cashRegister($rep,$api_url,$token)
    {
        $data = json_decode($rep['data']);

        try {
            $connector = new NetworkPrintConnector($data->printer->pr_ip);
            $printer = new Printer($connector);
            $printer -> selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> setTextSize(1,1);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> text($data->company_name."\n");
            $printer -> text($data->company_ruc."\n");
            $printer -> text("REPORTE DE MOVIMIENTO DE COBROS"."\n");
            $printer -> feed();
            $printer -> setJustification(Printer::JUSTIFY_LEFT);
            $printer -> text("CAJA:".$data->cash.'/'.$data->serie_num."\n");
            $printer -> text("USUARIO:".$data->user."\n");
            $printer -> text("APERTURA:".$data->issue_date."\n");
            $printer -> text("CIERRE:".$data->due_date."\n");
            $printer -> feed();
            $printer -> text("#  MEDIO         PAGO \n");
            $printer -> text("----------------------------------------\n");
            foreach ($data->details as $detail) {
                $printer -> text(" ".$detail->name."\n");
                foreach ($detail->payments as $payment) {
                    $printer -> text("   *".$payment->restaurant_order_id."    ".Str::substr($detail->name,0,5).($payment->operation_number ?'('.$payment->operation_number.')': '').($payment->type == '1' ?'(PRO)': '')."            S/".number_format($payment->amount,2)."\n");
                }
                $printer -> text("--------------------------------------\n");
            }
            $printer -> selectPrintMode();
            $printer -> feed();
            $printer -> text("Monto Inicial:"."    S/".number_format($data->amount_initial,2)."\n");
            foreach ($data->details as $detail2) {
                $printer -> text(" ".$detail2->name."                         S/".number_format($detail2->total,2)."\n");
            }
            $printer -> setTextSize(1,2);
            $printer -> text("TOTAL RECAUDADO:"."    S/".number_format($data->total,2)."\n");
            $printer -> setTextSize(1,1);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> feed();
            $printer -> text("FECHA:".now()->format('d/m/Y H:i')."\n");
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
