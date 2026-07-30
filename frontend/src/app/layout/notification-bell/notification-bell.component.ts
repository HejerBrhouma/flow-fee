import { Component, ElementRef, HostListener, OnDestroy, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { Subject, interval, takeUntil } from 'rxjs';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/fr';
import { NotificationService } from '../../core/services/notification.service';
import { Notification, NotificationType } from '../../core/models/notification.model';

dayjs.extend(relativeTime);
dayjs.locale('fr');

const POLL_INTERVAL_MS = 30_000;

@Component({
  selector: 'app-notification-bell',
  templateUrl: './notification-bell.component.html',
})
export class NotificationBellComponent implements OnInit, OnDestroy {
  notifications: Notification[] = [];
  unreadCount = 0;
  open = false;
  loading = false;

  readonly icons: Record<NotificationType, string> = {
    expense_submitted: '📤',
    expense_approved: '✅',
    expense_rejected: '❌',
    budget_alert: '⚠️',
    team_invite: '👥',
    savings_goal_reached: '🎯',
  };

  private destroy$ = new Subject<void>();

  constructor(
    private notificationService: NotificationService,
    private elementRef: ElementRef<HTMLElement>,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.load();

    // No push mechanism yet, so poll for new notifications in the background.
    interval(POLL_INTERVAL_MS)
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => this.load());
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent): void {
    if (this.open && !this.elementRef.nativeElement.contains(event.target as Node)) {
      this.open = false;
    }
  }

  toggle(): void {
    this.open = !this.open;
    if (this.open) {
      this.load();
    }
  }

  load(): void {
    this.loading = true;
    this.notificationService.getAll().subscribe({
      next: (res) => {
        this.notifications = res.items;
        this.unreadCount = res.unreadCount;
        this.loading = false;
      },
      error: () => { this.loading = false; },
    });
  }

  select(notification: Notification): void {
    if (!notification.isRead) {
      this.notificationService.markRead(notification.id).subscribe(() => {
        notification.isRead = true;
        this.unreadCount = Math.max(0, this.unreadCount - 1);
      });
    }

    this.open = false;
    this.navigateTo(notification);
  }

  markAllRead(event: Event): void {
    event.stopPropagation();
    if (this.unreadCount === 0) {
      return;
    }

    this.notificationService.markAllRead().subscribe(() => {
      this.notifications.forEach(n => (n.isRead = true));
      this.unreadCount = 0;
    });
  }

  timeAgo(dateStr: string): string {
    return dayjs(dateStr).fromNow();
  }

  private navigateTo(notification: Notification): void {
    const data = notification.data as { expenseId?: number } | undefined;

    if (data?.expenseId) {
      this.router.navigate(['/expenses', data.expenseId]);
    }
  }
}
