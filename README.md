# PGA Open

**Plateforme de Gestion des Agents de Sante Communautaire** — Multi-pays, multi-tenant.

Une plateforme open source pour la gestion des agents de sante communautaire (ASC/ASBC/Relais) deployable dans plusieurs pays d'Afrique.

## Fonctionnalites

- Gestion complete des agents (CRUD, validation, desactivation)
- Suivi de la fonctionnalite mensuelle (4 criteres)
- Import et reconciliation des paiements mobile money
- Tableau de bord avec indicateurs en temps reel
- Systeme d'alertes (documents d'identite, paiements)
- Export CSV et PDF
- **Multi-tenant** : 1 base de donnees par pays
- **Hierarchie geographique dynamique** (3 a 8 niveaux)
- Configuration complete par pays (agent, ID, devise, operateur mobile)
- Analyse par IA

## Architecture

```
Tenant Resolution (X-Tenant header / subdomain)
        |
   TenantResolver Middleware
        |
   DB Switch + Config Merge
        |
   Laravel 11 API (Sanctum)
        |
   PostgreSQL (1 DB / pays)
```

### Hierarchie geographique dynamique

Chaque pays definit ses propres niveaux :

| Pays | Niveaux |
|------|---------|
| Burkina Faso | Region > Province > District > Commune > CSPS > Village |
| Senegal | Region > District > Poste de Sante > Village |
| Mali | Region > Cercle > Centre de Sante > Village |

2 tables (`geo_levels` + `geo_units`) remplacent les N tables fixes.

## Installation

```bash
# Cloner le projet
git clone https://github.com/your-org/PGA-Open.git
cd PGA-Open

# Installer les dependances
composer install

# Configurer l'environnement
cp .env.example .env
# Editer .env : DB_DATABASE=pga_central, DB_USERNAME, DB_PASSWORD

# Migrer la base centrale
php artisan migrate --path=database/migrations/central

# Creer un tenant
php artisan tenant:create burkina-faso "Burkina Faso" --create-db

# Migrer et peupler le tenant
php artisan tenant:migrate burkina-faso --seed
```

## Configuration par pays

Chaque tenant surcharge `config/pga.php` via sa colonne `config` JSON :

```json
{
  "agent": { "type_name": "ASC", "code_prefix": "ASC" },
  "identity_document": { "name": "CNI", "length_min": 13, "length_max": 13 },
  "payment": { "provider_name": "Wave" },
  "country": { "name": "Senegal", "currency": "FCFA" }
}
```

## Stack technique

- **Backend** : Laravel 11, PHP 8.2+, PostgreSQL 16
- **Auth** : Laravel Sanctum (token API)
- **Frontend** : HTML/JS statique (pas de build step)
- **PDF** : jsPDF + AutoTable (client-side)

## Licence

Ce projet est sous licence **AGPL-3.0** — voir [LICENSE](LICENSE).

Les modules premium dans `modules/` ont leur propre licence commerciale.

---

*Developpe par Dr Mahamadi Tassembedo*
