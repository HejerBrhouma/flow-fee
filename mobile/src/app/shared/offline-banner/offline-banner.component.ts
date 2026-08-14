import { Component } from '@angular/core';
import { NetworkService } from '../../core/services/network.service';
import { OfflineQueueService } from '../../core/services/offline-queue.service';

@Component({
  selector: 'app-offline-banner',
  templateUrl: './offline-banner.component.html',
  styleUrls: ['./offline-banner.component.scss'],
  standalone: false,
})
export class OfflineBannerComponent {
  constructor(
    public networkService: NetworkService,
    public offlineQueue: OfflineQueueService,
  ) {}

  sync(): void {
    this.offlineQueue.processQueue();
  }
}
