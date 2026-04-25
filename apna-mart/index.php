<?php
require_once __DIR__ . '/includes/functions.php';
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
    <link rel="stylesheet" href="assets/style.css">
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
    window.APP_DATA = {
        products: <?= json_encode($allProducts) ?>,
        catHindi: {"Oils":"तेल","Atta":"आटा","Dal":"दाल","Rice":"चावल","Organic":"जैविक","Spices":"मसाले","Dairy":"डेयरी","Others":"अन्य"},
        prodHindi: {1:'गेहूँ का आटा',2:'चावल',3:'दाल',4:'चीनी',5:'नमक',6:'पकाने का तेल',7:'चाय',8:'कॉफ़ी',9:'दूध पाउडर',10:'घी',11:'सूरजमुखी तेल',12:'सरसों तेल',13:'जैतून तेल',14:'सोयाबीन तेल',15:'नारियल तेल',16:'मल्टीग्रेन आटा',17:'चक्की ताज़ा आटा',18:'बेसन',19:'मैदा',20:'रागी आटा',21:'तूर दाल',22:'मूंग दाल',23:'मसूर दाल',24:'चना दाल',25:'उड़द दाल',26:'बासमती चावल',27:'सोना मसूरी चावल',28:'ब्राउन चावल',29:'कोलम चावल',30:'जीरा चावल',31:'जैविक गेहूँ आटा',32:'जैविक चावल',33:'जैविक शहद',34:'जैविक चीनी',35:'जैविक हल्दी पाउडर',36:'हल्दी पाउडर',37:'लाल मिर्च पाउडर',38:'गरम मसाला',39:'धनिया पाउडर',40:'जीरा',41:'फुल क्रीम दूध',42:'पनीर',43:'मक्खन',44:'चीज़ स्लाइस',45:'दही',46:'मैगी नूडल्स',47:'बिस्कुट',48:'कॉर्नफ्लेक्स',49:'ओट्स',50:'पीनट बटर'},
        coupons: [
            {code:'SAVE10', title:'10% Off Everything', desc:'Get 10% discount on your entire cart. No minimum order required.', color:'#10b981', icon:'fas fa-percent'},
            {code:'SAVE20', title:'20% Mega Discount', desc:'Save big with 20% off on your full cart order.', color:'#3b82f6', icon:'fas fa-tag'},
            {code:'SAVE30', title:'30% Super Saver', desc:'Flat 30% off — our biggest percentage discount available.', color:'#8b5cf6', icon:'fas fa-bolt'},
            {code:'FLAT50', title:'Flat ₹50 Off', desc:'Instant ₹50 deduction on any cart. No minimum value.', color:'#f59e0b', icon:'fas fa-rupee-sign'},
            {code:'FLAT100', title:'Flat ₹100 Off', desc:'Save ₹100 on cart orders. Best for larger purchases.', color:'#ef4444', icon:'fas fa-gift'},
            {code:'FIRSTORDER', title:'15% First Order', desc:'Exclusive 15% off on first purchase above ₹300.', color:'#10b981', icon:'fas fa-star'}
        ]
    };
    </script>
    <script src="assets/script.js" defer></script>

</body>
</html>
