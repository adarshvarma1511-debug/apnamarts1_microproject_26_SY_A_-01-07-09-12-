<?php
// =====================================================
// ALL FUNCTIONS FIRST
// =====================================================
function getCartCount($conn, $uid) {
    if (!$conn || $uid == 0) return 0;
    $stmt = $conn->prepare("SELECT SUM(qty) as total FROM cart WHERE user_id = ?");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $uid);
    if (!$stmt->execute()) return 0;
    $res = $stmt->get_result();
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return (int)($row['total'] ?? 0);
}

function getCart($conn, $uid) {
    if (!$conn || $uid == 0) return [];
    $cart = [];
    $stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ?");
    if (!$stmt) return [];
    $stmt->bind_param("i", $uid);
    if (!$stmt->execute()) return [];
    $res = $stmt->get_result();
    if (!$res) return [];
    while ($row = $res->fetch_assoc()) {
        $cart[$row['id']] = $row;
    }
    return $cart;
}

function getOrders($conn, $uid) {
    if (!$conn || $uid == 0) return [];
    $stmt = $conn->prepare("SELECT o.*, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON o.order_id = oi.order_id WHERE o.user_id = ? GROUP BY o.order_id ORDER BY o.created_at DESC LIMIT 10");
    if (!$stmt) return [];
    $stmt->bind_param("i", $uid);
    if (!$stmt->execute()) return [];
    $res = $stmt->get_result();
    if (!$res) return [];
    return $res->fetch_all(MYSQLI_ASSOC);
}

function getWishlist($conn, $uid) {
    if (!$conn || $uid == 0) return [];
    $stmt = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    if (!$stmt) return [];
    $stmt->bind_param("i", $uid);
    if (!$stmt->execute()) return [];
    $res = $stmt->get_result();
    if (!$res) return [];
    $result = $res->fetch_all(MYSQLI_ASSOC);
    return array_column($result, 'product_id');
}

function getUserStats($conn, $uid) {
    if (!$conn || $uid == 0) return ['total_orders' => 0, 'total_spent' => 0, 'member_since' => ''];
    $stmt = $conn->prepare("SELECT COUNT(*) as total_orders, COALESCE(SUM(total),0) as total_spent, MIN(created_at) as first_order FROM orders WHERE user_id = ?");
    if (!$stmt) return ['total_orders' => 0, 'total_spent' => 0, 'member_since' => ''];
    $stmt->bind_param("i", $uid);
    if (!$stmt->execute()) return ['total_orders' => 0, 'total_spent' => 0, 'member_since' => ''];
    $res = $stmt->get_result();
    if (!$res) return ['total_orders' => 0, 'total_spent' => 0, 'member_since' => ''];
    $row = $res->fetch_assoc();
    return $row ?: ['total_orders' => 0, 'total_spent' => 0, 'member_since' => ''];
}

function getUserProfile($conn, $uid) {
    if (!$conn || $uid == 0) return [];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    if (!$stmt) return [];
    $stmt->bind_param("i", $uid);
    if (!$stmt->execute()) return [];
    $res = $stmt->get_result();
    if (!$res) return [];
    return $res->fetch_assoc() ?? [];
}

function getAddresses($conn, $uid) {
    if (!$conn || $uid == 0) return [];
    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    if (!$stmt) return [];
    $stmt->bind_param("i", $uid);
    if (!$stmt->execute()) return [];
    $res = $stmt->get_result();
    if (!$res) return [];
    return $res->fetch_all(MYSQLI_ASSOC);
}

// ---- DB SETUP: Create extra tables if not exist ----
function ensureTables($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        category VARCHAR(100) NOT NULL,
        img TEXT NOT NULL
    )");
    
    $res = $conn->query("SELECT COUNT(*) as c FROM products");
    if ($res && $res->fetch_assoc()['c'] == 0) {
        $seed = [
            ['name'=>'Wheat Atta','price'=>45,'category'=>'Atta','img'=>'https://2.wlimg.com/product_images/bc-small/2025/11/14978261/premium-multigrain-wheat-flour-1762163885-8414485.jpeg'],
            ['name'=>'Rice','price'=>60,'category'=>'Rice','img'=>'https://thehammergoods.com/assets/images/products/minikit%20husk%201.png'],
            ['name'=>'Dal','price'=>90,'category'=>'Dal','img'=>'https://tiimg.tistatic.com/fp/1/003/177/chana-dal-655.jpg'],
            ['name'=>'Sugar','price'=>40,'category'=>'Others','img'=>'https://hsingredients.com/wp-content/uploads/sweeteners_product-300x200.jpg'],
            ['name'=>'Salt','price'=>20,'category'=>'Spices','img'=>'https://img1.exportersindia.com/product_images/bc-small/2024/2/13113700/iodised-salt-1709036249-7313249.jpeg'],
            ['name'=>'Cooking Oil','price'=>140,'category'=>'Oils','img'=>'https://www.foodfair.gd/media/catalog/product/cache/cf74414bcb44f5c30fb7c110990adbd7/F/F/FF1000194_QvehWzlAlkaQyKWO.jpg'],
            ['name'=>'Tea','price'=>120,'category'=>'Others','img'=>'https://rukminim2.flixcart.com/image/300/300/xif0q/tea/f/h/s/100-matcha-tea-100g-japanese-matcha-green-tea-matcha-tea-powder-original-imahdnr8hsy9rxdw.jpeg'],
            ['name'=>'Coffee','price'=>180,'category'=>'Others','img'=>'https://cdn.kindlife.in/images/detailed/149/COFFEE_POWDER_JAR_1.jpg?t=1705124575'],
            ['name'=>'Milk Powder','price'=>220,'category'=>'Dairy','img'=>'https://img.freepik.com/premium-psd/skimmed-milk-powder-sachets-product-display_1288574-913.jpg'],
            ['name'=>'Ghee','price'=>520,'category'=>'Dairy','img'=>'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTIj9ZYDAzdZNQ40SU1rHO-ZstjRBeMWe4ynw&s'],
            ['name'=>'Sunflower Oil 1L','price'=>160,'category'=>'Oils','img'=>'https://imgs.search.brave.com/D8BLjN4Gle5PyU4rQ-Ec3H5M-0qVwxYkurAy71LNclg/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly81Lmlt/aW1nLmNvbS9kYXRh/NS9BTkRST0lEL0Rl/ZmF1bHQvMjAyMS85/L0VVL0RJL0JRLzg0/MTUyODYvaW1nLTIw/MjEwODA4LXdhMDA1/OC1qcGctNTAweDUw/MC5qcGc'],
            ['name'=>'Mustard Oil 1L','price'=>180,'category'=>'Oils','img'=>'https://imgs.search.brave.com/w04CqBUXN4y2zX1SrIHfJjew6nVSQA6uPn2zTivR404/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly9ydWtt/aW5pbTIuZmxpeGNh/cnQuY29tL2ltYWdl/LzI4MC8zNzAveGlm/MHEvZWRpYmxlLW9p/bC80LzQveC8xLXdv/b2QtcHJlc3NlZC13/aGl0ZS1zZXNhbWUt/b2lsLTEwMC1uYXR1/cmFsLTFsLWZvci1j/b29raW5nLXNraW4t/b3JpZ2luYWwtaW1h/aGM3aDV5dnJwZnJj/OS5qcGVnP3E9OTA'],
            ['name'=>'Olive Oil 500ml','price'=>450,'category'=>'Oils','img'=>'https://imgs.search.brave.com/Qk4rCu5UtSIskXIIKnyGCQR7_ne6c8yqIz8vD74GEIk/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly93d3cu/YW52ZXNoYW4uZmFy/bS9jZG4vc2hvcC9m/aWxlcy9vbGl2ZS1v/aWwtNTAwbWxfYzlj/ZWJlZjgtYzVhOS00/MzMwLTk0NjgtZWU5/ZDRlOTVlY2M2Lmpw/Zz92PTE3NDk1NzYx/NTQmd2lkdGg9MTQ0/NQ'],
            ['name'=>'Soybean Oil 1L','price'=>150,'category'=>'Oils','img'=>'https://imgs.search.brave.com/2Hwh8BNh-eRRehQj_EZTAL1j5zPfu78Ec-bqxTJBdIs/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly9zLmFs/aWNkbi5jb20vQHNj/MDQva2YvQWU0MDcz/NTdlNTEwMzQ1NmM5/MzM1NWYxZWZiMTZi/ZGMxdy5qcGdfMzAw/eDMwMC5qcGc'],
            ['name'=>'Coconut Oil 500ml','price'=>200,'category'=>'Oils','img'=>'https://m.media-amazon.com/images/I/71Egl5tvcWL.jpg'],
            ['name'=>'Multigrain Atta 5kg','price'=>250,'category'=>'Atta','img'=>'https://m.media-amazon.com/images/I/71mIEGQ+31L.jpg'],
            ['name'=>'Chakki Fresh Atta 10kg','price'=>420,'category'=>'Atta','img'=>'https://images-eu.ssl-images-amazon.com/images/I/61H4YvoS61L._AC_UL165_SR165,165_.jpg'],
            ['name'=>'Besan (Gram Flour) 1kg','price'=>90,'category'=>'Atta','img'=>'https://images-eu.ssl-images-amazon.com/images/I/714uCiJsIRL._AC_UL300_SR300,200_.jpg'],
            ['name'=>'Maida 1kg','price'=>60,'category'=>'Atta','img'=>'https://5.imimg.com/data5/ANDROID/Default/2021/4/UK/SJ/VH/38454797/img-20210414-wa0001-jpg-500x500.jpg'],
            ['name'=>'Ragi Flour 1kg','price'=>110,'category'=>'Atta','img'=>'https://rukminim2.flixcart.com/image/800/1070/kfh5ifk0/flour/6/g/a/1-premium-quality-ragi-atta-finger-millet-flour-1kg-1-ragi-flour-original-imafvxzyw2tggupg.jpeg'],
            ['name'=>'Toor Dal 1kg','price'=>130,'category'=>'Dal','img'=>'https://rukminim2.flixcart.com/image/280/370/xif0q/pulses/z/t/e/500-toor-dal-500g-1-toor-dal-melghat-pure-original-imahk3a4rgmkgd2a.jpeg'],
            ['name'=>'Moong Dal 1kg','price'=>120,'category'=>'Dal','img'=>'https://imgs.search.brave.com/yZtHg_hKEZyvNIxRKPkgHNAQzi9gkarzza_BegC8-7c/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly9ydWtt/aW5pbTIuZmxpeGNh/cnQuY29tL2ltYWdl/LzgwMC8xMDcwL3hp/ZjBxL2ZtY2ctY29t/Ym8vZC9hL2MvcG9w/dWxhci1tb29uZy1k/YWwtNTAwZy11cmFk/LWNoaWxrYS1kYWwt/NTAwZy1handhaW4t/NTAwZ20tY29tYm8t/b3JpZ2luYWwtaW1h/aGN1dnlueGd5Ynpi/ei5qcGVnP3E9OTA'],
            ['name'=>'Masoor Dal 1kg','price'=>100,'category'=>'Dal','img'=>'https://imgs.search.brave.com/JHSbNBf5fuwhmFtWvqnwzAZ5j0yuLoo7Wk6stfXVkV0/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly9ydWtt/aW5pbTIuZmxpeGNh/cnQuY29tL2ltYWdl/LzI4MC8zNzAva3Z0/dXhlODAvcHVsc2Vz/L3Uvcy9rLzEwMDAt/cHJlbWl1bS1xdWFs/aXR5LWJsYWNrLW1h/c29vci1kYWwtMWtn/LXBhY2stb2YtMS0x/LW1hc29vci1kYWwt/b3JpZ2luYWwtaW1h/ZzhteXBnc3NoaGh2/bS5qcGVnP3E9OTA'],
            ['name'=>'Chana Dal 1kg','price'=>95,'category'=>'Dal','img'=>'https://frugivore-bucket.s3.amazonaws.com/media/package/img_one/2021-01-18/24_Mantra_Organic_Chana_Dal_1kg.jpg'],
            ['name'=>'Urad Dal 1kg','price'=>140,'category'=>'Dal','img'=>'https://5.imimg.com/data5/SELLER/Default/2022/9/SU/RN/TK/48023543/1-500x500.jpg'],
            ['name'=>'Basmati Rice 5kg','price'=>550,'category'=>'Rice','img'=>'https://5.imimg.com/data5/SELLER/Default/2022/9/SU/RN/TK/48023543/1-500x500.jpg'],
            ['name'=>'Sona Masoori Rice 5kg','price'=>420,'category'=>'Rice','img'=>'https://imgs.search.brave.com/Qd5P1ecpJIflRfzhIpadEHj4zrcWE44bhsaKbeja4SI/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly81Lmlt/aW1nLmNvbS9kYXRh/NS9BTkRST0lEL0Rl/ZmF1bHQvMjAyNC8y/LzM4MzcwNTY5NS9O/Qy9YVC9PSS83MDc3/MTMwOS9wcm9kdWN0/LWpwZWctNTAweDUw/MC5qcGc'],
            ['name'=>'Brown Rice 1kg','price'=>120,'category'=>'Rice','img'=>'https://imgs.search.brave.com/_kYX8RtHuxnNUyJMUf4_TuPYucFUW7CJUMMn7Xmh7QQ/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly9ydWtt/aW5pbTIuZmxpeGNh/cnQuY29tL2ltYWdl/LzI4MC8zNzAveGlm/MHEvcmljZS82L2ov/ai8xLWJyb3duLXJp/Y2UtMWtnLXVucG9s/aXNoZWQtMTAwLW5h/dHVyYWwtbmF0dXJh/bGx5LWxvdy1naS1o/aWdoLWluLW9yaWdp/bmFsLWltYWhoc3d4/NW42eTdobXouanBl/Zz9xPTkw'],
            ['name'=>'Kolam Rice 5kg','price'=>400,'category'=>'Rice','img'=>'https://imgs.search.brave.com/IGT-2nBlX4oUEs1FaoVoBrz3PBOydF5i1iJG0RMDe64/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly81Lmlt/aW1nLmNvbS9kYXRh/NS9BTkRST0lEL0Rl/ZmF1bHQvMjAyNC8x/Mi80NzExNTY2ODEv/RkMvTEsvSUsvMTY0/MjE5Mzg1L3Byb2R1/Y3QtanBlZy01MDB4/NTAwLmpwZWc'],
            ['name'=>'Jeera Rice 1kg','price'=>140,'category'=>'Rice','img'=>'https://5.imimg.com/data5/IOS/Default/2024/1/376449417/SC/ON/TA/38768188/product-jpeg-500x500.png'],
            ['name'=>'Organic Wheat Flour 1kg','price'=>120,'category'=>'Organic','img'=>'https://zamaorganics.com/cdn/shop/files/IMG_0709_11zon_1.jpg'],
            ['name'=>'Organic Rice 1kg','price'=>150,'category'=>'Organic','img'=>'https://rukminim2.flixcart.com/image/800/800/l4zxn680/rice/u/p/a/1-brown-basmati-rice-1-kg-brown-raw-pouch-basmati-rice-organic-original-imagfrqcrwzcw64b.jpeg'],
            ['name'=>'Organic Honey 500g','price'=>250,'category'=>'Organic','img'=>'https://www.natures-nectar.com/cdn/shop/products/IMG_55242000x2000_250x250@2x.png'],
            ['name'=>'Organic Sugar 1kg','price'=>90,'category'=>'Organic','img'=>'https://rukminim2.flixcart.com/image/800/800/khp664w0-0/sugar/m/q/z/1000-organic-coconut-sugar-1kg-pack-of-2-pouch-coconut-sugar-original-imafxmwayfnyxvjc.jpeg'],
            ['name'=>'Organic Turmeric Powder 200g','price'=>80,'category'=>'Organic','img'=>'https://imgs.search.brave.com/QppNibxWUU64CVpguGV6dN7JVHx-mwkQR8RHCAX2wd8/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly93d3cu/a2VyYWxhc3BpY2Vz/b25saW5lLmNvbS93/cC1jb250ZW50L3Vw/bG9hZHMvMjAyMC8x/MS9JTUctMjAyMDEx/MTAtV0EwMDY5LTMw/MHgzMDAud2VicA'],
            ['name'=>'Turmeric Powder 200g','price'=>60,'category'=>'Spices','img'=>'https://imgs.search.brave.com/QppNibxWUU64CVpguGV6dN7JVHx-mwkQR8RHCAX2wd8/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly93d3cu/a2VyYWxhc3BpY2Vz/b25saW5lLmNvbS93/cC1jb250ZW50L3Vw/bG9hZHMvMjAyMC8x/MS9JTUctMjAyMDEx/MTAtV0EwMDY5LTMw/MHgzMDAud2VicA'],
            ['name'=>'Red Chilli Powder 200g','price'=>70,'category'=>'Spices','img'=>'https://5.imimg.com/data5/SELLER/Default/2022/3/VN/OD/SV/1998375/02-chilli-powder-200g-500x500.png'],
            ['name'=>'Garam Masala 100g','price'=>85,'category'=>'Spices','img'=>'https://5.imimg.com/data5/SELLER/Default/2022/3/VN/OD/SV/1998375/02-chilli-powder-200g-500x500.png'],
            ['name'=>'Coriander Powder 200g','price'=>55,'category'=>'Spices','img'=>'https://i0.wp.com/rcmdeal.in/wp-content/uploads/2021/04/RCM-CORIANDER-POWDER200g.jpg'],
            ['name'=>'Cumin Seeds 100g','price'=>65,'category'=>'Spices','img'=>'https://rukminim2.flixcart.com/image/280/370/xif0q/spice-masala/d/4/a/100-cumin-seed-jeera-100-natural-spice-no-added-colors-or-original-imahb2gg6pff5xhg.jpeg'],
            ['name'=>'Full Cream Milk 1L','price'=>60,'category'=>'Dairy','img'=>'https://tiimg.tistatic.com/fp/1/009/196/full-cream-milk-964.jpg'],
            ['name'=>'Paneer 200g','price'=>90,'category'=>'Dairy','img'=>'https://imgs.search.brave.com/Q5nLUjSsgbGCexc3bN6U5wsPu3O2xmeQVjtmZl5GMYA/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly9jZG4u/bWFmcnNlcnZpY2Vz/LmNvbS9zeXMtbWFz/dGVyLXJvb3QvaDI1/L2gxYi80NTMyNDAw/NTAxNTU4Mi8xMjM4/MzAwX21haW4uanBn/P2ltPVJlc2l6ZT00/ODA'],
            ['name'=>'Butter 500g','price'=>250,'category'=>'Dairy','img'=>'https://rukminim2.flixcart.com/image/800/1070/jmjhifk0/butter/j/q/y/500-pasteurised-butter-unsalted-nandini-original-imaf9fqqzapsguzp.jpeg'],
            ['name'=>'Cheese Slices 200g','price'=>140,'category'=>'Dairy','img'=>'https://frugivore-bucket.s3.amazonaws.com/media/package/img_one/2020-05-26/Mother_Dairy_Cheese_Slices_-_200_Gm.jpg'],
            ['name'=>'Curd 500g','price'=>40,'category'=>'Dairy','img'=>'https://m.media-amazon.com/images/I/61TilidAYnL.jpg'],
            ['name'=>'Maggi Noodles Pack','price'=>15,'category'=>'Others','img'=>'https://rukminim2.flixcart.com/image/800/800/xif0q/noodle/e/5/s/832-2-minutes-masala-noodles-pack-832-g-1-instant-noodles-maggi-original-imagrxd8g7exswz3.jpeg'],
            ['name'=>'Biscuits Pack','price'=>30,'category'=>'Others','img'=>'https://m.media-amazon.com/images/I/51vOV4G9NuL.jpg'],
            ['name'=>'Cornflakes 500g','price'=>180,'category'=>'Others','img'=>'https://www.tops.in/cdn/shop/files/CornflakesPouch500g.jpg'],
            ['name'=>'Oats 1kg','price'=>150,'category'=>'Others','img'=>'https://rukminim2.flixcart.com/image/280/370/xif0q/cereal-flake/v/j/m/1-4-1kg-x-1-jar-and-0-4kg-x-1-pouch-steel-cut-rolled-oats-original-imahg8dmp65pzvhq.jpeg'],
            ['name'=>'Peanut Butter 500g','price'=>220,'category'=>'Others','img'=>'https://rukminim2.flixcart.com/image/800/800/kf4ajrk0/jam-spread/p/k/6/500-peanut-butter-unsweetened-500g-sugar-free-gluten-free-original-imafvng9gh4mbb3y.jpeg']
        ];
        $stmt = $conn->prepare("INSERT INTO products (name, price, category, img) VALUES (?, ?, ?, ?)");
        foreach ($seed as $p) {
            $stmt->bind_param("sdss", $p['name'], $p['price'], $p['category'], $p['img']);
            $stmt->execute();
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS wishlist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_wish (user_id, product_id)
    )");
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        avatar_letter VARCHAR(2) DEFAULT '',
        email VARCHAR(255) DEFAULT '',
        phone VARCHAR(20) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $conn->query("INSERT IGNORE INTO users (username, password, email) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@apnamart.com')");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT ''");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT ''");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_letter VARCHAR(2) DEFAULT ''");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $conn->query("CREATE TABLE IF NOT EXISTS user_addresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        label VARCHAR(50) DEFAULT 'Home',
        full_name VARCHAR(100),
        phone VARCHAR(20),
        address_line TEXT,
        city VARCHAR(100),
        pincode VARCHAR(10),
        is_default TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        username VARCHAR(255),
        action VARCHAR(100) NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_user (user_id),
        INDEX idx_activity_type (action_type),
        INDEX idx_activity_time (timestamp)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255),
        status VARCHAR(20),
        ip_address VARCHAR(45),
        user_agent TEXT,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_user (username),
        INDEX idx_login_time (attempt_time)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS product_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        user_id INT,
        view_count INT DEFAULT 1,
        last_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_product_view (product_id, user_id),
        INDEX idx_product (product_id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS payment_failures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        order_id INT,
        amount DECIMAL(10,2),
        failure_reason VARCHAR(255),
        failure_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payment_user (user_id),
        INDEX idx_payment_time (failure_time)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS admin_actions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT,
        admin_name VARCHAR(255),
        action VARCHAR(100),
        target_type VARCHAR(50),
        target_id INT,
        details TEXT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_action (admin_id),
        INDEX idx_admin_time (timestamp)
    )");
}

