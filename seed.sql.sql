-- ============================================================
-- 5 STAR ELECTRIC & ELECTRONICS
-- Seed Data (Sample)
-- ============================================================

USE `5star_db`;

-- ---------- ROLES ----------
INSERT INTO `roles` (`id`, `name`, `display_name`, `description`) VALUES
(1, 'super_admin', 'Super Admin', 'Platform administrator'),
(2, 'vendor',      'Vendor',      'Distributor (your uncle)'),
(3, 'shop',        'Shop',        'Retail electric/electronics shop'),
(4, 'company',     'Company',     'Manufacturer / supplier'),
(5, 'transport',   'Transport',   'Bilty / logistics provider');

-- ---------- COUNTRIES & LOCATIONS ----------
INSERT INTO `countries` (`id`, `name`, `code`, `phone_code`) VALUES
(1, 'Pakistan', 'PK', '+92');

INSERT INTO `provinces` (`id`, `country_id`, `name`) VALUES
(1, 1, 'Punjab'),
(2, 1, 'Sindh'),
(3, 1, 'Khyber Pakhtunkhwa'),
(4, 1, 'Balochistan'),
(5, 1, 'Gilgit-Baltistan'),
(6, 1, 'Azad Jammu & Kashmir');

INSERT INTO `districts` (`id`, `province_id`, `name`) VALUES
(1, 1, 'Lahore'),
(2, 1, 'Karachi'),
(3, 6, 'Muzaffarabad'),
(4, 6, 'Mirpur');

INSERT INTO `cities` (`id`, `district_id`, `name`) VALUES
(1, 1, 'Lahore'),
(2, 2, 'Karachi'),
(3, 3, 'Muzaffarabad'),
(4, 4, 'Mirpur');

-- ---------- USERS ----------
-- Password for ALL demo users = "password"
-- Hash below is bcrypt of "password"
-- IMPORTANT: Change on first real login.

SET @pwd = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

INSERT INTO `users` (`id`, `role_id`, `email`, `phone`, `password_hash`, `full_name`, `status`) VALUES
(1, 1, 'admin@5star.pk',     '+923000000001', @pwd, 'Super Admin',       'active'),
(2, 2, 'vendor@5star.pk',    '+923000000002', @pwd, 'Uncle (Vendor)',    'active'),
(3, 3, 'shop1@5star.pk',     '+923000000003', @pwd, 'ABC Electronics',   'active'),
(4, 3, 'shop2@5star.pk',     '+923000000004', @pwd, 'Kashmir Lights',    'active'),
(5, 4, 'company1@5star.pk',  '+923000000005', @pwd, 'XYZ Fans Ltd',      'active'),
(6, 4, 'company2@5star.pk',  '+923000000006', @pwd, 'Bright LED Co',     'active'),
(7, 5, 'transport1@5star.pk','+923000000007', @pwd, 'Kashmir Bilty',     'active');

-- ---------- PROFILES ----------
INSERT INTO `vendors` (`id`, `user_id`, `business_name`, `address`, `province_id`, `city_id`, `default_commission_type`, `default_commission_value`) VALUES
(1, 2, '5 Star Electric & Electronics', 'Main Bazaar, Lahore', 1, 1, 'fixed', 500.00);

INSERT INTO `shops` (`id`, `user_id`, `shop_name`, `owner_name`, `address`, `province_id`, `district_id`, `city_id`, `landmark`) VALUES
(1, 3, 'ABC Electronics',  'Ali Ahmad',    'Main Bazaar, Muzaffarabad', 6, 3, 3, 'Jamia Masjid ke samne'),
(2, 4, 'Kashmir Lights',   'Bilal Khan',   'Sector F-1, Mirpur',        6, 4, 4, 'Old Bus Stand ke paas');

