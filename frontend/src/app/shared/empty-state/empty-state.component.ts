import { Component, Input } from '@angular/core';
import { IconName } from '../../layout/icon/icon.component';

@Component({
  selector: 'app-empty-state',
  templateUrl: './empty-state.component.html',
})
export class EmptyStateComponent {
  @Input() icon: IconName = 'inbox';
  @Input() message = '';
  @Input() ctaLabel?: string;
  @Input() ctaLink?: string;
}
