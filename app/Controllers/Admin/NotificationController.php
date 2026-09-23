<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Guard;
use App\Models\Notification;
use App\Services\NotificationService;

/**
 * Admin Notification Controller
 * Manages operational notifications for header notification center popover
 * and full web administrative alert broadcast & history module.
 */
class NotificationController extends Controller
{
    private Notification $notificationModel;

    public function __construct(?Request $request = null, ?Response $response = null)
    {
        parent::__construct($request, $response);
        $this->notificationModel = new Notification();
    }

    /**
     * Get recent notifications and unread count for current admin (header popover API)
     */
    public function index(Request $request = null, Response $response = null): void
    {
        $userId = Auth::id() ?? 0;
        $orgId = Auth::organisationId() ?? 1;

        $rawNotifications = $this->notificationModel->findForAdmin($userId, $orgId, 15);
        $unreadCount = $this->notificationModel->getUnreadCountForAdmin($userId, $orgId);

        $formatted = array_map(function ($notif) {
            $type = strtolower((string)($notif['type'] ?? 'system'));
            $title = (string)($notif['title'] ?? 'Notification');
            $createdAt = (string)($notif['created_at'] ?? date('Y-m-d H:i:s'));

            // Determine status accent color and destination link
            $accent = 'blue';
            $actionUrl = null;
            $entityId = !empty($notif['entity_id']) ? (string)$notif['entity_id'] : null;

            if (str_contains($type, 'checkin') || str_contains(strtolower($title), 'check-in') || str_contains(strtolower($title), 'checked in')) {
                $accent = 'green';
                $actionUrl = $entityId ? url('/admin/attendance/' . $entityId) : url('/admin/attendance');
            } elseif (str_contains($type, 'checkout') || str_contains(strtolower($title), 'check-out') || str_contains(strtolower($title), 'checked out')) {
                $accent = 'blue';
                $actionUrl = $entityId ? url('/admin/attendance/' . $entityId) : url('/admin/attendance');
            } elseif (str_contains($type, 'departure') || str_contains(strtolower($title), 'departure')) {
                $accent = 'amber';
                $actionUrl = url('/admin/attendance');
            } elseif (str_contains($type, 'wake_up') || str_contains($type, 'wakeup')) {
                $accent = 'red';
                $actionUrl = url('/admin/notifications/manage?type=wake_up_call');
            } elseif (str_contains($type, 'attendance')) {
                $accent = 'green';
                $actionUrl = $entityId ? url('/admin/attendance/' . $entityId) : url('/admin/attendance');
            } elseif (str_contains($type, 'guard') || str_contains($type, 'assign') || str_contains(strtolower($title), 'guard')) {
                $accent = 'blue';
                $actionUrl = url('/admin/guards');
            } elseif (str_contains($type, 'contract')) {
                $accent = 'amber';
                $actionUrl = url('/admin/contracts');
            } elseif (str_contains($type, 'site') || str_contains($type, 'client')) {
                $accent = 'blue';
                $actionUrl = url('/admin/clients-sites');
            } elseif (str_contains($type, 'sos') || str_contains($type, 'alert') || str_contains($type, 'emergency')) {
                $accent = 'red';
                $actionUrl = url('/admin/reports');
            }

            return [
                'id' => (int)$notif['id'],
                'type' => $type,
                'title' => $title,
                'message' => (string)($notif['message'] ?? ''),
                'is_read' => (bool)$notif['is_read'],
                'created_at' => $createdAt,
                'time_ago' => $this->formatRelativeTime($createdAt),
                'accent' => $accent,
                'action_url' => $actionUrl,
            ];
        }, $rawNotifications);

        $this->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'notifications' => $formatted,
        ]);
    }

    /**
     * Mark a single notification as read (popover API)
     */
    public function markRead(string|int $id, Request $request = null, Response $response = null): void
    {
        $userId = Auth::id() ?? 0;
        $orgId = Auth::organisationId() ?? 1;
        $notifId = (int)$id;

        $this->notificationModel->markAsReadForAdmin($notifId, $userId, $orgId);
        $unreadCount = $this->notificationModel->getUnreadCountForAdmin($userId, $orgId);

        $this->json([
            'success' => true,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark all notifications as read for the current admin (popover API)
     */
    public function markAllRead(Request $request = null, Response $response = null): void
    {
        $userId = Auth::id() ?? 0;
        $orgId = Auth::organisationId() ?? 1;

        $this->notificationModel->markAllAsReadForAdmin($userId, $orgId);

        $this->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }

    /**
     * Admin Notification History & Audit Management Page
     * GET /admin/notifications/manage
     */
    public function manage(Request $request = null, Response $response = null): void
    {
        $orgId = Auth::organisationId() ?? 1;

        // Sanitize and extract query filters
        $filters = [
            'type' => trim((string)($this->request->query('type') ?? '')),
            'priority' => trim((string)($this->request->query('priority') ?? '')),
            'delivery_status' => trim((string)($this->request->query('delivery_status') ?? '')),
            'date_from' => trim((string)($this->request->query('date_from') ?? '')),
            'date_to' => trim((string)($this->request->query('date_to') ?? '')),
            'search' => trim((string)($this->request->query('search') ?? '')),
            'recipient_id' => trim((string)($this->request->query('recipient_id') ?? '')),
        ];

        // Clean empty filters
        $activeFilters = array_filter($filters, fn($val) => $val !== '');

        // Pagination setup
        $perPage = 20;
        $currentPage = max(1, (int)($this->request->query('page') ?? 1));
        $totalCount = $this->notificationModel->countForAdminHistory($orgId, $activeFilters);
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        $offset = ($currentPage - 1) * $perPage;

        $notifications = $this->notificationModel->findForAdminHistory($orgId, $activeFilters, $perPage, $offset);

        // Fetch guards for filter dropdown
        $guardModel = new Guard();
        $guards = $guardModel->allByTenant($orgId);

        $this->render('admin/notifications/index', [
            'pageTitle' => 'Alerts & Notices - Secure360',
            'notifications' => $notifications,
            'guards' => $guards,
            'filters' => $filters,
            'totalCount' => $totalCount,
            'totalRecords' => $totalCount,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'pageSize' => $perPage,
            'queryParams' => $activeFilters,
            'baseUrl' => url('/admin/notifications/manage'),
        ], 'layouts/admin');
    }

    /**
     * Render Alert Creation Form
     * GET /admin/notifications/create
     */
    public function createAlertForm(Request $request = null, Response $response = null): void
    {
        $orgId = Auth::organisationId() ?? 1;

        $guardModel = new Guard();
        $guards = $guardModel->allByTenant($orgId);

        $this->render('admin/notifications/create', [
            'pageTitle' => 'Create Guard Alert - Secure360',
            'guards' => $guards,
        ], 'layouts/admin');
    }

    /**
     * Dispatch Alert to Guards
     * POST /admin/notifications/create
     */
    public function sendAlert(Request $request = null, Response $response = null): void
    {
        $adminId = Auth::id() ?? 0;
        $orgId = Auth::organisationId() ?? 1;

        $title = trim((string)$this->request->input('title'));
        $message = trim((string)$this->request->input('message'));
        $alertType = trim((string)($this->request->input('alert_type') ?? 'general_alert'));
        $priority = trim((string)($this->request->input('priority') ?? 'normal'));
        $sendTo = trim((string)($this->request->input('send_to') ?? 'all'));
        $specificGuardId = (int)$this->request->input('guard_id');
        $multipleGuardIds = $this->request->input('guard_ids');
        $schedule = trim((string)($this->request->input('schedule') ?? 'now'));

        // Basic validation
        if ($title === '' || $message === '') {
            $this->setFlash('error', 'Please provide both an Alert Title and Message.');
            $this->redirect('/admin/notifications/create');
            return;
        }

        $options = [
            'priority' => $priority,
            'sender_user_id' => $adminId,
            'organization_id' => $orgId,
            'entity_type' => 'admin_alert',
        ];

        $data = [
            'screen' => 'notifications',
            'type' => $alertType,
            'priority' => $priority,
        ];

        try {
            if ($sendTo === 'specific') {
                if ($specificGuardId <= 0) {
                    $this->setFlash('error', 'Please select a guard to receive the alert.');
                    $this->redirect('/admin/notifications/create');
                    return;
                }

                $res = NotificationService::sendToGuard($specificGuardId, $title, $message, $alertType, $data, $options);
                if (!$res['success']) {
                    $this->setFlash('error', $res['error'] ?? 'Failed to send alert to selected guard.');
                    $this->redirect('/admin/notifications/create');
                    return;
                }

                $this->setFlash('success', 'Alert successfully sent to the selected guard.');
            } elseif ($sendTo === 'multiple') {
                $guardIds = is_array($multipleGuardIds) ? array_map('intval', $multipleGuardIds) : [];
                $guardIds = array_filter($guardIds, fn($id) => $id > 0);

                if (empty($guardIds)) {
                    $this->setFlash('error', 'Please select at least one guard.');
                    $this->redirect('/admin/notifications/create');
                    return;
                }

                $res = NotificationService::sendToGuards($guardIds, $title, $message, $alertType, $data, $options);
                $this->setFlash('success', "Alert dispatched to {$res['successful_dispatches']} guard(s).");
            } else {
                // Send to ALL guards in organisation
                $res = NotificationService::sendToRole('guard', $orgId, $title, $message, $alertType, $data, $options);
                $this->setFlash('success', "Alert successfully broadcast to all active guards ({$res['successful_dispatches']} sent).");
            }

            $this->redirect('/admin/notifications/manage');
        } catch (\Throwable $e) {
            error_log('[NotificationController] Failed to dispatch alert: ' . $e->getMessage());
            $this->setFlash('error', 'An error occurred while dispatching the alert: ' . $e->getMessage());
            $this->redirect('/admin/notifications/create');
        }
    }

    /**
     * Dispatch Wake-Up Call to Guard from Web Admin
     * POST /admin/notifications/wake-up
     */
    public function sendWakeUpCall(Request $request = null, Response $response = null): void
    {
        $adminId = Auth::id() ?? 0;
        $orgId = Auth::organisationId() ?? 1;

        $guardId = (int)$this->request->input('guard_id');
        $accept = (string)($this->request->getHeader('accept') ?? '');
        $isAjax = $this->request->isAjax() || str_contains($accept, 'application/json');

        if ($guardId <= 0) {
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'Please select a valid guard.'], 422);
                return;
            }
            $this->setFlash('error', 'Please select a valid guard.');
            $this->redirect('/admin/notifications/manage');
            return;
        }

        $res = NotificationService::sendWakeUpCall($guardId, $adminId, $orgId);

        if (!$res['success']) {
            if ($isAjax) {
                $this->json(['success' => false, 'message' => $res['message']], 404);
                return;
            }
            $this->setFlash('error', $res['message']);
            $this->redirect('/admin/notifications/manage');
            return;
        }

        if ($isAjax) {
            $this->json([
                'success' => true,
                'message' => $res['message'],
                'data' => $res,
            ]);
            return;
        }

        $this->setFlash('success', $res['message']);
        $this->redirect('/admin/notifications/manage');
    }

    /**
     * Helper to compute relative time string
     */
    private function formatRelativeTime(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return 'Recently';
        }
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $mins = max(1, (int)floor($diff / 60));
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 86400) {
            $hours = (int)floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 604800) {
            $days = (int)floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
        return format_date($timestamp);
    }
}
