<?php

namespace App\Integrations\WooCommerce;

use App\Models\Store;
use App\Repositories\StoreRepository;
use Exception;

class WooCommerceAdapterFactory
{
    private static ?StoreRepository $storeRepo = null;

    private static function getStoreRepo(): StoreRepository
    {
        if (self::$storeRepo === null) {
            self::$storeRepo = new StoreRepository();
        }
        return self::$storeRepo;
    }

    /**
     * Resolve WooCommerce adapter instance for a Store.
     * Selects between DemoWooCommerceAdapter and WooCommerceApiAdapter based on store configuration.
     */
    public static function create(Store|int $storeOrId): WooCommerceAdapterInterface
    {
        if (is_int($storeOrId)) {
            $store = self::getStoreRepo()->findById($storeOrId);
            if (!$store) {
                throw new Exception("فروشگاه با شناسه {$storeOrId} یافت نشد.", 404);
            }
        } else {
            $store = $storeOrId;
        }

        if ($store->isDemo()) {
            return new DemoWooCommerceAdapter($store);
        }

        return new WooCommerceApiAdapter($store);
    }
}
