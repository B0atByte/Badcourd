-- Fix pricing gap: extend group 1 weekday rule to cover full day (08:00-23:30)
-- Previously ended at 20:30, causing ฿0 bookings for 21:00+ slots
UPDATE `pricing_rules` SET `end_time` = '23:30:00' WHERE `id` = 32 AND `group_id` = 1 AND `day_type` = 'weekday';

-- Fix historical bookings that were created with ฿0 price due to the gap
UPDATE `bookings` SET `price_per_hour` = 200.00, `total_amount` = 400.00 WHERE `id` = 26 AND `price_per_hour` = 0;
UPDATE `bookings` SET `price_per_hour` = 150.00, `total_amount` = 300.00 WHERE `id` = 32 AND `price_per_hour` = 0;
