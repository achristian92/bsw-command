<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;

class TestController extends Controller
{
    public function index()
    {
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
        dd($respApi);
        foreach ($respApi as $rep) {
            if($rep['success'] == '0')
                continue;

            if($rep['model_type'] === 'command')
                $this->command($rep);
            if($rep['model_type'] === 'precuenta')
                $this->preCuenta($rep);
            if($rep['model_type'] === 'invoice')
                $this->invoice($rep);
            if($rep['model_type'] === 'cashRegister')
                $this->cashRegister($rep);
        }

        // Espera 20 segundos antes de la siguiente iteración
    }
    private function command($rep)
    {
        $data = json_decode($rep['data']);

        try {
            $connector = new NetworkPrintConnector($data->printer->pr_ip);
            $printer = new Printer($connector);
            $printer -> selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> setTextSize(1,1);
            $printer -> setJustification(Printer::JUSTIFY_CENTER);
            $printer -> text($data->company."\n");
            $printer -> text("#:".$data->num."\n");
            $printer -> setJustification(Printer::JUSTIFY_LEFT);
            $printer -> text("AREA:".$data->printer->area."\n");
            $printer -> text("HORA:".$data->date.' '.$data->time."\n");
            $printer -> text("MOZO:".$data->waiter."\n");
            $printer -> text("MESA:".$data->table."\n");
            $printer -> feed();
            $printer -> text("CANT  DETALLE                    \n");
            $printer -> text("---------------------------------\n");
            foreach ($data->printer->items as $item) {
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

            $notify = $this->sendStatusCommand($rep['uuid'],1,"Exitoso");

            if (!$notify->successful())
                Log::error("Error para actualizar envio de comanda");

        } catch (Exception $e) {
            $this->sendStatusCommand($rep['uuid'],0,$e->getMessage());
            Log::error($e->getMessage());
        }
    }
    private function preCuenta($rep)
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

            $notify = $this->sendStatusCommand($rep['uuid'],1,"Existoso");

            if (!$notify->successful())
                Log::error("Error para actualizar envio de comanda");

        } catch (Exception $e) {
            $this->sendStatusCommand($rep['uuid'],0,$e->getMessage());
            Log::error($e->getMessage());
        }

    }
    private function invoice($rep)
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
                    $notify = $this->sendStatusCommand($rep['uuid'],1,"Existoso");

                    if (!$notify->successful())
                        Log::error("Error para actualizar envio de comanda");
                }


            } catch (Exception $e) {
                $this->sendStatusCommand($rep['uuid'],0,$e->getMessage());
                Log::error($e->getMessage());
            }
        }

    }
    private function cashRegister($rep)
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


            $notify = $this->sendStatusCommand($rep['uuid'],1,'Exitoso');

            if (!$notify->successful())
                Log::error("Error para actualizar envio de comanda");

        } catch (Exception $e) {
            $this->sendStatusCommand($rep['uuid'],0,$e->getMessage());
            Log::error($e->getMessage());
        }

    }
    private function sendStatusCommand($uuid, $code, $msg)
    {
        $api_url = App::environment('production') ?  env('API_URL_PROD') :  env('API_URL_DEV');
        $token = env('COMPANY_TOKEN');

        return Http::withHeaders([
            'accept' => 'application/json'
        ])->put($api_url."api/v1/command/".$uuid,[
            'token' => $token,
            'success' => $code,
            'message' => $msg
        ]);
    }

}
