import 'package:flutter/material.dart';
import 'core/services/api_service.dart';
import 'features/auth/screens/login_screen.dart';
import 'features/splash/screens/splash_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

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

  runApp(const Secure360App());
}

class Secure360App extends StatelessWidget {
  const Secure360App({super.key});

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
