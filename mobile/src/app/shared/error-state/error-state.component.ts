import { Component, EventEmitter, Input, Output } from '@angular/core';

@Component({
  selector: 'app-error-state',
  templateUrl: './error-state.component.html',
  standalone: false,
})
export class ErrorStateComponent {
  @Input() message = 'Une erreur est survenue lors du chargement.';
  @Output() retry = new EventEmitter<void>();
}
