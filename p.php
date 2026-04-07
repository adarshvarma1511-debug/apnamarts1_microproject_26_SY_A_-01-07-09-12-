<?php
// =====================================================
// ALL FUNCTIONS FIRST - NO ERRORS
// =====================================================
function getCartCount($conn, $uid) {
    if (!$conn || $uid == 0) return 0;
    $stmt = @$conn->prepare("SELECT SUM(qty) as total FROM cart WHERE user_id = ?");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return (int)($res['total'] ?? 0);
}

function getCart($conn, $uid) {
    if (!$conn || $uid == 0) return [];
    $cart = [];
    $stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $cart[$row['id']] = $row;
    }
    return $cart;
}

function getOrders($conn, $uid) {
    if (!$conn || $uid == 0) return [];
    $stmt = $conn->prepare("SELECT o.*, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON o.order_id = oi.order_id WHERE o.user_id = ? GROUP BY o.order_id ORDER BY o.created_at DESC LIMIT 10");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

session_start();
$conn = new mysqli("localhost", "root", "", "apna_marts1");
$error = "";
$success = "";
$cartCount = 0;
$isLoggedIn = isset($_SESSION['user_id']);
$uid = $_SESSION['user_id'] ?? 0;

if (!$conn->connect_error) {
    $cartCount = getCartCount($conn, $uid);
}

// AUTH + CART OPERATIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['auth_action'])) {
        if ($_POST['auth_action'] === 'signup') {
            $user = trim($_POST['username'] ?? '');
            $pass = trim($_POST['password'] ?? '');
            $confirm = trim($_POST['confirm_password'] ?? '');
            if ($pass !== $confirm) $error = "Passwords don't match!";
            elseif (strlen($pass) < 4) $error = "Password too short!";
            else {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT IGNORE INTO users (username, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $user, $hashed);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $_SESSION['user_id'] = $conn->insert_id;
                        $_SESSION['username'] = $user;
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit;
                    } else $error = "Username exists!";
                } else $error = "Signup failed!";
            }
        } elseif ($_POST['auth_action'] === 'login') {
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
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            }
            $error = "Invalid credentials!";
        } elseif ($_POST['auth_action'] === 'logout') {
            session_destroy();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// AJAX HANDLERS - FULL CART & ORDER SYSTEM
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $conn) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $id = (int)($_POST['id'] ?? 0);
    
    // PRODUCT LIST
    $products = [
        1=>['name'=>'Wheat Atta','price'=>45], 2=>['name'=>'Rice','price'=>60], 
        3=>['name'=>'Dal','price'=>90], 4=>['name'=>'Sugar','price'=>40], 
        5=>['name'=>'Salt','price'=>20], 6=>['name'=>'Cooking Oil','price'=>140], 
        7=>['name'=>'Tea','price'=>120], 8=>['name'=>'Coffee','price'=>180], 
        9=>['name'=>'Milk Powder','price'=>220], 10=>['name'=>'Ghee','price'=>520]
    ];
    
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
        
        // MULTIPLE COUPON SUPPORT
        switch ($coupon) {
            case 'SAVE10':      // 10% off
                $discount = $total * 0.10;
                break;
            case 'SAVE20':      // 20% off
                $discount = $total * 0.20;
                break;
            case 'SAVE30':      // 30% off
                $discount = $total * 0.30;
                break;
            case 'FLAT50':      // flat 50 rupees off
                $discount = 50;
                break;
            case 'FLAT100':     // flat 100 rupees off
                $discount = 100;
                break;
            case 'FIRSTORDER':  // 15% off first order (min ₹300)
                if ($total >= 300) $discount = $total * 0.15;
                break;
            default:
                $coupon = '';   // invalid / no coupon
                break;
        }
        
        // Do not allow discount more than total
        if ($discount > $total) {
            $discount = $total;
        }
        
        $finalTotal = $total - $discount;
        
        // CREATE ORDER
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total, coupon_used) VALUES (?, ?, ?)");
        $stmt->bind_param("ids", $uid, $finalTotal, $coupon);
        $stmt->execute();
        $orderId = $conn->insert_id;
        
        // ADD ORDER ITEMS
        foreach ($cart as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, name, price, qty) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisdi", $orderId, $item['id'], $item['name'], $item['price'], $item['qty']);
            $stmt->execute();
        }
        
        // CLEAR CART
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        
        exit(json_encode(['success' => true, 'order_id' => $orderId]));
    }
    
    if ($action === 'getorders') {
        exit(json_encode(getOrders($conn, $uid)));
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
            --primary: #10b981; --primary-dark: #059669; --accent: #f59e0b;
            --success: #10b981; --danger: #ef4444; --bg-primary: #ffffff;
            --bg-secondary: #f8fafc; --text-primary: #0f172a; --text-secondary: #475569;
            --border: #e2e8f0; --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1); --shadow-lg: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --radius-sm: 8px; --radius-md: 12px; --radius-lg: 20px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); color: var(--text-primary); min-height: 100vh; }
        .navbar { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; padding: 1rem 2rem; box-shadow: var(--shadow-sm); }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .logo { font-size: 1.75rem; font-weight: 800; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .search-container { flex: 1; max-width: 500px; margin: 0 2rem; position: relative; }
        .search-input { width: 100%; height: 48px; padding: 0 1rem 0 3rem; border: 2px solid var(--border); border-radius: var(--radius-lg); font-size: 1rem; background: rgba(255,255,255,0.8); transition: all 0.3s ease; }
        .search-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16,185,129,0.1); background: white; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; transition: all 0.2s ease; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; box-shadow: var(--shadow-md); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: var(--shadow-lg); }
        .btn-secondary { background: rgba(16,185,129,0.1); color: var(--primary); border: 2px solid rgba(16,185,129,0.2); }
        .btn-danger { background: rgba(239,68,68,0.1); color: #ef4444; border: 2px solid rgba(239,68,68,0.2); }
        .products-grid { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2rem; }
        .product-card { background: white; border-radius: var(--radius-lg); padding: 1.5rem; box-shadow: var(--shadow-md); transition: all 0.3s ease; border: 1px solid var(--border); position: relative; overflow: hidden; }
        .product-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), var(--accent)); transform: scaleX(0); transition: transform 0.3s ease; }
        .product-card:hover::before { transform: scaleX(1); }
        .product-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
        .product-image { width: 100%; height: 180px; object-fit: cover; border-radius: var(--radius-md); margin-bottom: 1rem; }
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(8px); }
        .modal-card { background: white; padding: 2.5rem; border-radius: var(--radius-lg); width: 90vw; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-lg); animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .auth-overlay { position: fixed; inset: 0; background: linear-gradient(135deg, rgba(16,185,129,0.95), rgba(34,197,94,0.95)); display: flex; align-items: center; justify-content: center; z-index: 1000; }
        .auth-card { background: white; padding: 3rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); width: 100%; max-width: 420px; text-align: center; }
        .form-input { width: 100%; padding: 1rem; margin: 0.5rem 0; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 1rem; transition: all 0.3s ease; }
        .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }
        .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid var(--border); }
        .qty-controls { display: flex; align-items: center; gap: 0.5rem; }
        .qty-btn { width: 36px; height: 36px; border: 2px solid var(--border); background: white; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; transition: all 0.2s; }
        .qty-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
        .order-card { background: #f8fafc; border-left: 4px solid var(--primary); padding: 1.5rem; margin: 1rem 0; border-radius: var(--radius-md); }
        .coupon-info { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); padding: 1rem; border-radius: var(--radius-md); margin: 1rem 0; }
        @media (max-width: 768px) { 
            .nav-container { flex-direction: column; gap: 1rem; padding: 1rem; } 
            .search-container { margin: 0; order: -1; width: 100%; } 
            .products-grid { grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); padding: 0 1rem; } 
        }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<div class="auth-overlay">
    <div class="auth-card">
        <h2 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 2rem; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            🛒 Apna Mart
        </h2>
        <div style="display:flex; background:var(--bg-secondary); border-radius:var(--radius-md); overflow:hidden; margin-bottom:2rem;">
            <button class="btn auth-tab active" onclick="showTab('login')" style="flex:1;padding:1rem;font-weight:600;background:none;border:none;cursor:pointer;">Login</button>
            <button class="btn auth-tab" onclick="showTab('signup')" style="flex:1;padding:1rem;font-weight:600;background:none;border:none;cursor:pointer;">Sign Up</button>
        </div>
        <form method="POST" id="loginForm">
            <input type="hidden" name="auth_action" value="login">
            <input name="username" class="form-input" placeholder="Username" required>
            <input name="password" type="password" class="form-input" placeholder="Password" required>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:1.25rem;font-size:1.1rem;">Enter Apna Mart</button>
        </form>
        <form method="POST" id="signupForm" style="display:none;">
            <input type="hidden" name="auth_action" value="signup">
            <input name="username" class="form-input" placeholder="New Username" required>
            <input name="password" type="password" class="form-input" placeholder="New Password (min 4)" required>
            <input name="confirm_password" type="password" class="form-input" placeholder="Confirm Password" required>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:1.25rem;font-size:1.1rem;">Create Account</button>
        </form>
        <?php if ($error): ?><p style="color:var(--danger);font-size:14px;margin-top:1rem;"><?=$error?></p><?php endif; ?>
        <p style="font-size:12px;color:var(--text-secondary);margin-top:1.5rem;">Demo: <strong>admin</strong> / <strong>admin123</strong></p>
    </div>
