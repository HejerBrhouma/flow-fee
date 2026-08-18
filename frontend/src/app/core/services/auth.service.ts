import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, map, Observable, of, switchMap, tap } from 'rxjs';
import { Router } from '@angular/router';
import { environment } from '../../../environments/environment';
import { AuthResponse, ChangePasswordPayload, LoginPayload, LoginResult, RegisterPayload, UpdateProfilePayload, User } from '../models/user.model';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly TOKEN_KEY = 'flow_fee_token';
  private readonly USER_KEY = 'flow_fee_user';

  private currentUserSubject = new BehaviorSubject<User | null>(this.getStoredUser());
  currentUser$ = this.currentUserSubject.asObservable();

  constructor(private http: HttpClient, private router: Router) {}

  get currentUser(): User | null {
    return this.currentUserSubject.value;
  }

  get token(): string | null {
    return localStorage.getItem(this.TOKEN_KEY);
  }

  get isAuthenticated(): boolean {
    return !!this.token;
  }

  register(payload: RegisterPayload): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${environment.apiUrl}/auth/register`, payload).pipe(
      tap(response => this.handleAuthSuccess(response))
    );
  }

  login(payload: LoginPayload): Observable<LoginResult> {
    // login_check (handled natively by the JWT bundle) normally only ever returns { token },
    // unlike /auth/register which returns { token, user } — so the user has to be fetched
    // separately via /auth/me instead of trusting a "user" field that never comes back.
    // For an account with 2FA enabled, TwoFactorLoginListener swaps that response for
    // { twoFactorRequired: true, challengeToken } instead — no token is issued until the
    // code is verified via verifyTwoFactor().
    return this.http.post<{ token?: string; twoFactorRequired?: boolean; challengeToken?: string }>(
      `${environment.apiUrl}/auth/login_check`, payload
    ).pipe(
      switchMap(response => {
        if (response.twoFactorRequired) {
          return of<LoginResult>({ requiresTwoFactor: true, challengeToken: response.challengeToken! });
        }

        localStorage.setItem(this.TOKEN_KEY, response.token!);
        return this.fetchCurrentUser().pipe(
          map(user => ({ requiresTwoFactor: false, user }) as LoginResult),
        );
      }),
    );
  }

  verifyTwoFactor(challengeToken: string, code: string): Observable<User> {
    return this.http.post<{ token: string }>(`${environment.apiUrl}/auth/2fa/verify`, { challengeToken, code }).pipe(
      tap(response => localStorage.setItem(this.TOKEN_KEY, response.token)),
      switchMap(() => this.fetchCurrentUser()),
    );
  }

  setupTwoFactor(): Observable<{ secret: string; provisioningUri: string }> {
    return this.http.post<{ secret: string; provisioningUri: string }>(`${environment.apiUrl}/auth/2fa/setup`, {});
  }

  enableTwoFactor(code: string): Observable<User> {
    return this.http.post<{ message: string }>(`${environment.apiUrl}/auth/2fa/enable`, { code }).pipe(
      switchMap(() => this.fetchCurrentUser()),
    );
  }

  disableTwoFactor(password: string): Observable<User> {
    return this.http.post<{ message: string }>(`${environment.apiUrl}/auth/2fa/disable`, { password }).pipe(
      switchMap(() => this.fetchCurrentUser()),
    );
  }

  loginWithGoogle(): Observable<User> {
    return this.loginWithPopup('google');
  }

  loginWithFacebook(): Observable<User> {
    return this.loginWithPopup('facebook');
  }

  /**
   * Opens the OAuth flow in a popup so window.opener stays set — the backend's callback
   * page posts the token back via window.postMessage and closes itself (see OAuthAuthenticator).
   * A full-page redirect would break this, since there'd be no opener to post the message to.
   */
  private loginWithPopup(provider: 'google' | 'facebook'): Observable<User> {
    return new Observable<User>(observer => {
      const popup = window.open(
        `${environment.apiUrl}/auth/oauth/${provider}`,
        'flow-fee-oauth',
        'width=500,height=650',
      );

      if (!popup) {
        observer.error(new Error('popup_blocked'));
        return;
      }

      const apiOrigin = new URL(environment.apiUrl).origin;

      const handleMessage = (event: MessageEvent) => {
        if (event.origin !== apiOrigin || !event.data?.token) return;

        window.removeEventListener('message', handleMessage);
        localStorage.setItem(this.TOKEN_KEY, event.data.token);

        this.fetchCurrentUser().subscribe({
          next: user => { observer.next(user); observer.complete(); },
          error: err => observer.error(err),
        });
      };

      window.addEventListener('message', handleMessage);
    });
  }

  fetchCurrentUser(): Observable<User> {
    return this.http.get<User>(`${environment.apiUrl}/auth/me`).pipe(
      tap(user => {
        this.currentUserSubject.next(user);
        localStorage.setItem(this.USER_KEY, JSON.stringify(user));
      })
    );
  }

  updateProfile(payload: UpdateProfilePayload): Observable<User> {
    return this.http.patch<User>(`${environment.apiUrl}/auth/me`, payload).pipe(
      tap(user => {
        this.currentUserSubject.next(user);
        localStorage.setItem(this.USER_KEY, JSON.stringify(user));
      })
    );
  }

  uploadAvatar(file: File): Observable<User> {
    const formData = new FormData();
    formData.append('avatar', file);

    return this.http.post<User>(`${environment.apiUrl}/auth/me/avatar`, formData).pipe(
      tap(user => {
        this.currentUserSubject.next(user);
        localStorage.setItem(this.USER_KEY, JSON.stringify(user));
      })
    );
  }

  deleteAvatar(): Observable<User> {
    return this.http.delete<User>(`${environment.apiUrl}/auth/me/avatar`).pipe(
      tap(user => {
        this.currentUserSubject.next(user);
        localStorage.setItem(this.USER_KEY, JSON.stringify(user));
      })
    );
  }

  changePassword(payload: ChangePasswordPayload): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${environment.apiUrl}/auth/me/password`, payload);
  }

  forgotPassword(email: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${environment.apiUrl}/auth/forgot-password`, { email });
  }

  resetPassword(token: string, newPassword: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${environment.apiUrl}/auth/reset-password`, { token, newPassword });
  }

  exportData(): Observable<Blob> {
    return this.http.get(`${environment.apiUrl}/auth/me/export`, { responseType: 'blob' });
  }

  deleteAccount(password: string): Observable<void> {
    return this.http.delete<void>(`${environment.apiUrl}/auth/me`, { body: { password } });
  }

  logout(): void {
    localStorage.removeItem(this.TOKEN_KEY);
    localStorage.removeItem(this.USER_KEY);
    this.currentUserSubject.next(null);
    this.router.navigate(['/auth/login']);
  }

  hasRole(role: string): boolean {
    return this.currentUser?.roles?.includes(role) ?? false;
  }

  isProfessional(): boolean {
    return this.currentUser?.type === 'professional';
  }

  private handleAuthSuccess(response: AuthResponse): void {
    localStorage.setItem(this.TOKEN_KEY, response.token);
    localStorage.setItem(this.USER_KEY, JSON.stringify(response.user));
    this.currentUserSubject.next(response.user);
  }

  private getStoredUser(): User | null {
    const stored = localStorage.getItem(this.USER_KEY);
    if (!stored) return null;

    try {
      return JSON.parse(stored);
    } catch {
      localStorage.removeItem(this.USER_KEY);
      return null;
    }
  }
}
