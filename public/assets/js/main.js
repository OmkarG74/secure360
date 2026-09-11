/**
 * Secure360 - Frontend Core Scripts
 * Handles modal toggles, alert dismissals, and basic client-side interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    // Auto-dismiss session flash alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach((alert) => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
