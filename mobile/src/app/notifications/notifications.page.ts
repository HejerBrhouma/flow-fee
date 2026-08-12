import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/fr';
import { NotificationService } from '../core/services/notification.service';
import { Notification, NotificationType } from '../core/models/notification.model';

dayjs.extend(relativeTime);
dayjs.locale('fr');

@Component({
  selector: 'app-notifications',
  templateUrl: './notifications.page.html',
  styleUrls: ['./notifications.page.scss'],
  standalone: false,
})
export class NotificationsPage implements OnInit {
  notifications: Notification[] = [];
  unreadCount = 0;
  loading = true;
  error = false;

  readonly icons: Record<NotificationType, string> = {
    expense_submitted: 'send-outline',
    expense_approved: 'checkmark-circle-outline',
    expense_rejected: 'close-circle-outline',
    budget_alert: 'warning-outline',
    team_invite: 'people-outline',
    savings_goal_reached: 'trophy-outline',
  };

  readonly colors: Record<NotificationType, string> = {
    expense_submitted: 'primary',
    expense_approved: 'success',
    expense_rejected: 'danger',
    budget_alert: 'warning',
    team_invite: 'primary',
    savings_goal_reached: 'warning',
  };

  constructor(
    private notificationService: NotificationService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.load();
  }

  ionViewWillEnter(): void {
    this.load();
  }

  load(event?: CustomEvent): void {
    this.loading = !event;
    this.error = false;
    this.notificationService.getAll().subscribe({
      next: (res) => {
        this.notifications = res.items;
        this.unreadCount = res.unreadCount;
        this.loading = false;
        (event?.target as HTMLIonRefresherElement | undefined)?.complete();
      },
      error: () => {
        this.loading = false;
        if (!event) this.error = true;
        (event?.target as HTMLIonRefresherElement | undefined)?.complete();
      },
    });
  }

  select(notification: Notification): void {
    if (!notification.isRead) {
      this.notificationService.markRead(notification.id).subscribe(() => {
        notification.isRead = true;
        this.unreadCount = Math.max(0, this.unreadCount - 1);
      });
    }

    const data = notification.data as { expenseId?: number } | undefined;
    if (data?.expenseId) {
      this.router.navigate(['/tabs/expenses', data.expenseId]);
    }
  }

  markAllRead(): void {
    if (this.unreadCount === 0) return;

    this.notificationService.markAllRead().subscribe(() => {
      this.notifications.forEach(n => (n.isRead = true));
      this.unreadCount = 0;
    });
  }

  timeAgo(dateStr: string): string {
    return dayjs(dateStr).fromNow();
  }
}