</div>
<?php else: ?>

<div id="nav_bar" class="navbar">
    <div class="nav-container">
        <div class="logo">🛒 Apna Mart</div>
        <div class="search-container">
            <i class="fas fa-search" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-secondary);"></i>
            <input id="searchbar" class="search-input" placeholder="Search atta, rice, dal...">
        </div>
        <div style="display:flex;gap:1rem;align-items:center;">
            <span style="font-weight:500;color:var(--text-secondary);">Hi, <?=htmlspecialchars($_SESSION['username'])?></span>
            <button id="viewCart" class="btn btn-secondary"><i class="fas fa-shopping-cart"></i> Cart (<?=$cartCount?>)</button>
            <button id="viewOrders" class="btn btn-secondary"><i class="fas fa-box"></i> Orders</button>
            <form method="POST" style="display:inline;"><input type="hidden" name="auth_action" value="logout"><button type="submit" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</button></form>
        </div>
    </div>
</div>

<div id="product-grid" class="products-grid"></div>

<!-- CART MODAL -->
<div id="cartModal" class="modal-overlay">
    <div class="modal-card" style="max-width:700px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;">
            <h2 style="font-size:1.75rem;font-weight:700;margin:0;"><i class="fas fa-shopping-cart"></i> Your Basket</h2>
            <button onclick="closeModal('cartModal')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-secondary);"><i class="fas fa-times"></i></button>
        </div>
        <div id="cartItems" style="min-height:200px;"></div>
        <div id="cartTotal" style="margin:2rem 0;font-size:1.5rem;font-weight:700;text-align:center;color:var(--primary);"></div>
        <button class="btn btn-primary" style="width:100%;padding:1.25rem;font-size:1.1rem;" onclick="openCheckout()">Proceed to Checkout</button>
    </div>
