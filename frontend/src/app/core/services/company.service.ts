import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Company, Department, UserCompany } from '../models/company.model';

@Injectable({ providedIn: 'root' })
export class CompanyService {
  private readonly url = `${environment.apiUrl}/companies`;

  constructor(private http: HttpClient) {}

  create(payload: Partial<Company>): Observable<Company> {
    return this.http.post<Company>(this.url, payload);
  }

  getMyMembership(): Observable<UserCompany | null> {
    return this.http.get<UserCompany | null>(`${this.url}/me`);
  }

  getById(id: number): Observable<Company> {
    return this.http.get<Company>(`${this.url}/${id}`);
  }

  getTeam(id: number): Observable<UserCompany[]> {
    return this.http.get<UserCompany[]>(`${this.url}/${id}/team`);
  }

  invite(companyId: number, payload: { email: string; role: string; departmentId?: number }): Observable<UserCompany> {
    return this.http.post<UserCompany>(`${this.url}/${companyId}/invite`, payload);
  }

  getDepartments(companyId: number): Observable<Department[]> {
    return this.http.get<Department[]>(`${this.url}/${companyId}/departments`);
  }

  createDepartment(companyId: number, payload: Partial<Department>): Observable<Department> {
    return this.http.post<Department>(`${this.url}/${companyId}/departments`, payload);
  }

  removeMember(companyId: number, memberId: number): Observable<void> {
    return this.http.delete<void>(`${this.url}/${companyId}/team/${memberId}`);
  }

  verifyAddress(country: string, city: string, zipCode: string): Observable<{ valid: boolean | null }> {
    return this.http.get<{ valid: boolean | null }>(`${this.url}/verify-address`, {
      params: { country, city, zipCode },
    });
  }
}
