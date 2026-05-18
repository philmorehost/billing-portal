# Billing Portal — Complete Multi-Tier Billing & Automation Platform

A full WHMCS alternative built in **vanilla PHP + MySQL + Bootstrap 5**. Self-hosted, no frameworks, fully modular.

---

## Technology Stack
| Layer | Technology |
|---|---|
| Backend | PHP 8.0+, vanilla (no frameworks) |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Frontend | HTML5, Bootstrap 5, CSS3, JavaScript |
| Design | Fintech-style, curved stat cards, dark sidebar |
| Email | Raw SMTP (no PHP mail()), configurable via admin |
| Automation | Cron Job Manager |

---

## Requirements
- PHP 8.0+ with extensions: `mysqli`, `curl`, `openssl`, `json`, `mbstring`
- MySQL 5.7+ or MariaDB 10.3+
- Apache (mod_rewrite) or Nginx
- SSL certificate (recommended)

---

## Installation

### 1. Upload Files
Upload all files to your web server's public directory (e.g., `public_html/billing` or directly to `public_html`).

### 2. Run the Installer
Visit `http://yourdomain.com/install/` in your browser and follow 4 steps:
1. **Requirements check** — verifies PHP version and extensions
2. **Database** — enter MySQL credentials (database auto-created)
3. **Setup** — create admin account and set company name/URL
4. **Complete** — auto-login link + security instructions

### 3. Delete the Installer
```bash
rm -rf /path/to/billing-portal/install/
```

### 4. Set Up Cron Job
```bash
crontab -e
# Add this line:
* * * * * php /path/to/billing-portal/cron/run.php >> /var/log/billing-cron.log 2>&1
```

---

## File Structure
```
billing-portal/
├── index.php                    # Domain router (reseller + main)
├── maintenance.php              # Maintenance mode page
├── terms.php / privacy.php      # Legal pages
├── .htaccess                    # Apache security config
│
├── install/
│   ├── index.php                # 4-step web installer
│   └── schema.sql               # Full database schema
│
├── includes/
│   ├── config.php               # Bootstrap, paths, session, maintenance check
│   ├── db.config.php            # DB credentials (auto-created by installer)
│   ├── db.php                   # Database class (prepared statements)
│   ├── functions.php            # Global helpers (CSRF, format, flash, paginate)
│   ├── auth.php                 # Auth (2FA, brute-force, remember-me, password reset)
│   ├── mailer.php               # SMTP mailer (raw socket, no dependencies)
│   └── modules/
│       ├── billing.php          # Invoice creation, Paystack, credit, coupons
│       ├── pdf.php              # Print-ready invoice PDF
│       ├── reseller.php         # Wholesale pricing, SSL, white-label, branding
│       ├── wordpress.php        # WP-CLI console (plugins, themes, core, login)
│       └── provisioning/
│           ├── base.php         # Abstract base class
│           ├── dispatcher.php   # Central provisioning router
│           ├── cpanel.php       # WHM/cPanel module
│           ├── resellerclub.php # ResellerClub domains
│           ├── namecheap.php    # Namecheap domains
│           ├── connectreseller.php # ConnectReseller domains
│           ├── upperlink.php    # Upperlink .NG domains
│           ├── nocix.php        # NOCIX dedicated servers
│           └── time4vps.php     # Time4VPS VPS
│
├── admin/                       # Admin panel
│   ├── login.php / logout.php / 2fa.php
│   ├── index.php                # Dashboard
│   ├── clients.php / clients/   # Client management
│   ├── invoices.php / invoices/ # Invoice management
│   ├── services.php / services/ # Service management + provisioning
│   ├── orders.php               # Orders
│   ├── products.php / products/ # Product management
│   ├── transactions.php         # All transactions
│   ├── approvals.php            # Manual payment approvals
│   ├── tickets.php / tickets/   # Support tickets
│   ├── resellers.php            # Reseller management
│   ├── servers.php / servers/   # Server management + testing
│   ├── coupons.php              # Coupon codes
│   ├── affiliates.php           # Affiliate system + payouts
│   ├── campaigns.php            # Email campaigns
│   ├── email-templates.php      # Email template editor
│   ├── price-adjust.php         # Bulk price adjustment tool
│   ├── staff.php                # Staff accounts + ACL roles
│   ├── cron.php                 # Cron job manager
│   ├── activity.php             # Activity log
│   ├── search.php               # Global search
│   ├── settings.php             # All settings (tabbed)
│   ├── reports/revenue.php      # Revenue reports with chart
│   ├── reports/tax.php          # VAT/Tax report
│   └── partials/                # header.php, footer.php
│
├── client/                      # Client portal
│   ├── login.php / logout.php / register.php
│   ├── 2fa.php / forgot-password.php / reset-password.php
│   ├── index.php                # Dashboard
│   ├── services.php             # My services
│   ├── domains.php              # Domain management (NS, EPP)
│   ├── invoices.php / invoices/ # Invoices + payment
│   ├── add-funds.php            # Add credit balance
│   ├── order.php                # Order flow (browse → configure → checkout)
│   ├── tickets.php / tickets/   # Support tickets
│   ├── profile.php              # Profile + password change
│   ├── security.php             # 2FA setup with QR code
│   ├── affiliate.php            # Affiliate program
│   ├── reseller-apply.php       # Reseller application
│   ├── wordpress.php            # WordPress management console
│   └── partials/                # header.php, footer.php
│
├── reseller/                    # Reseller portal (white-labeled)
│   ├── login.php / logout.php / 2fa.php
│   ├── index.php                # Dashboard with balance display
│   ├── clients.php / clients/   # Tier 2 client management
│   ├── invoices.php / invoices/ # Invoice management
│   ├── services.php             # Services overview
│   ├── transactions.php         # Balance + client transactions
│   ├── products.php             # Pricing comparison (wholesale vs retail)
│   ├── topup.php                # Balance top-up
│   ├── tickets.php              # Support tickets
│   ├── settings.php             # Branding + custom domain + SSL
│   └── partials/                # White-labeled header.php, footer.php
│
├── api/
│   ├── paystack-webhook.php     # Paystack payment webhook (HMAC verified)
│   └── paystack-callback.php    # Paystack redirect callback
│
├── cron/
│   └── run.php                  # Cron runner (all jobs + auto-provision)
│
└── assets/
    └── css/style.css            # Fintech design system
```

