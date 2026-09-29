<?php

namespace App\Controllers;

use App\Models\Store;
use App\Support\Request;
use App\Support\Response;
use App\Support\StoreContext;

abstract class BaseController
{
    protected function success(mixed $data = [], array $meta = [], int $status = 200): Response
    {
        return Response::success($data, $meta, $status);
    }

    protected function error(string $code, string $message, array $details = [], int $status = 400): Response
    {
        return Response::error($code, $message, $details, $status);
    }

    protected function currentUser(Request $request): ?object
    {
        return $request->getUser();
    }

    /**
     * Centrally resolve and authorize the store for current request.
     */
    protected function resolveStore(Request $request, bool $allowDisabled = false): Store
    {
        return StoreContext::resolve($request, $allowDisabled);
    }

    /**
     * Centrally resolve and authorize the store ID for current request.
     */
    protected function resolveStoreId(Request $request, bool $allowDisabled = false): int
    {
        return StoreContext::resolveId($request, $allowDisabled);
    }

    /**
     * Backwards-compatible alias for existing controllers.
     */
    protected function resolveStoreContext(Request $request): int
    {
        return StoreContext::resolveId($request);
    }

    protected function validateStoreAccess(Request $request, int $storeId): void
    {
        StoreContext::validateAccess($request, $storeId);
    }
}
