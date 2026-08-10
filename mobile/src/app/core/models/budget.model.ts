export type BudgetPeriod = 'monthly' | 'yearly';

export interface Budget {
  id: number;
  amount: string;
  currency: string;
  period: BudgetPeriod;
  year: number;
  month?: number | null;
}

export interface BudgetCreatePayload {
  amount: number;
  currency?: string;
  period: BudgetPeriod;
  year: number;
  month?: number;
}

export interface BudgetConsumption {
  budget: Budget;
  spent: number;
  remaining: number;
  percentage: number;
}
