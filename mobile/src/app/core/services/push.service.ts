import { Injectable, NgZone } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Capacitor } from '@capacitor/core';
import { ActionPerformed, PushNotifications, Token } from '@capacitor/push-notifications';
import { environment } from '../../../environments/environment';

/**
 * Registers this device for Firebase Cloud Messaging and forwards the resulting token to the
 * backend (see PushTokenController). No-ops entirely outside a native build — there is no
 * meaningful web push path here, and @capacitor/push-notifications throws if called in a
 * plain browser, so every method bails out via Capacitor.isNativePlatform() first.
 */
@Injectable({ providedIn: 'root' })
export class PushService {
  private currentToken: string | null = null;

  constructor(
    private http: HttpClient,
    private router: Router,
    private zone: NgZone,
  ) {}

  async init(): Promise<void> {
    if (!Capacitor.isNativePlatform()) return;

    let status = await PushNotifications.checkPermissions();
    if (status.receive === 'prompt') {
      status = await PushNotifications.requestPermissions();
    }
    if (status.receive !== 'granted') return;

    await PushNotifications.register();

    PushNotifications.addListener('registration', (token: Token) => {
      this.currentToken = token.value;
      this.http.post(`${environment.apiUrl}/push/register`, {
        token: token.value,
        platform: Capacitor.getPlatform(),
      }).subscribe();
    });

    PushNotifications.addListener('registrationError', (error) => {
      console.error('Push registration error', error);
    });

    // Tapping a notification while the app is backgrounded/closed — navigate straight to
    // whatever the notification was about instead of just opening the dashboard.
    PushNotifications.addListener('pushNotificationActionPerformed', (action: ActionPerformed) => {
      this.zone.run(() => this.navigateFromData(action.notification.data));
    });
  }

  async unregister(): Promise<void> {
    if (!Capacitor.isNativePlatform() || !this.currentToken) return;

    this.http.post(`${environment.apiUrl}/push/unregister`, { token: this.currentToken }).subscribe();
    this.currentToken = null;
    await PushNotifications.removeAllListeners();
  }

  private navigateFromData(data: Record<string, string> | undefined): void {
    if (data?.['expenseId']) {
      this.router.navigate(['/tabs/expenses', data['expenseId']]);
    } else if (data?.['savingsGoalId']) {
      this.router.navigate(['/tabs/budget'], { queryParams: { segment: 'savings' } });
    } else if (data?.['budgetId']) {
      this.router.navigateByUrl('/tabs/budget');
    } else {
      this.router.navigateByUrl('/tabs/notifications');
    }
  }
}
