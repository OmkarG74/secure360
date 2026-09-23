import 'package:flutter/material.dart';
import '../../../core/services/api_service.dart';
import '../../../core/services/notification_service.dart';
import '../../../core/services/wake_up_manager.dart';
import '../../../core/utils/time_formatter.dart';

/// Guard Mobile Notifications Screen
/// Displays system notices, shift updates, contract assignments, and administrative alerts.
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<dynamic> _notifications = [];
  bool _isLoading = true;
  bool _isMarkingAll = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadNotifications();
  }

  Future<void> _loadNotifications() async {
    setState(() => _isLoading = true);
    final response = await ApiService.getNotifications();

    if (!mounted) return;
    setState(() {
      _isLoading = false;
      if (response.success && response.data != null) {
        _notifications = response.data!;
      } else {
        _errorMessage = response.message;
      }
    });
  }

  Future<void> _handleMarkAllAsRead() async {
    if (_isMarkingAll || _notifications.isEmpty) return;
    setState(() => _isMarkingAll = true);

    final res = await ApiService.markAllNotificationsRead();

    if (!mounted) return;
    setState(() {
      _isMarkingAll = false;
      if (res.success) {
        for (var item in _notifications) {
          if (item is Map) {
            item['is_read'] = 1;
          }
        }
      }
    });

    if (res.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('All notifications marked as read'),
          duration: Duration(seconds: 2),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _onNotificationTap(Map<String, dynamic> item) async {
    final rawId = item['id'];
    final notifId = rawId is int ? rawId : int.tryParse(rawId?.toString() ?? '0') ?? 0;
    final isRead = item['is_read'] == 1 || item['is_read'] == true;

    final type = (item['type'] ?? '').toString().toLowerCase();
    final isWakeUp = (type == 'wake_up' || type == 'wake_up_call' || type.contains('wakeup'));

    // Check if this wake-up call is already acknowledged
    if (isWakeUp && notifId > 0) {
      final isAckLocally = WakeUpManager.isAcknowledged(notifId);
      final isAckInItem = item['acknowledged'] == true ||
          item['acknowledged_at'] != null ||
          (item['data_json'] is Map &&
              (item['data_json']['acknowledged'] == true || item['data_json']['acknowledged_at'] != null));

      if (isAckLocally || isAckInItem) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('This wake-up call has already been acknowledged.'),
            duration: Duration(seconds: 2),
            behavior: SnackBarBehavior.floating,
          ),
        );
        return;
      }

      // Check authoritative status from backend
      final statusRes = await ApiService.getNotificationStatus(notifId);
      if (statusRes.success && statusRes.data != null && statusRes.data!['acknowledged'] == true) {
        WakeUpManager.acknowledge(notifId);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('This wake-up call has already been acknowledged.'),
            duration: Duration(seconds: 2),
            behavior: SnackBarBehavior.floating,
          ),
        );
        return;
      }
    }

    // Optimistically mark as read locally
    if (!isRead && notifId > 0) {
      setState(() {
        item['is_read'] = 1;
      });
      ApiService.markNotificationRead(notifId);
    }

    // Build payload for routing
    final payload = <String, dynamic>{
      'type': item['type'] ?? '',
      'screen': item['entity_type'] ?? item['type'] ?? '',
      'entity_id': item['entity_id']?.toString() ?? '',
      'notification_id': notifId.toString(),
    };

    if (item['data_json'] != null) {
      if (item['data_json'] is Map<String, dynamic>) {
        payload.addAll(item['data_json'] as Map<String, dynamic>);
      }
    }

    NotificationService.handleNotificationNavigation(payload);
  }

  @override
  Widget build(BuildContext context) {
    final unreadCount = _notifications.where((n) => n['is_read'] != 1 && n['is_read'] != true).length;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0.5,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Color(0xFF0F172A)),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Alerts & Notices',
              style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 17),
            ),
            if (unreadCount > 0)
              Text(
                '$unreadCount unread notice${unreadCount > 1 ? 's' : ''}',
                style: const TextStyle(color: Color(0xFF2563EB), fontSize: 11, fontWeight: FontWeight.w600),
              ),
          ],
        ),
        actions: [
          if (_notifications.isNotEmpty && unreadCount > 0)
            _isMarkingAll
                ? const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16.0),
                    child: Center(
                      child: SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF2563EB)),
                      ),
                    ),
                  )
                : TextButton.icon(
                    onPressed: _handleMarkAllAsRead,
                    icon: const Icon(Icons.done_all, size: 16, color: Color(0xFF2563EB)),
                    label: const Text(
                      'Mark all read',
                      style: TextStyle(color: Color(0xFF2563EB), fontSize: 12, fontWeight: FontWeight.w600),
                    ),
                  ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF2563EB)))
          : RefreshIndicator(
              color: const Color(0xFF2563EB),
              onRefresh: _loadNotifications,
              child: _notifications.isEmpty
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24.0),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Container(
                              width: 64,
                              height: 64,
                              decoration: const BoxDecoration(
                                color: Color(0xFFEFF6FF),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(Icons.notifications_none, size: 36, color: Color(0xFF3B82F6)),
                            ),
                            const SizedBox(height: 16),
                            const Text(
                              'No New Notifications',
                              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              _errorMessage ?? 'You have reviewed all dispatch notices and duty alerts.',
                              textAlign: TextAlign.center,
                              style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                            ),
                          ],
                        ),
                      ),
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: _notifications.length,
                      itemBuilder: (context, index) {
                        final rawItem = _notifications[index];
                        final item = rawItem is Map<String, dynamic>
                            ? rawItem
                            : Map<String, dynamic>.from(rawItem as Map);

                        final title = item['title'] ?? 'Notice';
                        final message = item['message'] ?? '';
                        final isRead = item['is_read'] == 1 || item['is_read'] == true;
                        final createdAt = item['created_at'] ?? '';
                        final priority = (item['priority'] ?? 'normal').toString().toLowerCase();

                        // Priority badge styling
                        Color priorityColor = const Color(0xFF64748B);
                        Color priorityBg = const Color(0xFFF1F5F9);
                        String priorityLabel = 'Normal';

                        if (priority == 'critical') {
                          priorityColor = const Color(0xFFDC2626);
                          priorityBg = const Color(0xFFFEF2F2);
                          priorityLabel = 'Critical';
                        } else if (priority == 'high') {
                          priorityColor = const Color(0xFFD97706);
                          priorityBg = const Color(0xFFFFFBEB);
                          priorityLabel = 'High';
                        } else if (priority == 'low') {
                          priorityColor = const Color(0xFF64748B);
                          priorityBg = const Color(0xFFF8FAFC);
                          priorityLabel = 'Low';
                        }

                        return InkWell(
                          onTap: () => _onNotificationTap(item),
                          borderRadius: BorderRadius.circular(12),
                          child: Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: isRead ? Colors.white : const Color(0xFFF8FAFF),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: isRead ? const Color(0xFFE2E8F0) : const Color(0xFF93C5FD),
                                width: isRead ? 1.0 : 1.5,
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.02),
                                  blurRadius: 4,
                                  offset: const Offset(0, 2),
                                ),
                              ],
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Container(
                                  width: 40,
                                  height: 40,
                                  decoration: BoxDecoration(
                                    color: priority == 'critical'
                                        ? const Color(0xFFFEF2F2)
                                        : isRead
                                            ? const Color(0xFFF1F5F9)
                                            : const Color(0xFFEFF6FF),
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Icon(
                                    priority == 'critical'
                                        ? Icons.warning_amber_rounded
                                        : Icons.notifications_active_outlined,
                                    size: 22,
                                    color: priority == 'critical'
                                        ? const Color(0xFFDC2626)
                                        : isRead
                                            ? const Color(0xFF64748B)
                                            : const Color(0xFF2563EB),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Expanded(
                                            child: Text(
                                              title,
                                              style: TextStyle(
                                                fontSize: 14,
                                                fontWeight: isRead ? FontWeight.w600 : FontWeight.bold,
                                                color: const Color(0xFF0F172A),
                                              ),
                                            ),
                                          ),
                                          if (!isRead)
                                            Container(
                                              width: 8,
                                              height: 8,
                                              decoration: const BoxDecoration(
                                                color: Color(0xFF2563EB),
                                                shape: BoxShape.circle,
                                              ),
                                            ),
                                        ],
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        message,
                                        style: const TextStyle(fontSize: 13, color: Color(0xFF475569), height: 1.35),
                                      ),
                                      const SizedBox(height: 10),
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          if (createdAt.isNotEmpty)
                                            Text(
                                              TimeFormatter.formatDateTime(createdAt),
                                              style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
                                            )
                                          else
                                            const SizedBox.shrink(),
                                          if (priority != 'normal')
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                              decoration: BoxDecoration(
                                                color: priorityBg,
                                                borderRadius: BorderRadius.circular(4),
                                              ),
                                              child: Text(
                                                priorityLabel,
                                                style: TextStyle(
                                                  fontSize: 10,
                                                  fontWeight: FontWeight.bold,
                                                  color: priorityColor,
                                                ),
                                              ),
                                            ),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
            ),
    );
  }
}
