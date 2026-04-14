# Installation des modules premium — Guide client

Ce guide vous accompagne dans l'installation et l'activation des modules premium de PGA Open (Analytics, Rapports avancés, Intégrations, Multi-organisation).

---

## Prérequis

- PGA Open core installé et fonctionnel (version >= 1.0)
- Accès SSH au serveur de déploiement
- Composer 2.x installé
- **Token d'accès** au repository privé `pga-premium-modules` (fourni par eSanteCoM après souscription)
- Pour le module **Analytics** : clé API Claude (Anthropic) — voir section dédiée

---

## 1. Obtention du token d'accès

Après souscription à un pack premium, vous recevez un email contenant :

- **Votre identifiant client** (ex: `client-burkina-faso-2026`)
- **Votre token GitHub** (ex: `ghp_xxxxxxxxxxxxxxxxxx`)
- **Les modules inclus** dans votre pack
- **La date d'expiration** de votre licence

Conservez ce token de manière sécurisée (trousseau, gestionnaire de mots de passe).

---

## 2. Configuration Composer

Sur votre serveur, éditez `~/.composer/auth.json` (ou `auth.json` à la racine du projet) :

```json
{
    "github-oauth": {
        "github.com": "ghp_xxxxxxxxxxxxxxxxxx"
    }
}
```

Ajoutez le repository privé dans `composer.json` du projet :

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Tassmaha/pga-premium-modules"
        }
    ]
}
```

---

## 3. Installation du module

### Module Analytics (Assistant IA + Rapports narratifs)

```bash
cd /var/www/pga-open
composer require pga-open/premium-analytics

# Publier la config
php artisan vendor:publish --tag=pga-analytics-config
```

### Module Rapports avancés

```bash
composer require pga-open/premium-advanced-reports
php artisan vendor:publish --tag=pga-advanced-reports-config
```

### Module Intégrations (SMS, WhatsApp, DHIS2)

```bash
composer require pga-open/premium-integrations
php artisan vendor:publish --tag=pga-integrations-config
```

### Module Multi-organisation

```bash
composer require pga-open/premium-multi-org
php artisan vendor:publish --tag=pga-multi-org-config
```

---

## 4. Configuration spécifique — Module Analytics

Le module Analytics nécessite une clé API Claude (Anthropic) pour l'assistant IA et les rapports narratifs.

### Obtenir une clé API Claude

1. Créez un compte sur [console.anthropic.com](https://console.anthropic.com)
2. Ajoutez un moyen de paiement (carte bancaire ou virement)
3. Allez dans **API Keys** → **Create Key**
4. Copiez la clé (format `sk-ant-api03-xxxxx...`)
5. **Recommandé** : Définissez un plafond mensuel (ex: 50 USD) dans les paramètres du compte

### Coûts indicatifs

Pour un tenant de ~15 000 agents avec 20 utilisateurs :

| Usage | Coût mensuel estimé |
|-------|---------------------|
| Assistant IA (3 000 requêtes) | ~14 USD |
| Rapports narratifs (20/mois) | ~1 USD |
| **Total** | **~15 USD/mois** |

### Configurer la clé dans `.env`

```bash
# Éditer le fichier .env sur le serveur
nano .env

# Ajouter ces lignes :
CLAUDE_API_KEY=sk-ant-api03-xxxxxxxxxxxxxxxxxxxxxxxxx
CLAUDE_MODEL_FAST=claude-haiku-4-5-20251001
CLAUDE_MODEL_SMART=claude-sonnet-4-6
```

Puis nettoyer le cache :

```bash
php artisan config:clear && php artisan config:cache
```

---

## 5. Activation du module pour un tenant

Les modules sont activés **par tenant** (par pays). Utilisez la commande artisan :

### Lister les modules disponibles et leur statut

```bash
php artisan tenant:module burkina-faso list
```

### Activer un module (avec date d'expiration)

```bash
# Activation pour 1 an (expiration auto dans 365 jours)
php artisan tenant:module burkina-faso activate analytics

# Activation avec date précise
php artisan tenant:module burkina-faso activate analytics --expires=2027-04-11
```

### Désactiver un module

```bash
php artisan tenant:module burkina-faso deactivate analytics
```

### Vérification

```bash
# Relire la config du tenant
php artisan tenant:module burkina-faso list

# Résultat attendu :
# [ACTIF] analytics — Analytics & IA (expire: 2027-04-11)
# [inactif] advanced_reports — Rapports avancés (expire: —)
```

---

## 6. Renouvellement de licence

30 jours avant l'expiration, vous recevrez un email de rappel. Pour renouveler :

1. Contactez eSanteCoM à **support@esantecom.org**
2. Après paiement, vous recevrez une **nouvelle date d'expiration**
3. Mettez à jour localement :

```bash
php artisan tenant:module burkina-faso activate analytics --expires=2028-04-11
```

---

## 7. Dépannage

### Erreur 403 "Module non activé"

```
{"error":"Le module 'analytics' n'est pas activé pour ce tenant.","upgrade":true}
```

**Causes possibles** :
- Module non activé pour ce tenant → `php artisan tenant:module {slug} activate {module}`
- Licence expirée → vérifier la date avec `tenant:module {slug} list`
- Package composer non installé → `composer show pga-open/premium-analytics`

### Erreur "Class not found"

```
Class "PgaOpen\Analytics\AnalyticsServiceProvider" not found
```

**Solution** :
```bash
composer dump-autoload
php artisan config:clear
```

### Erreur Claude API "401 Unauthorized"

Clé API invalide ou révoquée. Régénérez une clé sur [console.anthropic.com](https://console.anthropic.com) et mettez à jour `CLAUDE_API_KEY` dans `.env`.

### Erreur "Rate limit exceeded"

Claude API limite à 50 requêtes/minute par défaut. Augmentez dans votre compte Anthropic ou mettez en cache plus agressivement dans `config/analytics.php` :

```php
'assistant' => [
    'cache_ttl' => 1800, // 30 min au lieu de 5 min
],
```

---

## 8. Surveillance de la consommation

### Consommation Claude API

Consultez votre tableau de bord sur [console.anthropic.com/settings/usage](https://console.anthropic.com/settings/usage).

### Logs d'utilisation locaux

```bash
# Voir les requêtes assistant IA
tail -f storage/logs/laravel.log | grep "Claude API"

# Compter les requêtes du mois
grep "Claude API" storage/logs/laravel.log | wc -l
```

---

## 9. Désinstallation

Si vous souhaitez retirer un module :

```bash
# 1. Désactiver pour tous les tenants concernés
php artisan tenant:module burkina-faso deactivate analytics

# 2. Supprimer le package
composer remove pga-open/premium-analytics

# 3. Nettoyer la config
php artisan config:clear
```

---

## Support

- **Email** : support@esantecom.org
- **Documentation** : [https://docs.pga-open.org](https://docs.pga-open.org)
- **Issues** : [GitHub Issues](https://github.com/Tassmaha/PGA-Open/issues) (pour le core uniquement)

Pour le support premium, utilisez votre **identifiant client** dans chaque email.

---

*PGA Open Premium — Développé par Dr Mahamadi Tassembedo*
