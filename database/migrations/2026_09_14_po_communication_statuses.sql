-- Keep PO communication status aligned with supported supplier reply classifications.
ALTER TABLE `purchase_orders`
  MODIFY COLUMN `communication_status` ENUM(
    'not_sent','awaiting_response','supplier_confirmed','partially_available',
    'supplier_declined','out_of_stock','declined','needs_clarification','delivery_update',
    'preparing','shipped','in_transit','out_for_delivery','delayed',
    'supplier_says_delivered','needs_review','unknown'
  ) NOT NULL DEFAULT 'not_sent';
