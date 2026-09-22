import 'package:flutter/material.dart';
import 'core/services/api_service.dart';
import 'core/services/location_service.dart';
import 'core/services/notification_service.dart';
import 'features/auth/screens/login_screen.dart';
import 'features/splash/screens/splash_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Firebase and Push Notification handlers
  await NotificationService.initialize();

  // Setup global session expiration callback (401)
  ApiService.onSessionExpired = () {
    ApiService.navigatorKey.currentState?.pushAndRemoveUntil(
      MaterialPageRoute(
        builder: (_) => const LoginScreen(
          expiredMessage: 'Your session has expired. Please sign in again.',
        ),
      ),
      (route) => false,
    );
  };

  // Stop live location telemetry at the very start of every logout,
  // before the server token is revoked or local state is cleared.
  // Uses a hook to avoid a circular import (location_service imports api_service).
  ApiService.onBeforeLogout = LocationService.stopLiveTracking;

  runApp(const Secure360App());
}

class Secure360App extends StatefulWidget {
  const Secure360App({super.key});

  @override
  State<Secure360App> createState() => _Secure360AppState();
}

class _Secure360AppState extends State<Secure360App> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      NotificationService.checkInitialMessage();
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Secure360 Guard Mobile',
      debugShowCheckedModeBanner: false,
      navigatorKey: ApiService.navigatorKey,
      theme: ThemeData(
        fontFamily: 'Roboto',
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF2563EB),
          primary: const Color(0xFF2563EB),
        ),
        scaffoldBackgroundColor: const Color(0xFFF8FAFC),
        useMaterial3: true,
      ),
      home: const SplashScreen(),
    );
  }
}
