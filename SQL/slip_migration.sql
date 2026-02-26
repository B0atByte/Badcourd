-- ============================================================
-- Payment Slip Migration
-- BARGAIN_SPORT — badcourt database
-- ============================================================
ALTER TABLE bookings
  ADD COLUMN payment_slip_path VARCHAR(255) NULL AFTER promotion_discount_percent;