function logActivity($conn, $uid, $username, $action, $actionType, $details = '') {
    if (!$conn) return false;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, username, action, action_type, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("issssss", $uid, $username, $action, $actionType, $details, $ip, $ua);
    return $stmt->execute();
}

function logLoginAttempt($conn, $username, $status) {
    if (!$conn) return false;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO login_attempts (username, status, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("ssss", $username, $status, $ip, $ua);
    return $stmt->execute();
}

function getAdminDashboardStats($conn) {
    if (!$conn) return [];
    $stats = [
        'total_orders' => 0,
        'total_revenue' => 0,
        'orders_today' => 0,
        'revenue_today' => 0,
        'total_users' => 0,
        'new_users_today' => 0,
        'total_products' => 0,
        'active_carts' => 0,
        'avg_order_value' => 0,
        'abandoned_carts_7d' => 0,
    ];

    $res = $conn->query("SELECT COUNT(*) as total_orders, COALESCE(SUM(total),0) as total_revenue FROM orders");
    if ($res) {
        $row = $res->fetch_assoc();
        $stats['total_orders'] = (int)$row['total_orders'];
        $stats['total_revenue'] = (float)$row['total_revenue'];
    }

    $res = $conn->query("SELECT COUNT(*) as orders_today, COALESCE(SUM(total),0) as revenue_today FROM orders WHERE DATE(created_at)=CURDATE()");
    if ($res) {
        $row = $res->fetch_assoc();
        $stats['orders_today'] = (int)$row['orders_today'];
        $stats['revenue_today'] = (float)$row['revenue_today'];
    }

    $res = $conn->query("SELECT COUNT(*) as total_users FROM users WHERE username != 'admin'");
    if ($res) $stats['total_users'] = (int)$res->fetch_assoc()['total_users'];
    $res = $conn->query("SELECT COUNT(*) as new_users_today FROM users WHERE DATE(created_at)=CURDATE() AND username != 'admin'");
    if ($res) $stats['new_users_today'] = (int)$res->fetch_assoc()['new_users_today'];
    $res = $conn->query("SELECT COUNT(*) as total_products FROM products");
    if ($res) $stats['total_products'] = (int)$res->fetch_assoc()['total_products'];
    $res = $conn->query("SELECT COUNT(DISTINCT user_id) as active_carts FROM cart");
    if ($res) $stats['active_carts'] = (int)$res->fetch_assoc()['active_carts'];
    $stats['avg_order_value'] = $stats['total_orders'] ? round($stats['total_revenue'] / $stats['total_orders'], 2) : 0;
    $res = $conn->query("SELECT COUNT(*) as abandoned_carts_7d FROM activity_logs WHERE action_type='cart' AND timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)");
    if ($res) $stats['abandoned_carts_7d'] = (int)$res->fetch_assoc()['abandoned_carts_7d'];
    return $stats;
}

function getRecentActivityLogs($conn, $limit = 50) {
    if (!$conn) return [];
    $stmt = $conn->prepare("SELECT * FROM activity_logs ORDER BY timestamp DESC LIMIT ?");
    if (!$stmt) return [];
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getProductPerformance($conn) {
    if (!$conn) return [];
    $res = $conn->query("SELECT p.id, p.name, p.category, p.price, p.img, 
        COALESCE(SUM(oi.qty),0) as units_sold, 
        COALESCE(SUM(oi.qty * oi.price),0) as revenue,
        COALESCE(SUM(pv.view_count),0) as view_count
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN product_views pv ON p.id = pv.product_id
        GROUP BY p.id
        ORDER BY revenue DESC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function getUserActivityReport($conn) {
    if (!$conn) return [];
    $res = $conn->query("SELECT u.id, u.username, u.email, u.created_at, 
        COALESCE(COUNT(DISTINCT o.order_id),0) as total_orders,
        COALESCE(SUM(o.total),0) as total_spent,
        COALESCE(SUM(c.qty),0) as items_in_cart,
        COALESCE(COUNT(DISTINCT w.product_id),0) as wishlisted_items
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        LEFT JOIN cart c ON u.id = c.user_id
        LEFT JOIN wishlist w ON u.id = w.user_id
        WHERE u.username != 'admin'
        GROUP BY u.id
        ORDER BY total_spent DESC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function getSecurityData($conn) {
    if (!$conn) return [];
    $res = $conn->query("SELECT COUNT(*) as failed_logins FROM login_attempts WHERE status='failed' AND attempt_time > DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $failed = $res ? (int)$res->fetch_assoc()['failed_logins'] : 0;
    $res = $conn->query("SELECT COUNT(DISTINCT username) as suspicious_users FROM login_attempts WHERE status='failed' GROUP BY username HAVING COUNT(*) > 5");
    return [
        'failed_logins_7d' => $failed,
        'suspicious_users' => $res ? $res->num_rows : 0,
    ];
}

function getCategoryBreakdown($conn) {
    if (!$conn) return [];
    $res = $conn->query("SELECT p.category, COALESCE(SUM(oi.qty),0) as total_units, COALESCE(SUM(oi.qty * oi.price),0) as category_revenue, COUNT(DISTINCT o.order_id) as orders_count 
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.order_id
        GROUP BY p.category
        ORDER BY category_revenue DESC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function getRevenueByDay($conn, $days = 30) {
    if (!$conn) return [];
    $res = $conn->query("SELECT DATE(created_at) as day, COUNT(*) as orders, COALESCE(SUM(total),0) as revenue 
        FROM orders 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL $days DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function getPaymentFailures($conn, $limit = 20) {
    if (!$conn) return [];
    $stmt = $conn->prepare("SELECT * FROM payment_failures ORDER BY failure_time DESC LIMIT ?");
    if (!$stmt) return [];
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

session_start();
$conn = null;
$error = "";
$success = "";
$cartCount = 0;
$isLoggedIn = isset($_SESSION['user_id']);
$uid = $_SESSION['user_id'] ?? 0;

try {
    $conn = new mysqli("localhost", "root", "");
    if ($conn->connect_error) {
        throw new Exception("Cannot connect to MySQL: " . $conn->connect_error);
    }
    $conn->query("CREATE DATABASE IF NOT EXISTS apna_marts1");
    $conn->select_db("apna_marts1");
} catch (Exception $e) {
    $error = "Database connection failed: " . $e->getMessage();
}

if ($conn && !$conn->connect_error) {
    ensureTables($conn);
    $cartCount = getCartCount($conn, $uid);
}

// AUTH OPERATIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['auth_action'])) {
        if ($_POST['auth_action'] === 'signup') {
            $user = trim($_POST['username'] ?? '');
            $pass = trim($_POST['password'] ?? '');
            $confirm = trim($_POST['confirm_password'] ?? '');
            if ($pass !== $confirm) {
                $error = "Passwords don't match!";
            } else if (strlen($pass) < 4) {
                $error = "Password too short!";
            } else {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $letter = strtoupper(substr($user, 0, 1));
                $stmt = $conn->prepare("INSERT IGNORE INTO users (username, password, avatar_letter) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $user, $hashed, $letter);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $_SESSION['user_id'] = $conn->insert_id;
                        $_SESSION['username'] = $user;
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit;
                    } else {
                        $error = "Username exists!";
                    }
                } else {
                    $error = "Signup failed: " . $stmt->error;
                }
            }
        } else if ($_POST['auth_action'] === 'login') {
            $user = trim($_POST['username']);
            $pass = $_POST['password'];
            $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
            $stmt->bind_param("s", $user);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (password_verify($pass, $row['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    logLoginAttempt($conn, $user, 'success');
                    logActivity($conn, $row['id'], $row['username'], 'Login', 'auth', 'User logged in');
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            }
            logLoginAttempt($conn, $user, 'failed');
            $error = "Invalid credentials!";
        } else if ($_POST['auth_action'] === 'logout') {
            if ($isLoggedIn) {
                logActivity($conn, $uid, $_SESSION['username'], 'Logout', 'auth', 'User logged out');
            }
            session_destroy();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// AJAX HANDLERS
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $conn) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $id = (int)($_POST['id'] ?? 0);

    $products = [];
    $res = $conn->query("SELECT * FROM products");
    while ($row = $res->fetch_assoc()) {
        $products[(int)$row['id']] = $row;
    }

    if ($_SESSION['username'] === 'admin') {
        if ($action === 'get_admin_dashboard') {
            exit(json_encode(getAdminDashboardStats($conn)));
        }
        if ($action === 'get_activity_logs') {
            exit(json_encode(getRecentActivityLogs($conn, 50)));
        }
        if ($action === 'get_product_performance') {
            exit(json_encode(getProductPerformance($conn)));
        }
        if ($action === 'get_user_report') {
            exit(json_encode(getUserActivityReport($conn)));
        }
        if ($action === 'get_security_data') {
            exit(json_encode(getSecurityData($conn)));
        }
        if ($action === 'get_category_breakdown') {
            exit(json_encode(getCategoryBreakdown($conn)));
        }
        if ($action === 'get_revenue_chart') {
            exit(json_encode(getRevenueByDay($conn, 30)));
        }
        if ($action === 'get_payment_failures') {
            exit(json_encode(getPaymentFailures($conn, 30)));
        }
    }

    if ($action === 'add_product' && $_SESSION['username'] === 'admin') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? 'Others');
        $img = trim($_POST['img'] ?? '');
        $stmt = $conn->prepare("INSERT INTO products (name, price, category, img) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdss", $name, $price, $category, $img);
        $stmt->execute();
        $prodId = $conn->insert_id;
        logActivity($conn, $uid, $_SESSION['username'], 'Add Product', 'admin', "Product: $name (ID: $prodId), Price: ₹$price, Category: $category");
        exit(json_encode(['success' => true]));
    }
    
    if ($action === 'delete_product' && $_SESSION['username'] === 'admin') {
        $stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $prodName = $prod['name'] ?? 'Unknown';
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt = $conn->prepare("DELETE FROM wishlist WHERE product_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        logActivity($conn, $uid, $_SESSION['username'], 'Delete Product', 'admin', "Product: $prodName (ID: $id)");
        exit(json_encode(['success' => true]));
    }

    if ($action === 'update') {
        if (!isset($products[$id])) exit(json_encode(['count'=>0]));
        $change = (int)($_POST['change'] ?? 0);
        $stmt = $conn->prepare("SELECT qty FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $uid);
        $stmt->execute();
        $check = $stmt->get_result();
        if ($check->num_rows > 0) {
            $row = $check->fetch_assoc();
            $newQty = $row['qty'] + $change;
            if ($newQty <= 0) {
                $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $id, $uid);
            } else {
                $stmt = $conn->prepare("UPDATE cart SET qty = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param("iii", $newQty, $id, $uid);
            }
        } elseif ($change > 0) {
            $name = $products[$id]['name'];
            $price = $products[$id]['price'];
            $stmt = $conn->prepare("INSERT INTO cart (id, name, price, qty, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isdii", $id, $name, $price, $change, $uid);
        }
        $stmt->execute();
        exit(json_encode(['count' => getCartCount($conn, $uid)]));
    }

    if ($action === 'fetch') {
        exit(json_encode(getCart($conn, $uid)));
    }

    if ($action === 'placeorder') {
        $cart = getCart($conn, $uid);
        if (empty($cart)) exit(json_encode(['error' => 'Cart empty']));
        $total = 0;
        foreach ($cart as $item) $total += $item['price'] * $item['qty'];
        $coupon = strtoupper(trim($_POST['coupon'] ?? ''));
        $discount = 0;
        switch ($coupon) {
            case 'SAVE10':  $discount = $total * 0.10; break;
            case 'SAVE20':  $discount = $total * 0.20; break;
            case 'SAVE30':  $discount = $total * 0.30; break;
            case 'FLAT50':  $discount = 50; break;
            case 'FLAT100': $discount = 100; break;
            case 'FIRSTORDER': if ($total >= 300) $discount = $total * 0.15; break;
            default: $coupon = ''; break;
        }
        if ($discount > $total) $discount = $total;
        $finalTotal = $total - $discount;
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total, coupon_used) VALUES (?, ?, ?)");
        $stmt->bind_param("ids", $uid, $finalTotal, $coupon);
        $stmt->execute();
        $orderId = $conn->insert_id;
        foreach ($cart as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, name, price, qty) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisdi", $orderId, $item['id'], $item['name'], $item['price'], $item['qty']);
            $stmt->execute();
        }
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        exit(json_encode(['success' => true, 'order_id' => $orderId]));
    }

    if ($action === 'getorders') {
        exit(json_encode(getOrders($conn, $uid)));
    }

    // ---- WISHLIST ACTIONS ----
    if ($action === 'wishlist_toggle') {
        $stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $uid, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $uid, $id);
            $stmt->execute();
            exit(json_encode(['wishlisted' => false]));
        } else {
            $stmt = $conn->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $uid, $id);
            $stmt->execute();
            exit(json_encode(['wishlisted' => true]));
        }
    }

    if ($action === 'get_wishlist') {
        exit(json_encode(getWishlist($conn, $uid)));
    }

    // ---- PROFILE UPDATE ----
    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $stmt = $conn->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $email, $phone, $uid);
        $stmt->execute();
        exit(json_encode(['success' => true]));
    }

    if ($action === 'change_password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($new !== $confirm) exit(json_encode(['error' => "Passwords don't match"]));
        if (strlen($new) < 4) exit(json_encode(['error' => 'Password too short']));
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!password_verify($old, $row['password'])) exit(json_encode(['error' => 'Current password incorrect']));
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $uid);
        $stmt->execute();
        exit(json_encode(['success' => true]));
    }

    // ---- ADDRESS ACTIONS ----
    if ($action === 'add_address') {
        $label    = trim($_POST['label'] ?? 'Home');
        $fullname = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $addr     = trim($_POST['address_line'] ?? '');
        $city     = trim($_POST['city'] ?? '');
        $pin      = trim($_POST['pincode'] ?? '');
        $def      = (int)($_POST['is_default'] ?? 0);
        if ($def) {
            $stmt = $conn->prepare("UPDATE user_addresses SET is_default=0 WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
        }
        $stmt = $conn->prepare("INSERT INTO user_addresses (user_id, label, full_name, phone, address_line, city, pincode, is_default) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssssi", $uid, $label, $fullname, $phone, $addr, $city, $pin, $def);
        $stmt->execute();
        exit(json_encode(['success' => true, 'id' => $conn->insert_id]));
    }

    if ($action === 'delete_address') {
        $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $uid);
        $stmt->execute();
        exit(json_encode(['success' => true]));
    }

    if ($action === 'set_default_address') {
        $stmt = $conn->prepare("UPDATE user_addresses SET is_default=0 WHERE user_id=?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt = $conn->prepare("UPDATE user_addresses SET is_default=1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $uid);
        $stmt->execute();
        exit(json_encode(['success' => true]));
    }

    if ($action === 'get_addresses') {
        exit(json_encode(getAddresses($conn, $uid)));
    }

    if ($action === 'get_profile') {
        $profile = getUserProfile($conn, $uid);
        $stats   = getUserStats($conn, $uid);
        exit(json_encode(['profile' => $profile, 'stats' => $stats]));
    }
}

// Load data for PHP-rendered parts
$userProfile = $isLoggedIn ? getUserProfile($conn, $uid) : [];
$avatarLetter = strtoupper(substr($userProfile['username'] ?? 'U', 0, 1));

$allProducts = [];
if ($conn) {
    $res = $conn->query("SELECT * FROM products");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['price'] = (float)$row['price'];
            $row['id'] = (int)$row['id'];
            $allProducts[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apna Mart | Digital Grocery</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary-rgb: 255, 255, 255;
            --bg-secondary-rgb: 248, 250, 252;
            --primary: #10b981; --primary-dark: #059669; --accent: #f59e0b;
            --success: #10b981; --danger: #ef4444; --bg-primary: #ffffff;
            --bg-secondary: #f8fafc; --text-primary: #0f172a; --text-secondary: #475569;
            --border: #e2e8f0; --shadow-sm: 0 1px 2px 0 rgb(0 0 0/0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0/0.1); --shadow-lg: 0 20px 25px -5px rgb(0 0 0/0.1);
            --radius-sm: 8px; --radius-md: 12px; --radius-lg: 20px;
        }
        [data-theme="dark"] {
            --bg-primary: #0f172a; --bg-primary-rgb: 15, 23, 42;
            --bg-secondary: #1e293b; --bg-secondary-rgb: 30, 41, 59;
            --text-primary: #f8fafc; --text-secondary: #94a3b8;
            --border: #334155; --shadow-sm: 0 1px 2px 0 rgb(0 0 0/0.3);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0/0.4); --shadow-lg: 0 20px 25px -5px rgb(0 0 0/0.5);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--border) 100%); color: var(--text-primary); min-height: 100vh; transition: background 0.3s, color 0.3s; }

        /* NAVBAR */
        .navbar { background: rgba(var(--bg-primary-rgb),0.95); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; padding: 0.75rem 2rem; box-shadow: var(--shadow-sm); }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .logo { font-size: 1.6rem; font-weight: 800; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; white-space: nowrap; cursor: pointer; }
        .search-container { flex: 1; max-width: 500px; margin: 0 1.5rem; position: relative; }
        .search-input { width: 100%; height: 44px; padding: 0 1rem 0 3rem; border: 2px solid var(--border); border-radius: var(--radius-lg); font-size: 1rem; background: var(--bg-secondary); color: var(--text-primary); transition: all 0.3s ease; }
        .search-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16,185,129,0.1); background: var(--bg-primary); }

        /* AVATAR */
        .avatar-circle { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; cursor: pointer; flex-shrink: 0; border: 2px solid rgba(255,255,255,0.5); box-shadow: var(--shadow-md); transition: transform 0.2s; }
        .avatar-circle:hover { transform: scale(1.08); }

        /* DROPDOWN MENU */
        .user-dropdown-wrapper { position: relative; }
        .user-dropdown { position: absolute; right: 0; top: calc(100% + 0.75rem); background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); min-width: 240px; z-index: 200; overflow: hidden; animation: dropIn 0.2s ease; }
        @keyframes dropIn { from { opacity:0; transform: translateY(-8px); } to { opacity:1; transform: translateY(0); } }
        .dropdown-header { padding: 1rem 1.25rem; background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(245,158,11,0.08)); border-bottom: 1px solid var(--border); }
        .dropdown-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.25rem; cursor: pointer; transition: background 0.15s; font-size: 0.9rem; color: var(--text-primary); border: none; background: none; width: 100%; text-align: left; }
        .dropdown-item:hover { background: var(--bg-secondary); }
        .dropdown-item i { width: 18px; color: var(--primary); }
        .dropdown-divider { height: 1px; background: var(--border); margin: 0.25rem 0; }

        /* BUTTONS */
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; transition: all 0.2s ease; text-decoration: none; font-size: 0.9rem; color: inherit; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; box-shadow: var(--shadow-md); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: var(--shadow-lg); }
        .btn-secondary { background: rgba(16,185,129,0.1); color: var(--primary); border: 2px solid rgba(16,185,129,0.2); }
        .btn-secondary:hover { background: rgba(16,185,129,0.2); }
        .btn-danger { background: rgba(239,68,68,0.1); color: #ef4444; border: 2px solid rgba(239,68,68,0.2); }
        .btn-ghost { background: none; border: 1.5px solid var(--border); color: var(--text-secondary); }
        .btn-ghost:hover { border-color: var(--primary); color: var(--primary); background: rgba(16,185,129,0.05); }

        /* CART BADGE */
        .cart-badge-wrap { position: relative; }
        .cart-badge { position: absolute; top: -6px; right: -6px; background: var(--danger); color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 0.7rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }

        /* PAGE SECTIONS */
        .page-section { display: none; }
        .page-section.active { display: block; }
        
        /* RECIPES */
        .recipe-card { background: var(--bg-primary); border-radius: var(--radius-lg); padding: 0; box-shadow: var(--shadow-md); transition: all 0.3s ease; border: 1px solid var(--border); overflow: hidden; display: flex; flex-direction: column; height: 100%; position: relative; }
        .recipe-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: var(--accent); }
        .recipe-img { width: 100%; height: 200px; object-fit: cover; border-bottom: 1px solid var(--border); }
        .recipe-content { padding: 1.5rem; display: flex; flex-direction: column; flex: 1; }
        .recipe-badge { position: absolute; top: 1rem; left: 1rem; background: rgba(245, 158, 11, 0.9); color: white; padding: 0.35rem 0.85rem; border-radius: 20px; font-weight: 700; font-size: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.25); backdrop-filter: blur(4px); letter-spacing: 0.5px; text-transform: uppercase; border: 1px solid rgba(255,255,255,0.3); }

        /* PRODUCTS */
        .category-aisles { display: flex; overflow-x: auto; gap: 0.75rem; max-width: 1400px; margin: 1rem auto -1rem; padding: 0 2rem; -ms-overflow-style: none; scrollbar-width: none; }
        .category-aisles::-webkit-scrollbar { display: none; }
        .aisle-chip { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem; background: var(--bg-primary); border: 1.5px solid var(--border); border-radius: 99px; white-space: nowrap; cursor: pointer; font-weight: 600; font-size: 0.95rem; transition: all 0.2s; box-shadow: var(--shadow-sm); flex-shrink: 0; color: var(--text-primary); }
        [data-theme="dark"] .aisle-chip { background: var(--bg-secondary); border-color: var(--border); color: var(--text-primary); }
        .aisle-chip:hover { border-color: var(--primary); color: var(--primary); background: var(--bg-secondary); }
        .aisle-chip.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-color: var(--primary); }
        [data-theme="dark"] .aisle-chip.active { color: white; }
        .products-grid { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .product-card { background: var(--bg-primary); border-radius: var(--radius-lg); padding: 1.25rem; box-shadow: var(--shadow-md); transition: all 0.3s ease; border: 1px solid var(--border); position: relative; overflow: hidden; }
        .product-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), var(--accent)); transform: scaleX(0); transition: transform 0.3s ease; }
        .product-card:hover::before { transform: scaleX(1); }
        .product-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
        .product-image { width: 100%; height: 160px; object-fit: cover; border-radius: var(--radius-md); margin-bottom: 0.875rem; }
        .wishlist-btn { position: absolute; top: 1rem; right: 1rem; width: 34px; height: 34px; border-radius: 50%; background: var(--bg-primary); border: 1.5px solid var(--border); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; z-index: 2; box-shadow: var(--shadow-sm); }
        .wishlist-btn:hover { border-color: #ef4444; }
        .wishlist-btn.active { background: #fef2f2; border-color: #ef4444; }
        .wishlist-btn.active i { color: #ef4444; }
        .wishlist-btn i { color: var(--text-secondary); font-size: 0.85rem; }

        /* MODALS */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(6px); }
        .modal-card { background: var(--bg-primary); padding: 2rem; border-radius: var(--radius-lg); width: 90vw; max-width: 640px; max-height: 88vh; overflow-y: auto; box-shadow: var(--shadow-lg); animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { opacity:0; transform: translateY(-16px); } to { opacity:1; transform: translateY(0); } }

        /* AUTH */
        .auth-overlay { position: fixed; inset: 0; background: linear-gradient(135deg, rgba(16,185,129,0.95), rgba(34,197,94,0.95)); display: flex; align-items: center; justify-content: center; z-index: 2000; }
        [data-theme="dark"] .auth-overlay { background: linear-gradient(135deg, rgba(15,23,42,0.95), rgba(30,41,59,0.95)); }
        .auth-card { background: var(--bg-primary); padding: 2.5rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); width: 100%; max-width: 420px; text-align: center; color: var(--text-primary); }
        .form-input { width: 100%; padding: 0.875rem 1rem; margin: 0.4rem 0; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 1rem; transition: all 0.3s ease; font-family: 'Inter', sans-serif; color: var(--text-primary); background: var(--bg-secondary); }
        .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }

        /* CART */
        .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 0.875rem 0; border-bottom: 1px solid var(--border); }
        .qty-controls { display: flex; align-items: center; gap: 0.5rem; }
        .qty-btn { width: 34px; height: 34px; border: 2px solid var(--border); background: var(--bg-primary); border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; transition: all 0.2s; flex-shrink: 0; }
        .qty-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

        /* COUPON */
        .coupon-info { background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); padding: 1rem; border-radius: var(--radius-md); margin: 1rem 0; }

        /* ORDER CARD */
        .order-card { background: var(--bg-secondary); border-left: 4px solid var(--primary); padding: 1.25rem; margin: 0.875rem 0; border-radius: var(--radius-md); }

        /* INNER PAGES (Profile, Settings, Wishlist, Address, Offers) */
        .inner-page { max-width: 900px; margin: 2rem auto; padding: 0 2rem; }
        .page-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; }
        .page-header h1 { font-size: 1.75rem; font-weight: 700; }
        .back-btn { width: 40px; height: 40px; border-radius: 50%; background: var(--bg-primary); border: 1.5px solid var(--border); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; flex-shrink: 0; }
        .back-btn:hover { border-color: var(--primary); color: var(--primary); }
        .card-section { background: var(--bg-primary); border-radius: var(--radius-lg); padding: 1.75rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-md); border: 1px solid var(--border); }
        .card-section h2 { font-size: 1.1rem; font-weight: 700; margin-bottom: 1.25rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; }
        .card-section h2 i { color: var(--primary); }

        /* PROFILE */
        .profile-hero { background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: var(--radius-lg); padding: 2.5rem 2rem; text-align: center; color: white; margin-bottom: 1.5rem; }
        .profile-avatar { width: 90px; height: 90px; border-radius: 50%; background: rgba(255,255,255,0.25); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; margin: 0 auto 1rem; border: 4px solid rgba(255,255,255,0.4); }
        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        .stat-card { background: var(--bg-primary); border-radius: var(--radius-md); padding: 1rem; text-align: center; border: 1px solid var(--border); }
        .stat-card .val { font-size: 1.75rem; font-weight: 800; color: var(--primary); }
        .stat-card .lbl { font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem; }

        /* SETTINGS TABS */
        .settings-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; background: var(--bg-primary); padding: 0.5rem; border-radius: var(--radius-md); border: 1px solid var(--border); }
        .settings-tab { flex: 1; padding: 0.625rem; border-radius: var(--radius-sm); border: none; background: none; cursor: pointer; font-weight: 600; font-size: 0.875rem; color: var(--text-secondary); transition: all 0.2s; }
        .settings-tab.active { background: var(--primary); color: white; }
        .settings-panel { display: none; }
        .settings-panel.active { display: block; }

        /* TOGGLE SWITCH */
        .toggle-row { display: flex; justify-content: space-between; align-items: center; padding: 0.875rem 0; border-bottom: 1px solid var(--border); }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-label { font-size: 0.95rem; }
        .toggle-desc { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
        .toggle { position: relative; width: 46px; height: 26px; flex-shrink: 0; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; inset: 0; background: #cbd5e1; border-radius: 999px; cursor: pointer; transition: 0.3s; }
        .toggle-slider::before { content: ''; position: absolute; width: 20px; height: 20px; left: 3px; top: 3px; background: var(--bg-primary); border-radius: 50%; transition: 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        .toggle input:checked + .toggle-slider { background: var(--primary); }
        .toggle input:checked + .toggle-slider::before { transform: translateX(20px); }

        /* OFFERS PAGE */
        .coupon-card { background: var(--bg-primary); border-radius: var(--radius-lg); padding: 1.5rem; border: 2px dashed var(--primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 1.5rem; }
        .coupon-badge { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 0.5rem 1rem; border-radius: var(--radius-sm); font-weight: 800; font-size: 1rem; white-space: nowrap; }
        .copy-btn { margin-left: auto; flex-shrink: 0; }

        /* ADDRESS CARD */
        .address-card { background: var(--bg-secondary); border-radius: var(--radius-md); padding: 1.25rem; border: 1.5px solid var(--border); margin-bottom: 0.875rem; position: relative; }
        .address-card.default { border-color: var(--primary); background: rgba(16,185,129,0.04); }
        .address-badge { display: inline-block; background: var(--primary); color: white; font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 99px; margin-left: 0.5rem; }

        /* WISHLIST GRID */
        .wishlist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.25rem; }
        .wishlist-item { background: var(--bg-primary); border-radius: var(--radius-md); padding: 1rem; border: 1px solid var(--border); text-align: center; transition: all 0.2s; }
        .wishlist-item:hover { box-shadow: var(--shadow-md); transform: translateY(-3px); }
        .wishlist-item img { width: 100%; height: 120px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 0.75rem; }

        /* NOTIFICATION TOAST */
        #toast { position: fixed; bottom: 2rem; right: 2rem; background: #1e293b; color: white; padding: 0.875rem 1.5rem; border-radius: var(--radius-md); z-index: 9999; font-size: 0.9rem; opacity: 0; transform: translateY(10px); transition: all 0.3s; pointer-events: none; }
        #toast.show { opacity: 1; transform: translateY(0); }

        @media (max-width: 768px) {
            .nav-container { flex-wrap: wrap; }
            .search-container { order: 3; margin: 0; flex: 1 1 100%; }
            .products-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); padding: 0 1rem; }
            .stat-grid { grid-template-columns: repeat(3, 1fr); }
            .inner-page { padding: 0 1rem; }
        }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<!-- ======================== AUTH OVERLAY ======================== -->
<div class="auth-overlay">
    <div class="auth-card">
        <h2 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 0.25rem; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">🛒 Apna Mart</h2>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.75rem;">Smart Grocery & Coupons Comparator</p>
        <div style="display:flex; background:var(--bg-secondary); border-radius:var(--radius-md); overflow:hidden; margin-bottom:1.75rem;">
            <button class="btn auth-tab active" onclick="showTab('login')" style="flex:1;padding:0.875rem;font-weight:600;background:none;border:none;cursor:pointer;">Login</button>
            <button class="btn auth-tab" onclick="showTab('signup')" style="flex:1;padding:0.875rem;font-weight:600;background:none;border:none;cursor:pointer;">Sign Up</button>
        </div>
        <form method="POST" id="loginForm">
            <input type="hidden" name="auth_action" value="login">
            <input name="username" class="form-input" placeholder="Username" required>
            <input name="password" type="password" class="form-input" placeholder="Password" required>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:1rem;font-size:1rem;margin-top:0.5rem;">Enter Apna Mart</button>
        </form>
        <form method="POST" id="signupForm" style="display:none;">
            <input type="hidden" name="auth_action" value="signup">
            <input name="username" class="form-input" placeholder="New Username" required>
            <input name="password" type="password" class="form-input" placeholder="New Password (min 4)" required>
            <input name="confirm_password" type="password" class="form-input" placeholder="Confirm Password" required>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:1rem;font-size:1rem;margin-top:0.5rem;">Create Account</button>
        </form>
        <?php if ($error): ?><p style="color:var(--danger);font-size:0.875rem;margin-top:0.875rem;"><?=htmlspecialchars($error)?></p><?php endif; ?>
        <p style="font-size:0.75rem;color:var(--text-secondary);margin-top:1.25rem;">Demo: <strong>admin</strong> / <strong>password</strong></p>
    </div>
</div>
<?php else: ?>

<!-- ======================== NAVBAR ======================== -->
<div class="navbar">
    <div class="nav-container">
        <div class="logo" onclick="showPage('home')">🛒 Apna Mart</div>
        <div class="search-container">
            <i class="fas fa-search" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-secondary);"></i>
            <input id="searchbar" class="search-input" placeholder="Search atta, rice, dal, spices..." style="padding:0 3rem;">
            <i id="micBtn" class="fas fa-microphone" style="position:absolute;right:1.25rem;top:50%;transform:translateY(-50%);color:var(--primary);cursor:pointer;font-size:1.15rem;transition:0.2s;" title="Voice Search"></i>
        </div>
        <div style="display:flex;gap:0.75rem;align-items:center;">
            <!-- Cart -->
            <button id="viewCart" class="btn btn-secondary cart-badge-wrap" style="padding: 0.625rem 1rem;">
                <i class="fas fa-shopping-cart"></i>
                <span>Cart</span>
                <span id="cartBadge" class="cart-badge"><?=$cartCount?></span>
            </button>
            <!-- Theme Toggle -->
            <button onclick="toggleTheme()" class="btn btn-ghost" style="padding: 0.625rem 1rem;" title="Dark Mode">
                <i id="themeIcon" class="fas fa-moon"></i>
            </button>
            <!-- Wishlist shortcut -->
            <button onclick="showPage('wishlist')" class="btn btn-ghost" style="padding: 0.625rem 1rem;" title="Wishlist">
                <i class="fas fa-heart" style="color:#ef4444;"></i>
            </button>
            <!-- User Dropdown -->
            <div class="user-dropdown-wrapper" id="userDropdownWrapper">
                <div class="avatar-circle" id="avatarBtn" onclick="toggleDropdown()"><?=htmlspecialchars($avatarLetter)?></div>
                <div class="user-dropdown" id="userDropdown" style="display:none;">
                    <div class="dropdown-header">
                        <div style="font-weight:700;font-size:1rem;"><?=htmlspecialchars($_SESSION['username'])?></div>
                        <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:2px;" id="dropEmailPreview">Loading...</div>
                    </div>
                    <button class="dropdown-item" onclick="showPage('profile');closeDropdown()"><i class="fas fa-user"></i> My Profile</button>
                    <button class="dropdown-item" onclick="showPage('orders');closeDropdown()"><i class="fas fa-box"></i> My Orders</button>
                    <button class="dropdown-item" onclick="showPage('wishlist');closeDropdown()"><i class="fas fa-heart"></i> Wishlist</button>
                    <button class="dropdown-item" onclick="showPage('addresses');closeDropdown()"><i class="fas fa-map-marker-alt"></i> Saved Addresses</button>
                    <button class="dropdown-item" onclick="showPage('offers');closeDropdown()"><i class="fas fa-tags"></i> Offers & Coupons</button>
                    <button class="dropdown-item" onclick="showPage('recipes');closeDropdown()"><i class="fas fa-utensils" style="color:var(--accent);"></i> Smart Recipes</button>
                    <?php if ($_SESSION['username'] === 'admin'): ?>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item" onclick="showPage('admin');closeDropdown()"><i class="fas fa-cogs" style="color:var(--danger);"></i> Admin Panel</button>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item" onclick="showPage('settings');closeDropdown()"><i class="fas fa-cog"></i> Settings</button>
                    <div class="dropdown-divider"></div>
                    <form method="POST" style="display:block;">
                        <input type="hidden" name="auth_action" value="logout">
                        <button type="submit" class="dropdown-item" style="color:#ef4444;"><i class="fas fa-sign-out-alt" style="color:#ef4444;"></i> Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================== HOME PAGE ======================== -->
