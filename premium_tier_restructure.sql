-- Rename "Paid Plan" to "Premium" and raise price to reflect Custom
-- Templates now being included rather than sold separately.
UPDATE products
SET label = 'Premium Plan',
    description = 'Enrollment forms, the full report (results, attendance, position, trends, fees), and every premium theme.',
    price = 3000.00
WHERE product_key = 'paid_plan';

-- Deactivate Custom Templates as a standalone purchase — it's now included
-- in Premium. Deactivating (not deleting) preserves order history for
-- schools who already bought it separately in the past.
UPDATE products SET is_active = 0 WHERE product_key = 'custom_templates';

-- Note: custom_templates_enabled on schools was already dead code — it was
-- only ever set (in includes/payments.php), never actually read anywhere.
-- Premium theme access is correctly gated at signup by is_premium_locked()
-- via school-setup.php's Trial/Free check. Leaving the column in place is
-- harmless; nothing reads it, so it's safe to ignore going forward.
