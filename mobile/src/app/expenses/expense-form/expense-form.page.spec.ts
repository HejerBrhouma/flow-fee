import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ExpenseFormPage } from './expense-form.page';

describe('ExpenseFormPage', () => {
  let component: ExpenseFormPage;
  let fixture: ComponentFixture<ExpenseFormPage>;

  beforeEach(() => {
    fixture = TestBed.createComponent(ExpenseFormPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
