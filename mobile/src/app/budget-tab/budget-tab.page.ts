import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AlertController, ToastController } from '@ionic/angular';
import { forkJoin, of } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { BudgetService } from '../core/services/budget.service';
import { BudgetConsumption } from '../core/models/budget.model';
import { SavingsGoalService } from '../core/services/savings-goal.service';
import { SavingsGoal } from '../core/models/savings-goal.model';

@Component({
  selector: 'app-budget-tab',
  templateUrl: './budget-tab.page.html',
  styleUrls: ['./budget-tab.page.scss'],
  standalone: false,
})
export class BudgetTabPage implements OnInit {
  segment: 'budget' | 'savings' = 'budget';
  showForm = false;

  // --- Budget ---
  budgets: BudgetConsumption[] = [];
  budgetsLoading = true;
  budgetsError = false;

  readonly months = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
  ];

  budgetForm: FormGroup;

  // --- Savings goals ---
  goals: SavingsGoal[] = [];
  goalsLoading = true;
  goalsError = false;
  goalForm: FormGroup;
  contributingId: number | null = null;
  contributionAmount: number | null = null;

  readonly termLabels: Record<string, string> = {
    short: 'Court terme',
    long: 'Long terme',
  };

  constructor(
    private budgetService: BudgetService,
    private savingsGoalService: SavingsGoalService,
    private fb: FormBuilder,
    private toastController: ToastController,
    private alertController: AlertController,
  ) {
    const now = new Date();
    this.budgetForm = this.fb.group({
      period: ['monthly', Validators.required],
      year: [now.getFullYear(), Validators.required],
      month: [now.getMonth() + 1],
      amount: [null, [Validators.required, Validators.min(1)]],
    });

    this.goalForm = this.fb.group({
      name: ['', Validators.required],
      targetAmount: [null, [Validators.required, Validators.min(1)]],
      targetDate: [''],
      term: ['short', Validators.required],
    });
  }

  ngOnInit(): void {
    this.loadBudgets();
    this.loadGoals();
  }

  ionViewWillEnter(): void {
    this.loadBudgets();
    this.loadGoals();
  }

  changeSegment(): void {
    this.showForm = false;
  }

  // --- Budget logic ---

  loadBudgets(event?: CustomEvent): void {
    this.budgetsLoading = !event;
    this.budgetsError = false;

    this.budgetService.getMyBudgets().subscribe({
      next: (budgets) => {
        if (budgets.length === 0) {
          this.budgets = [];
          this.budgetsLoading = false;
          (event?.target as HTMLIonRefresherElement | undefined)?.complete();
          return;
        }

        forkJoin(
          budgets.map(b => this.budgetService.getConsumption(b.id).pipe(catchError(() => of(null))))
        ).subscribe(results => {
          this.budgets = results.filter((r): r is BudgetConsumption => r !== null);
          this.budgetsLoading = false;
          (event?.target as HTMLIonRefresherElement | undefined)?.complete();
        });
      },
      error: () => {
        this.budgetsLoading = false;
        if (!event) this.budgetsError = true;
        (event?.target as HTMLIonRefresherElement | undefined)?.complete();
      },
    });
  }

  createBudget(): void {
    if (this.budgetForm.invalid) return;

    const { period, year, month, amount } = this.budgetForm.value;
    this.budgetService.createMyBudget({
      period,
      year,
      month: period === 'monthly' ? month : undefined,
      amount,
    }).subscribe({
      next: async () => {
        this.showForm = false;
        this.budgetForm.reset({ period: 'monthly', year: new Date().getFullYear(), month: new Date().getMonth() + 1 });
        (await this.toastController.create({
          message: 'Budget créé.', duration: 2000, color: 'success', position: 'top',
        })).present();
        this.loadBudgets();
      },
      error: async () => {
        (await this.toastController.create({
          message: 'Impossible de créer ce budget (existe-t-il déjà pour cette période ?).',
          duration: 3000, color: 'danger', position: 'top',
        })).present();
      },
    });
  }

  async confirmDeleteBudget(item: BudgetConsumption): Promise<void> {
    const alert = await this.alertController.create({
      header: 'Supprimer ce budget ?',
      buttons: [
        { text: 'Annuler', role: 'cancel' },
        { text: 'Supprimer', role: 'destructive', handler: () => this.deleteBudget(item) },
      ],
    });
    await alert.present();
  }

  private deleteBudget(item: BudgetConsumption): void {
    this.budgetService.delete(item.budget.id).subscribe({
      next: () => {
        this.budgets = this.budgets.filter(b => b.budget.id !== item.budget.id);
      },
    });
  }

  barColor(percentage: number): string {
    if (percentage >= 100) return 'danger';
    if (percentage >= 90) return 'warning';
    return 'primary';
  }

  periodLabel(item: BudgetConsumption): string {
    const b = item.budget;
    return b.period === 'monthly' ? `${this.months[(b.month ?? 1) - 1]} ${b.year}` : `Année ${b.year}`;
  }

  // --- Savings goals logic ---

  loadGoals(event?: CustomEvent): void {
    this.goalsLoading = !event;
    this.goalsError = false;

    this.savingsGoalService.getAll().subscribe({
      next: (goals) => {
        this.goals = goals;
        this.goalsLoading = false;
        (event?.target as HTMLIonRefresherElement | undefined)?.complete();
      },
      error: () => {
        this.goalsLoading = false;
        if (!event) this.goalsError = true;
        (event?.target as HTMLIonRefresherElement | undefined)?.complete();
      },
    });
  }

  createGoal(): void {
    if (this.goalForm.invalid) return;

    const { name, targetAmount, targetDate, term } = this.goalForm.value;
    this.savingsGoalService.create({ name, targetAmount, targetDate: targetDate || undefined, term }).subscribe({
      next: async (goal) => {
        this.goals.unshift(goal);
        this.goalForm.reset({ term: 'short' });
        this.showForm = false;
        (await this.toastController.create({
          message: 'Objectif créé.', duration: 2000, color: 'success', position: 'top',
        })).present();
      },
      error: async () => {
        (await this.toastController.create({
          message: 'Impossible de créer cet objectif.', duration: 3000, color: 'danger', position: 'top',
        })).present();
      },
    });
  }

  startContribute(goal: SavingsGoal): void {
    this.contributingId = goal.id;
    this.contributionAmount = null;
  }

  cancelContribute(): void {
    this.contributingId = null;
  }

  confirmContribute(goal: SavingsGoal): void {
    if (!this.contributionAmount || this.contributionAmount <= 0) return;

    const wasReached = parseFloat(goal.currentAmount) >= parseFloat(goal.targetAmount);

    this.savingsGoalService.contribute(goal.id, this.contributionAmount).subscribe({
      next: async (updated) => {
        const index = this.goals.findIndex(g => g.id === updated.id);
        if (index !== -1) this.goals[index] = updated;
        this.contributingId = null;

        const isReached = parseFloat(updated.currentAmount) >= parseFloat(updated.targetAmount);
        const message = isReached && !wasReached
          ? `Objectif "${updated.name}" atteint !`
          : 'Contribution ajoutée.';
        (await this.toastController.create({
          message, duration: 2500, color: 'success', position: 'top',
        })).present();
      },
      error: async () => {
        (await this.toastController.create({
          message: 'Impossible d\'ajouter cette contribution.', duration: 3000, color: 'danger', position: 'top',
        })).present();
      },
    });
  }

  async confirmDeleteGoal(goal: SavingsGoal): Promise<void> {
    const alert = await this.alertController.create({
      header: `Supprimer l'objectif "${goal.name}" ?`,
      buttons: [
        { text: 'Annuler', role: 'cancel' },
        { text: 'Supprimer', role: 'destructive', handler: () => this.deleteGoal(goal) },
      ],
    });
    await alert.present();
  }

  private deleteGoal(goal: SavingsGoal): void {
    this.savingsGoalService.delete(goal.id).subscribe({
      next: () => {
        this.goals = this.goals.filter(g => g.id !== goal.id);
      },
    });
  }

  progress(goal: SavingsGoal): number {
    const target = parseFloat(goal.targetAmount);
    if (target <= 0) return 0;
    return Math.min(100, Math.round((parseFloat(goal.currentAmount) / target) * 100));
  }

  isReached(goal: SavingsGoal): boolean {
    return parseFloat(goal.currentAmount) >= parseFloat(goal.targetAmount);
  }
}
