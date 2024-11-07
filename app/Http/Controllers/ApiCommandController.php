<?php

namespace App\Http\Controllers;

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
    public function __invoke(Request $request)
    {
        Log::info("entroo");
        $isSuccessful = false;

        $api_url = App::isProduction() ?  env('API_URL_PROD') :  env('API_URL_DEV');
        $token = env('COMPANY_TOKEN');

        Log::info("Comandar: ".now()->format('d/m/Y H:i:s'));
        Log::info("token: ".$token);
        Log::info("env: ".$api_url);

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

    private function command($rep):bool
    {

        $data = json_decode($rep['data']);

        try {
            $connector = $this->getConnector($data);

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

           $isSuccess = true;
            if (!$notify->successful()) {
                $isSuccess = false;
                Log::error("Error para actualizar envio de comanda");
            }

            return $isSuccess;
        } catch (Exception $e) {
            $this->sendStatusCommand($rep['uuid'],0,$e->getMessage());
            Log::error($e->getMessage());
            return false;
        }
    }
    private function preCuenta($rep)
    {
        $data = json_decode($rep['data']);

        try {
            $connector = $this->getConnector($data);

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
            $isSuccess = true;
            if (!$notify->successful()) {
                $isSuccess = false;
                Log::error("Error para actualizar envio de comanda");
            }

            return $isSuccess;

        } catch (Exception $e) {
            $this->sendStatusCommand($rep['uuid'],0,$e->getMessage());
            Log::error($e->getMessage());
            return false;
        }

    }
    private function invoice($rep)
    {
        $data = json_decode($rep['data']);

        for ($i = 1; $i <= 2; $i++) {
            try {
                $connector = $this->getConnector($data);
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
                    $isSuccess = true;
                    if (!$notify->successful()) {
                        Log::error("Error para actualizar envio de comanda");
                        $isSuccess = false;
                    }  
                }

                return $isSuccess;
            } catch (Exception $e) {
                $this->sendStatusCommand($rep['uuid'],0,$e->getMessage());
                Log::error($e->getMessage());
                return false;
            }
        }

    }
    private function cashRegister($rep)
    {
        $data = json_decode($rep['data']);

        try {
            $connector = $this->getConnector($data);
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

            $isSuccess = true;
            if (!$notify->successful()) {
                $isSuccess = false;
                Log::error("Error para actualizar envio de comanda");
            }

            return $isSuccess;

        } catch (Exception $e) {
            $this->sendStatusCommand($rep['uuid'],0,$e->getMessage());
            Log::error($e->getMessage());
            return false;
        }

    }

    private function getConnector($data)
    {
        if($data->printer->win_usb) {
            $connector = new WindowsPrintConnector($data->printer->win_usb);
            Log::info("PRINTER BY WIN USB");
        }
        elseif ($data->printer->pr_ip) {
            Log::info("PRINTER BY IP");
            $connector = new NetworkPrintConnector($data->printer->pr_ip,'9100',true);
        }


        return $connector;
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
