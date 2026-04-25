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


