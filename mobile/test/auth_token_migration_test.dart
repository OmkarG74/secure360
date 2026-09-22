import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
// ignore: depend_on_referenced_packages
import 'package:flutter_secure_storage_platform_interface/flutter_secure_storage_platform_interface.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:secure360_mobile/core/services/api_service.dart';

class FakeSecureStoragePlatform extends FlutterSecureStoragePlatform {
  final Map<String, String> _storage = {};
  Exception? readException;

  @override
  Future<void> write({
    required String key,
    required String value,
    required Map<String, String> options,
  }) async {
    _storage[key] = value;
  }

  @override
  Future<String?> read({
    required String key,
    required Map<String, String> options,
  }) async {
    if (readException != null) {
      throw readException!;
    }
    return _storage[key];
  }

  @override
  Future<bool> containsKey({
    required String key,
    required Map<String, String> options,
  }) async {
    return _storage.containsKey(key);
  }

  @override
  Future<void> delete({
    required String key,
    required Map<String, String> options,
  }) async {
    _storage.remove(key);
  }

  @override
  Future<void> deleteAll({
    required Map<String, String> options,
  }) async {
    _storage.clear();
  }

  @override
  Future<Map<String, String>> readAll({
    required Map<String, String> options,
  }) async {
    return Map<String, String>.from(_storage);
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  late FakeSecureStoragePlatform fakeSecure;

  setUp(() {
    fakeSecure = FakeSecureStoragePlatform();
    FlutterSecureStoragePlatform.instance = fakeSecure;
  });

  test('TEST 1: Fresh state - no token exists anywhere', () async {
    SharedPreferences.setMockInitialValues({});
    final token = await ApiService.getToken();
    expect(token, isNull);
    final user = await ApiService.getUser();
    expect(user, isNull);
  });

  test('TEST 2: saveAuthSession stores token in secure storage and user in SharedPreferences', () async {
    SharedPreferences.setMockInitialValues({});
    final userMap = {'id': 10, 'name': 'Guard John', 'role': 'guard'};
    const dummyToken = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    await ApiService.saveAuthSession(dummyToken, userMap);

    final secureToken = await fakeSecure.read(key: 'secure360_guard_token', options: {});
    expect(secureToken, dummyToken);

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('secure360_guard_token'), isNull);

    final savedUser = await ApiService.getUser();
    expect(savedUser?['name'], 'Guard John');
    expect(savedUser?['id'], 10);
  });

  test('TEST 3: getToken retrieves token directly from secure storage without touching SharedPreferences', () async {
    SharedPreferences.setMockInitialValues({});
    const dummyToken = 'secure_stored_token_12345';
    await fakeSecure.write(key: 'secure360_guard_token', value: dummyToken, options: {});

    final retrieved = await ApiService.getToken();
    expect(retrieved, dummyToken);

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('secure360_guard_token'), isNull);
  });

  test('TEST 4: clearAuthSession deletes secure token, legacy token, and user profile', () async {
    SharedPreferences.setMockInitialValues({
      'secure360_guard_token': 'legacy_plain_token',
      'secure360_guard_user': jsonEncode({'id': 5, 'name': 'Guard'}),
    });
    await fakeSecure.write(key: 'secure360_guard_token', value: 'secure_token', options: {});

    await ApiService.clearAuthSession();

    final secureToken = await fakeSecure.read(key: 'secure360_guard_token', options: {});
    expect(secureToken, isNull);

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('secure360_guard_token'), isNull);
    expect(prefs.getString('secure360_guard_user'), isNull);
    expect(await ApiService.getUser(), isNull);
  });

  test('TEST 7: Legacy migration moves legacy SharedPreferences token to secure storage and deletes plaintext key', () async {
    const legacyTokenValue = 'legacy_plaintext_token_999888';
    SharedPreferences.setMockInitialValues({
      'secure360_guard_token': legacyTokenValue,
      'secure360_guard_user': jsonEncode({'id': 12, 'name': 'Legacy Guard'}),
    });

    expect(await fakeSecure.read(key: 'secure360_guard_token', options: {}), isNull);

    final token = await ApiService.getToken();

    expect(token, legacyTokenValue);

    final secureToken = await fakeSecure.read(key: 'secure360_guard_token', options: {});
    expect(secureToken, legacyTokenValue);

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('secure360_guard_token'), isNull);

    final user = await ApiService.getUser();
    expect(user?['name'], 'Legacy Guard');
  });

  test('Concurrent migration test: multiple simultaneous getToken calls migrate safely', () async {
    const legacyTokenValue = 'concurrent_legacy_token_777';
    SharedPreferences.setMockInitialValues({
      'secure360_guard_token': legacyTokenValue,
    });

    final results = await Future.wait([
      ApiService.getToken(),
      ApiService.getToken(),
      ApiService.getToken(),
      ApiService.getToken(),
      ApiService.getToken(),
    ]);

    for (final res in results) {
      expect(res, legacyTokenValue);
    }

    final secureToken = await fakeSecure.read(key: 'secure360_guard_token', options: {});
    expect(secureToken, legacyTokenValue);

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('secure360_guard_token'), isNull);
  });

  test('TEST 8: Fail-closed on secure storage exception (returns null, does NOT migrate legacy token)', () async {
    const legacyTokenValue = 'unmigrated_legacy_plaintext_token_404';
    SharedPreferences.setMockInitialValues({
      'secure360_guard_token': legacyTokenValue,
      'secure360_guard_user': jsonEncode({'id': 15, 'name': 'FailClosed Guard'}),
    });

    // Simulate an Android Keystore / decryption exception
    fakeSecure.readException = Exception('Keystore decryption failure (AEADBadTagException)');

    // getToken() must fail closed
    final token = await ApiService.getToken();

    // 1. Must return null (user must be prompted to re-authenticate)
    expect(token, isNull);

    // 2. Legacy plaintext token must NOT be migrated or deleted
    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('secure360_guard_token'), legacyTokenValue);

    // 3. User profile remains intact
    final user = await ApiService.getUser();
    expect(user?['name'], 'FailClosed Guard');
  });
}
