SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `settings` (`id` int NOT NULL AUTO_INCREMENT,`setting_key` varchar(100) NOT NULL,`setting_value` text,`setting_group` varchar(50) DEFAULT 'general',`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `setting_key` (`setting_key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admins` (`id` int NOT NULL AUTO_INCREMENT,`name` varchar(100) NOT NULL,`email` varchar(150) NOT NULL,`password` varchar(255) NOT NULL,`role_id` int DEFAULT NULL,`two_factor_secret` varchar(64) DEFAULT NULL,`two_factor_enabled` tinyint(1) DEFAULT 0,`login_attempts` tinyint(3) DEFAULT 0,`locked_until` datetime DEFAULT NULL,`last_login` datetime DEFAULT NULL,`last_login_ip` varchar(45) DEFAULT NULL,`remember_token` varchar(100) DEFAULT NULL,`password_reset_token` varchar(100) DEFAULT NULL,`password_reset_expires` datetime DEFAULT NULL,`status` enum('active','inactive','suspended') DEFAULT 'active',`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `email` (`email`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_roles` (`id` int NOT NULL AUTO_INCREMENT,`name` varchar(100) NOT NULL,`description` text,`permissions` json DEFAULT NULL,`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clients` (`id` int NOT NULL AUTO_INCREMENT,`first_name` varchar(100) NOT NULL,`last_name` varchar(100) NOT NULL,`email` varchar(150) NOT NULL,`password` varchar(255) NOT NULL,`phone` varchar(30) DEFAULT NULL,`company` varchar(150) DEFAULT NULL,`address1` varchar(255) DEFAULT NULL,`city` varchar(100) DEFAULT NULL,`state` varchar(100) DEFAULT NULL,`postcode` varchar(20) DEFAULT NULL,`country` varchar(2) DEFAULT 'NG',`currency` varchar(3) DEFAULT 'NGN',`credit_balance` decimal(15,2) DEFAULT 0.00,`account_type` enum('client','reseller') DEFAULT 'client',`two_factor_secret` varchar(64) DEFAULT NULL,`two_factor_enabled` tinyint(1) DEFAULT 0,`login_attempts` tinyint(3) DEFAULT 0,`locked_until` datetime DEFAULT NULL,`last_login` datetime DEFAULT NULL,`last_login_ip` varchar(45) DEFAULT NULL,`remember_token` varchar(100) DEFAULT NULL,`password_reset_token` varchar(100) DEFAULT NULL,`password_reset_expires` datetime DEFAULT NULL,`email_verified` tinyint(1) DEFAULT 0,`email_verify_token` varchar(100) DEFAULT NULL,`tos_accepted` tinyint(1) DEFAULT 0,`tos_accepted_at` datetime DEFAULT NULL,`affiliate_id` int DEFAULT NULL,`status` enum('active','inactive','suspended','pending') DEFAULT 'pending',`notes` text,`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `email` (`email`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `resellers` (`id` int NOT NULL AUTO_INCREMENT,`client_id` int NOT NULL,`company_name` varchar(150) NOT NULL,`custom_domain` varchar(255) DEFAULT NULL,`ssl_status` enum('none','pending','active','expired') DEFAULT 'none',`ssl_expires` date DEFAULT NULL,`balance` decimal(15,2) DEFAULT 0.00,`markup_percentage` decimal(5,2) DEFAULT 20.00,`branding_logo` varchar(255) DEFAULT NULL,`branding_color` varchar(7) DEFAULT '#0066cc',`branding_name` varchar(100) DEFAULT NULL,`status` enum('active','suspended','pending') DEFAULT 'pending',`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `client_id` (`client_id`),UNIQUE KEY `custom_domain` (`custom_domain`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_groups` (`id` int NOT NULL AUTO_INCREMENT,`name` varchar(100) NOT NULL,`slug` varchar(100) NOT NULL,`description` text,`sort_order` int DEFAULT 0,`visible` tinyint(1) DEFAULT 1,PRIMARY KEY (`id`),UNIQUE KEY `slug` (`slug`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (`id` int NOT NULL AUTO_INCREMENT,`group_id` int DEFAULT NULL,`name` varchar(150) NOT NULL,`slug` varchar(150) NOT NULL,`description` text,`type` enum('hosting','domain','vps','dedicated','other') DEFAULT 'other',`pricing_model` enum('recurring','one_time','free') DEFAULT 'recurring',`price_monthly` decimal(15,2) DEFAULT NULL,`price_quarterly` decimal(15,2) DEFAULT NULL,`price_semi_annually` decimal(15,2) DEFAULT NULL,`price_annually` decimal(15,2) DEFAULT NULL,`price_biennially` decimal(15,2) DEFAULT NULL,`setup_fee` decimal(15,2) DEFAULT 0.00,`currency` varchar(3) DEFAULT 'NGN',`wholesale_discount` decimal(5,2) DEFAULT 0.00,`module` varchar(50) DEFAULT NULL,`module_config` json DEFAULT NULL,`welcome_email_id` int DEFAULT NULL,`tax_enabled` tinyint(1) DEFAULT 1,`auto_provision` tinyint(1) DEFAULT 1,`visible` tinyint(1) DEFAULT 1,`sort_order` int DEFAULT 0,`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `slug` (`slug`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (`id` int NOT NULL AUTO_INCREMENT,`client_id` int NOT NULL,`reseller_id` int DEFAULT NULL,`order_number` varchar(20) NOT NULL,`total` decimal(15,2) NOT NULL DEFAULT 0.00,`currency` varchar(3) DEFAULT 'NGN',`status` enum('pending','active','fraud','cancelled') DEFAULT 'pending',`payment_method` varchar(50) DEFAULT NULL,`promo_code` varchar(50) DEFAULT NULL,`promo_discount` decimal(15,2) DEFAULT 0.00,`ip_address` varchar(45) DEFAULT NULL,`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `order_number` (`order_number`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `services` (`id` int NOT NULL AUTO_INCREMENT,`client_id` int NOT NULL,`order_id` int DEFAULT NULL,`product_id` int NOT NULL,`reseller_id` int DEFAULT NULL,`domain` varchar(255) DEFAULT NULL,`username` varchar(100) DEFAULT NULL,`password` varchar(255) DEFAULT NULL,`server_id` int DEFAULT NULL,`billing_cycle` enum('monthly','quarterly','semi_annually','annually','biennially','one_time') DEFAULT 'monthly',`price` decimal(15,2) NOT NULL,`next_due_date` date DEFAULT NULL,`registration_date` date DEFAULT NULL,`termination_date` date DEFAULT NULL,`module_data` json DEFAULT NULL,`status` enum('pending','active','suspended','terminated','cancelled','fraud') DEFAULT 'pending',`notes` text,`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoices` (`id` int NOT NULL AUTO_INCREMENT,`client_id` int NOT NULL,`order_id` int DEFAULT NULL,`invoice_number` varchar(20) NOT NULL,`subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,`tax_amount` decimal(15,2) DEFAULT 0.00,`discount_amount` decimal(15,2) DEFAULT 0.00,`total` decimal(15,2) NOT NULL DEFAULT 0.00,`currency` varchar(3) DEFAULT 'NGN',`exchange_rate` decimal(10,4) DEFAULT 1.0000,`status` enum('unpaid','paid','cancelled','refunded','overdue') DEFAULT 'unpaid',`due_date` date DEFAULT NULL,`paid_date` datetime DEFAULT NULL,`payment_method` varchar(50) DEFAULT NULL,`gateway_transaction_id` varchar(150) DEFAULT NULL,`notes` text,`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `invoice_number` (`invoice_number`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_items` (`id` int NOT NULL AUTO_INCREMENT,`invoice_id` int NOT NULL,`service_id` int DEFAULT NULL,`description` varchar(255) NOT NULL,`quantity` int DEFAULT 1,`unit_price` decimal(15,2) NOT NULL,`total` decimal(15,2) NOT NULL,`tax_rate` decimal(5,2) DEFAULT 0.00,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transactions` (`id` int NOT NULL AUTO_INCREMENT,`client_id` int NOT NULL,`invoice_id` int DEFAULT NULL,`type` enum('payment','refund','credit','debit','fee') DEFAULT 'payment',`amount` decimal(15,2) NOT NULL,`currency` varchar(3) DEFAULT 'NGN',`gateway` varchar(50) DEFAULT NULL,`gateway_ref` varchar(150) DEFAULT NULL,`description` varchar(255) DEFAULT NULL,`status` enum('pending','completed','failed','refunded') DEFAULT 'pending',`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupons` (`id` int NOT NULL AUTO_INCREMENT,`code` varchar(50) NOT NULL,`type` enum('percentage','fixed') DEFAULT 'percentage',`value` decimal(10,2) NOT NULL,`max_uses` int DEFAULT NULL,`uses_count` int DEFAULT 0,`max_uses_per_client` int DEFAULT 1,`valid_from` date DEFAULT NULL,`valid_until` date DEFAULT NULL,`status` enum('active','inactive') DEFAULT 'active',`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `code` (`code`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `affiliates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `referral_code` varchar(20) NOT NULL,
  `commission_type` enum('percentage','fixed') DEFAULT 'percentage',
  `commission_value` decimal(10,2) DEFAULT 10.00,
  `balance` decimal(15,2) DEFAULT 0.00,
  `total_paid` decimal(15,2) DEFAULT 0.00,
  `total_earned` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`),
  UNIQUE KEY `referral_code` (`referral_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `affiliate_referrals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `affiliate_id` int NOT NULL,
  `referred_client_id` int NOT NULL,
  `commission_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','paid','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `affiliate_id` (`affiliate_id`),
  KEY `referred_client_id` (`referred_client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_templates` (`id` int NOT NULL AUTO_INCREMENT,`name` varchar(100) NOT NULL,`slug` varchar(100) NOT NULL,`subject` varchar(255) NOT NULL,`body_html` longtext NOT NULL,`is_system` tinyint(1) DEFAULT 0,`status` enum('active','inactive') DEFAULT 'active',`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `slug` (`slug`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_campaigns` (`id` int NOT NULL AUTO_INCREMENT,`name` varchar(150) NOT NULL,`subject` varchar(255) NOT NULL,`body_html` longtext NOT NULL,`target_group` enum('all','active','inactive','resellers') DEFAULT 'all',`total_sent` int DEFAULT 0,`status` enum('draft','sending','sent','cancelled') DEFAULT 'draft',`sent_at` datetime DEFAULT NULL,`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `servers` (`id` int NOT NULL AUTO_INCREMENT,`name` varchar(100) NOT NULL,`type` enum('cpanel','nocix','time4vps','other') DEFAULT 'cpanel',`hostname` varchar(255) NOT NULL,`ip_address` varchar(45) DEFAULT NULL,`port` int DEFAULT 2087,`username` varchar(100) DEFAULT NULL,`password` varchar(255) DEFAULT NULL,`api_key` varchar(255) DEFAULT NULL,`status` enum('active','inactive','maintenance') DEFAULT 'active',`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tickets` (`id` int NOT NULL AUTO_INCREMENT,`client_id` int NOT NULL,`assigned_admin_id` int DEFAULT NULL,`ticket_number` varchar(20) NOT NULL,`subject` varchar(255) NOT NULL,`department` varchar(50) DEFAULT 'general',`priority` enum('low','medium','high','urgent') DEFAULT 'medium',`status` enum('open','answered','client_reply','closed') DEFAULT 'open',`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `ticket_number` (`ticket_number`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_replies` (`id` int NOT NULL AUTO_INCREMENT,`ticket_id` int NOT NULL,`author_type` enum('client','admin') DEFAULT 'client',`author_id` int NOT NULL,`message` longtext NOT NULL,`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_log` (`id` int NOT NULL AUTO_INCREMENT,`actor_type` enum('admin','client','system','cron') DEFAULT 'system',`actor_id` int DEFAULT NULL,`action` varchar(100) NOT NULL,`description` text,`ip_address` varchar(45) DEFAULT NULL,`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cron_jobs` (`id` int NOT NULL AUTO_INCREMENT,`name` varchar(100) NOT NULL,`slug` varchar(100) NOT NULL,`description` text,`frequency` enum('hourly','daily','weekly','monthly') DEFAULT 'daily',`last_run` datetime DEFAULT NULL,`next_run` datetime DEFAULT NULL,`last_status` enum('success','failed','running') DEFAULT NULL,`last_output` text,`enabled` tinyint(1) DEFAULT 1,PRIMARY KEY (`id`),UNIQUE KEY `slug` (`slug`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `admin_roles` (`name`,`description`,`permissions`) VALUES ('Super Admin','Full system access','{"all":true}'),('Support Staff','Client support only','{"clients":true,"tickets":true}'),('Billing Staff','Billing access','{"clients":true,"invoices":true,"transactions":true}');

INSERT IGNORE INTO `settings` (`setting_key`,`setting_value`,`setting_group`) VALUES ('company_name','My Billing Portal','general'),('company_email','admin@example.com','general'),('company_phone','','general'),('company_address','','general'),('company_country','NG','general'),('base_currency','NGN','billing'),('invoice_prefix','INV','billing'),('invoice_due_days','7','billing'),('tax_name','VAT','billing'),('tax_rate','7.5','billing'),('tax_enabled','1','billing'),('smtp_host','','email'),('smtp_port','587','email'),('smtp_user','','email'),('smtp_pass','','email'),('smtp_from_name','Billing Portal','email'),('smtp_from_email','noreply@example.com','email'),('smtp_encryption','tls','email'),('login_max_attempts','5','security'),('login_lockout_minutes','30','security'),('session_lifetime_hours','24','security'),('two_factor_required','0','security'),('maintenance_mode','0','general'),('tos_content','<h2>Terms of Service</h2><p>Update this in Admin > Settings > Legal.</p>','legal'),('privacy_content','<h2>Privacy Policy</h2><p>Update this in Admin > Settings > Legal.</p>','legal'),('paystack_public_key','','gateways'),('paystack_secret_key','','gateways'),('paystack_enabled','0','gateways'),('bank_transfer_enabled','1','gateways'),('bank_transfer_details','Bank: \nAccount Name: \nAccount Number: ','gateways'),('crypto_enabled','0','gateways'),('crypto_details','','gateways'),('reseller_enabled','1','reseller'),('reseller_default_discount','20','reseller'),('installer_complete','0','system');

INSERT IGNORE INTO `cron_jobs` (`name`,`slug`,`description`,`frequency`,`next_run`) VALUES ('Invoice Generation','invoice_generation','Generate renewal invoices for due services','daily',NOW()),('Payment Reminders','payment_reminders','Send reminders for unpaid invoices','daily',NOW()),('Service Suspension','service_suspension','Suspend services with overdue invoices','daily',NOW()),('Service Termination','service_termination','Terminate services past grace period','daily',NOW()),('Domain Expiry Check','domain_expiry_check','Check and notify for expiring domains','daily',NOW()),('SSL Certificate Check','ssl_cert_check','Check reseller SSL certificate expiry','daily',NOW()),('Affiliate Payouts','affiliate_payouts','Process pending affiliate commissions','monthly',NOW()),('Report Generation','report_generation','Generate monthly financial reports','monthly',NOW());

INSERT IGNORE INTO `email_templates` (`name`,`slug`,`subject`,`body_html`,`is_system`) VALUES ('Welcome Email','welcome','Welcome to {company_name}!','<p>Dear {client_name},</p><p>Welcome to {company_name}! Your account has been created successfully.</p><p><a href="{login_url}" class="btn">Login to Your Account</a></p>',1),('Invoice Created','invoice_created','Invoice #{invoice_number} - {company_name}','<p>Dear {client_name},</p><p>This is a notice that invoice <strong>#{invoice_number}</strong> is now due on {due_date}. Please find the invoice summary below:</p><div style="overflow-x: auto; margin: 20px 0;"><table style="width: 100%; border-collapse: collapse; border: 1px solid #edf2f7; border-radius: 8px; font-size: 14px;"><thead><tr style="background-color: #f7fafc;"><th style="padding: 12px; text-align: left; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #718096; border-bottom: 2px solid #edf2f7;">Description</th><th style="padding: 12px; text-align: center; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #718096; border-bottom: 2px solid #edf2f7; width: 50px;">Qty</th><th style="padding: 12px; text-align: right; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #718096; border-bottom: 2px solid #edf2f7; width: 100px;">Price</th><th style="padding: 12px; text-align: right; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #718096; border-bottom: 2px solid #edf2f7; width: 100px;">Total</th></tr></thead><tbody>{invoice_items}</tbody></table></div><table align="right" style="width: 260px; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;"><tr><td style="padding: 6px 0; border-bottom: 1px solid #edf2f7; color: #718096;">Subtotal:</td><td style="padding: 6px 0; border-bottom: 1px solid #edf2f7; text-align: right; color: #2d3748; font-weight: 500;">{subtotal}</td></tr><tr><td style="padding: 6px 0; border-bottom: 1px solid #edf2f7; color: #718096;">Tax/VAT:</td><td style="padding: 6px 0; border-bottom: 1px solid #edf2f7; text-align: right; color: #2d3748; font-weight: 500;">{tax_amount}</td></tr><tr><td style="padding: 12px 0; font-size: 16px; font-weight: bold; color: #2d3748;">Total Due:</td><td style="padding: 12px 0; font-size: 16px; font-weight: 800; text-align: right; color: #0f172a;">{invoice_total}</td></tr></table><div style="clear: both;"></div>{bank_details}<p style="margin-top: 30px; text-align: center; clear: both;"><a href="{invoice_url}" class="btn" style="background-color: #0f172a; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 14px;">Pay Invoice Online &rarr;</a></p>',1),('Payment Received','payment_received','Payment Received - Invoice #{invoice_number}','<p>Dear {client_name},</p><p>We have received your payment of <strong>{amount}</strong> for invoice #{invoice_number}. Thank you!</p>',1),('Password Reset','password_reset','Password Reset - {company_name}','<p>Dear {client_name},</p><p>Click below to reset your password. This link expires in 1 hour.</p><p><a href="{reset_url}" class="btn">Reset Password</a></p>',1),('Service Suspended','service_suspended','Service Suspended - {domain}','<p>Dear {client_name},</p><p>Your service <strong>{domain}</strong> has been suspended due to non-payment. Pay invoice #{invoice_number} to restore access.</p>',1),('Service Activated','service_activated','Service Activated - {domain}','<p>Dear {client_name},</p><p>Your service <strong>{domain}</strong> is now active.</p>',1);

-- Reseller balance top-up tracking
CREATE TABLE IF NOT EXISTS `reseller_topups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reseller_id` int NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `gateway_ref` varchar(150) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `reseller_id` (`reseller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reseller product pricing overrides (per-reseller custom prices)
CREATE TABLE IF NOT EXISTS `reseller_product_prices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reseller_id` int NOT NULL,
  `product_id` int NOT NULL,
  `wholesale_override` decimal(15,2) DEFAULT NULL,
  `retail_override` decimal(15,2) DEFAULT NULL,
  `billing_cycle` varchar(20) DEFAULT 'monthly',
  PRIMARY KEY (`id`),
  UNIQUE KEY `reseller_product_cycle` (`reseller_id`,`product_id`,`billing_cycle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add auto-provision cron job
INSERT IGNORE INTO `cron_jobs` (`name`,`slug`,`description`,`frequency`,`next_run`) VALUES
('Auto Provision Services','auto_provision','Automatically provision pending services after payment','hourly',NOW());

-- Add module settings keys
INSERT IGNORE INTO `settings` (`setting_key`,`setting_value`,`setting_group`) VALUES
('module_resellerclub_reseller_id','','modules'),
('module_resellerclub_api_key','','modules'),
('module_resellerclub_test_mode','1','modules'),
('module_namecheap_api_user','','modules'),
('module_namecheap_api_key','','modules'),
('module_namecheap_sandbox','1','modules'),
('module_connectreseller_username','','modules'),
('module_connectreseller_password','','modules'),
('module_connectreseller_brand_id','','modules'),
('module_connectreseller_api_key','','modules'),
('module_upperlink_api_key','','modules'),
('module_nocix_api_key','','modules'),
('module_nocix_default_location','dallas','modules'),
('module_time4vps_username','','modules'),
('module_time4vps_password','','modules'),
('module_time4vps_default_product_id','','modules');
