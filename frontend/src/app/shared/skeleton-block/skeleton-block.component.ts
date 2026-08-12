import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-skeleton-block',
  template: `<div class="animate-pulse bg-gray-200 rounded" [style.width]="width" [style.height]="height"></div>`,
})
export class SkeletonBlockComponent {
  @Input() width = '100%';
  @Input() height = '1rem';
}
