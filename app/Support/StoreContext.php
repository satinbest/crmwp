<?php

namespace App\Support;

use App\Models\Store;
use App\Repositories\StoreRepository;
use App\Services\RbacService;
use Exception;

class StoreContext
{
    private static ?StoreRepository $storeRepo = null;
    private static ?RbacService $rbac = null;

    private static function getStoreRepo(): StoreRepository
    {
        if (self::$storeRepo === null) {
            self::$storeRepo = new StoreRepository();
        }
        return self::$storeRepo;
    }

    private static function getRbac(): RbacService
    {
        if (self::$rbac === null) {
            self::$rbac = new RbacService();
        }
        return self::$rbac;
    }

    /**
     * Centrally resolve and authorize the active Store for the current Request.
     */
    public static function resolve(Request $request, bool $allowDisabled = false): Store
    {
        $storeId = self::resolveId($request, $allowDisabled);
        $store = self::getStoreRepo()->findById($storeId);

        if (!$store) {
            throw new Exception("فروشگاه با شناسه {$storeId} در سامانه یافت نشد.", 404);
        }

        if (!$allowDisabled && in_array($store->status, ['disabled', 'inactive'], true)) {
            // Note: If store is inactive/disabled, block operational access
            throw new Exception("این فروشگاه در وضعیت غیرفعال یا مسدود قرار دارد.", 403);
        }

        return $store;
    }

    /**
     * Centrally resolve and authorize the active Store ID for the current Request.
     */
    public static function resolveId(Request $request, bool $allowDisabled = false): int
    {
        // 1. Check Header (primary for SPA client)
        $raw = $request->getHeader('x-store-id');

        // 2. Check Query parameter
        if (empty($raw)) {
            $raw = $request->query('store_id');
        }

        // 3. Check JSON/Form Body parameter
        if (empty($raw)) {
            $raw = $request->input('store_id');
        }

        // 4. Check route parameters (e.g. /stores/{id}/... or /webhooks/woocommerce/{store})
        if (empty($raw)) {
            $routeStore = $request->param('store') ?? $request->param('store_id');
            if (!empty($routeStore) && is_numeric($routeStore)) {
                $raw = $routeStore;
            }
        }

        $storeId = (int)$raw;

        // 5. Fallback if not explicitly passed:
        if ($storeId <= 0) {
            $user = $request->getUser();
            if ($user) {
                $accessibleStores = self::getRbac()->getUserAccessibleStores((int)$user->id);
                if (count($accessibleStores) === 1) {
                    $storeId = (int)$accessibleStores[0]->id;
                } elseif (count($accessibleStores) === 0) {
                    throw new Exception("هیچ فروشگاهی برای حساب کاربری شما تعریف نشده است.", 403);
                } else {
                    throw new Exception("شناسه فروشگاه (X-Store-Id یا store_id) الزامی است.", 400);
                }
            } else {
                throw new Exception("شناسه فروشگاه (X-Store-Id یا store_id) الزامی است.", 400);
            }
        }

        // 6. Verify Store exists in Database
        $store = self::getStoreRepo()->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه با شناسه {$storeId} یافت نشد.", 404);
        }

        // 7. Security: Verify User Access (Anti-IDOR)
        self::validateAccess($request, $storeId);

        // 8. Verify Store status if disabled
        if (!$allowDisabled && in_array($store->status, ['disabled'], true)) {
            throw new Exception("این فروشگاه در وضعیت مسدود قرار دارد.", 403);
        }

        return $storeId;
    }

    /**
     * Enforce strict store authorization on user.
     * Throws 403 if unauthorized.
     */
    public static function validateAccess(Request $request, int $storeId): void
    {
        $user = $request->getUser();
        if ($user) {
            $hasAccess = self::getRbac()->userHasStoreAccess((int)$user->id, $storeId);
            if (!$hasAccess) {
                Logger::warning("Unauthorized store access attempt (IDOR blocked)", [
                    'user_id' => $user->id,
                    'target_store_id' => $storeId,
                    'ip' => $request->getIp(),
                ]);
                throw new Exception("شما مجوز دسترسی به این فروشگاه را ندارید.", 403);
            }
        }
    }
}