</div>

<!-- CHECKOUT MODAL -->
<div id="checkoutModal" class="modal-overlay">
    <div class="modal-card" style="max-width:700px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;">
            <h2 style="font-size:1.75rem;font-weight:700;margin:0;"><i class="fas fa-credit-card"></i> Checkout</h2>
            <button onclick="closeModal('checkoutModal')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-secondary);"><i class="fas fa-times"></i></button>
        </div>
        <div id="checkoutSummary"></div>
        
        <!-- COUPON SECTION WITH INFO -->
        <div class="coupon-info">
            <div style="font-weight:600;margin-bottom:0.5rem;">🎟️ Apply Coupon</div>
            <div style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:0.75rem;">
                SAVE10 (10%) • SAVE20 (20%) • SAVE30 (30%) • FLAT50 (₹50) • FLAT100 (₹100) • FIRSTORDER (15% on ₹300+)
            </div>
            <input id="couponInput" class="form-input" placeholder="Enter coupon code..." style="margin:0;">
        </div>
        
        <div id="finalTotal" style="font-size:1.5rem;font-weight:700;text-align:center;color:var(--primary);margin:1rem 0;"></div>
        <button class="btn btn-primary" style="width:100%;padding:1.25rem;font-size:1.1rem;" onclick="placeOrder()">Place Order</button>
    </div>
</div>

<!-- ORDERS MODAL -->
<div id="ordersModal" class="modal-overlay">
    <div class="modal-card" style="max-width:700px;max-height:70vh;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;">
            <h2 style="font-size:1.75rem;font-weight:700;margin:0;"><i class="fas fa-box"></i> Order History</h2>
            <button onclick="closeModal('ordersModal')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-secondary);"><i class="fas fa-times"></i></button>
        </div>
        <div id="ordersList"></div>
    </div>
</div>

<?php endif; ?>

