<?php

namespace App\Jobs;

use App\Models\ProcessedWebhook;
use App\Traits\CommandTraits;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessWebhook extends ProcessWebhookJob
{
    use CommandTraits;

    public function handle()
    {
        $rep = json_decode($this->webhookCall,true)['payload']['command'];

        $webhookId = $rep['id'];

//        if($rep['resend'])
//            ProcessedWebhook::where('webhook_id', $webhookId)->delete();

        if (ProcessedWebhook::where('webhook_id', $webhookId)->exists())
            return response('Webhook already processed', 200);


        ProcessedWebhook::create(['webhook_id' => $webhookId]);

        Log::info("MODEL TYPE: ".$rep['model_type']);

        if($rep['model_type'] === 'command')
            $this->command($rep);
        elseif($rep['model_type'] === 'precuenta')
            $this->preCuenta($rep);
        elseif($rep['model_type'] === 'invoice')
            $this->invoice($rep);
        elseif($rep['model_type'] === 'cashRegister')
            $this->cashRegister($rep);

        Log::info('Webhook recibido: '.$rep['id']);

        return http_response_code(200);
    }
}
