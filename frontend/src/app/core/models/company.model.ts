import { User } from './user.model';

export interface Company {
  id: number;
  name: string;
  siret?: string;
  address?: string;
  city?: string;
  zipCode?: string;
  country?: string;
  logo?: string;
  departments: Department[];
  createdAt: string;
}

export interface Department {
  id: number;
  name: string;
  description?: string;
  monthlyBudget?: string;
  yearlyBudget?: string;
  company?: Company;
}

export interface UserCompany {
  id: number;
  user: User;
  company: Company;
  department?: Department;
  role: 'ROLE_COMPANY_ADMIN' | 'ROLE_MANAGER' | 'ROLE_EMPLOYEE';
  joinedAt: string;
}

export interface Budget {
  id: number;
  department: Department;
  amount: string;
  currency: string;
  period: 'monthly' | 'yearly';
  year: number;
  month?: number;
}

export interface DashboardStats {
  monthlyTotal: number;
  yearlyTotal: number;
  pendingCount: number;
  monthlyByCategory: { name: string; color: string; icon: string; total: number }[];
  monthlyTrend: { month: number; total: number }[];
}
