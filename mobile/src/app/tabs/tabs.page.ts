import { Component, OnInit } from '@angular/core';
import { interval } from 'rxjs';
import { NotificationService } from '../core/services/notification.service';

const POLL_INTERVAL_MS = 3_000;

@Component({
  selector: 'app-tabs',
  templateUrl: 'tabs.page.html',
  styleUrls: ['tabs.page.scss'],
  standalone: false,
})
export class TabsPage implements OnInit {
  unreadCount = 0;

  constructor(private notificationService: NotificationService) {}

  ngOnInit(): void {
    this.loadUnreadCount();
    interval(POLL_INTERVAL_MS).subscribe(() => this.loadUnreadCount());
  }

  private loadUnreadCount(): void {
    this.notificationService.getAll().subscribe({
      next: (res) => { this.unreadCount = res.unreadCount; },
      error: () => {},
    });
  }
}
