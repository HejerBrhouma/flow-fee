import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ExpenseDetailPage } from './expense-detail.page';

describe('ExpenseDetailPage', () => {
  let component: ExpenseDetailPage;
  let fixture: ComponentFixture<ExpenseDetailPage>;

  beforeEach(() => {
    fixture = TestBed.createComponent(ExpenseDetailPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
