import 'package:flutter/material.dart';
import '../../../core/services/api_service.dart';
import '../../../core/utils/time_formatter.dart';

class AttendanceHistoryScreen extends StatefulWidget {
  final bool isEmbedded;

  const AttendanceHistoryScreen({super.key, this.isEmbedded = false});

  @override
  State<AttendanceHistoryScreen> createState() => _AttendanceHistoryScreenState();
}

class _AttendanceHistoryScreenState extends State<AttendanceHistoryScreen> {
  List<dynamic> _allHistory = [];
  String _selectedFilter = 'all'; // 'all', 'active', 'completed'
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadHistory();
  }

  Future<void> _loadHistory() async {
    setState(() => _isLoading = true);
    final response = await ApiService.getAttendanceHistory();

    if (!mounted) return;
    setState(() {
      _isLoading = false;
      if (response.success && response.data != null) {
        _allHistory = response.data!;
      } else {
        _errorMessage = response.message;
      }
    });
  }

  List<dynamic> get _filteredHistory {
    if (_selectedFilter == 'active') {
      return _allHistory.where((log) {
        final status = log['status'];
        final checkOutAt = log['check_out_at'];
        return (status == 0 || status == '0') && (checkOutAt == null || checkOutAt.toString().isEmpty);
      }).toList();
    } else if (_selectedFilter == 'completed') {
      return _allHistory.where((log) {
        final status = log['status'];
        final checkOutAt = log['check_out_at'];
        return (status == 1 || status == '1') || (checkOutAt != null && checkOutAt.toString().isNotEmpty);
      }).toList();
    }
    return _allHistory;
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _filteredHistory;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: widget.isEmbedded
          ? null
          : AppBar(
              backgroundColor: Colors.white,
              elevation: 0,
              leading: IconButton(
                icon: const Icon(Icons.arrow_back, color: Color(0xFF0F172A)),
                onPressed: () => Navigator.of(context).pop(),
              ),
              title: const Text(
                'Attendance History',
                style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18),
              ),
            ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF2563EB)))
          : _errorMessage != null && _allHistory.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24.0),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.error_outline, size: 48, color: Color(0xFFEF4444)),
                        const SizedBox(height: 12),
                        Text(
                          _errorMessage!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(color: Color(0xFF64748B)),
                        ),
                        const SizedBox(height: 16),
                        ElevatedButton.icon(
                          onPressed: _loadHistory,
                          icon: const Icon(Icons.refresh, size: 16, color: Colors.white),
                          label: const Text('Retry Connection', style: TextStyle(color: Colors.white)),
                          style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF2563EB)),
                        ),
                      ],
                    ),
                  ),
                )
              : Column(
                  children: [
                    // Filter Chips Bar
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      color: Colors.white,
                      child: Row(
                        children: [
                          _buildFilterChip('all', 'All (${_allHistory.length})'),
                          const SizedBox(width: 8),
                          _buildFilterChip(
                            'active',
                            'On Duty (${_allHistory.where((l) => (l['status'] == 0 || l['status'] == '0') && (l['check_out_at'] == null || l['check_out_at'].toString().isEmpty)).length})',
                          ),
                          const SizedBox(width: 8),
                          _buildFilterChip(
                            'completed',
                            'Completed (${_allHistory.where((l) => (l['status'] == 1 || l['status'] == '1') || (l['check_out_at'] != null && l['check_out_at'].toString().isNotEmpty)).length})',
                          ),
                        ],
                      ),
                    ),
                    const Divider(height: 1, color: Color(0xFFE2E8F0)),

                    // Content List
                    Expanded(
                      child: RefreshIndicator(
                        onRefresh: _loadHistory,
                        child: filtered.isEmpty
                            ? Center(
                                child: Padding(
                                  padding: const EdgeInsets.all(32.0),
                                  child: Column(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      const Icon(Icons.history_toggle_off, size: 52, color: Color(0xFF94A3B8)),
                                      const SizedBox(height: 14),
                                      const Text(
                                        'No Records Matching Filter',
                                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                                      ),
                                      const SizedBox(height: 6),
                                      Text(
                                        _selectedFilter == 'all'
                                            ? 'No check-in duty logs recorded yet.'
                                            : 'No logs match the "$_selectedFilter" filter.',
                                        style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                                      ),
                                    ],
                                  ),
                                ),
                              )
                            : ListView.builder(
                                padding: const EdgeInsets.all(16.0),
                                itemCount: filtered.length,
                                itemBuilder: (context, index) {
                                  final log = filtered[index];
                                  final siteName = log['site_name'] ?? 'Assigned Duty Site';
                                  final rawCheckIn = log['check_in_at']?.toString();
                                  final checkInAt = rawCheckIn != null && rawCheckIn.isNotEmpty
                                      ? TimeFormatter.formatDateTime(rawCheckIn)
                                      : '—';
                                  final checkOutAt = log['check_out_at'];
                                  final isCompleted = (log['status'] == 1 || log['status'] == '1') || (checkOutAt != null && checkOutAt.toString().isNotEmpty);
                                  final notes = log['notes']?.toString();
                                  final selfieId = log['selfie_id'];

                                  return Container(
                                    margin: const EdgeInsets.only(bottom: 12),
                                    padding: const EdgeInsets.all(16),
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(
                                        color: isCompleted ? const Color(0xFFE2E8F0) : const Color(0xFF86EFAC),
                                        width: isCompleted ? 1 : 1.5,
                                      ),
                                       boxShadow: const [
                                         BoxShadow(
                                           color: Color(0x05000000),
                                           blurRadius: 4,
                                           offset: Offset(0, 2),
                                         ),
                                       ],
                                    ),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            Container(
                                              width: 36,
                                              height: 36,
                                              decoration: BoxDecoration(
                                                color: isCompleted ? const Color(0xFFF1F5F9) : const Color(0xFFECFDF5),
                                                borderRadius: BorderRadius.circular(8),
                                              ),
                                              child: Icon(
                                                isCompleted ? Icons.check_circle_outline : Icons.security,
                                                color: isCompleted ? const Color(0xFF64748B) : const Color(0xFF059669),
                                                size: 20,
                                              ),
                                            ),
                                            const SizedBox(width: 12),
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment: CrossAxisAlignment.start,
                                                children: [
                                                  Text(
                                                    siteName,
                                                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                                                  ),
                                                  if (log['site_address'] != null)
                                                    Text(
                                                      log['site_address'].toString(),
                                                      style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                                                    ),
                                                ],
                                              ),
                                            ),
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                              decoration: BoxDecoration(
                                                color: isCompleted ? const Color(0xFFF1F5F9) : const Color(0xFFECFDF5),
                                                borderRadius: BorderRadius.circular(20),
                                                border: Border.all(
                                                  color: isCompleted ? const Color(0xFFE2E8F0) : const Color(0xFFA7F3D0),
                                                ),
                                              ),
                                              child: Text(
                                                isCompleted ? 'Completed' : 'On Duty',
                                                style: TextStyle(
                                                  fontSize: 11,
                                                  fontWeight: FontWeight.bold,
                                                  color: isCompleted ? const Color(0xFF64748B) : const Color(0xFF059669),
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                        const Divider(height: 20, color: Color(0xFFF8FAFC)),
                                        Row(
                                          children: [
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment: CrossAxisAlignment.start,
                                                children: [
                                                  const Text('Check-In Time', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
                                                  const SizedBox(height: 2),
                                                  Text(checkInAt, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF334155))),
                                                ],
                                              ),
                                            ),
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment: CrossAxisAlignment.start,
                                                children: [
                                                  const Text('Check-Out Time', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
                                                  const SizedBox(height: 2),
                                                   Text(
                                                     checkOutAt != null && checkOutAt.toString().isNotEmpty
                                                         ? TimeFormatter.formatDateTime(checkOutAt.toString())
                                                         : 'Active Session',
                                                     style: TextStyle(
                                                       fontSize: 12,
                                                       fontWeight: FontWeight.w600,
                                                       color: checkOutAt != null ? const Color(0xFF334155) : const Color(0xFF059669),
                                                     ),
                                                   ),
                                                ],
                                              ),
                                            ),
                                          ],
                                        ),
                                        if (selfieId != null || (notes != null && notes.isNotEmpty)) ...[
                                          const SizedBox(height: 10),
                                          Row(
                                            children: [
                                              if (selfieId != null) ...[
                                                const Icon(Icons.verified, size: 14, color: Color(0xFF2563EB)),
                                                const SizedBox(width: 4),
                                                const Text('Selfie Verified', style: TextStyle(fontSize: 11, color: Color(0xFF2563EB), fontWeight: FontWeight.w500)),
                                                const SizedBox(width: 12),
                                              ],
                                              if (notes != null && notes.isNotEmpty)
                                                Expanded(
                                                  child: Text(
                                                    'Notes: $notes',
                                                    maxLines: 1,
                                                    overflow: TextOverflow.ellipsis,
                                                    style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontStyle: FontStyle.italic),
                                                  ),
                                                ),
                                            ],
                                          ),
                                        ],
                                      ],
                                    ),
                                  );
                                },
                              ),
                      ),
                    ),
                  ],
                ),
    );
  }

  Widget _buildFilterChip(String filterKey, String label) {
    final isSelected = _selectedFilter == filterKey;
    return GestureDetector(
      onTap: () => setState(() => _selectedFilter = filterKey),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFF2563EB) : const Color(0xFFF1F5F9),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
            color: isSelected ? Colors.white : const Color(0xFF475569),
          ),
        ),
      ),
    );
  }
}
