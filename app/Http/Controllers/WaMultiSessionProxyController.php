<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WaMultiSessionProxyController extends Controller
{
    public function __invoke(Request $request, string $path = '')
    {
        $target = rtrim('http://'.config('wa.multi_session.host', '127.0.0.1').':'.(int) config('wa.multi_session.port', 3100), '/')
            .'/'.ltrim($path, '/');

        $query = $request->getQueryString();
        if (is_string($query) && $query !== '') {
            $target .= '?'.$query;
        }

        $headers = array_filter([
            'Authorization' => $request->header('Authorization'),
            'key' => $request->header('key'),
            'X-Session-Id' => $request->header('X-Session-Id'),
            'Content-Type' => $request->header('Content-Type', 'application/json'),
            'Accept' => $request->header('Accept', 'application/json'),
        ], fn (?string $value): bool => $value !== null && $value !== '');

        $response = Http::withHeaders($headers)
            ->withBody((string) $request->getContent(), $headers['Content-Type'] ?? 'application/json')
            ->send($request->method(), $target);

        return response($response->body(), $response->status())
            ->withHeaders([
                'Content-Type' => $response->header('Content-Type', 'application/json'),
            ]);
    }
}