---

## Admin Panel Features

| Module | Features |
|---|---|
| **Clients** | List, add, view, edit, status toggle, credit management, notes |
| **Invoices** | Create, view, PDF, mark paid, cancel, admin overrides |
| **Approvals** | Bank transfer + crypto manual review with approve/reject |
| **Transactions** | Full transaction log with gateway filter |
| **Services** | Suspend, unsuspend, terminate, manual provision, API status check, reboot, EPP code |
| **Products** | Add/edit products with per-cycle pricing, module assignment, wholesale discount |
| **Orders** | Order history |
| **Servers** | Add cPanel/NOCIX/Time4VPS servers, connection testing |
| **Tickets** | Priority queue, reply, close, reopen |
| **Coupons** | Percentage/fixed, limits, expiry, per-client max |
| **Affiliates** | Commission management, payout processing |
| **Campaigns** | Bulk email to targeted groups (active/inactive/resellers/all) |
| **Email Templates** | Edit system templates with variable substitution + live preview |
| **Price Adjustment** | Bulk % increase/decrease across selected products |
| **Resellers** | Approve, suspend, add balance |
| **Staff & Roles** | Create staff accounts with granular ACL permissions |
| **Cron Jobs** | Enable/disable, run-now, last output, next run |
| **Reports** | Monthly revenue chart + breakdown, VAT/tax report |
| **Settings** | General, Billing, Email/SMTP, Gateways, Security, Legal, Reseller, API Modules |
| **Activity Log** | Full audit trail |
| **Search** | Global search across clients, invoices, services, tickets |

---

## Client Portal Features

| Module | Features |
|---|---|
| **Dashboard** | Stats, recent services, recent invoices, quick actions |
| **Services** | View all services with status, next due date, WordPress button |
| **Domains** | View nameservers, update NS, get EPP/auth code |
| **Invoices** | List, view, pay with credit/Paystack/bank transfer/crypto |
| **Add Funds** | Top up credit balance via all payment methods |
| **Order** | Browse products, configure cycle, apply coupon, checkout |
| **Support Tickets** | Open, view, reply |
| **Profile** | Edit personal info, change password |
| **Security** | Enable/disable 2FA with QR code (Google Authenticator compatible) |
| **Affiliate** | Join program, get referral link, view referral history |
| **Reseller Apply** | Apply to become a reseller with profit calculator |
| **WordPress Console** | One-click login, core update, plugin/theme management, maintenance mode |

