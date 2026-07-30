import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Budget, BudgetConsumption, BudgetCreatePayload } from '../models/company.model';

@Injectable({ providedIn: 'root' })
export class BudgetService {
  private readonly baseUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getMyBudgets(): Observable<Budget[]> {
    return this.http.get<Budget[]>(`${this.baseUrl}/me/budgets`);
  }

  createMyBudget(payload: BudgetCreatePayload): Observable<Budget> {
    return this.http.post<Budget>(`${this.baseUrl}/me/budgets`, payload);
  }

  getDepartmentBudgets(departmentId: number): Observable<Budget[]> {
    return this.http.get<Budget[]>(`${this.baseUrl}/departments/${departmentId}/budgets`);
  }

  createDepartmentBudget(departmentId: number, payload: BudgetCreatePayload): Observable<Budget> {
    return this.http.post<Budget>(`${this.baseUrl}/departments/${departmentId}/budgets`, payload);
  }

  getConsumption(budgetId: number): Observable<BudgetConsumption> {
    return this.http.get<BudgetConsumption>(`${this.baseUrl}/budgets/${budgetId}/consumption`);
  }

  delete(budgetId: number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/budgets/${budgetId}`);
  }
}
