CREATE TABLE IF NOT EXISTS platform_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed with current hardcoded values, so nothing changes behavior until
-- you actually edit something in the new admin/settings.php page.
INSERT IGNORE INTO platform_settings (setting_key, setting_value) VALUES
  ('payment_till_number', '4567050'),
  ('payment_pochi_number', '254707306888'),
  ('payment_send_money_number', '254707306888'),
  ('payment_equity_paybill', '247247'),
  ('payment_equity_account_number', ''),
  ('payment_display_name', 'Kelvin Njehia'),
  ('admin_notify_email', 'admin@somahub.top'),
  ('whatsapp_outreach_number', '254707306888'),
  ('cron_secret', 'CHANGE_THIS_TO_A_REAL_RANDOM_STRING');
