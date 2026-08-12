import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-skeleton-block',
  template: `<ion-skeleton-text [animated]="true" [style.width]="width" [style.height]="height" [style.borderRadius]="radius"></ion-skeleton-text>`,
  standalone: false,
})
export class SkeletonBlockComponent {
  @Input() width = '100%';
  @Input() height = '1rem';
  @Input() radius = '6px';
}
