import { Component, NgZone } from '@angular/core';
import { Router } from '@angular/router';
import { App, URLOpenListenerEvent } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { AuthService } from './core/services/auth.service';

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
    private router: Router,
    private zone: NgZone,
  ) {
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
