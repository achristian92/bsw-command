<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VerifyWebhookUniqueness
{
    public function handle(Request $request, Closure $next)
    {
        $signature = $request->header('X-Signature'); // Firma en el encabezado
        $bodyHash = sha1($request->getContent()); // Hash único del contenido del payload

        // Combina firma + hash
        $uniqueKey = $signature . ':' . $bodyHash;
        Log::info("uniquekey".$uniqueKey);
        // Verifica si ya se procesó
        if (Cache::has($uniqueKey)) {
            return response('Duplicate webhook ignored', 200);
        }

        // Guarda el identificador en caché por 1 día
        Cache::put($uniqueKey, true, now()->addDay());

        return $next($request);
    }
}
