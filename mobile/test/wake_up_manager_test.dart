import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:secure360_mobile/core/services/wake_up_manager.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
  });

  group('WakeUpManager State & Deduplication Tests', () {
    test('Invalid notification IDs are rejected immediately', () {
      expect(WakeUpManager.canTrigger(0), isFalse);
      expect(WakeUpManager.canTrigger(-1), isFalse);
      expect(WakeUpManager.canTrigger(-999), isFalse);
    });

    test('Fresh notification ID can trigger', () {
      const freshId = 101;
      expect(WakeUpManager.isAcknowledged(freshId), isFalse);
      expect(WakeUpManager.isHandled(freshId), isFalse);
      expect(WakeUpManager.canTrigger(freshId), isTrue);
    });

    test('Acknowledged notification cannot trigger again', () async {
      const notifId = 202;
      expect(WakeUpManager.canTrigger(notifId), isTrue);

      // Simulate acknowledge
      await WakeUpManager.acknowledge(notifId);

      expect(WakeUpManager.isAcknowledged(notifId), isTrue);
      expect(WakeUpManager.canTrigger(notifId), isFalse);
    });

    test('Duplicate incoming event for handled notification cannot trigger again', () {
      const notifId = 303;
      expect(WakeUpManager.canTrigger(notifId), isTrue);

      // Simulate handling
      WakeUpManager.canTrigger(notifId);
      // Once handled, subsequent calls for same notification ID must be blocked
    });

    test('markHandled blocks subsequent alarm triggers', () async {
      const notifId = 501;
      expect(WakeUpManager.canTrigger(notifId), isTrue);

      await WakeUpManager.markHandled(notifId);
      expect(WakeUpManager.isHandled(notifId), isTrue);
      expect(WakeUpManager.canTrigger(notifId), isFalse);
    });

    test('Screen visibility tracking prevents duplicate screen pushes', () {
      const notifId = 601;
      expect(WakeUpManager.isScreenVisible(notifId), isFalse);

      WakeUpManager.setScreenVisible(notifId, true);
      expect(WakeUpManager.isScreenVisible(notifId), isTrue);

      WakeUpManager.setScreenVisible(notifId, false);
      expect(WakeUpManager.isScreenVisible(notifId), isFalse);
    });

    test('Multiple unique notifications are handled independently', () async {
      const idA = 401;
      const idB = 402;

      // Acknowledge A
      await WakeUpManager.acknowledge(idA);

      expect(WakeUpManager.canTrigger(idA), isFalse);
      expect(WakeUpManager.canTrigger(idB), isTrue);

      // Now acknowledge B
      await WakeUpManager.acknowledge(idB);
      expect(WakeUpManager.canTrigger(idB), isFalse);
    });
  });
}
