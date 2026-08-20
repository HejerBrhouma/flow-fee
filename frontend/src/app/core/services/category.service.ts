import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Category, CategoryPayload } from '../models/category.model';

@Injectable({ providedIn: 'root' })
export class CategoryService {
  private readonly url = `${environment.apiUrl}/categories`;

  constructor(private http: HttpClient) {}

  getAll(): Observable<Category[]> {
    return this.http.get<Category[]>(this.url);
  }

  create(payload: CategoryPayload): Observable<Category> {
    return this.http.post<Category>(this.url, payload);
  }

  update(id: number, payload: Partial<CategoryPayload>): Observable<Category> {
    return this.http.patch<Category>(`${this.url}/${id}`, payload);
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.url}/${id}`);
  }
}
