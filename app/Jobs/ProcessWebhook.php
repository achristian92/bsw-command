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
            $this->command($rep);
        elseif($rep['model_type'] === 'precuenta')
            $this->preCuenta($rep);
        elseif($rep['model_type'] === 'invoice')
            $this->invoice($rep);
        elseif($rep['model_type'] === 'cashRegister')
            $this->cashRegister($rep);

        Log::info('Webhook recibido: '.$rep['id']);

        http_response_code(200);
    }
}
