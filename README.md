# 🌍 EcoDash v2 — Plateforme ESMS Intégrée

**Système complet de surveillance environnementale et sociale**  
Eau · Air · Sol · Environnement Social (ESMS/PGES) · Biodiversité

---

## 📊 Les 5 Modules de Surveillance

| Module | Paramètres clés | Normes |
|--------|----------------|--------|
| 💧 **EAU** | pH, turbidité, O₂ dissous, DBO5, métaux lourds (Pb, Hg, As, Cd), coliformes, E. coli | OMS eau potable |
| 🌬 **AIR** | PM2.5, PM10, CO2, NO2, SO2, O3, H2S, COV, bruit dB, météo | OMS qualité air |
| 🌱 **SOL** | pH, MO, N/P/K, texture, métaux lourds mg/kg, hydrocarbures, pesticides, érosion | Normes pédologiques |
| 👥 **SOCIAL** | Griefs/plaintes (MGG), PGES, emploi local, santé communautaire, RSE | Standards IFC, Banque Mondiale |
| 🦋 **BIODIVERSITÉ** | Flore, faune, avifaune, ichtyofaune, statuts UICN, espèces endémiques/invasives | UICN |

---

## 🏗 Architecture

```
ecodash/
├── laravel/              # Backend Laravel 10 + Dashboard Web
│   ├── app/Models/       # 10+ modèles avec calculs automatiques
│   ├── app/Http/Controllers/Api/  # 7 controllers API REST
│   ├── database/migrations/      # 6 migrations complètes
│   └── resources/views/          # Dashboard HTML interactif
│
└── flutter/              # App mobile terrain
    ├── lib/models/       # 8 modèles complets (EAU/AIR/SOL/SOCIAL/BIO)
    ├── lib/screens/      # 10+ écrans terrain
    └── lib/services/     # API service unifié
```

---

## 🚀 Installation Laravel

```bash
cd laravel/
composer install
cp .env.example .env && php artisan key:generate

# Base de données
php artisan migrate
php artisan db:seed

# Lancer
php artisan serve
```

### Variables .env importantes

```env
# App
APP_URL=http://localhost:8000
DB_DATABASE=ecodash_esms

# Seuils OMS personnalisables
PH_MIN=6.5
PH_MAX=8.5
PM25_MAX=15          # µg/m³ OMS 2021
PM10_MAX=45          # µg/m³
NO2_MAX=25           # µg/m³
PLOMB_EAU_MAX=0.01   # mg/L
PLOMB_SOL_MAX=100    # mg/kg
HTH_SOL_MAX=500      # mg/kg hydrocarbures
```

---

## 📡 API REST — Endpoints complets

### Auth
```
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/me
```

### Dashboard global
```
GET /api/dashboard     → KPIs tous modules + carte sites
```

### Module EAU
```
GET  /api/eau                        → Liste mesures
POST /api/eau                        → Nouvelle mesure (+ calcul IQE auto)
GET  /api/eau/{id}
POST /api/eau/{id}/valider
GET  /api/eau/sites/{id}/stats       → Évolution 30j
```

### Module AIR
```
GET  /api/air
POST /api/air                        → Nouvelle mesure (+ calcul IQA auto)
GET  /api/air/sites/{id}/stats
```

### Module SOL
```
GET  /api/sol
POST /api/sol                        → Analyse sol (+ calcul IQS auto)
GET  /api/sol/sites/{id}/stats
```

### Module SOCIAL
```
# Griefs / Plaintes
GET  /api/social/plaintes
POST /api/social/plaintes            → + /api/grievances (public)
PUT  /api/social/plaintes/{id}
GET  /api/social/plaintes-stats

# PGES
GET  /api/social/pges
POST /api/social/pges
PUT  /api/social/pges/{id}
GET  /api/social/pges-tableau-bord

# Indicateurs
GET/POST /api/social/sante
GET/POST /api/social/emploi
```

### Biodiversité
```
GET  /api/biodiversite
POST /api/biodiversite
GET  /api/biodiversite/sites/{id}/stats
```

---

## 📱 Application Flutter

### Fonctionnalités terrain

| Écran | Module | Fonctions |
|-------|--------|-----------|
| 🏠 Accueil | Global | KPIs 4 modules, alertes, actions rapides |
| 💧 Saisie Eau | EAU | pH, turbidité, O₂, 8 métaux lourds, bactério, photo |
| 🌬 Saisie Air | AIR | PM2.5/10, 6 gaz, bruit, météo complète, IQA auto |
| 🌱 Analyse Sol | SOL | pH, MO, N/P, 8 métaux, hydrocarbures, érosion, photos |
| 📋 Grief | SOCIAL | Formulaire complet, anonymat, catégories IFC |
| 🦋 Biodiversité | BIO | Observation espèces, statut UICN, GPS, photos |
| 🗺 Carte | Global | Tous sites colorés par module |
| 📊 PGES | SOCIAL | Suivi actions, conformité, avancement |

### Installation
```bash
cd flutter/
flutter pub get

# Configurer l'API dans lib/services/api_service.dart :
# static const String baseUrl = 'https://VOTRE-SERVEUR.com/api';

flutter run
```

---

## 📋 Standards & Conformité

Le système est aligné sur :

- **OMS** — Normes qualité eau (2017), qualité air (2021)
- **IFC Performance Standards** — PS1 (ESMS), PS2 (Emploi), PS4 (Santé), PS6 (Biodiversité)
- **Banque Mondiale OP 4.01** — Évaluation Environnementale
- **UICN** — Statuts de conservation espèces
- **ISO 14001** — Management environnemental

---

## 🗃 Modèle de données résumé

```
users          → 8 rôles (admin, ingénieur, technicien x4, agent social, observateur)
sites          → Caractéristiques, modules actifs, type (minier, agricole, fluvial...)
zones_influence→ Population, groupes vulnérables, langue

mesures_eau    → 30+ paramètres + calcul IQE automatique
mesures_air    → 15+ paramètres + calcul IQA automatique
mesures_sol    → 25+ paramètres + calcul IQS automatique

plaintes       → Mécanisme de gestion des griefs (MGG) complet
indicateurs_sante → Santé communautaire trimestrielle
indicateurs_emploi → Emploi local, RSE, projets sociaux
pges_actions   → Plan de Gestion Environnementale et Sociale
observations_biodiversite → Inventaires espèces + UICN
```

---

## 👤 Comptes de démonstration (après seeding)

| Email | Mot de passe | Rôle |
|-------|-------------|------|
| admin@ecodash.app | password | Administrateur |
| ing.env@ecodash.app | password | Ingénieur Environnement |
| ing.social@ecodash.app | password | Ingénieur Social |
| tech.eau@ecodash.app | password | Technicien Eau |
| tech.air@ecodash.app | password | Technicien Air |
| tech.sol@ecodash.app | password | Technicien Sol |
| agent.social@ecodash.app | password | Agent Social |
