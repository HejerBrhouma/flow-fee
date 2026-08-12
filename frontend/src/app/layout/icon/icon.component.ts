import { Component, Input } from '@angular/core';

export type IconName =
  | 'dashboard'
  | 'expenses'
  | 'budget'
  | 'savings'
  | 'reports'
  | 'team'
  | 'departments'
  | 'logout'
  | 'settings'
  | 'profile'
  | 'chevron'
  | 'globe'
  | 'inbox'
  | 'alert'
  | 'sun'
  | 'moon';

@Component({
  selector: 'app-icon',
  templateUrl: './icon.component.html',
})
export class IconComponent {
  @Input() name!: IconName;
}
