import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { CompanyService } from '../../../core/services/company.service';
import { AuthService } from '../../../core/services/auth.service';
import { UserCompany } from '../../../core/models/company.model';

@Component({
  selector: 'app-team',
  templateUrl: './team.component.html',
})
export class TeamComponent implements OnInit {
  companyId!: number;
  members: UserCompany[] = [];
  loading = true;
  error = false;
  showInviteForm = false;

  inviteForm: FormGroup;

  readonly roleLabels: Record<string, string> = {
    ROLE_COMPANY_ADMIN: 'Administrateur',
    ROLE_MANAGER: 'Manager',
    ROLE_EMPLOYEE: 'Employé',
  };

  constructor(
    private route: ActivatedRoute,
    private companyService: CompanyService,
    private fb: FormBuilder,
    private toastr: ToastrService,
    public authService: AuthService,
  ) {
    this.inviteForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      role: ['ROLE_EMPLOYEE', Validators.required],
    });
  }

  ngOnInit(): void {
    this.companyId = +this.route.snapshot.params['id'];
    this.loadTeam();
  }

  loadTeam(): void {
    this.loading = true;
    this.error = false;
    this.companyService.getTeam(this.companyId).subscribe({
      next: (members) => { this.members = members; this.loading = false; },
      error: () => { this.loading = false; this.error = true; },
    });
  }

  invite(): void {
    if (this.inviteForm.invalid) return;

    this.companyService.invite(this.companyId, this.inviteForm.value).subscribe({
      next: (member) => {
        this.members.push(member);
        this.inviteForm.reset({ role: 'ROLE_EMPLOYEE' });
        this.showInviteForm = false;
        this.toastr.success('Membre ajouté à l\'équipe.');
      },
      error: (err) => this.toastr.error(err.error?.message ?? 'Impossible d\'inviter cet utilisateur.'),
    });
  }

  removeMember(member: UserCompany): void {
    if (!confirm(`Retirer ${member.user.fullName} de l'équipe ?`)) return;

    this.companyService.removeMember(this.companyId, member.id).subscribe({
      next: () => {
        this.members = this.members.filter(m => m.id !== member.id);
        this.toastr.success('Membre retiré de l\'équipe.');
      },
      error: (err) => this.toastr.error(err.error?.message ?? 'Impossible de retirer ce membre.'),
    });
  }
}
