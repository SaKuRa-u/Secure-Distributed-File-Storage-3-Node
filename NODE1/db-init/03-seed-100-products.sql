-- ============================================================
-- Seed 100 produk demo — SONY Shop (Node 2 Gabriel)
-- Kategori bervariasi (Makanan s/d Kesehatan), harga & stok bebas.
-- Tiap produk punya 1 foto UNIK sesuai namanya (p100.jpg s/d p199.jpg).
-- Storage UTAMA = SeaweedFS object images/products/<basename>
--   (di-upload via seed-media-to-seaweed.sh).
--   Folder src/uploads/ HANYA fallback/cache image-proxy.php, bukan storage.
-- Urutan deploy di server: (1) timpa kode + uploads/, (2) seed SQL ini,
--   (3) bash seed-media-to-seaweed.sh untuk push media ke filer.
-- Cara pakai di DB LAMA (volume sudah ada, db-init tidak jalan ulang):
--   cat db-init/03-seed-100-products.sql | docker exec -i psp-db psql -U psp -d psp_project
-- DB BARU (volume kosong): file ini jalan otomatis berurutan.
-- File idempotent: baris DELETE di bawah membuat seed ulang aman
-- (mis. setelah perbaikan foto) tanpa duplikat.
-- ============================================================

-- Kategori baru (mengisi id 5 yang kosong + 7,8,9,10,11,12)
INSERT INTO categories (id, category_name) VALUES
(5, 'Beauty'),
(7, 'Home'),
(8, 'Fitness'),
(9, 'Bayi & Balita'),
(10, 'Olahraga'),
(11, 'Aksesoris'),
(12, 'Kesehatan')
ON CONFLICT (id) DO NOTHING;

-- Hapus seed lama (100-199) agar seed ulang tidak duplikat / foto basi
DELETE FROM items WHERE id BETWEEN 100 AND 199;

-- ---------------- Makanan (1) ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(100, 1, 'Basreng Pedas Daun Jeruk 150g', 15000.00, 60, NULL, 'p100.jpg'),
(101, 1, 'Keripik Singkong Balado 250g', 12000.00, 80, NULL, 'p101.jpg'),
(102, 1, 'Rendang Sapi Kemasan Vakum', 45000.00, 35, NULL, 'p102.jpg'),
(103, 1, 'Sambal Bawang Homemade 120ml', 18000.00, 50, NULL, 'p103.jpg'),
(104, 1, 'Mie Goreng Premium 5pcs', 8000.00, 120, NULL, 'p104.jpg'),
(105, 1, 'Cokelat Batangan Dark 70%', 25000.00, 45, NULL, 'p105.jpg'),
(106, 1, 'Granola Madu Original 400g', 35000.00, 40, NULL, 'p106.jpg'),
(107, 1, 'Kopi Tubruk Robusta 200g', 28000.00, 55, NULL, 'p107.jpg')
ON CONFLICT (id) DO NOTHING;

-- ---------------- Minuman (2) ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(108, 2, 'Matcha Latte Bubuk Ceremonial', 42000.00, 48, NULL, 'p108.jpg'),
(109, 2, 'Kopi Susu Gula Aren 1L', 22000.00, 70, NULL, 'p109.jpg'),
(110, 2, 'Teh Melati Premium 50 Kantong', 15000.00, 90, NULL, 'p110.jpg'),
(111, 2, 'Sari Jeruk Peras Murni', 18000.00, 65, NULL, 'p111.jpg'),
(112, 2, 'Susu Oat Barista Edition', 38000.00, 52, NULL, 'p112.jpg'),
(113, 2, 'Soda Gembira Kaleng 330ml', 12000.00, 110, NULL, 'p113.jpg'),
(114, 2, 'Infused Water Lemon Mint', 10000.00, 75, NULL, 'p114.jpg'),
(115, 2, 'Cokelat Panas Klasik Sachet', 20000.00, 85, NULL, 'p115.jpg')
ON CONFLICT (id) DO NOTHING;

