import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, from, map, of, switchMap } from 'rxjs';
import { Router } from '@angular/router';
import { Preferences } from '@capacitor/preferences';
import { Browser } from '@capacitor/browser';
import { environment } from '../../../environments/environment';
import { AuthResponse, ChangePasswordPayload, LoginPayload, LoginResult, RegisterPayload, UpdateProfilePayload, User } from '../models/user.model';

const TOKEN_KEY = 'flow_fee_token';
const USER_KEY = 'flow_fee_user';

@Injectable({ providedIn: 'root' })
export class AuthService {
  // Preferences is async (native secure storage), so the token/user are also kept
  // in-memory here — populated once at startup via init() — so guards and the HTTP
  // interceptor can read them synchronously like on the web app.
  private currentUserSubject = new BehaviorSubject<User | null>(null);
  currentUser$ = this.currentUserSubject.asObservable();

  private tokenSubject = new BehaviorSubject<string | null>(null);

  constructor(private http: HttpClient, private router: Router) {}

  get currentUser(): User | null {
    return this.currentUserSubject.value;
  }

  get token(): string | null {
    return this.tokenSubject.value;
  }

  get isAuthenticated(): boolean {
    return !!this.token;
  }

  /** Loads the persisted session into memory. Must resolve before the router activates any guard. */
  async init(): Promise<void> {
    const [{ value: token }, { value: storedUser }] = await Promise.all([
      Preferences.get({ key: TOKEN_KEY }),
      Preferences.get({ key: USER_KEY }),
    ]);

    this.tokenSubject.next(token);

    if (!storedUser) return;

    try {
      this.currentUserSubject.next(JSON.parse(storedUser));
    } catch {
      await Preferences.remove({ key: USER_KEY });
    }
  }

  register(payload: RegisterPayload): Observable<User> {
    return this.http.post<AuthResponse>(`${environment.apiUrl}/auth/register`, payload).pipe(
      switchMap(response => this.persistSession(response.token, response.user)),
    );
  }

  login(payload: LoginPayload): Observable<LoginResult> {
    // login_check (handled natively by the JWT bundle) normally only ever returns { token },
    // unlike /auth/register which returns { token, user } — so the user has to be fetched
    // separately via /auth/me instead of trusting a "user" field that never comes back.
    // For an account with 2FA enabled, the backend swaps that response for
    // { twoFactorRequired: true, challengeToken } instead — no token is issued until the
    // code is verified via verifyTwoFactor().
    return this.http.post<{ token?: string; twoFactorRequired?: boolean; challengeToken?: string }>(
      `${environment.apiUrl}/auth/login_check`, payload
    ).pipe(
      switchMap(response => {
        if (response.twoFactorRequired) {
          return of<LoginResult>({ requiresTwoFactor: true, challengeToken: response.challengeToken! });
        }

        this.tokenSubject.next(response.token!);
        return from(Preferences.set({ key: TOKEN_KEY, value: response.token! })).pipe(
          switchMap(() => this.fetchCurrentUser()),
          map(user => ({ requiresTwoFactor: false, user }) as LoginResult),
        );
      }),
    );
  }

  verifyTwoFactor(challengeToken: string, code: string): Observable<User> {
    return this.http.post<{ token: string }>(`${environment.apiUrl}/auth/2fa/verify`, { challengeToken, code }).pipe(
      switchMap(response => {
        this.tokenSubject.next(response.token);
        return from(Preferences.set({ key: TOKEN_KEY, value: response.token }));
      }),
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

  /**
   * Opens the OAuth flow in the system browser (SFSafariViewController / Chrome Custom
   * Tabs), which Google/Facebook allow unlike an embedded webview. The backend's mobile
   * callback route redirects to a custom URL scheme that AppComponent catches via
   * App.addListener('appUrlOpen', ...) and resolves with loginWithToken().
   */
  loginWithGoogle(): Promise<void> {
    return Browser.open({ url: `${environment.apiUrl}/auth/oauth/mobile/google` });
  }

  loginWithFacebook(): Promise<void> {
    return Browser.open({ url: `${environment.apiUrl}/auth/oauth/mobile/facebook` });
  }

  /** Completes the OAuth flow once AppComponent extracts the token from the deep link. */
  async loginWithToken(token: string): Promise<void> {
    this.tokenSubject.next(token);
    await Preferences.set({ key: TOKEN_KEY, value: token });
    await new Promise<void>((resolve, reject) => {
      this.fetchCurrentUser().subscribe({ next: () => resolve(), error: reject });
    });
  }

  fetchCurrentUser(): Observable<User> {
    return this.http.get<User>(`${environment.apiUrl}/auth/me`).pipe(
      switchMap(user => this.persistUser(user)),
    );
  }

  updateProfile(payload: UpdateProfilePayload): Observable<User> {
    return this.http.patch<User>(`${environment.apiUrl}/auth/me`, payload).pipe(
      switchMap(user => this.persistUser(user)),
    );
  }

  uploadAvatar(file: File): Observable<User> {
    const formData = new FormData();
    formData.append('avatar', file);

    return this.http.post<User>(`${environment.apiUrl}/auth/me/avatar`, formData).pipe(
      switchMap(user => this.persistUser(user)),
    );
  }

  deleteAvatar(): Observable<User> {
    return this.http.delete<User>(`${environment.apiUrl}/auth/me/avatar`).pipe(
      switchMap(user => this.persistUser(user)),
    );
  }

  changePassword(payload: ChangePasswordPayload): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${environment.apiUrl}/auth/me/password`, payload);
  }

  // The reset link itself opens in the phone's browser (points at the web app's
  // /auth/reset-password, which needs no native counterpart) — no token ever needs to flow
  // back into the mobile app, so there's nothing else to wire up here.
  forgotPassword(email: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${environment.apiUrl}/auth/forgot-password`, { email });
  }

  exportData(): Observable<string> {
    // Returned as text (not blob) — on mobile the JSON is opened via the in-app Browser as a
    // data: URL rather than triggering an <a download> click, which native webviews don't
    // reliably honor without the Filesystem/Share plugins.
    return this.http.get(`${environment.apiUrl}/auth/me/export`, { responseType: 'text' });
  }

  deleteAccount(password: string): Observable<void> {
    return this.http.delete<void>(`${environment.apiUrl}/auth/me`, { body: { password } });
  }

  logout(): void {
    this.tokenSubject.next(null);
    this.currentUserSubject.next(null);
    Preferences.remove({ key: TOKEN_KEY });
    Preferences.remove({ key: USER_KEY });
    this.router.navigate(['/auth/login']);
  }

  hasRole(role: string): boolean {
    return this.currentUser?.roles?.includes(role) ?? false;
  }

  isProfessional(): boolean {
    return this.currentUser?.type === 'professional';
  }

  private persistSession(token: string, user: User): Observable<User> {
    this.tokenSubject.next(token);
    return from(Preferences.set({ key: TOKEN_KEY, value: token })).pipe(
      switchMap(() => this.persistUser(user)),
    );
  }

  private persistUser(user: User): Observable<User> {
    this.currentUserSubject.next(user);
    return from(Preferences.set({ key: USER_KEY, value: JSON.stringify(user) })).pipe(
      map(() => user),
    );
  }
}
