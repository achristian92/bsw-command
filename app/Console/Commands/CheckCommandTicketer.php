<?php

namespace App\Console\Commands;

use App\Traits\CommandTraits;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckCommandTicketer extends Command
{
    use CommandTraits;
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
        while (true) {

            $api_url = App::environment('production') ?  env('API_URL_PROD') :  env('API_URL_DEV');
            $token = env('COMPANY_TOKEN');

            $response = Http::withHeaders([
                'accept' => 'application/json'
            ])->get($api_url.'api/v1/command',[
                'token' => $token
            ]);

            if (!$response->successful()) {
                Log::error($response->json()['errors'][0]['detail'][0]);
                return 0;
            }
            Log::info("***** Command from JOB ******");


            $respApi = $response->json()['data'];

            foreach ($respApi as $rep) {
                if($rep['is_from_cashier'])
                    continue;

                Log::info("*****Job UUID: " .$rep['uuid']);
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
            sleep(30);
        }

        return 0;
    }


}
