import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import { Network } from '@capacitor/network';

@Injectable({ providedIn: 'root' })
export class NetworkService {
  // Optimistic default: assume online until the first status check resolves, since most
  // sessions start connected and we'd rather risk one failed request than flash an
  // "offline" banner on every cold start.
  private onlineSubject = new BehaviorSubject<boolean>(true);
  online$ = this.onlineSubject.asObservable();

  constructor() {
    Network.getStatus().then(status => this.onlineSubject.next(status.connected));
    Network.addListener('networkStatusChange', status => this.onlineSubject.next(status.connected));
  }

  get isOnline(): boolean {
    return this.onlineSubject.value;
  }
}