<div id="page-home" class="page-section active">
    <div id="category-aisles" class="category-aisles"></div>
    <div id="product-grid" class="products-grid"></div>
</div>

<!-- ======================== RECIPES PAGE ======================== -->
<div id="page-recipes" class="page-section">
    <div class="inner-page" style="max-width:1400px;">
        <div class="page-header">
            <div class="back-btn" onclick="showPage('home')"><i class="fas fa-arrow-left"></i></div>
            <h1><i class="fas fa-utensils" style="color:var(--accent);margin-right:0.5rem;"></i> Smart Recipes Planner</h1>
        </div>
        <p style="color:var(--text-secondary);margin-bottom:1.5rem;">Pick a meal plan and we'll automatically add any missing ingredients to your cart!</p>
        <div id="recipesGrid" class="products-grid" style="margin:0;padding:0;"></div>
    </div>
</div>

<!-- ======================== PROFILE PAGE ======================== -->
<div id="page-profile" class="page-section">
    <div class="inner-page">
        <div class="page-header">
            <div class="back-btn" onclick="showPage('home')"><i class="fas fa-arrow-left"></i></div>
            <h1><i class="fas fa-user" style="color:var(--primary);margin-right:0.5rem;"></i> My Profile</h1>
        </div>
        <div class="profile-hero">
            <div class="profile-avatar" id="profileAvatarBig"><?=htmlspecialchars($avatarLetter)?></div>
            <h2 style="font-size:1.5rem;font-weight:700;" id="profileNameBig"><?=htmlspecialchars($_SESSION['username'])?></h2>
            <p style="opacity:0.85;font-size:0.9rem;margin-top:0.25rem;" id="profileEmailBig">Loading...</p>
        </div>
        <div class="stat-grid" style="margin-bottom:1.5rem;">
            <div class="stat-card"><div class="val" id="statOrders">-</div><div class="lbl">Orders Placed</div></div>
            <div class="stat-card"><div class="val" id="statSpent">-</div><div class="lbl">Total Spent (₹)</div></div>
            <div class="stat-card"><div class="val" id="statMember">-</div><div class="lbl">Member Since</div></div>
        </div>
        <div class="card-section">
            <h2><i class="fas fa-edit"></i> Edit Info</h2>
            <input id="profileEmail" class="form-input" placeholder="Email address" type="email">
            <input id="profilePhone" class="form-input" placeholder="Phone number" type="tel" style="margin-top:0.75rem;">
            <button class="btn btn-primary" style="margin-top:1rem;" onclick="saveProfile()"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </div>