<script>
const products = [
    {id:1,name:"Wheat Atta",price:45,img:"https://2.wlimg.com/product_images/bc-small/2025/11/14978261/premium-multigrain-wheat-flour-1762163885-8414485.jpeg"},
    {id:2,name:"Rice",price:60,img:"https://thehammergoods.com/assets/images/products/minikit%20husk%201.png"},
    {id:3,name:"Dal",price:90,img:"https://tiimg.tistatic.com/fp/1/003/177/chana-dal-655.jpg"},
    {id:4,name:"Sugar",price:40,img:"https://hsingredients.com/wp-content/uploads/sweeteners_product-300x200.jpg"},
    {id:5,name:"Salt",price:20,img:"https://img1.exportersindia.com/product_images/bc-small/2024/2/13113700/iodised-salt-1709036249-7313249.jpeg"},
    {id:6,name:"Cooking Oil",price:140,img:"https://www.foodfair.gd/media/catalog/product/cache/cf74414bcb44f5c30fb7c110990adbd7/F/F/FF1000194_QvehWzlAlkaQyKWO.jpg"},
    {id:7,name:"Tea",price:120,img:"https://rukminim2.flixcart.com/image/300/300/xif0q/tea/f/h/s/100-matcha-tea-100g-japanese-matcha-green-tea-matcha-tea-powder-original-imahdnr8hsy9rxdw.jpeg"},
    {id:8,name:"Coffee",price:180,img:"https://cdn.kindlife.in/images/detailed/149/COFFEE_POWDER_JAR_1.jpg?t=1705124575"},
    {id:9,name:"Milk Powder",price:220,img:"https://img.freepik.com/premium-psd/skimmed-milk-powder-sachets-product-display_1288574-913.jpg?semt=ais_user_personalization&w=740&q=80"},
    {id:10,name:"Ghee",price:520,img:"https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTIj9ZYDAzdZNQ40SU1rHO-ZstjRBeMWe4ynw&s"}
];
let cartState = {};

async function api(body) {
    const r = await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body});
    return await r.json();
}

async function sync() {
    cartState = await api('action=fetch');
    render();
}

function render(filter = '') {
    const grid = document.getElementById('product-grid');
    if (!grid) return;
    grid.innerHTML = products
        .filter(p => p.name.toLowerCase().includes(filter.toLowerCase()))
        .map(p => {
            const qty = cartState[p.id]?.qty || 0;
            return `
            <div class="product-card">
                <img src="${p.img}" class="product-image" loading="lazy" alt="${p.name}" onerror="this.src='https://via.placeholder.com/280x180?text=${p.name}'">
                <div style="font-weight:700;font-size:1.125rem;margin-bottom:0.5rem;line-height:1.3;">${p.name}</div>
                <div style="font-size:1.5rem;font-weight:800;color:var(--primary);margin-bottom:1rem;">₹${p.price}</div>
                <div style="display:flex;align-items:center;justify-content:center;gap:1rem;background:var(--bg-secondary);padding:1rem;border-radius:var(--radius-lg);">
                    ${qty > 0 ? `
                        <button class="qty-btn" onclick="update(${p.id},-1)" title="Remove"><i class="fas fa-minus"></i></button>
                        <span style="font-weight:600;min-width:40px;text-align:center;font-size:1.1rem;">${qty}</span>
                        <button class="qty-btn" onclick="update(${p.id},1)" title="Add"><i class="fas fa-plus"></i></button>
                    ` : `<button class="btn btn-primary" onclick="update(${p.id},1)" style="flex:1;padding:0.875rem 1rem;"><i class="fas fa-plus"></i> Add to Cart</button>`}
                </div>
            </div>`;
        }).join('');
}

async function update(id, change) {
    const res = await api(`action=update&id=${id}&change=${change}`);
    if (document.querySelector('#viewCart')) document.querySelector('#viewCart').innerHTML = `<i class="fas fa-shopping-cart"></i> Cart (${res.count})`;
    sync();
}

async function showCart() {
    document.getElementById('cartModal').style.display = 'flex';
    const cart = await api('action=fetch');
    let html = '', total = 0;
    for (let id in cart) {
        const item = cart[id];
        total += item.price * item.qty;
        html += `
            <div class="cart-item">
                <div>
                    <div style="font-weight:600;margin-bottom:0.25rem;">${item.name}</div>
                    <div style="color:var(--text-secondary);font-size:0.875rem;">₹${item.price} x ${item.qty}</div>
                </div>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <div class="qty-controls">
                        <button class="qty-btn" onclick="update(${item.id},-1)"><i class="fas fa-minus"></i></button>
                        <span style="min-width:30px;text-align:center;font-weight:600;">${item.qty}</span>
                        <button class="qty-btn" onclick="update(${item.id},1)"><i class="fas fa-plus"></i></button>
                    </div>
                    <span style="font-weight:600;min-width:80px;text-align:right;">₹${(item.price * item.qty).toLocaleString()}</span>
                </div>
            </div>`;
    }
    document.getElementById('cartItems').innerHTML = html || '<div style="text-align:center;padding:3rem;color:var(--text-secondary);"><i class="fas fa-shopping-cart" style="font-size:4rem;margin-bottom:1rem;opacity:0.5;"></i><div>Your cart is empty</div></div>';
    document.getElementById('cartTotal').innerHTML = total ? `Total: ₹${total.toLocaleString()}` : '';
}

