<?php

namespace App\Automation\Actions;

use App\Automation\VariableResolver;
use App\Events\AutomationEvent;
use App\Repositories\StoreRepository;
use App\Repositories\UserRepository;
use App\Services\NotificationService;
use Exception;

class CreateNotificationAction implements ActionInterface
{
    private NotificationService $notificationService;
    private UserRepository $userRepository;
    private StoreRepository $storeRepository;
    private VariableResolver $variableResolver;

    public function __construct(
        ?NotificationService $notificationService = null,
        ?UserRepository $userRepository = null,
        ?StoreRepository $storeRepository = null,
        ?VariableResolver $variableResolver = null
    ) {
        $this->notificationService = $notificationService ?? new NotificationService();
        $this->userRepository = $userRepository ?? new UserRepository();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->variableResolver = $variableResolver ?? new VariableResolver();
    }

    public function getName(): string
    {
        return 'create_notification';
    }

    public function getLabel(): string
    {
        return 'ارسال اعلان درون‌برنامه‌ای';
    }

    public function validate(array $config): array
    {
        $errors = [];
        if (empty($config['title']) && empty($config['message'])) {
            $errors[] = 'عنوان یا متن اعلان الزامی است.';
        }
        return $errors;
    }

    public function execute(array $config, AutomationEvent $event, array $resolvedVars, int $userId): array
    {
        $storeId = $event->getStoreId();

        $rawTitle = $config['title'] ?? 'اعلان اتوماسیون';
        $title = $this->variableResolver->resolveText($rawTitle, $resolvedVars);

        $rawMsg = $config['message'] ?? 'یک رویداد خودکار با موفقیت پردازش شد.';
        $message = $this->variableResolver->resolveText($rawMsg, $resolvedVars);

        $priority = $config['priority'] ?? 'normal';
        $type = $config['type'] ?? 'automation_alert';

        // Target users:
        // 1. Explicit user_id
        // 2. 'admin' or 'managers'
        // 3. Current active user or users with store access
        $targetUserIds = [];
        if (!empty($config['user_id']) && is_numeric($config['user_id'])) {
            $targetUserIds[] = (int)$config['user_id'];
        } else {
            // Find active users with access to this store
            $users = $this->userRepository->listFiltered(['status' => 'active'], 50);
            foreach ($users as $u) {
                $uid = (int)$u->id;
                $isAdmin = method_exists($u, 'hasRole') ? $u->hasRole('administrator') : false;
                if ($this->storeRepository->userHasAccess($uid, $storeId, $isAdmin)) {
                    $targetUserIds[] = $uid;
                }
            }
        }

        if (empty($targetUserIds) && $userId > 0) {
            $targetUserIds[] = $userId;
        }

        $sentCount = 0;
        foreach ($targetUserIds as $targetUid) {
            $notifId = $this->notificationService->createForUser(
                $targetUid,
                $type,
                $title,
                $message,
                [
                    'event_type' => $event->getType(),
                    'resource_id' => $event->getResourceId(),
                    'source' => 'automation_engine',
                ],
                $storeId,
                $config['action_url'] ?? null,
                $priority
            );
            if ($notifId) {
                $sentCount++;
            }
        }

        return [
            'action' => $this->getName(),
            'sent_count' => $sentCount,
            'target_users' => count($targetUserIds),
            'title' => $title,
            'status' => 'success',
        ];
    }
}
