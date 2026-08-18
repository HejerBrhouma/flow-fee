import { Component, NgZone } from '@angular/core';
import { Router } from '@angular/router';
import { App, URLOpenListenerEvent } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { Capacitor } from '@capacitor/core';
import { StatusBar } from '@capacitor/status-bar';
import { AuthService } from './core/services/auth.service';
import { ThemeService } from './core/services/theme.service';
import { PushService } from './core/services/push.service';

const OAUTH_CALLBACK_PREFIX = 'com.flowfee.app://oauth-callback';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
  standalone: false,
})
export class AppComponent {
  constructor(
    private authService: AuthService,
    private themeService: ThemeService,
    private pushService: PushService,
    private router: Router,
    private zone: NgZone,
  ) {
    this.themeService.init();

    // Android 15+ (API 35+, our compileSdk/targetSdk) renders edge-to-edge by default,
    // drawing the WebView behind the status bar — without this, headers (and any buttons in
    // them) end up partly hidden under the status bar icons.
    if (Capacitor.getPlatform() === 'android') {
      StatusBar.setOverlaysWebView({ overlay: false }).catch(() => {});
    }

    // Single reactive spot for push registration — covers cold start with a persisted
    // session, login, register and OAuth alike (all of them update currentUser$), and
    // unregisters the device token the moment the user logs out.
    let wasLoggedIn = false;
    this.authService.currentUser$.subscribe(user => {
      if (user && !wasLoggedIn) {
        this.pushService.init();
      } else if (!user && wasLoggedIn) {
        this.pushService.unregister();
      }
      wasLoggedIn = !!user;
    });

    App.addListener('appUrlOpen', (event: URLOpenListenerEvent) => {
      this.zone.run(() => this.handleAppUrlOpen(event.url));
    });
  }

  private async handleAppUrlOpen(url: string): Promise<void> {
    if (!url.startsWith(OAUTH_CALLBACK_PREFIX)) return;

    await Browser.close().catch(() => {});

    const token = new URL(url).searchParams.get('token');
    if (!token) return;

    try {
      await this.authService.loginWithToken(token);
      this.router.navigateByUrl('/tabs/dashboard');
    } catch {
      this.router.navigateByUrl('/auth/login');
    }
  }
}
