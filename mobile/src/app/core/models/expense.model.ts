import { User } from './user.model';
import { Category } from './category.model';
import { Department } from './company.model';

export type ExpenseStatus = 'draft' | 'submitted' | 'approved' | 'rejected';

export interface Expense {
  id: number;
  title: string;
  amount: string;
  currency: string;
  expenseDate: string;
  description?: string;
  status: ExpenseStatus;
  user: User;
  category?: Category;
  department?: Department;
  reviewedBy?: User;
  reviewComment?: string;
  reviewedAt?: string;
  receipts: ExpenseReceipt[];
  createdAt: string;
}

export interface ExpenseReceipt {
  id: number;
  filePath: string;
  originalName?: string;
  mimeType?: string;
  fileSize?: number;
  uploadedAt: string;
}

export interface ExpenseCreatePayload {
  title: string;
  amount: number;
  currency: string;
  expenseDate: string;
  description?: string;
  categoryId?: number;
  departmentId?: number;
}

export interface ExpenseReviewPayload {
  action: 'approve' | 'reject';
  comment?: string;
}

export interface ExpenseListResponse {
  items: Expense[];
  total: number;
  page: number;
  pages: number;
}

export interface ExpenseFilters {
  status?: ExpenseStatus;
  category?: number;
  dateFrom?: string;
  dateTo?: string;
  page?: number;
  limit?: number;
}