-- ---------------- Elektronik (3) ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(116, 3, 'Headphone Bass Boost Pro', 349000.00, 22, NULL, 'p116.jpg'),
(117, 3, 'Keyboard Mekanikal RGB Hotswap', 599000.00, 18, NULL, 'p117.jpg'),
(118, 3, 'Mouse Wireless Silent Click', 189000.00, 40, NULL, 'p118.jpg'),
(119, 3, 'Speaker Bluetooth Mini 360', 459000.00, 15, NULL, 'p119.jpg'),
(120, 3, 'Power Bank 20000mAh Fast Charge', 329000.00, 28, NULL, 'p120.jpg'),
(121, 3, 'Charger GaN 65W 3-Port', 279000.00, 33, NULL, 'p121.jpg'),
(122, 3, 'Webcam Full HD Autofocus', 499000.00, 12, NULL, 'p122.jpg'),
(123, 3, 'Lampu Meja LED Eye-Care', 149000.00, 26, NULL, 'p123.jpg')
ON CONFLICT (id) DO NOTHING;

-- ---------------- Furnitur (4) ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(124, 4, 'Kursi Lounge Minimalis Skandi', 1299000.00, 8, NULL, 'p124.jpg'),
(125, 4, 'Sofa 2-Seater Abu Mist', 3499000.00, 4, NULL, 'p125.jpg'),
(126, 4, 'Meja Kerja Kayu Solid', 899000.00, 10, NULL, 'p126.jpg'),
(127, 4, 'Rak Dinding Vintage 3 Susun', 459000.00, 14, NULL, 'p127.jpg'),
(128, 4, 'Lampu Lantai Anyaman Rotan', 679000.00, 7, NULL, 'p128.jpg'),
(129, 4, 'Cermin Bulat Aesthetic 60cm', 329000.00, 16, NULL, 'p129.jpg'),
(130, 4, 'Pot Tanaman Keramik Matte', 89000.00, 45, NULL, 'p130.jpg'),
(131, 4, 'Lilin Aromaterapi Vanilla Bean', 95000.00, 38, NULL, 'p131.jpg')
ON CONFLICT (id) DO NOTHING;

-- ---------------- Fashion (6) ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(132, 6, 'Hoodie Oversize Cream Fleece', 189000.00, 42, NULL, 'p132.jpg'),
(133, 6, 'Kaos Polos Heavyweight 220gsm', 79000.00, 95, NULL, 'p133.jpg'),
(134, 6, 'Dress Floral Midi Vintage', 249000.00, 27, NULL, 'p134.jpg'),
(135, 6, 'Jaket Denim Washed Unisex', 329000.00, 21, NULL, 'p135.jpg'),
(136, 6, 'Tas Selempang Kulit Asli', 279000.00, 19, NULL, 'p136.jpg'),
(137, 6, 'Kacamata Retro Bulat Klasik', 149000.00, 31, NULL, 'p137.jpg'),
(138, 6, 'Sandal Slide Empuk Cloud', 89000.00, 58, NULL, 'p138.jpg'),
(139, 6, 'Jam Tangan Minimalis Tan', 499000.00, 13, NULL, 'p139.jpg')
ON CONFLICT (id) DO NOTHING;

-- ---------------- Beauty (5) ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(140, 5, 'Serum Vitamin C 20ml Brightening', 129000.00, 44, NULL, 'p140.jpg'),
(141, 5, 'Lipstik Matte Terracotta Longwear', 89000.00, 52, NULL, 'p141.jpg'),
(142, 5, 'Sunscreen SPF50 PA++++ Ringan', 119000.00, 47, NULL, 'p142.jpg'),
(143, 5, 'Moisturizer Aloe Vera Gel', 99000.00, 39, NULL, 'p143.jpg'),
(144, 5, 'Parfum Fleur EDP 30ml', 199000.00, 29, NULL, 'p144.jpg'),
(145, 5, 'Face Wash Charcoal Deep Clean', 59000.00, 61, NULL, 'p145.jpg'),
(146, 5, 'Masker Wajah Clay Detox', 45000.00, 66, NULL, 'p146.jpg'),
(147, 5, 'Hair Oil Argan Repair', 79000.00, 36, NULL, 'p147.jpg')
ON CONFLICT (id) DO NOTHING;

