<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WeatherCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherCheckController extends Controller
{
    public function __construct(
        private readonly WeatherCheckService $weatherCheckService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        logger()->info('Incoming weather check request', [
            'body' => $request->except([]),
            'ip' => $request->ip(),
            'headers' => [
                'content-type' => $request->header('Content-Type'),
                'x-retell-signature' => $request->header('X-Retell-Signature'),
                'user-agent' => $request->header('User-Agent'),
            ],
        ]);

        $validated = $request->validate([
            'city' => 'required|string|min:1',
            'country' => 'nullable|string',
            'callerPhone' => 'nullable|string',
        ]);

        logger()->info('Processing weather check', [
            'city' => $validated['city'],
            'country' => $validated['country'] ?? null,
            'callerPhone' => $validated['callerPhone']
                ? substr($validated['callerPhone'], 0, 6).'****'
                : null,
        ]);

        $result = $this->weatherCheckService->check(
            city: trim($validated['city']),
            country: $validated['country'] ?? null,
            callerPhone: $validated['callerPhone'] ?? null,
        );

        $response = response()->json($result->data, $result->status);

        logger()->info('Weather check response', [
            'status' => $response->getStatusCode(),
            'body' => $response->getData(true),
        ]);

        return $response;
    }
}