INSERT INTO `companies` (`id`, `user_id`, `company_name`, `contact_person`, `address`, `province_id`, `city_id`) VALUES
(1, 5, 'XYZ Fans Ltd',   'Mr. Tariq',   'Industrial Area, Lahore', 1, 1),
(2, 6, 'Bright LED Co',  'Ms. Sana',    'SITE Area, Karachi',      2, 2);

INSERT INTO `transport_companies` (`id`, `user_id`, `company_name`, `contact_person`, `address`, `city_id`, `vehicle_types`, `service_areas`) VALUES
(1, 7, 'Kashmir Bilty Service', 'Muhammad Aslam', 'Truck Adda, Lahore', 1, 'Truck, Mazda', 'Punjab, AJK');

-- ---------- CATEGORIES ----------
INSERT INTO `categories` (`id`, `name`, `slug`, `sort_order`) VALUES
(1, 'Fans',                 'fans',                 1),
(2, 'LED Lights',           'led-lights',           2),
(3, 'Switches & Sockets',   'switches-sockets',     3),
(4, 'Wires & Cables',       'wires-cables',         4),
(5, 'Breakers',             'breakers',             5),
(6, 'Electrical Accessories','electrical-accessories',6),
(7, 'Electronics',          'electronics',          7),
(8, 'Home Appliances',      'home-appliances',      8),
(9, 'Other',                'other',                9);

-- ---------- PRODUCTS ----------
INSERT INTO `products` (`id`, `sku`, `name`, `slug`, `category_id`, `company_id`, `brand`, `description`, `unit`, `base_price`, `show_price`, `min_order_qty`, `availability`, `status`) VALUES
(1, 'FAN-XYZ-56',   'XYZ Ceiling Fan 56"',    'xyz-ceiling-fan-56',   1, 1, 'XYZ',   'High-speed ceiling fan, 56 inch, copper motor',   'pcs', 8000.00, 0, 1, 'in_stock', 'active'),
(2, 'LED-BRT-18',   'Bright LED 18W',         'bright-led-18w',       2, 2, 'Bright','Energy-saving LED bulb 18W',                      'pcs',  350.00, 1, 1, 'in_stock', 'active'),
(3, 'SWT-ANCH-1',   'Anchor Switch 1-Gang',   'anchor-switch-1-gang', 3, NULL,'Anchor','Single gang switch',                             'pcs',  120.00, 1, 5, 'in_stock', 'active'),
(4, 'WIR-PAK-7-90', 'Pak Wire 7/90',          'pak-wire-7-90',        4, NULL,'Pak',  'Copper wire 7/90, 90 meter coil',                 'coil', 4500.00, 0, 1, 'in_stock', 'active'),
(5, 'BRK-SCH-32',   'Schneider Breaker 32A',  'schneider-breaker-32a',5, NULL,'Schneider','Single pole MCB 32A',                          'pcs',  850.00, 1, 1, 'in_stock', 'active');

-- ---------- PRODUCT COMPANY PRICES ----------
INSERT INTO `product_company_prices` (`product_id`, `company_id`, `base_price`) VALUES
(1, 1, 8000.00),
(2, 2, 350.00);

-- ---------- SAMPLE ORDER (#ORD-2026-000001) ----------
INSERT INTO `orders` (`id`, `order_number`, `shop_id`, `vendor_id`, `status`, `subtotal`, `commission_total`, `transport_charges`, `discount`, `total_amount`, `paid_amount`, `payment_status`, `confirmed_at`) VALUES
(1, 'ORD-2026-000001', 1, 1, 'delivered', 80000.00, 5000.00, 0.00, 0.00, 85000.00, 50000.00, 'partially_paid', NOW());

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `company_id`, `quantity`, `unit`, `company_base_price`, `vendor_commission`, `final_price`, `line_total`, `status`) VALUES
(1, 1, 1, 1, 10, 'pcs', 8000.00, 500.00, 8500.00, 85000.00, 'delivered');