</div>

<!-- ======================== ORDERS PAGE ======================== -->
<div id="page-orders" class="page-section">
    <div class="inner-page">
        <div class="page-header">
            <div class="back-btn" onclick="showPage('home')"><i class="fas fa-arrow-left"></i></div>
            <h1><i class="fas fa-box" style="color:var(--primary);margin-right:0.5rem;"></i> My Orders</h1>
        </div>
        <div id="ordersFullList" class="card-section"></div>
    </div>
</div>

<!-- ======================== WISHLIST PAGE ======================== -->
<div id="page-wishlist" class="page-section">
    <div class="inner-page">
        <div class="page-header">
            <div class="back-btn" onclick="showPage('home')"><i class="fas fa-arrow-left"></i></div>
            <h1><i class="fas fa-heart" style="color:#ef4444;margin-right:0.5rem;"></i> My Wishlist</h1>
        </div>
        <div id="wishlistGrid" class="wishlist-grid"></div>
    </div>
</div>

<!-- ======================== ADDRESSES PAGE ======================== -->
<div id="page-addresses" class="page-section">
    <div class="inner-page">
        <div class="page-header">
            <div class="back-btn" onclick="showPage('home')"><i class="fas fa-arrow-left"></i></div>
            <h1><i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:0.5rem;"></i> Saved Addresses</h1>
        </div>
        <div class="card-section">
            <h2><i class="fas fa-plus-circle"></i> Add New Address</h2>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                <select id="addrLabel" class="form-input" style="margin:0;">
                    <option value="Home">🏠 Home</option>
                    <option value="Work">💼 Work</option>
                    <option value="Other">📍 Other</option>
                </select>
                <input id="addrName" class="form-input" style="margin:0;" placeholder="Full Name">
                <input id="addrPhone" class="form-input" style="margin:0;" placeholder="Phone Number" type="tel">
                <input id="addrPincode" class="form-input" style="margin:0;" placeholder="Pincode">
                <input id="addrCity" class="form-input" style="margin:0;" placeholder="City / District">
                <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.9rem;cursor:pointer;">
                    <input type="checkbox" id="addrDefault"> Set as Default
                </label>
            </div>
            <textarea id="addrLine" class="form-input" style="margin-top:0.75rem;resize:vertical;min-height:70px;" placeholder="Full address (house no, street, landmark...)"></textarea>
            <button class="btn btn-primary" style="margin-top:1rem;" onclick="addAddress()"><i class="fas fa-plus"></i> Add Address</button>
        </div>
        <div id="addressList"></div>
    </div>