async function openCheckout() {
    closeModal('cartModal');
    document.getElementById('checkoutModal').style.display = 'flex';
    showCart(); // Reuse cart display logic
    document.getElementById('checkoutSummary').innerHTML = document.getElementById('cartItems').innerHTML;
    calculateTotal();
}

function calculateTotal() {
    const cart = Object.values(cartState);
    let total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const rawCoupon = document.getElementById('couponInput').value || '';
    const coupon = rawCoupon.trim().toUpperCase();

    let discount = 0;
    let discountText = '';

    if (coupon === 'SAVE10') {
        discount = total * 0.10;
        discountText = '10% OFF';
    } else if (coupon === 'SAVE20') {
        discount = total * 0.20;
        discountText = '20% OFF';
    } else if (coupon === 'SAVE30') {
        discount = total * 0.30;
        discountText = '30% OFF';
    } else if (coupon === 'FLAT50') {
        discount = 50;
        discountText = 'FLAT ₹50';
    } else if (coupon === 'FLAT100') {
        discount = 100;
        discountText = 'FLAT ₹100';
    } else if (coupon === 'FIRSTORDER' && total >= 300) {
        discount = total * 0.15;
        discountText = '15% FIRST ORDER';
    }

    if (discount > total) {
        discount = total;
        discountText = '100% OFF (FREE)';
    }

    const final = total - discount;

    document.getElementById('finalTotal').innerHTML =
        `Payable: ₹${final.toLocaleString()} ` +
        (discount
            ? `<span style="text-decoration:line-through;font-size:0.875rem;color:var(--text-secondary);">₹${total.toLocaleString()}</span> 
               <span style="color:var(--accent);font-size:0.9rem;font-weight:600;">(Saved ₹${discount.toFixed(0)} - ${discountText})</span>`
            : '<span style="color:var(--text-secondary);font-size:0.875rem;">No coupon applied</span>');
}

async function placeOrder() {
    const coupon = document.getElementById('couponInput').value;
    const res = await api(`action=placeorder&coupon=${encodeURIComponent(coupon)}`);
    if (res.success) {
        alert(`🎉 Order #${res.order_id} placed successfully! Thank you for shopping!`);
        location.reload();
    } else {
        alert('❌ ' + (res.error || 'Order failed!'));
    }
}

async function showOrders() {
    document.getElementById('ordersModal').style.display = 'flex';
    const orders = await api('action=getorders');
    let html = orders.length ? orders.map(o => `
        <div class="order-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                <span style="font-weight:700;font-size:1.125rem;">Order #${o.order_id}</span>
                <span style="color:var(--primary);font-weight:700;font-size:1.25rem;">₹${parseFloat(o.total).toLocaleString()}</span>
            </div>
            <div style="color:var(--text-secondary);font-size:0.875rem;margin-bottom:0.5rem;">${new Date(o.created_at).toLocaleString('en-IN')}</div>
            <div style="font-size:0.875rem;color:var(--text-secondary);">
                📦 ${o.item_count} items ${o.coupon_used ? `| 💰 ${o.coupon_used}` : ''} | Status: ${o.status || 'Processing'}
            </div>
        </div>
    `).join('') : '<div style="text-align:center;padding:3rem;color:var(--text-secondary);"><i class="fas fa-box-open" style="font-size:4rem;margin-bottom:1rem;opacity:0.5;"></i><div>No orders yet! Start shopping 🛒</div></div>';
    document.getElementById('ordersList').innerHTML = html;
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function showTab(tab) {
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('loginForm').style.display = tab === 'login' ? 'block' : 'none';
    document.getElementById('signupForm').style.display = tab === 'signup' ? 'block' : 'none';
}

// INIT
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('product-grid')) {
        sync();
        document.getElementById('searchbar')?.addEventListener('input', e => {
            clearTimeout(window.searchTimeout);
            window.searchTimeout = setTimeout(() => render(e.target.value), 300);
        });
        document.getElementById('couponInput')?.addEventListener('input', calculateTotal);
        document.getElementById('viewCart')?.addEventListener('click', showCart);
        document.getElementById('viewOrders')?.addEventListener('click', showOrders);
    }
});
</script>
</body>
</html>