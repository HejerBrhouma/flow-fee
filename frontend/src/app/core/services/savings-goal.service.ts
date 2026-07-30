import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { SavingsGoal, SavingsGoalCreatePayload } from '../models/savings-goal.model';

@Injectable({ providedIn: 'root' })
export class SavingsGoalService {
  private readonly url = `${environment.apiUrl}/savings-goals`;

  constructor(private http: HttpClient) {}

  getAll(): Observable<SavingsGoal[]> {
    return this.http.get<SavingsGoal[]>(this.url);
  }

  create(payload: SavingsGoalCreatePayload): Observable<SavingsGoal> {
    return this.http.post<SavingsGoal>(this.url, payload);
  }

  contribute(id: number, amount: number): Observable<SavingsGoal> {
    return this.http.post<SavingsGoal>(`${this.url}/${id}/contribute`, { amount });
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.url}/${id}`);
  }
}
