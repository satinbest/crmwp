<?php

namespace App\Controllers;

use App\Models\Store;
use App\Repositories\StoreRepository;
use App\Services\RbacService;
use App\Services\TagService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class TagController extends BaseController
{
    private TagService $tagService;
    private StoreRepository $storeRepository;
    private RbacService $rbacService;

    public function __construct(
        ?TagService $tagService = null,
        ?StoreRepository $storeRepository = null,
        ?RbacService $rbacService = null
    ) {
        $this->tagService = $tagService ?? new TagService();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->rbacService = $rbacService ?? new RbacService();
    }


    private function authorizePermission(Request $request, string $permission): int
    {
        $user = $this->currentUser($request);
        if (!$user) {
            throw new Exception("احراز هویت الزامی است.", 401);
        }

        $userId = (int)$user->id;
        if (!$this->rbacService->userHasPermission($userId, $permission)) {
            throw new Exception("شما مجوز دسترسی لازم ({$permission}) را ندارید.", 403);
        }

        return $userId;
    }

    /**
     * GET /api/v1/tags
     */
    public function index(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'tags.view');
            $store = $this->resolveStore($request);

            $tags = $this->tagService->listTags($store->id);
            return $this->success($tags);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TAG_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/tags
     */
    public function store(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'tags.create');
            $store = $this->resolveStore($request);

            $name = (string)$request->input('name', '');
            $color = (string)$request->input('color', '#4F46E5');

            $tag = $this->tagService->createTag($store->id, $name, $color, $userId);
            return $this->success($tag, [], 201);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TAG_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * PATCH /api/v1/tags/{id}
     */
    public function update(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'tags.update');
            $store = $this->resolveStore($request);
            $id = (int)$request->getRouteParam('id');

            $data = [];
            if ($request->has('name')) $data['name'] = $request->input('name');
            if ($request->has('color')) $data['color'] = $request->input('color');

            $tag = $this->tagService->updateTag($id, $store->id, $data, $userId);
            if (!$tag) {
                return $this->error('NOT_FOUND', 'برچسب مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($tag);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TAG_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * DELETE /api/v1/tags/{id}
     */
    public function destroy(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'tags.delete');
            $store = $this->resolveStore($request);
            $id = (int)$request->getRouteParam('id');

            $deleted = $this->tagService->deleteTag($id, $store->id, $userId);
            if (!$deleted) {
                return $this->error('NOT_FOUND', 'برچسب مورد نظر یافت نشد.', [], 404);
            }

            return $this->success(['message' => 'برچسب با موفقیت حذف شد.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TAG_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/customers/{id}/tags
     */
    public function attachToCustomer(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'tags.create');
            $store = $this->resolveStore($request);
            $customerId = (int)$request->getRouteParam('id');

            $name = (string)$request->input('name', '');
            $color = (string)$request->input('color', '#4F46E5');

            $tag = $this->tagService->addTagToCustomer($store->id, $customerId, $name, $color, $userId);
            return $this->success($tag, [], 201);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TAG_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * DELETE /api/v1/customers/{id}/tags/{tagId}
     */
    public function detachFromCustomer(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'tags.delete');
            $store = $this->resolveStore($request);
            $customerId = (int)$request->getRouteParam('id');
            $tagId = (int)$request->getRouteParam('tagId');

            $this->tagService->removeTagFromCustomer($store->id, $customerId, $tagId, $userId);
            return $this->success(['message' => 'برچسب از مشتری با موفقیت حذف شد.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TAG_ERROR', $e->getMessage(), [], $code);
        }
    }
}
