export interface SavingsGoal {
  id: number;
  name: string;
  targetAmount: string;
  currentAmount: string;
  currency: string;
  targetDate?: string | null;
  createdAt: string;
}

export interface SavingsGoalCreatePayload {
  name: string;
  targetAmount: number;
  currency?: string;
  targetDate?: string;
}
