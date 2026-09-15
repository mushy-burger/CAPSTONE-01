-- Preserve full parsed Gmail body while storing clean supplier-authored text.
ALTER TABLE `purchase_order_replies`
  ADD COLUMN IF NOT EXISTS `clean_body` MEDIUMTEXT NULL AFTER `raw_body`,
  ADD COLUMN IF NOT EXISTS `clean_body_needs_review` TINYINT(1) NOT NULL DEFAULT 0 AFTER `clean_body`;
