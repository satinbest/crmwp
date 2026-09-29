<?php

namespace App\Middleware;

use App\Support\Request;
use App\Support\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