-- ---------------- Home (7) ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(148, 7, 'Mug Keramik Speckle 350ml', 65000.00, 72, NULL, 'p148.jpg'),
(149, 7, 'Lilin Kedelai Lavender Calm', 85000.00, 41, NULL, 'p149.jpg'),
(150, 7, 'Selimut Rajut Hangat 150x200', 229000.00, 17, NULL, 'p150.jpg'),
(151, 7, 'Keset Kaki Memory Foam', 119000.00, 34, NULL, 'p151.jpg'),
(152, 7, 'Organizer Meja Bambu Sekat', 139000.00, 25, NULL, 'p152.jpg'),
(153, 7, 'Diffuser Reed Jasmine 100ml', 159000.00, 23, NULL, 'p153.jpg'),
(154, 7, 'Apron Dapur Linen Sage', 99000.00, 30, NULL, 'p154.jpg'),
(155, 7, 'Botol Minum Stainless 750ml', 129000.00, 49, NULL, 'p155.jpg')
ON CONFLICT (id) DO NOTHING;

-- ---------------- Fitness (8) ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(156, 8, 'Matras Yoga Anti Slip 6mm', 189000.00, 32, NULL, 'p156.jpg'),
(157, 8, 'Dumbbell 5kg Neoprene Pair', 249000.00, 20, NULL, 'p157.jpg'),
(158, 8, 'Resistance Band Set 5 Level', 99000.00, 46, NULL, 'p158.jpg'),
(159, 8, 'Botol Shaker Protein 700ml', 75000.00, 58, NULL, 'p159.jpg'),
(160, 8, 'Handuk Microfiber Sport Cepat Kering', 55000.00, 63, NULL, 'p160.jpg'),
(161, 8, 'Skipping Rope Bearing Pro', 45000.00, 54, NULL, 'p161.jpg'),
(162, 8, 'Kettlebell 8kg Vinyl', 329000.00, 11, NULL, 'p162.jpg'),
(163, 8, 'Gym Bag Waterproof 30L', 199000.00, 24, NULL, 'p163.jpg')
ON CONFLICT (id) DO NOTHING;

-- ---------------- Bayi & Balita (9) ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(164, 9, 'Baju Bayi Katun 3pcs Set', 149000.00, 37, NULL, 'p164.jpg'),
(165, 9, 'Selimut Bayi Lembut Flanel', 119000.00, 29, NULL, 'p165.jpg'),
(166, 9, 'Botol Susu Anti Kolik 250ml', 89000.00, 43, NULL, 'p166.jpg'),
(167, 9, 'Mainan Edukasi Kayu Montessori', 129000.00, 26, NULL, 'p167.jpg'),
(168, 9, 'Diaper Bag Multifungsi Ransel', 279000.00, 15, NULL, 'p168.jpg'),
(169, 9, 'Shampoo Bayi Gentle 200ml', 65000.00, 48, NULL, 'p169.jpg'),
(170, 9, 'Sepatu Prewalker Bayi Lucu', 99000.00, 33, NULL, 'p170.jpg'),
(171, 9, 'Boneka Kelinci Plush 35cm', 79000.00, 39, NULL, 'p171.jpg')
ON CONFLICT (id) DO NOTHING;

