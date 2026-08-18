<?php

namespace App\Http\Controllers\NajmHoda;

use App\Http\Controllers\Controller;
use App\Services\NajmHoda\Integrations\N8n\N8nCallbackIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class N8nCallbackController extends Controller
{
    public function __invoke(Request $request, N8nCallbackIngestor $ingestor): JsonResponse
    {
        try {
            $result = $ingestor->ingest($request->getContent(), [
                'x-najmhoda-timestamp' => $request->header('X-NajmHoda-Timestamp'),
                'x-najmhoda-request-id' => $request->header('X-NajmHoda-Request-Id'),
                'x-najmhoda-purpose' => $request->header('X-NajmHoda-Purpose'),
                'x-najmhoda-signature' => $request->header('X-NajmHoda-Signature'),
            ]);

            return response()->json($result, 202);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['accepted' => false, 'error' => 'invalid_callback'], 422);
        } catch (RuntimeException $exception) {
            return response()->json(['accepted' => false, 'error' => 'callback_unavailable'], 503);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['accepted' => false, 'error' => 'callback_failed'], 500);
        }
    }
}
