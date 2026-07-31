import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Notification, NotificationListResponse } from '../models/notification.model';

@Injectable({ providedIn: 'root' })
export class NotificationService {
  private readonly url = `${environment.apiUrl}/notifications`;

  constructor(private http: HttpClient) {}

  getAll(): Observable<NotificationListResponse> {
    return this.http.get<NotificationListResponse>(this.url);
  }

  markRead(id: number): Observable<Notification> {
    return this.http.post<Notification>(`${this.url}/${id}/read`, {});
  }

  markAllRead(): Observable<void> {
    return this.http.post<void>(`${this.url}/read-all`, {});
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.url}/${id}`);
  }
}