</div>

<!-- ======================== OFFERS PAGE ======================== -->
<div id="page-offers" class="page-section">
    <div class="inner-page">
        <div class="page-header">
            <div class="back-btn" onclick="showPage('home')"><i class="fas fa-arrow-left"></i></div>
            <h1><i class="fas fa-tags" style="color:var(--accent);margin-right:0.5rem;"></i> Offers & Coupons</h1>
        </div>
        <p style="color:var(--text-secondary);margin-bottom:1.5rem;">Tap a coupon to copy the code, then paste it at checkout.</p>
        <div id="offersGrid"></div>
    </div>
</div>

<!-- ======================== ADMIN PAGE ======================== -->
<div id="page-admin" class="page-section">
    <div class="inner-page">
        <div class="page-header">
            <div class="back-btn" onclick="showPage('home')"><i class="fas fa-arrow-left"></i></div>
            <h1><i class="fas fa-cogs" style="color:var(--danger);margin-right:0.5rem;"></i> Admin Panel</h1>
        </div>
        <div class="admin-tabs" style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
            <button class="btn btn-ghost admin-tab active" onclick="switchAdminTab('overview', this)"><i class="fas fa-chart-line"></i> Overview</button>
            <button class="btn btn-ghost admin-tab" onclick="switchAdminTab('activity', this)"><i class="fas fa-history"></i> Activity</button>
            <button class="btn btn-ghost admin-tab" onclick="switchAdminTab('products', this)"><i class="fas fa-boxes"></i> Products</button>
        </div>
        <div id="adminOverviewSection" class="admin-section active">
            <div class="card-section" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem;">
                <div class="stat-card"><div>Total Orders</div><div id="dashTotalOrders">0</div></div>
                <div class="stat-card"><div>Total Revenue</div><div id="dashTotalRevenue">₹0</div></div>
                <div class="stat-card"><div>Orders Today</div><div id="dashOrdersToday">0</div></div>
                <div class="stat-card"><div>Revenue Today</div><div id="dashRevenueToday">₹0</div></div>
                <div class="stat-card"><div>Total Users</div><div id="dashTotalUsers">0</div></div>
                <div class="stat-card"><div>New Users Today</div><div id="dashNewUsersToday">0</div></div>
                <div class="stat-card"><div>Total Products</div><div id="dashTotalProducts">0</div></div>
                <div class="stat-card"><div>Avg Order Value</div><div id="dashAvgOrderValue">₹0</div></div>
            </div>
            <div class="card-section" style="margin-top:1rem;">
                <h2><i class="fas fa-shield-alt"></i> Security Snapshot</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem;">
                    <div class="stat-card" style="padding:1rem;"><div>Failed logins (7d)</div><div id="dashFailedLogins">0</div></div>
                    <div class="stat-card" style="padding:1rem;"><div>Suspicious accounts</div><div id="dashSuspiciousUsers">0</div></div>
                </div>
            </div>
        </div>
        <div id="adminActivitySection" class="admin-section" style="display:none;">
            <div class="card-section">
                <h2><i class="fas fa-history"></i> Recent Admin Activity</h2>
                <div id="adminActivityLog" style="max-height:360px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-md);padding:0.5rem;background:var(--bg-secondary);"></div>
            </div>
        </div>
        <div id="adminProductsSection" class="admin-section" style="display:none;">
            <div class="card-section">
                <h2><i class="fas fa-plus"></i> Add New Product</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                    <input id="adminProdName" class="form-input" style="margin:0;" placeholder="Product Name">
                    <input id="adminProdPrice" type="number" class="form-input" style="margin:0;" placeholder="Price (₹)">
                    <select id="adminProdCat" class="form-input" style="margin:0;">
                        <option value="Atta">Atta</option>
                        <option value="Rice">Rice</option>
                        <option value="Dal">Dal</option>
                        <option value="Oils">Oils</option>
                        <option value="Spices">Spices</option>
                        <option value="Dairy">Dairy</option>
                        <option value="Organic">Organic</option>
                        <option value="Snacks">Snacks</option>
                        <option value="Others">Others</option>
                    </select>
                    <input id="adminProdImg" class="form-input" style="margin:0;" placeholder="Image URL">
                </div>
                <button class="btn btn-primary" style="margin-top:1rem;" onclick="adminAddProduct()"><i class="fas fa-save"></i> Save Product</button>
            </div>
            <div class="card-section">
                <h2><i class="fas fa-list"></i> Manage Products</h2>
                <div id="adminProductList" style="max-height:400px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-md);padding:0.5rem;background:var(--bg-secondary);"></div>
            </div>
        </div>
    </div>
</div>