-- Status history (timeline)
INSERT INTO `order_status_history` (`order_id`, `previous_status`, `new_status`, `changed_by`, `changed_by_role`, `note`, `created_at`) VALUES
(1, NULL,               'order_created',        3, 'shop',      'Order created by ABC Electronics',     NOW() - INTERVAL 3 DAY),
(1, 'order_created',    'order_accepted',       2, 'vendor',    'Accepted by vendor',                   NOW() - INTERVAL 3 DAY + INTERVAL 1 HOUR),
(1, 'order_accepted',   'sent_to_company',      2, 'vendor',    'Sent to XYZ Fans Ltd',                 NOW() - INTERVAL 3 DAY + INTERVAL 2 HOUR),
(1, 'sent_to_company',  'company_confirmed',    5, 'company',   'Available. Base price PKR 8000/pc',    NOW() - INTERVAL 3 DAY + INTERVAL 4 HOUR),
(1, 'company_confirmed','ready_for_transport',  5, 'company',   'Packed. Ready for pickup.',            NOW() - INTERVAL 2 DAY),
(1, 'ready_for_transport','picked_up',          7, 'transport', 'Picked up by Truck KBS-4421',          NOW() - INTERVAL 2 DAY + INTERVAL 4 HOUR),
(1, 'picked_up',        'in_transit',           7, 'transport', 'Departed from Lahore',                 NOW() - INTERVAL 2 DAY + INTERVAL 8 HOUR),
(1, 'in_transit',       'delivered',            7, 'transport', 'Delivered to ABC Electronics',         NOW() - INTERVAL 1 DAY);

-- ---------- SAMPLE PAYMENT ----------
INSERT INTO `payments` (`payment_no`, `order_id`, `shop_id`, `amount`, `payment_method`, `reference_no`, `status`, `notes`, `created_by`) VALUES
('PAY-2026-000001', 1, 1, 50000.00, 'cash', 'CASH-001', 'paid', 'Partial cash payment on delivery', 2);

-- ---------- SAMPLE SHIPMENT ----------
INSERT INTO `shipments` (`shipment_no`, `order_id`, `transport_user_id`, `transport_company_id`, `vehicle_no`, `vehicle_type`, `driver_name`, `driver_phone`, `bilty_no`, `pickup_address`, `delivery_address`, `pickup_date`, `expected_delivery`, `delivered_at`, `status`) VALUES
('SHP-2026-000001', 1, 7, 1, 'KBS-4421', 'Truck', 'Muhammad Aslam', '+923001234567', 'KB-2026-88741', 'XYZ Fans Ltd, Lahore', 'ABC Electronics, Muzaffarabad', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY, 'delivered');

INSERT INTO `shipment_tracking` (`shipment_id`, `status`, `note`, `updated_by`) VALUES
(1, 'picked_up',   'Package picked up from warehouse',  7),
(1, 'in_transit',  'Departed Lahore',                   7),
(1, 'delivered',   'Delivered to shop. 2 boxes intact.', 7);

-- ---------- SAMPLE INVOICE ----------
INSERT INTO `invoices` (`invoice_no`, `order_id`, `shop_id`, `vendor_id`, `subtotal`, `commission_total`, `total`, `payment_status`) VALUES
('INV-2026-000001', 1, 1, 1, 80000.00, 5000.00, 85000.00, 'partially_paid');

INSERT INTO `invoice_items` (`invoice_id`, `product_id`, `description`, `quantity`, `unit_price`, `line_total`) VALUES
(1, 1, 'XYZ Ceiling Fan 56" (incl. commission)', 10, 8500.00, 85000.00);

-- ---------- SETTINGS ----------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', '5 Star Electric & Electronics'),
('site_email', 'info@5starelectric.pk'),
('site_phone', '+923000000000'),
('currency', 'PKR'),
('commission_default_type', 'fixed'),
('commission_default_value', '500'),
('price_request_expiry_days', '7'),
('max_counter_offers', '3'),
('chat_poll_interval_ms', '3000');

-- ============================================================
-- ✅ SEED DATA COMPLETE
-- Demo login passwords: "password"
-- ============================================================