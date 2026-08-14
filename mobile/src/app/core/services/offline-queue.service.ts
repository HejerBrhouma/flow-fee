import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import { Preferences } from '@capacitor/preferences';
import { environment } from '../../../environments/environment';
import { ExpenseCreatePayload } from '../models/expense.model';
import { NetworkService } from './network.service';

const QUEUE_KEY = 'flow_fee_offline_expense_queue';

export interface PendingExpense {
  localId: string;
  payload: ExpenseCreatePayload;
  createdAt: string;
}

/**
 * Expense creation only — the one offline action explicitly asked for ("saisir une dépense
 * sans réseau"). Queued payloads are persisted via Preferences so they survive an app kill,
 * and get replayed in order as soon as NetworkService reports a reconnect. Deliberately kept
 * independent of ExpenseService (calls the API directly) to avoid a circular dependency —
 * the expense-form page decides whether to queue or call ExpenseService.create() directly.
 */
@Injectable({ providedIn: 'root' })
export class OfflineQueueService {
  private pendingSubject = new BehaviorSubject<PendingExpense[]>([]);
  pending$ = this.pendingSubject.asObservable();

  private syncing = false;

  constructor(
    private http: HttpClient,
    private networkService: NetworkService,
  ) {
    this.load();

    this.networkService.online$.subscribe(online => {
      if (online) this.processQueue();
    });
  }

  get pendingCount(): number {
    return this.pendingSubject.value.length;
  }

  async enqueue(payload: ExpenseCreatePayload): Promise<void> {
    const item: PendingExpense = {
      localId: `local_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
      payload,
      createdAt: new Date().toISOString(),
    };

    const queue = [...this.pendingSubject.value, item];
    await this.persist(queue);
  }

  async processQueue(): Promise<void> {
    if (this.syncing) return;
    this.syncing = true;

    try {
      // Process oldest-first, one at a time — stop at the first failure (still offline, or a
      // real server error) rather than reordering or dropping entries.
      while (this.pendingSubject.value.length > 0 && this.networkService.isOnline) {
        const [next, ...rest] = this.pendingSubject.value;

        try {
          await firstValueFrom(this.http.post(`${environment.apiUrl}/expenses`, next.payload));
          await this.persist(rest);
        } catch {
          break;
        }
      }
    } finally {
      this.syncing = false;
    }
  }

  private async load(): Promise<void> {
    const { value } = await Preferences.get({ key: QUEUE_KEY });
    if (!value) return;

    try {
      this.pendingSubject.next(JSON.parse(value));
    } catch {
      await Preferences.remove({ key: QUEUE_KEY });
    }
  }

  private async persist(queue: PendingExpense[]): Promise<void> {
    this.pendingSubject.next(queue);
    await Preferences.set({ key: QUEUE_KEY, value: JSON.stringify(queue) });
  }
}
