<?php

namespace App\Jobs;

use App\Traits\CommandTraits;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessWebhook extends ProcessWebhookJob
{
    use CommandTraits;

    public function handle()
    {
        $rep = json_decode($this->webhookCall,true)['payload']['command'];

        if($rep['model_type'] === 'command')
            $isSuccessful = $this->command($rep);
        if($rep['model_type'] === 'precuenta')
            $isSuccessful = $this->preCuenta($rep);
        if($rep['model_type'] === 'invoice')
            $isSuccessful = $this->invoice($rep);
        if($rep['model_type'] === 'cashRegister')
            $isSuccessful = $this->cashRegister($rep);

        Log::info('Webhook recibido: '.$rep['id']);
//
//        return $isSuccessful ? 'ok' : 'bad';
        // Lógica de procesamiento, por ejemplo:

    }
}
