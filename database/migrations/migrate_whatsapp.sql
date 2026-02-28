-- migrate_whatsapp.sql
-- Add column to track whether a resident has accepted the WhatsApp Terms & Conditions

ALTER TABLE owners 
  ADD COLUMN IF NOT EXISTS whatsapp_terms_accepted TINYINT(1) DEFAULT 0;

ALTER TABLE tenants
  ADD COLUMN IF NOT EXISTS whatsapp_terms_accepted TINYINT(1) DEFAULT 0;
