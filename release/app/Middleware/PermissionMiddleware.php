<?php

namespace App\Middleware;

use App\Services\RbacService;
use App\Support\Request;
use App\Support\Response;

class PermissionMiddleware implements MiddlewareInterface
{
    private string $permission;
    private RbacService $rbacService;

    public function __construct(string $permission = '', ?RbacService $rbacService = null)
    {
        $this->permission = $permission;
        $this->rbacService = $rbacService ?? new RbacService();
    }

    public static function for(string $permission): callable
    {
        return function (Request $req, callable $next) use ($permission): Response {
            $middleware = new self($permission);
            return $middleware->handle($req, $next);
        };
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $request->getUser();

        if (!$user) {
            return Response::error('UNAUTHENTICATED', 'دسترسی غیرمجاز. احراز هویت لازم است.', [], 401);
        }

        if (!empty($this->permission)) {
            $hasPermission = $this->rbacService->userHasPermission($user->id, $this->permission);
            if (!$hasPermission) {
                return Response::error(
                    'FORBIDDEN',
                    "شما مجوز لازم برای انجام این عملیات را ندارید [{$this->permission}].",
                    ['required_permission' => $this->permission],
                    403
                );
            }
        }

        return $next($request);
    }
}
