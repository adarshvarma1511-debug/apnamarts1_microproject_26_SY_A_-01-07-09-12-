window.APP_DATA = window.APP_DATA || {};
const products = window.APP_DATA.products || [];
const catHindi = window.APP_DATA.catHindi || {};
const prodHindi = window.APP_DATA.prodHindi || {};
const coupons = window.APP_DATA.coupons || [];
function showTab(tab) {
    const loginForm = document.getElementById('loginForm');
    const signupForm = document.getElementById('signupForm');
    const tabs = document.querySelectorAll('.auth-tab');
    tabs.forEach(btn => btn.classList.toggle('active', btn.getAttribute('onclick') === "showTab('" + tab + "')"));
    if (loginForm) loginForm.style.display = tab === 'login' ? 'block' : 'none';
    if (signupForm) signupForm.style.display = tab === 'signup' ? 'block' : 'none';
}



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
