import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { SavingsGoalService } from '../../core/services/savings-goal.service';
import { SavingsGoal } from '../../core/models/savings-goal.model';

@Component({
  selector: 'app-savings-goals',
  templateUrl: './savings-goals.component.html',
})
export class SavingsGoalsComponent implements OnInit {
  goals: SavingsGoal[] = [];
  loading = true;
  error = false;
  showForm = false;

  contributingId: number | null = null;
  contributionAmount: number | null = null;

  form: FormGroup;

  constructor(
    private savingsGoalService: SavingsGoalService,
    private fb: FormBuilder,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      name: ['', Validators.required],
      targetAmount: [null, [Validators.required, Validators.min(1)]],
      targetDate: [''],
      term: ['short', Validators.required],
    });
  }

  readonly termLabels: Record<string, string> = {
    short: 'Court terme',
    long: 'Long terme',
  };

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    this.error = false;
    this.savingsGoalService.getAll().subscribe({
      next: (goals) => { this.goals = goals; this.loading = false; },
      error: () => { this.loading = false; this.error = true; },
    });
  }

  create(): void {
    if (this.form.invalid) return;

    const { name, targetAmount, targetDate, term } = this.form.value;
    this.savingsGoalService.create({ name, targetAmount, targetDate: targetDate || undefined, term }).subscribe({
      next: (goal) => {
        this.goals.unshift(goal);
        this.form.reset();
        this.showForm = false;
        this.toastr.success('Objectif créé.');
      },
      error: () => this.toastr.error('Impossible de créer cet objectif.'),
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
      next: (updated) => {
        const index = this.goals.findIndex(g => g.id === updated.id);
        if (index !== -1) this.goals[index] = updated;

        this.contributingId = null;

        const isReached = parseFloat(updated.currentAmount) >= parseFloat(updated.targetAmount);
        if (isReached && !wasReached) {
          this.toastr.success(`Objectif "${updated.name}" atteint !`, undefined, { timeOut: 5000 });
        } else {
          this.toastr.success('Contribution ajoutée.');
        }
      },
      error: () => this.toastr.error('Impossible d\'ajouter cette contribution.'),
    });
  }

  delete(goal: SavingsGoal): void {
    if (!confirm(`Supprimer l'objectif "${goal.name}" ?`)) return;

    this.savingsGoalService.delete(goal.id).subscribe({
      next: () => {
        this.goals = this.goals.filter(g => g.id !== goal.id);
        this.toastr.success('Objectif supprimé.');
      },
      error: () => this.toastr.error('Impossible de supprimer cet objectif.'),
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