<!-- ======================== SETTINGS PAGE ======================== -->
<div id="page-settings" class="page-section">
    <div class="inner-page">
        <div class="page-header">
            <div class="back-btn" onclick="showPage('home')"><i class="fas fa-arrow-left"></i></div>
            <h1><i class="fas fa-cog" style="color:var(--primary);margin-right:0.5rem;"></i> Settings</h1>
        </div>

        <div class="settings-tabs">
            <button class="settings-tab active" onclick="switchSettingsTab('account',this)"><i class="fas fa-user"></i> Account</button>
            <button class="settings-tab" onclick="switchSettingsTab('security',this)"><i class="fas fa-lock"></i> Security</button>
            <button class="settings-tab" onclick="switchSettingsTab('preferences',this)"><i class="fas fa-sliders-h"></i> Preferences</button>
            <button class="settings-tab" onclick="switchSettingsTab('about',this)"><i class="fas fa-info-circle"></i> About</button>
        </div>

        <!-- Account Tab -->
        <div id="stab-account" class="settings-panel active">
            <div class="card-section">
                <h2><i class="fas fa-id-card"></i> Account Details</h2>
                <div style="margin-bottom:1rem;padding:1rem;background:var(--bg-secondary);border-radius:var(--radius-md);display:flex;align-items:center;gap:1rem;">
                    <div class="avatar-circle" style="width:56px;height:56px;font-size:1.4rem;"><?=htmlspecialchars($avatarLetter)?></div>
                    <div>
                        <div style="font-weight:700;font-size:1.1rem;"><?=htmlspecialchars($_SESSION['username'])?></div>
                        <div style="font-size:0.85rem;color:var(--text-secondary);" id="settingsEmailDisplay">Loading...</div>
                    </div>
                </div>
                <input id="settingsEmail" class="form-input" placeholder="Email address" type="email">
                <input id="settingsPhone" class="form-input" placeholder="Phone number" type="tel" style="margin-top:0.75rem;">
                <button class="btn btn-primary" style="margin-top:1rem;" onclick="saveSettingsProfile()"><i class="fas fa-save"></i> Update Account</button>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="stab-security" class="settings-panel">
            <div class="card-section">
                <h2><i class="fas fa-key"></i> Change Password</h2>
                <input id="oldPass" class="form-input" placeholder="Current password" type="password">
                <input id="newPass" class="form-input" placeholder="New password (min 4 chars)" type="password" style="margin-top:0.75rem;">
                <input id="confPass" class="form-input" placeholder="Confirm new password" type="password" style="margin-top:0.75rem;">
                <button class="btn btn-primary" style="margin-top:1rem;" onclick="changePassword()"><i class="fas fa-lock"></i> Change Password</button>
            </div>
            <div class="card-section">
                <h2><i class="fas fa-shield-alt"></i> Sessions</h2>
                <p style="color:var(--text-secondary);font-size:0.9rem;margin-bottom:1rem;">You are currently logged in on this device.</p>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="auth_action" value="logout">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout All Devices</button>
                </form>
            </div>
        </div>

        <!-- Preferences Tab -->
        <div id="stab-preferences" class="settings-panel">
            <div class="card-section">
                <h2><i class="fas fa-bell"></i> Notifications</h2>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Order Updates</div>
                        <div class="toggle-desc">Get notified when your order status changes</div>
                    </div>
                    <label class="toggle"><input type="checkbox" checked onchange="savePref('notif_orders',this.checked)"><span class="toggle-slider"></span></label>
                </div>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Offers & Deals</div>
                        <div class="toggle-desc">Receive coupon codes and flash sale alerts</div>
                    </div>
                    <label class="toggle"><input type="checkbox" id="prefOffers" onchange="savePref('notif_offers',this.checked)"><span class="toggle-slider"></span></label>
                </div>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Wishlist Alerts</div>
                        <div class="toggle-desc">Know when wishlisted items are on sale</div>
                    </div>
                    <label class="toggle"><input type="checkbox" id="prefWishAlerts" onchange="savePref('notif_wish',this.checked)"><span class="toggle-slider"></span></label>
                </div>
            </div>
            <div class="card-section">
                <h2><i class="fas fa-globe"></i> Language & Display</h2>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Show Hindi Names</div>
                        <div class="toggle-desc">Display product names in Hindi alongside English</div>
                    </div>
                    <label class="toggle"><input type="checkbox" id="prefHindi" checked onchange="savePref('show_hindi',this.checked);location.reload()"><span class="toggle-slider"></span></label>
                </div>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Compact Product Cards</div>
                        <div class="toggle-desc">Show more products with smaller cards</div>
                    </div>
                    <label class="toggle"><input type="checkbox" id="prefCompact" onchange="savePref('compact_cards',this.checked);applyPrefs()"><span class="toggle-slider"></span></label>
                </div>
            </div>
        </div>

        <!-- About Tab -->
        <div id="stab-about" class="settings-panel">
            <div class="card-section">
                <h2><i class="fas fa-info-circle"></i> About Apna Mart</h2>
                <p style="color:var(--text-secondary);line-height:1.7;margin-bottom:1rem;">Apna Mart is your trusted digital grocery comparator — find the best deals on daily essentials like atta, rice, dal, spices, oils, and dairy products.</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                    <div style="background:var(--bg-secondary);padding:1rem;border-radius:var(--radius-md);">
                        <div style="font-size:0.75rem;color:var(--text-secondary);">Version</div>
                        <div style="font-weight:700;">2.0.0</div>
                    </div>
                    <div style="background:var(--bg-secondary);padding:1rem;border-radius:var(--radius-md);">
                        <div style="font-size:0.75rem;color:var(--text-secondary);">Total Products</div>
                        <div style="font-weight:700;">50+</div>
                    </div>
                    <div style="background:var(--bg-secondary);padding:1rem;border-radius:var(--radius-md);">
                        <div style="font-size:0.75rem;color:var(--text-secondary);">Available Coupons</div>
                        <div style="font-weight:700;">6 Active</div>
                    </div>
                    <div style="background:var(--bg-secondary);padding:1rem;border-radius:var(--radius-md);">
                        <div style="font-size:0.75rem;color:var(--text-secondary);">Categories</div>
                        <div style="font-weight:700;">8 Categories</div>
                    </div>
                </div>
                <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--border);">
                    <p style="font-size:0.85rem;color:var(--text-secondary);">Built with PHP, MySQL &amp; vanilla JS. All prices are in Indian Rupees (₹).</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================== CART MODAL ======================== -->
<div id="cartModal" class="modal-overlay">
    <div class="modal-card" style="max-width:680px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.75rem;">
            <h2 style="font-size:1.5rem;font-weight:700;"><i class="fas fa-shopping-cart"></i> Your Basket</h2>
            <button onclick="closeModal('cartModal')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-secondary);"><i class="fas fa-times"></i></button>
        </div>
        <div id="cartItems" style="min-height:160px;"></div>
        <div id="cartTotal" style="margin:1.5rem 0;font-size:1.4rem;font-weight:700;text-align:center;color:var(--primary);"></div>
        <button class="btn btn-primary" style="width:100%;padding:1.1rem;font-size:1rem;" onclick="openCheckout()">Proceed to Checkout →</button>
    </div>
</div>

<!-- ======================== CHECKOUT MODAL ======================== -->
<div id="checkoutModal" class="modal-overlay">
    <div class="modal-card" style="max-width:680px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.75rem;">
            <h2 style="font-size:1.5rem;font-weight:700;"><i class="fas fa-credit-card"></i> Checkout</h2>
            <button onclick="closeModal('checkoutModal')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-secondary);"><i class="fas fa-times"></i></button>
        </div>
        <div id="checkoutSummary"></div>
        <div class="coupon-info">
            <div style="font-weight:600;margin-bottom:0.25rem;">🎟️ Apply Coupon Code</div>
            <div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:0.75rem;">
                SAVE10 • SAVE20 • SAVE30 • FLAT50 • FLAT100 • FIRSTORDER
            </div>
            <div style="display:flex;gap:0.75rem;">
                <input id="couponInput" class="form-input" placeholder="Enter coupon code..." style="margin:0;flex:1;">
                <button class="btn btn-secondary" onclick="calculateTotal()">Apply</button>
            </div>
        </div>
        <div id="finalTotal" style="font-size:1.4rem;font-weight:700;text-align:center;color:var(--primary);margin:1rem 0;"></div>
        <button class="btn btn-primary" style="width:100%;padding:1.1rem;font-size:1rem;" onclick="placeOrder()"><i class="fas fa-check-circle"></i> Place Order</button>
    </div>
</div>

<!-- TOAST -->
<div id="toast"></div>

<?php endif; ?>

<script>
function showTab(tab) {
    const loginForm = document.getElementById('loginForm');
    const signupForm = document.getElementById('signupForm');
    const tabs = document.querySelectorAll('.auth-tab');
    tabs.forEach(btn => btn.classList.toggle('active', btn.getAttribute('onclick') === "showTab('" + tab + "')"));
    if (loginForm) loginForm.style.display = tab === 'login' ? 'block' : 'none';
    if (signupForm) signupForm.style.display = tab === 'signup' ? 'block' : 'none';
}

<?php if ($isLoggedIn): ?>
const products = <?= json_encode($allProducts) ?>;

const catHindi = {"Oils":"तेल","Atta":"आटा","Dal":"दाल","Rice":"चावल","Organic":"जैविक","Spices":"मसाले","Dairy":"डेयरी","Others":"अन्य"};
const prodHindi = {1:'गेहूँ का आटा',2:'चावल',3:'दाल',4:'चीनी',5:'नमक',6:'पकाने का तेल',7:'चाय',8:'कॉफ़ी',9:'दूध पाउडर',10:'घी',11:'सूरजमुखी तेल',12:'सरसों तेल',13:'जैतून तेल',14:'सोयाबीन तेल',15:'नारियल तेल',16:'मल्टीग्रेन आटा',17:'चक्की ताज़ा आटा',18:'बेसन',19:'मैदा',20:'रागी आटा',21:'तूर दाल',22:'मूंग दाल',23:'मसूर दाल',24:'चना दाल',25:'उड़द दाल',26:'बासमती चावल',27:'सोना मसूरी चावल',28:'ब्राउन चावल',29:'कोलम चावल',30:'जीरा चावल',31:'जैविक गेहूँ आटा',32:'जैविक चावल',33:'जैविक शहद',34:'जैविक चीनी',35:'जैविक हल्दी पाउडर',36:'हल्दी पाउडर',37:'लाल मिर्च पाउडर',38:'गरम मसाला',39:'धनिया पाउडर',40:'जीरा',41:'फुल क्रीम दूध',42:'पनीर',43:'मक्खन',44:'चीज़ स्लाइस',45:'दही',46:'मैगी नूडल्स',47:'बिस्कुट',48:'कॉर्नफ्लेक्स',49:'ओट्स',50:'पीनट बटर'};

const coupons = [
    {code:'SAVE10', title:'10% Off Everything', desc:'Get 10% discount on your entire cart. No minimum order required.', color:'#10b981', icon:'fas fa-percent'},
    {code:'SAVE20', title:'20% Mega Discount', desc:'Save big with 20% off on your full cart order.', color:'#3b82f6', icon:'fas fa-tag'},
    {code:'SAVE30', title:'30% Super Saver', desc:'Flat 30% off — our biggest percentage discount available.', color:'#8b5cf6', icon:'fas fa-bolt'},
    {code:'FLAT50', title:'Flat ₹50 Off', desc:'Instant ₹50 deduction on any cart. No minimum value.', color:'#f59e0b', icon:'fas fa-rupee-sign'},
    {code:'FLAT100', title:'Flat ₹100 Off', desc:'Save ₹100 on cart orders. Best for larger purchases.', color:'#ef4444', icon:'fas fa-gift'},
    {code:'FIRSTORDER', title:'15% First Order', desc:'Exclusive 15% off on first purchase above ₹300.', color:'#10b981', icon:'fas fa-star'},
];

let cartState = {};
let wishlistState = [];
let currentPage = 'home';
let profileData = null;
let activeCategory = 'All';

// ---- THEME ----
function initTheme() {
    const isDark = localStorage.getItem('apna_theme') === 'dark';
    if (isDark) document.documentElement.setAttribute('data-theme', 'dark');
    const ti = document.getElementById('themeIcon');
    if(ti) ti.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
}
function toggleTheme() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('apna_theme', 'light');
        document.getElementById('themeIcon').className = 'fas fa-moon';
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('apna_theme', 'dark');
        document.getElementById('themeIcon').className = 'fas fa-sun';
    }
}
initTheme();

async function api(body) {
    const r = await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body});
    return await r.json();
}

function showToast(msg, duration=3000) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), duration);
}

// ---- PAGES ----
function showPage(name) {
    document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
    document.getElementById('page-' + name).classList.add('active');
    currentPage = name;
    if (name === 'home') { if (!document.getElementById('product-grid').children.length) sync(); }
    if (name === 'profile') loadProfile();
    if (name === 'orders') loadFullOrders();
    if (name === 'wishlist') renderWishlistPage();
    if (name === 'addresses') loadAddresses();
    if (name === 'offers') renderOffers();
    if (name === 'recipes') renderRecipes();
    if (name === 'settings') loadSettingsProfile();
    if (name === 'admin') {
        renderAdminProducts();
        renderAdminDashboard();
    }
    window.scrollTo(0, 0);
    closeDropdown();
}

// ---- DROPDOWN ----
function toggleDropdown() {
    const d = document.getElementById('userDropdown');
    d.style.display = d.style.display === 'none' ? 'block' : 'none';
    if (d.style.display === 'block') loadDropdownEmail();
}
function closeDropdown() {
    const d = document.getElementById('userDropdown');
    if (d) d.style.display = 'none';
}
document.addEventListener('click', e => {
    const w = document.getElementById('userDropdownWrapper');
    if (w && !w.contains(e.target)) closeDropdown();
});

async function loadDropdownEmail() {
    if (!profileData) profileData = await api('action=get_profile');
    const el = document.getElementById('dropEmailPreview');
    if (el) el.textContent = profileData.profile?.email || 'No email set';
}

// ---- SYNC CART + WISHLIST ----
async function sync(fullRender = false) {
    const [cart, wish] = await Promise.all([api('action=fetch'), api('action=get_wishlist')]);
    cartState = cart;
    wishlistState = wish;
    const grid = document.getElementById('product-grid');
    renderAisles();
    if (grid && (!grid.children.length || fullRender)) render(document.getElementById('searchbar')?.value || '');
    else renderQuantities();
    updateCartBadge();
}

function renderAisles() {
    const aislesEl = document.getElementById('category-aisles');
    if (!aislesEl) return;
    const cats = ["All", "Oils","Atta","Dal","Rice","Organic","Spices","Dairy","Others"];
    const catIcons = { "All": '🛒', "Oils": '🛢️', "Atta": '🌾', "Dal": '🥣', "Rice": '🍚', "Organic": '🌱', "Spices": '🌶️', "Dairy": '🥛', "Others": '📦' };
    
    aislesEl.innerHTML = cats.map(c => `
        <button class="aisle-chip ${c === activeCategory ? 'active' : ''}" onclick="filterByCategory('${c}')">
            <span>${catIcons[c] || '🛒'}</span> ${c}
        </button>
    `).join('');
}

function filterByCategory(cat) {
    activeCategory = cat;
    renderAisles();
    render(document.getElementById('searchbar')?.value || '');
}

function updateCartBadge() {
    const total = Object.values(cartState).reduce((s, i) => s + i.qty, 0);
    const badge = document.getElementById('cartBadge');
    if (badge) badge.textContent = total;
}

function renderQuantities() {
    products.forEach(p => {
        const qty = cartState[p.id]?.qty || 0;
        const wished = wishlistState.includes(p.id);
        const ad = document.getElementById(`product-action-${p.id}`);
        const wb = document.getElementById(`wishbtn-${p.id}`);
        if (ad) ad.innerHTML = qty > 0 ? `
            <button class="qty-btn" onclick="update(${p.id},-1)"><i class="fas fa-minus"></i></button>
            <span style="font-weight:600;min-width:36px;text-align:center;font-size:1.05rem;">${qty}</span>
            <button class="qty-btn" onclick="update(${p.id},1)"><i class="fas fa-plus"></i></button>
        ` : `<button class="btn btn-primary" onclick="update(${p.id},1)" style="flex:1;padding:0.75rem;font-size:0.9rem;"><i class="fas fa-plus"></i> Add</button>`;
        if (wb) {
            wb.className = 'wishlist-btn' + (wished ? ' active' : '');
            wb.querySelector('i').className = wished ? 'fas fa-heart' : 'far fa-heart';
        }
    });
    if (document.getElementById('cartModal').style.display === 'flex') showCart(cartState);
}