-- ---------------- Olahraga (10) ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(172, 10, 'Sepatu Lari Airflow Flex', 549000.00, 22, NULL, 'p172.jpg'),
(173, 10, 'Jersey Badminton Dryfit Pro', 129000.00, 41, NULL, 'p173.jpg'),
(174, 10, 'Raket Tenis Meja Carbon Pro', 229000.00, 18, NULL, 'p174.jpg'),
(175, 10, 'Bola Futsal Size 4 Match', 149000.00, 27, NULL, 'p175.jpg'),
(176, 10, 'Sarung Tangan Gym Grip', 89000.00, 35, NULL, 'p176.jpg'),
(177, 10, 'Tas Pinggang Lari Reflektif', 119000.00, 31, NULL, 'p177.jpg'),
(178, 10, 'Kacamata Renang Anti Fog', 95000.00, 28, NULL, 'p178.jpg'),
(179, 10, 'Peluit Coach + Tali', 25000.00, 80, NULL, 'p179.jpg')
ON CONFLICT (id) DO NOTHING;

-- ---------------- Aksesoris (11) ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(180, 11, 'Ransel Kanvas Vintage 15L', 259000.00, 23, NULL, 'p180.jpg'),
(181, 11, 'Dompet Kulit Lipat RFID', 149000.00, 36, NULL, 'p181.jpg'),
(182, 11, 'Sabuk Kulit Klasik Gesper', 99000.00, 44, NULL, 'p182.jpg'),
(183, 11, 'Syal Wol Hangat Winter', 129000.00, 25, NULL, 'p183.jpg'),
(184, 11, 'Bros Bunga Akrilik Pastel', 35000.00, 70, NULL, 'p184.jpg'),
(185, 11, 'Gelang Manik Alam Handmade', 55000.00, 52, NULL, 'p185.jpg'),
(186, 11, 'Bandana Satin Premium', 45000.00, 47, NULL, 'p186.jpg'),
(187, 11, 'Gantungan Kunci Kulit Initial', 30000.00, 90, NULL, 'p187.jpg')
ON CONFLICT (id) DO NOTHING;

-- ---------------- Kesehatan (12) ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(188, 12, 'Vitamin C 1000mg Effervescent', 89000.00, 56, NULL, 'p188.jpg'),
(189, 12, 'Masker KN95 10pcs Box', 45000.00, 100, NULL, 'p189.jpg'),
(190, 12, 'Termometer Digital Cepat', 79000.00, 38, NULL, 'p190.jpg'),
(191, 12, 'Minyak Kayu Putih 120ml', 35000.00, 74, NULL, 'p191.jpg'),
(192, 12, 'Teh Herbal Detox 20 Kantong', 55000.00, 62, NULL, 'p192.jpg'),
(193, 12, 'Madu Hutan Murni 500ml', 129000.00, 33, NULL, 'p193.jpg'),
(194, 12, 'Plester Luka Waterproof 50pcs', 25000.00, 85, NULL, 'p194.jpg'),
(195, 12, 'Hand Sanitizer Spray 100ml', 30000.00, 95, NULL, 'p195.jpg')
ON CONFLICT (id) DO NOTHING;

-- ---------------- Tambahan lintas kategori ----------------
INSERT INTO items (id, category_id, item_name, price, stock, image_path, image) VALUES
(196, 3, 'Speaker Sound Horeg Mini Party', 899000.00, 9, NULL, 'p196.jpg'),
(197, 6, 'Sepatu Pria Casual Knit', 47000.00, 64, NULL, 'p197.jpg'),
(198, 2, 'Kopi Arabika Gayo Wine Process', 68000.00, 42, NULL, 'p198.jpg'),
(199, 7, 'Tanaman Monstera + Pot Putih', 149000.00, 21, NULL, 'p199.jpg')
ON CONFLICT (id) DO NOTHING;

-- Sinkronkan sequence agar INSERT berikutnya tidak tabrakan id
SELECT setval(pg_get_serial_sequence('categories', 'id'), (SELECT MAX(id) FROM categories));
SELECT setval(pg_get_serial_sequence('items', 'id'), (SELECT MAX(id) FROM items));