---

## Reseller Portal Features

| Module | Features |
|---|---|
| **Dashboard** | Balance display, client/service/revenue stats |
| **Clients** | Manage Tier 2 clients in isolation |
| **Invoices** | View all client invoices, PDF export |
| **Services** | Monitor all provisioned services |
| **Transactions** | Balance history + client payment log |
| **Product Pricing** | Wholesale vs retail comparison with margin calculator |
| **Top Up** | Add balance via all payment methods |
| **Branding** | Company name, brand color (live preview), markup % |
| **Custom Domain** | Set CNAME domain, provision Let's Encrypt SSL |
| **Tickets** | Support tickets to main platform |

---

## Payment Gateways

| Gateway | Type | Notes |
|---|---|---|
| **Paystack** | Automated | NGN + USD, webhook auto-marks paid |
| **Bank Transfer** | Manual | Admin review + approve/reject |
| **Cryptocurrency** | Manual | Admin review + approve/reject |
| **Credit Balance** | Instant | Pre-paid account credit |

---

## Provisioning Modules

| Module | Provider | Functions |
|---|---|---|
| `cpanel` | WHM/cPanel | Create, suspend, unsuspend, terminate, change package, password, SSO login |
| `resellerclub` | ResellerClub | Register, renew, transfer, NS update, WHOIS, EPP code |
| `namecheap` | Namecheap | Register, renew, transfer, NS update, EPP code |
| `connectreseller` | ConnectReseller | Register, renew, transfer, NS update, EPP code |
| `upperlink` | Upperlink (.NG) | Register, renew, transfer, NS update, EPP code |
| `nocix` | NOCIX | Order, power on/off, terminate, reboot, status |
| `time4vps` | Time4VPS | Order, boot, shutdown, terminate, reboot, reinstall OS, console URL |

---

## Security Features
- All passwords hashed with bcrypt (cost 12)
- CSRF tokens on every form
- Brute-force protection with configurable account lockout
- TOTP 2FA (Google Authenticator, Authy, 1Password)
- Remember-me via secure HTTP-only cookies
- Session hardening (httponly, samesite=strict, regenerate on login)
- Paystack webhook signature verification (HMAC-SHA512)
- No sensitive data in error messages
- Directory listing disabled, config files protected via .htaccess
- Unauthorized reseller host detection → 400 error page

---

## Cron Jobs

| Job | Frequency | Function |
|---|---|---|
| `auto_provision` | Hourly | Provision pending services after payment |
| `invoice_generation` | Daily | Generate renewal invoices 7 days before due |
| `payment_reminders` | Daily | Send reminders + mark overdue |
| `service_suspension` | Daily | Suspend services 3 days after due |
| `service_termination` | Daily | Terminate services 14 days after suspension |
| `domain_expiry_check` | Daily | Email warnings for domains expiring in 30 days |
| `ssl_cert_check` | Daily | Auto-renew Let's Encrypt certs expiring in 14 days |
| `affiliate_payouts` | Monthly | Process approved affiliate commissions |
| `report_generation` | Monthly | Generate monthly financial reports |

---

## Environment Variables / Settings
All configurable via **Admin → Settings**:
- Company info, currencies, invoice prefix, tax rate
- SMTP credentials (host, port, TLS, from name/email)
- Paystack API keys (public + secret)
- Bank transfer details, crypto wallet details
- Login security (max attempts, lockout duration)
- Reseller default discount %
- API module credentials (all registrars + hosting providers)
- Terms of Service + Privacy Policy content (HTML)

---

## Phase Completion Summary

| Phase | Module | Files |
|---|---|---|
| Phase 1 | Foundation, installer, auth, dashboards | 33 |
| Phase 2 | Billing engine, invoicing, Paystack, approvals | 22 |
| Phase 3 | Email templates, campaigns, affiliates, tickets, reports, staff ACL | 19 |
| Phase 4 | Multi-tier reseller system, white-label, custom domains, SSL | 23 |
| Phase 5 | All provisioning APIs (7 modules), servers management | 12 |
| Phase 6 | WordPress console, order flow, search, maintenance mode | 8 |
| **Total** | | **~120 files** |

---

*Built with ❤ — Vanilla PHP, zero external dependencies at runtime.*
