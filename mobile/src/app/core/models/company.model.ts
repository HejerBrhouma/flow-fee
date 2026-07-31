// Trimmed down to what the mobile v1 scope needs (expense department display,
// dashboard stats). Extend when company management lands on mobile.

export interface Department {
  id: number;
  name: string;
  description?: string;
}

export interface DashboardStats {
  monthlyTotal: number;
  yearlyTotal: number;
  pendingCount: number;
  monthlyByCategory: { name: string | null; color: string | null; icon: string | null; total: number }[];
  monthlyTrend: { month: number; total: number }[];
}
