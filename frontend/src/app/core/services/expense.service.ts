import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import {
  Expense,
  ExpenseCreatePayload,
  ExpenseFilters,
  ExpenseListResponse,
  ExpenseReviewPayload
} from '../models/expense.model';

@Injectable({ providedIn: 'root' })
export class ExpenseService {
  private readonly url = `${environment.apiUrl}/expenses`;

  constructor(private http: HttpClient) {}

  getAll(filters: ExpenseFilters = {}): Observable<ExpenseListResponse> {
    let params = new HttpParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        params = params.set(key, String(value));
      }
    });
    return this.http.get<ExpenseListResponse>(this.url, { params });
  }

  getById(id: number): Observable<Expense> {
    return this.http.get<Expense>(`${this.url}/${id}`);
  }

  create(payload: ExpenseCreatePayload): Observable<Expense> {
    return this.http.post<Expense>(this.url, payload);
  }

  update(id: number, payload: Partial<ExpenseCreatePayload>): Observable<Expense> {
    return this.http.patch<Expense>(`${this.url}/${id}`, payload);
  }

  submit(id: number): Observable<Expense> {
    return this.http.post<Expense>(`${this.url}/${id}/submit`, {});
  }

  review(id: number, payload: ExpenseReviewPayload): Observable<Expense> {
    return this.http.post<Expense>(`${this.url}/${id}/review`, payload);
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.url}/${id}`);
  }

  uploadReceipt(expenseId: number, file: File): Observable<Expense> {
    const formData = new FormData();
    formData.append('receipt', file);
    return this.http.post<Expense>(`${this.url}/${expenseId}/receipts`, formData);
  }
}
