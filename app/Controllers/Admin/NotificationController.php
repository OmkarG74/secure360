<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Notification;

/**
 * Admin Notification Controller
 * Manages operational notifications for header notification center popover
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
     * Get recent notifications and unread count for current admin
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

            if (str_contains($type, 'checkin') || str_contains($type, 'attendance') || str_contains(strtolower($title), 'check-in') || str_contains(strtolower($title), 'checked in')) {
                $accent = 'green';
                $actionUrl = url('/admin/attendance');
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
     * Mark a single notification as read
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
     * Mark all notifications as read for the current admin
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