function render(filter = '') {
    const grid = document.getElementById('product-grid');
    if (!grid) return;
    const showHindi = localStorage.getItem('show_hindi') !== 'false';
    const compact = localStorage.getItem('compact_cards') === 'true';
    if (compact) grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(200px, 1fr))';
    else grid.style.gridTemplateColumns = '';

    const cats = ["Oils","Atta","Dal","Rice","Organic","Spices","Dairy","Others"];
    const grouped = {};
    cats.forEach(c => grouped[c] = []);
    products.filter(p => p.name.toLowerCase().includes(filter.toLowerCase()))
        .filter(p => activeCategory === 'All' || p.category === activeCategory)
        .forEach(p => { if (grouped[p.category]) grouped[p.category].push(p); });

    let html = '';
    cats.forEach(cat => {
        if (!grouped[cat]?.length) return;
        const catLabel = showHindi && catHindi[cat] ? `${cat} <span style="color:var(--text-secondary);font-size:0.9rem;">(${catHindi[cat]})</span>` : cat;
        html += `<div style="grid-column:1/-1;margin-top:${html?'2rem':'0'};margin-bottom:0.25rem;border-bottom:2px solid var(--border);padding-bottom:0.5rem;">
            <h2 style="font-size:1.5rem;font-weight:700;"><i class="fas fa-tag" style="color:var(--primary);margin-right:0.5rem;"></i>${catLabel}</h2></div>`;
        grouped[cat].forEach(p => {
            const qty = cartState[p.id]?.qty || 0;
            const wished = wishlistState.includes(p.id);
            const pLabel = showHindi && prodHindi[p.id] ? `${p.name} <span style="font-size:0.8rem;color:var(--text-secondary);">(${prodHindi[p.id]})</span>` : p.name;
            html += `
            <div class="product-card">
                <button id="wishbtn-${p.id}" class="wishlist-btn ${wished?'active':''}" onclick="toggleWish(${p.id})" title="Wishlist">
                    <i class="${wished?'fas':'far'} fa-heart"></i>
                </button>
                <img src="${p.img}" class="product-image" loading="lazy" alt="${p.name}" onerror="this.src='https://via.placeholder.com/280x160?text=${encodeURIComponent(p.name)}'">
                <div style="font-weight:600;font-size:1rem;margin-bottom:0.4rem;line-height:1.35;padding-right:1.5rem;">${pLabel}</div>
                <div style="font-size:1.4rem;font-weight:800;color:var(--primary);margin-bottom:0.875rem;">₹${p.price}</div>
                <div id="product-action-${p.id}" style="display:flex;align-items:center;justify-content:center;gap:0.75rem;background:var(--bg-secondary);padding:0.75rem;border-radius:var(--radius-md);">
                    ${qty > 0 ? `
                        <button class="qty-btn" onclick="update(${p.id},-1)"><i class="fas fa-minus"></i></button>
                        <span style="font-weight:600;min-width:36px;text-align:center;font-size:1.05rem;">${qty}</span>
                        <button class="qty-btn" onclick="update(${p.id},1)"><i class="fas fa-plus"></i></button>
                    ` : `<button class="btn btn-primary" onclick="update(${p.id},1)" style="flex:1;padding:0.75rem;font-size:0.9rem;"><i class="fas fa-plus"></i> Add</button>`}
                </div>
            </div>`;
        });
    });
    grid.innerHTML = html || '<div style="text-align:center;padding:4rem;color:var(--text-secondary);grid-column:1/-1;"><i class="fas fa-search" style="font-size:3rem;opacity:0.3;"></i><p style="margin-top:1rem;">No products found</p></div>';
}

async function update(id, change) {
    const res = await api(`action=update&id=${id}&change=${change}`);
    updateCartBadge();
    await sync();
}

async function toggleWish(id) {
    const res = await api(`action=wishlist_toggle&id=${id}`);
    wishlistState = res.wishlisted ? [...new Set([...wishlistState, id])] : wishlistState.filter(x => x !== id);
    renderQuantities();
    showToast(res.wishlisted ? '❤️ Added to wishlist' : '💔 Removed from wishlist');
}

// ---- CART ----
async function showCart(cachedCart = null) {
    document.getElementById('cartModal').style.display = 'flex';
    const cart = cachedCart || await api('action=fetch');
    let html = '', total = 0;
    for (let id in cart) {
        const item = cart[id];
        total += item.price * item.qty;
        html += `<div class="cart-item">
            <div><div style="font-weight:600;margin-bottom:0.2rem;">${item.name}</div>
            <div style="color:var(--text-secondary);font-size:0.85rem;">₹${item.price} × ${item.qty} = ₹${(item.price*item.qty).toLocaleString()}</div></div>
            <div class="qty-controls">
                <button class="qty-btn" onclick="update(${item.id},-1)"><i class="fas fa-minus"></i></button>
                <span style="min-width:28px;text-align:center;font-weight:600;">${item.qty}</span>
                <button class="qty-btn" onclick="update(${item.id},1)"><i class="fas fa-plus"></i></button>
            </div>
        </div>`;
    }
    document.getElementById('cartItems').innerHTML = html || '<div style="text-align:center;padding:2rem;color:var(--text-secondary);"><i class="fas fa-shopping-cart" style="font-size:3rem;opacity:0.3;display:block;margin-bottom:1rem;"></i>Your cart is empty</div>';
    document.getElementById('cartTotal').innerHTML = total ? `Total: ₹${total.toLocaleString()}` : '';
}

async function openCheckout() {
    closeModal('cartModal');
    document.getElementById('checkoutModal').style.display = 'flex';
    await showCart(cartState);
    document.getElementById('checkoutSummary').innerHTML = document.getElementById('cartItems').innerHTML;
    calculateTotal();
}

function calculateTotal() {
    const cart = Object.values(cartState);
    let total = cart.reduce((s, i) => s + i.price * i.qty, 0);
    const coupon = (document.getElementById('couponInput').value || '').trim().toUpperCase();
    let discount = 0, discountText = '';
    if (coupon === 'SAVE10') { discount = total * 0.10; discountText = '10% OFF'; }
    else if (coupon === 'SAVE20') { discount = total * 0.20; discountText = '20% OFF'; }
    else if (coupon === 'SAVE30') { discount = total * 0.30; discountText = '30% OFF'; }
    else if (coupon === 'FLAT50') { discount = 50; discountText = 'FLAT ₹50'; }
    else if (coupon === 'FLAT100') { discount = 100; discountText = 'FLAT ₹100'; }
    else if (coupon === 'FIRSTORDER' && total >= 300) { discount = total * 0.15; discountText = '15% FIRSTORDER'; }
    if (discount > total) discount = total;
    const final = total - discount;
    document.getElementById('finalTotal').innerHTML = `Payable: ₹${final.toLocaleString()} ` + (discount ? `<span style="text-decoration:line-through;font-size:0.85rem;color:var(--text-secondary);">₹${total.toLocaleString()}</span> <span style="color:var(--accent);font-size:0.875rem;font-weight:600;">(Saved ₹${Math.round(discount)} – ${discountText})</span>` : '<span style="color:var(--text-secondary);font-size:0.875rem;">No coupon applied</span>');
}

async function placeOrder() {
    const coupon = document.getElementById('couponInput').value;
    const res = await api(`action=placeorder&coupon=${encodeURIComponent(coupon)}`);
    if (res.success) {
        closeModal('checkoutModal');
        showToast(`🎉 Order #${res.order_id} placed! Thank you for shopping.`);
        sync();
    } else alert('❌ ' + (res.error || 'Order failed!'));
}

// ---- ORDERS PAGE ----
async function loadFullOrders() {
    const orders = await api('action=getorders');
    const el = document.getElementById('ordersFullList');
    el.innerHTML = orders.length ? orders.map(o => `
        <div class="order-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                <span style="font-weight:700;font-size:1.05rem;">Order #${o.order_id}</span>
                <span style="color:var(--primary);font-weight:700;font-size:1.15rem;">₹${parseFloat(o.total).toLocaleString()}</span>
            </div>
            <div style="color:var(--text-secondary);font-size:0.85rem;margin-bottom:0.4rem;">${new Date(o.created_at).toLocaleString('en-IN')}</div>
            <div style="font-size:0.875rem;display:flex;gap:1rem;flex-wrap:wrap;">
                <span>📦 ${o.item_count} items</span>
                ${o.coupon_used ? `<span>💰 Coupon: <strong>${o.coupon_used}</strong></span>` : ''}
                <span style="color:var(--primary);font-weight:600;">● ${o.status || 'Processing'}</span>
            </div>
        </div>`) .join('') : '<div style="text-align:center;padding:3rem;color:var(--text-secondary);"><i class="fas fa-box-open" style="font-size:3rem;opacity:0.3;display:block;margin-bottom:1rem;"></i>No orders yet! Start shopping.</div>';
}

// ---- WISHLIST PAGE ----
function renderWishlistPage() {
    const grid = document.getElementById('wishlistGrid');
    const wished = products.filter(p => wishlistState.includes(p.id));
    if (!wished.length) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:4rem;color:var(--text-secondary);"><i class="fas fa-heart" style="font-size:3rem;opacity:0.2;display:block;margin-bottom:1rem;"></i>Your wishlist is empty.<br>Tap the heart icon on any product.</div>';
        return;
    }
    grid.innerHTML = wished.map(p => `
        <div class="wishlist-item">
            <img src="${p.img}" alt="${p.name}" onerror="this.src='https://via.placeholder.com/200x120?text=${encodeURIComponent(p.name)}'">
            <div style="font-weight:600;font-size:0.9rem;margin-bottom:0.4rem;">${p.name}</div>
            <div style="color:var(--primary);font-weight:700;margin-bottom:0.75rem;">₹${p.price}</div>
            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                <button class="btn btn-primary" style="padding:0.5rem;font-size:0.8rem;width:100%;" onclick="update(${p.id},1);showToast('Added to cart ✓')"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                <button class="btn btn-danger" style="padding:0.4rem;font-size:0.8rem;width:100%;justify-content:center;" onclick="toggleWish(${p.id});renderWishlistPage()"><i class="fas fa-trash"></i> Remove</button>
            </div>
        </div>`).join('');
}


// ---- SMART RECIPES ----
const smartRecipes = [
    {
        title: 'Paneer Butter Masala',
        desc: 'A rich and creamy curry made with paneer, spices, and butter. A classic dinner delight!',
        img: 'https://imgs.search.brave.com/Q5nLUjSsgbGCexc3bN6U5wsPu3O2xmeQVjtmZl5GMYA/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly9jZG4u/bWFmcnNlcnZpY2Vz/LmNvbS9zeXMtbWFz/dGVyLXJvb3QvaDI1/L2gxYi80NTMyNDAw/NTAxNTU4Mi8xMjM4/MzAwX21haW4uanBn/P2ltPVJlc2l6ZT00/ODA', // Using Paneer image as fallback if not realistic
        ingredients: [42, 43, 36, 38, 37] // Paneer, Butter, Turmeric, Garam Masala, Red Chilli
    },
    {
        title: 'Hearty Dal Khichdi',
        desc: 'Comforting, healthy, and easy to digest one-pot meal. Perfectly paired with a dollop of ghee.',
        img: 'https://rukminim2.flixcart.com/image/800/800/l4zxn680/rice/u/p/a/1-brown-basmati-rice-1-kg-brown-raw-pouch-basmati-rice-organic-original-imagfrqcrwzcw64b.jpeg',
        ingredients: [26, 22, 10, 40, 35] // Basmati Rice, Moong Dal, Ghee, Cumin Seeds, Organic Turmeric
    },
    {
        title: 'Masala Chai & Biscuits',
        desc: 'The essential evening snack routine for total relaxation.',
        img: 'https://rukminim2.flixcart.com/image/300/300/xif0q/tea/f/h/s/100-matcha-tea-100g-japanese-matcha-green-tea-matcha-tea-powder-original-imahdnr8hsy9rxdw.jpeg',
        ingredients: [7, 4, 41, 47] // Tea, Sugar, Full cream milk, Biscuits
    },
    {
        title: 'Ghee Jeera Rice',
        desc: 'Fragrant jeera rice finished with ghee and cumin seeds, simple yet delicious.',
        img: 'https://imgs.search.brave.com/IGT-2nBlX4oUEs1FaoVoBrz3PBOydF5i1iJG0RMDe64/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly81Lmlt/aW1nLmNvbS9kYXRh/NS9BTkRST0lEL0Rl/ZmF1bHQvMjAyNC8x/Mi80NzExNTY2ODEv/RkMvTEsvSUsvMTY0/MjE5Mzg1L3Byb2R1/Y3QtanBlZy01MDB4/NTAwLmpwZWc',
        ingredients: [26, 10, 40, 5] // Basmati Rice, Ghee, Cumin Seeds, Salt
    }
];

