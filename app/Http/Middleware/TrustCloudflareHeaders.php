<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TrustCloudflareHeaders
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('cloudflare.enabled')) {
            return $next($request);
        }

        $connectingIpHeader = config('cloudflare.connecting_ip_header', 'CF-Connecting-IP');
        $visitorHeader = config('cloudflare.visitor_header', 'CF-Visitor');

        $connectingIp = $request->headers->get($connectingIpHeader);
        if (is_string($connectingIp) && $connectingIp !== '') {
            $forwardedFor = (string) $request->headers->get('X-Forwarded-For', '');

            if ($forwardedFor === '' || ! str_contains($forwardedFor, $connectingIp)) {
                $request->headers->set('X-Forwarded-For', $connectingIp);
            }
        }

        $visitorPayload = $request->headers->get($visitorHeader);
        if (is_string($visitorPayload) && $visitorPayload !== '') {
            $visitorData = json_decode($visitorPayload, true);

            if (is_array($visitorData) && ! empty($visitorData['scheme'])) {
                $request->headers->set('X-Forwarded-Proto', (string) $visitorData['scheme']);
            }
        }

        return $next($request);
    }
}
