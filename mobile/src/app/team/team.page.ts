import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AlertController, ToastController } from '@ionic/angular';
import { CompanyService } from '../core/services/company.service';
import { AuthService } from '../core/services/auth.service';
import { UserCompany } from '../core/models/company.model';

@Component({
  selector: 'app-team',
  templateUrl: './team.page.html',
  styleUrls: ['./team.page.scss'],
  standalone: false,
})
export class TeamPage implements OnInit {
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
    private toastController: ToastController,
    private alertController: AlertController,
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
      next: async (member) => {
        this.members.push(member);
        this.inviteForm.reset({ role: 'ROLE_EMPLOYEE' });
        this.showInviteForm = false;
        (await this.toastController.create({
          message: "Membre ajouté à l'équipe.", duration: 2000, color: 'success', position: 'top',
        })).present();
      },
      error: async (err) => {
        (await this.toastController.create({
          message: err.error?.message ?? "Impossible d'inviter cet utilisateur.", duration: 3000, color: 'danger', position: 'top',
        })).present();
      },
    });
  }

  async confirmRemove(member: UserCompany): Promise<void> {
    const alert = await this.alertController.create({
      header: `Retirer ${member.user.fullName} de l'équipe ?`,
      buttons: [
        { text: 'Annuler', role: 'cancel' },
        { text: 'Retirer', role: 'destructive', handler: () => this.removeMember(member) },
      ],
    });
    await alert.present();
  }

  private removeMember(member: UserCompany): void {
    this.companyService.removeMember(this.companyId, member.id).subscribe({
      next: async () => {
        this.members = this.members.filter(m => m.id !== member.id);
        (await this.toastController.create({
          message: "Membre retiré de l'équipe.", duration: 2000, color: 'success', position: 'top',
        })).present();
      },
      error: async (err) => {
        (await this.toastController.create({
          message: err.error?.message ?? 'Impossible de retirer ce membre.', duration: 3000, color: 'danger', position: 'top',
        })).present();
      },
    });
  }
}