function renderRecipes() {
    const el = document.getElementById('recipesGrid');
    if (!el) return;
    el.innerHTML = smartRecipes.map((r, i) => {
        const items = r.ingredients.map(id => products.find(p => p.id === id)?.name || 'Unknown Item');
        const imgSrc = r.img ? r.img : `https://via.placeholder.com/400x180.png?text=${encodeURIComponent(r.title)}`;
        return `
        <div class="recipe-card">
            <img src="${imgSrc}" class="recipe-img" alt="${r.title}" onerror="this.src='https://via.placeholder.com/400x180?text=Recipe'">
            <div class="recipe-content">
                <span class="recipe-badge">Meal Plan</span>
                <div style="font-weight:800;font-size:1.2rem;margin-bottom:0.5rem;">${r.title}</div>
                <div style="color:var(--text-secondary);font-size:0.9rem;line-height:1.5;margin-bottom:1rem;">${r.desc}</div>
                <div style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:1.5rem;">
                    <strong>Requires:</strong> ${items.join(', ')}
                </div>
                <button class="btn btn-primary" style="margin-top:auto;width:100%;justify-content:center;padding:0.75rem;" onclick="addRecipeIngredients(${i})">
                    <i class="fas fa-magic"></i> Add Missing Ingredients
                </button>
            </div>
        </div>
        `;
    }).join('');
}

async function addRecipeIngredients(idx) {
    const r = smartRecipes[idx];
    let addedCount = 0;
    
    // We send sequence of updates for missing ingredients
    for (let id of r.ingredients) {
        if (!cartState[id] || cartState[id].qty <= 0) {
            await api(`action=update&id=${id}&change=1`);
            addedCount++;
        }
    }
    
    if (addedCount > 0) {
        showToast(`🍲 Magic! Added ${addedCount} missing ingredients to cart.`);
        updateCartBadge();
        sync(false);
    } else {
        showToast(`✅ You already have all ingredients in your cart!`);
    }
}

// ---- OFFERS PAGE ----
function renderOffers() {
    const el = document.getElementById('offersGrid');
    el.innerHTML = coupons.map(c => `
        <div class="coupon-card" style="border-color:${c.color};">
            <div style="background:${c.color};color:white;padding:0.75rem 1.25rem;border-radius:var(--radius-md);text-align:center;flex-shrink:0;">
                <i class="${c.icon}" style="font-size:1.5rem;display:block;margin-bottom:0.3rem;"></i>
                <div style="font-weight:800;font-size:1.1rem;">${c.code}</div>
            </div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:1rem;margin-bottom:0.25rem;">${c.title}</div>
                <div style="color:var(--text-secondary);font-size:0.875rem;line-height:1.5;">${c.desc}</div>
            </div>
            <button class="btn btn-ghost copy-btn" onclick="copyCode('${c.code}')" style="flex-shrink:0;">
                <i class="fas fa-copy"></i> Copy
            </button>
        </div>`).join('');
}

function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => showToast(`✅ Coupon "${code}" copied to clipboard!`));
}

// ---- ADDRESSES ----
async function loadAddresses() {
    const addrs = await api('action=get_addresses');
    const el = document.getElementById('addressList');
    if (!addrs.length) { el.innerHTML = '<p style="text-align:center;color:var(--text-secondary);padding:2rem;">No saved addresses yet.</p>'; return; }
    el.innerHTML = addrs.map(a => `
        <div class="address-card ${a.is_default=='1'?'default':''}">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.5rem;">
                <div style="font-weight:700;font-size:0.95rem;">${a.label === 'Home' ? '🏠' : a.label === 'Work' ? '💼' : '📍'} ${a.label}
                    ${a.is_default=='1' ? '<span class="address-badge">Default</span>' : ''}
                </div>
                <div style="display:flex;gap:0.5rem;">
                    ${a.is_default!='1' ? `<button class="btn btn-ghost" style="padding:0.3rem 0.6rem;font-size:0.75rem;" onclick="setDefaultAddress(${a.id})">Set Default</button>` : ''}
                    <button class="btn btn-danger" style="padding:0.3rem 0.6rem;font-size:0.75rem;" onclick="deleteAddress(${a.id})"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <div style="font-size:0.9rem;color:var(--text-secondary);line-height:1.6;">${a.full_name} · ${a.phone}<br>${a.address_line}, ${a.city} – ${a.pincode}</div>
        </div>`).join('');
}

async function addAddress() {
    const body = `action=add_address&label=${encodeURIComponent(document.getElementById('addrLabel').value)}&full_name=${encodeURIComponent(document.getElementById('addrName').value)}&phone=${encodeURIComponent(document.getElementById('addrPhone').value)}&address_line=${encodeURIComponent(document.getElementById('addrLine').value)}&city=${encodeURIComponent(document.getElementById('addrCity').value)}&pincode=${encodeURIComponent(document.getElementById('addrPincode').value)}&is_default=${document.getElementById('addrDefault').checked?1:0}`;
    await api(body);
    showToast('✅ Address saved!');
    loadAddresses();
    ['addrName','addrPhone','addrLine','addrCity','addrPincode'].forEach(id => document.getElementById(id).value='');
    document.getElementById('addrDefault').checked = false;
}

async function deleteAddress(id) {
    if (!confirm('Delete this address?')) return;
    await api(`action=delete_address&id=${id}`);
    showToast('🗑️ Address removed');
    loadAddresses();
}

async function setDefaultAddress(id) {
    await api(`action=set_default_address&id=${id}`);
    showToast('✅ Default address updated');
    loadAddresses();
}

// ---- PROFILE ----
async function loadProfile() {
    profileData = await api('action=get_profile');
    const p = profileData.profile || {};
    const s = profileData.stats || {};
    document.getElementById('profileEmailBig').textContent = p.email || 'No email set';
    document.getElementById('profileEmail').value = p.email || '';
    document.getElementById('profilePhone').value = p.phone || '';
    document.getElementById('statOrders').textContent = s.total_orders || 0;
    document.getElementById('statSpent').textContent = Math.round(parseFloat(s.total_spent||0)).toLocaleString('en-IN');
    document.getElementById('statMember').textContent = s.first_order ? new Date(s.first_order).toLocaleDateString('en-IN',{month:'short',year:'numeric'}) : 'New';
}

async function saveProfile() {
    const email = document.getElementById('profileEmail').value;
    const phone = document.getElementById('profilePhone').value;
    await api(`action=update_profile&email=${encodeURIComponent(email)}&phone=${encodeURIComponent(phone)}`);
    showToast('✅ Profile updated!');
    profileData = null;
    loadProfile();
}

// ---- SETTINGS ----
function switchSettingsTab(name, btn) {
    document.querySelectorAll('.settings-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('stab-' + name).classList.add('active');
}

async function loadSettingsProfile() {
    if (!profileData) profileData = await api('action=get_profile');
    const p = profileData.profile || {};
    const email = p.email || '';
    const phone = p.phone || '';
    document.getElementById('settingsEmail').value = email;
    document.getElementById('settingsPhone').value = phone;
    document.getElementById('settingsEmailDisplay').textContent = email || 'No email set';
}

async function saveSettingsProfile() {
    const email = document.getElementById('settingsEmail').value;
    const phone = document.getElementById('settingsPhone').value;
    await api(`action=update_profile&email=${encodeURIComponent(email)}&phone=${encodeURIComponent(phone)}`);
    document.getElementById('settingsEmailDisplay').textContent = email || 'No email set';
    profileData = null;
    showToast('✅ Account updated!');
}

async function changePassword() {
    const old = document.getElementById('oldPass').value;
    const nw  = document.getElementById('newPass').value;
    const cf  = document.getElementById('confPass').value;
    const res = await api(`action=change_password&old_password=${encodeURIComponent(old)}&new_password=${encodeURIComponent(nw)}&confirm_password=${encodeURIComponent(cf)}`);
    if (res.success) {
        showToast('✅ Password changed!');
        ['oldPass','newPass','confPass'].forEach(id => document.getElementById(id).value='');
    } else alert('❌ ' + (res.error || 'Failed'));
}

function savePref(key, val) {
    localStorage.setItem(key, val);
    showToast('✅ Preference saved');
}

function applyPrefs() {
    const compact = localStorage.getItem('compact_cards') === 'true';
    const grid = document.getElementById('product-grid');
    if (grid) {
        grid.style.gridTemplateColumns = compact ? 'repeat(auto-fill, minmax(200px, 1fr))' : '';
    }
}

// ---- ADMIN ----
async function adminAddProduct() {
    const name = document.getElementById('adminProdName').value;
    const price = document.getElementById('adminProdPrice').value;
    const category = document.getElementById('adminProdCat').value;
    const img = document.getElementById('adminProdImg').value;
    if(!name || !price) return showToast('Name and price needed');
    
    await api(`action=add_product&name=${encodeURIComponent(name)}&price=${encodeURIComponent(price)}&category=${encodeURIComponent(category)}&img=${encodeURIComponent(img)}`);
    showToast('✅ Product added! Reloading...');
    setTimeout(()=>location.reload(), 1000); // Reload to fetch fresh variables
}

async function adminDeleteProduct(id) {
    if(!confirm('Delete this product?')) return;
    await api(`action=delete_product&id=${id}`);
    showToast('🗑️ Product deleted');
    setTimeout(()=>location.reload(), 800);
}

function renderAdminProducts() {
    const el = document.getElementById('adminProductList');
    if(!el) return;
    el.innerHTML = products.map(p => `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem;border-bottom:1px solid var(--border);">
            <div style="display:flex;align-items:center;gap:1rem;">
                <img src="${p.img}" style="width:40px;height:40px;border-radius:var(--radius-sm);object-fit:cover;" onerror="this.src='https://via.placeholder.com/40'">
                <div><div style="font-weight:600;">${p.name}</div><div style="font-size:0.8rem;color:var(--text-secondary);">${p.category} · ₹${p.price}</div></div>
            </div>
            <button class="btn btn-danger" style="padding:0.4rem 0.75rem;font-size:0.8rem;" onclick="adminDeleteProduct(${p.id})"><i class="fas fa-trash"></i></button>
        </div>
    `).join('');
}

async function renderAdminDashboard() {
    const [stats, activity, security] = await Promise.all([
        api('action=get_admin_dashboard'),
        api('action=get_activity_logs'),
        api('action=get_security_data')
    ]);
    document.getElementById('dashTotalOrders').textContent = stats.total_orders || 0;
    document.getElementById('dashTotalRevenue').textContent = `₹${Math.round(stats.total_revenue || 0).toLocaleString()}`;
    document.getElementById('dashOrdersToday').textContent = stats.orders_today || 0;
    document.getElementById('dashRevenueToday').textContent = `₹${Math.round(stats.revenue_today || 0).toLocaleString()}`;
    document.getElementById('dashTotalUsers').textContent = stats.total_users || 0;
    document.getElementById('dashNewUsersToday').textContent = stats.new_users_today || 0;
    document.getElementById('dashTotalProducts').textContent = stats.total_products || 0;
    document.getElementById('dashAvgOrderValue').textContent = `₹${Math.round(stats.avg_order_value || 0).toLocaleString()}`;
    document.getElementById('dashFailedLogins').textContent = security.failed_logins_7d || 0;
    document.getElementById('dashSuspiciousUsers').textContent = security.suspicious_users || 0;
    renderAdminActivityLogs(activity);
}

function renderAdminActivityLogs(logs = []) {
    const el = document.getElementById('adminActivityLog');
    if(!el) return;
    if (!logs.length) {
        el.innerHTML = '<div style="text-align:center;color:var(--text-secondary);padding:2rem;">No activity logged yet.</div>';
        return;
    }
    el.innerHTML = logs.map(entry => `
        <div style="padding:0.75rem;border-bottom:1px solid var(--border);">
            <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                <div style="font-weight:700;">${entry.action || 'Activity'}</div>
                <div style="color:var(--text-secondary);font-size:0.8rem;">${new Date(entry.timestamp).toLocaleString('en-IN')}</div>
            </div>
            <div style="font-size:0.9rem;color:var(--text-secondary);margin-top:0.35rem;">${entry.username ? `${entry.username} — ` : ''}${entry.details || ''}</div>
        </div>
    `).join('');
}

function switchAdminTab(tab, btn) {
    document.querySelectorAll('.admin-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.admin-section').forEach(s => s.style.display = 'none');
    btn.classList.add('active');
    document.getElementById('admin' + tab.charAt(0).toUpperCase() + tab.slice(1) + 'Section').style.display = 'block';
    if (tab === 'overview') renderAdminDashboard();
    if (tab === 'activity') api('action=get_activity_logs').then(renderAdminActivityLogs);
    if (tab === 'products') renderAdminProducts();
}

function closeModal(id) { document.getElementById(id).style.display = 'none'; }

// INIT
document.addEventListener('DOMContentLoaded', () => {
    // Load prefs
    const compact = localStorage.getItem('compact_cards') === 'true';
    if (document.getElementById('prefCompact')) document.getElementById('prefCompact').checked = compact;
    const hindi = localStorage.getItem('show_hindi');
    if (hindi === 'false' && document.getElementById('prefHindi')) document.getElementById('prefHindi').checked = false;

    sync();
    document.getElementById('searchbar')?.addEventListener('input', e => {
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(() => render(e.target.value), 280);
    });

    const micBtn = document.getElementById('micBtn');
    const sb = document.getElementById('searchbar');
    if (micBtn && sb) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (SpeechRecognition) {
            const recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.lang = 'en-IN';
            recognition.onstart = () => {
                micBtn.className = 'fas fa-microphone-lines fa-beat-fade';
                micBtn.style.color = 'var(--danger)';
                sb.placeholder = 'Listening...';
            };
            recognition.onresult = (e) => {
                const txt = e.results[0][0].transcript;
                sb.value = txt;
                render(txt);
            };
            recognition.onerror = (e) => showToast('Voice search failed: ' + e.error);
            recognition.onend = () => {
                micBtn.className = 'fas fa-microphone';
                micBtn.style.color = 'var(--primary)';
                sb.placeholder = 'Search atta, rice, dal, spices...';
            };
            micBtn.addEventListener('click', () => recognition.start());
        } else {
            micBtn.style.display = 'none';
        }
    }

    document.getElementById('couponInput')?.addEventListener('input', calculateTotal);
    document.getElementById('viewCart')?.addEventListener('click', () => showCart());
});
<?php endif; ?>
</script>
</body>
</html>