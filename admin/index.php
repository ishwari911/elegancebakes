<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', 3600);
session_start();

// ── DB CONNECTION ─────────────────────────────────────────────────
$host   = 'sql200.infinityfree.com';
$dbname = 'if0_41054761_elegance_bakes';
$dbuser = 'if0_41054761';
$dbpass = 'elegance2026';
$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("<div style='font-family:sans-serif;padding:40px;color:red'><h2>DB Connection Failed</h2><p>" . htmlspecialchars($conn->connect_error) . "</p></div>");
}
$conn->set_charset('utf8');

// ── ADMIN CREDENTIALS ────────────────────────────────────────────
define('ADMIN_USER', 'admin');
define('ADMIN_HASH', '$2y$10$HVm5I2B4ARg5kGOlAtEPYOLYXFcyKrpJG4Y3Ml2ltXaNAqyp68cyO');

$loggedIn = !empty($_SESSION['eb_admin']);
$page     = isset($_GET['tab']) ? $_GET['tab'] : 'orders';
$action   = isset($_POST['_action']) ? $_POST['_action'] : '';
$error    = '';

// ── LOGOUT ───────────────────────────────────────────────────────
if ($action === 'logout') {
    session_unset(); session_destroy();
    header('Location: index.php'); exit;
}

// ── LOGIN ────────────────────────────────────────────────────────
if (!$loggedIn && $action === 'login') {
    $u = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $p = isset($_POST['password']) ? $_POST['password'] : '';
    if ($u === ADMIN_USER && password_verify($p, ADMIN_HASH)) {
        $_SESSION['eb_admin'] = true;
        $loggedIn = true;
    } else {
        $error = 'Wrong username or password.';
    }
}

// ── STATUS UPDATE (AJAX) ─────────────────────────────────────────
if ($loggedIn && $action === 'update_status') {
    header('Content-Type: application/json');
    $id  = (int)(isset($_POST['order_id']) ? $_POST['order_id'] : 0);
    $st  = isset($_POST['status']) ? trim($_POST['status']) : '';
    $ok_vals = array('pending','preparing','out_for_delivery','delivered','cancelled');
    if ($id && in_array($st, $ok_vals)) {
        $s = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
        $s->bind_param("si", $st, $id);
        $ok = $s->execute();
        echo json_encode(array('success' => $ok, 'message' => $ok ? 'Updated' : $conn->error));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Invalid'));
    }
    exit;
}

if (!$loggedIn) { renderLogin($error); exit; }

// ── FETCH DATA ───────────────────────────────────────────────────
function q($conn, $sql) {
    $r = $conn->query($sql);
    if (!$r) return 0;
    $row = $r->fetch_row();
    return isset($row[0]) ? $row[0] : 0;
}

$today       = date('Y-m-d');
$totalOrders = q($conn, "SELECT COUNT(*) FROM orders");
$totalRev    = q($conn, "SELECT COALESCE(SUM(total_price),0) FROM orders WHERE payment_status='paid'");
$pendingCnt  = q($conn, "SELECT COUNT(*) FROM orders WHERE status='pending'");
$todayOrders = q($conn, "SELECT COUNT(*) FROM orders WHERE DATE(order_date)='$today'");
$totalUsers  = q($conn, "SELECT COUNT(*) FROM users");

// Orders
$r = $conn->query("SELECT o.id, o.cake_name, o.quantity, o.total_price, o.delivery_address,
                          o.delivery_date, o.status, o.payment_status, o.razorpay_payment_id,
                          o.order_date, u.first_name, u.email, u.phone
                   FROM orders o
                   LEFT JOIN users u ON o.user_id = u.id
                   ORDER BY o.order_date DESC");
if (!$r) die("<div style='padding:40px;font-family:sans-serif;color:red'>Orders query error: " . htmlspecialchars($conn->error) . "</div>");
$orders = $r->fetch_all(MYSQLI_ASSOC);

// Users
$ru = $conn->query("SELECT id, first_name, email, phone, address, pincode, is_verified, created_at FROM users ORDER BY id DESC");
$users = $ru ? $ru->fetch_all(MYSQLI_ASSOC) : array();

$statuses = array('pending','preparing','out_for_delivery','delivered','cancelled');

renderDashboard($totalOrders, $totalRev, $pendingCnt, $todayOrders, $totalUsers, $orders, $users, $statuses, $page);
exit;

// ═══════════════════════════════════════════════════════════════════
function renderLogin($error) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin — Elegance Bakes</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Great+Vibes&display=swap" rel="stylesheet">
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{min-height:100vh;background:linear-gradient(135deg,#6a3c3c,#a0724a,#d4a574);display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif}
    .box{background:#fff;border-radius:24px;padding:48px 40px;width:380px;box-shadow:0 24px 60px rgba(0,0,0,.25);text-align:center}
    .emoji{font-size:2.5rem;margin-bottom:8px}
    h1{font-family:'Great Vibes',cursive;font-size:2.2rem;color:#6a3c3c;margin-bottom:4px}
    p{color:#a08070;font-size:13px;margin-bottom:28px}
    label{display:block;text-align:left;font-size:11px;font-weight:700;color:#6a3c3c;text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px}
    input{width:100%;padding:13px 15px;border:2px solid #f0e0d8;border-radius:10px;font-family:'Playfair Display',serif;font-size:14px;color:#4a2c2c;background:#fffaf8;margin-bottom:16px;box-sizing:border-box}
    input:focus{outline:none;border-color:#d4a574}
    .btn{width:100%;padding:14px;background:linear-gradient(135deg,#6a3c3c,#a0724a);color:#fff;border:none;border-radius:12px;font-family:'Playfair Display',serif;font-size:16px;font-weight:700;cursor:pointer}
    .err{background:#fff0f0;color:#c0392b;border:1px solid #fcc;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:16px;text-align:left}
    a{display:block;margin-top:20px;color:#b09080;text-decoration:none;font-size:13px}
  </style>
</head>
<body>
<div class="box">
  <div class="emoji">🔐</div>
  <h1>Admin Login</h1>
  <p>Elegance Bakes — Owner Panel</p>
  <?php if ($error): ?><div class="err">❌ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <form method="POST">
    <input type="hidden" name="_action" value="login">
    <label>Username</label>
    <input type="text" name="username" placeholder="admin" required>
    <label>Password</label>
    <input type="password" name="password" placeholder="••••••••" required>
    <button type="submit" class="btn">🍰 Enter Panel</button>
  </form>
  <a href="../index.html">← Back to website</a>
</div>
</body></html>
<?php }

// ═══════════════════════════════════════════════════════════════════
function renderDashboard($totalOrders,$totalRev,$pendingCnt,$todayOrders,$totalUsers,$orders,$users,$statuses,$page) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin — Elegance Bakes</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Playfair Display',serif;background:#f5ecea;min-height:100vh}

    /* NAV */
    nav{background:linear-gradient(135deg,#6a3c3c,#8b5b5b);padding:0 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:200;box-shadow:0 4px 20px rgba(106,60,60,.3);height:60px}
    .brand{font-family:'Great Vibes',cursive;font-size:1.8rem;color:#fff;text-decoration:none}
    .brand span{font-family:'Playfair Display',serif;font-size:12px;background:rgba(255,255,255,.2);padding:2px 10px;border-radius:20px;margin-left:8px;vertical-align:middle}
    .nav-r{display:flex;gap:12px;align-items:center}
    .nav-tab{color:rgba(255,255,255,.75);text-decoration:none;font-size:13px;font-weight:600;padding:8px 16px;border-radius:20px;transition:.2s;border:1px solid transparent}
    .nav-tab:hover,.nav-tab.active{background:rgba(255,255,255,.18);color:#fff;border-color:rgba(255,255,255,.3)}
    .nav-view{color:rgba(255,255,255,.75);text-decoration:none;font-size:13px;font-weight:600}
    .btn-lg{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);padding:8px 18px;border-radius:20px;font-family:'Playfair Display',serif;font-size:13px;font-weight:600;cursor:pointer}

    /* PAGE */
    .page{max-width:1280px;margin:0 auto;padding:28px 20px 60px}
    .page-title{font-family:'Great Vibes',cursive;font-size:2.6rem;color:#6a3c3c;margin-bottom:24px}

    /* STATS */
    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px}
    .stat{background:#fff;border-radius:18px;padding:20px 18px;box-shadow:0 8px 24px rgba(212,165,116,.12);border:1px solid rgba(212,165,116,.18);position:relative;overflow:hidden}
    .stat::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#6a3c3c,#d4a574)}
    .stat-i{font-size:1.6rem;margin-bottom:8px}
    .stat-v{font-size:1.8rem;font-weight:700;color:#6a3c3c}
    .stat-l{font-size:11px;color:#b09080;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-top:3px}

    /* CARD */
    .card{background:#fff;border-radius:20px;box-shadow:0 8px 32px rgba(212,165,116,.12);border:1px solid rgba(212,165,116,.18);overflow:hidden}
    .card-hd{padding:20px 26px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(212,165,116,.12);flex-wrap:wrap;gap:12px}
    .card-hd h2{font-family:'Great Vibes',cursive;font-size:1.8rem;color:#d4a574}

    /* FILTERS */
    .filters{display:flex;gap:8px;flex-wrap:wrap}
    .fb{padding:6px 14px;border-radius:20px;border:1px solid rgba(212,165,116,.35);background:#fff;color:#6a3c3c;font-family:'Playfair Display',serif;font-size:12px;font-weight:600;cursor:pointer}
    .fb.active{background:linear-gradient(135deg,#6a3c3c,#a0724a);color:#fff;border-color:transparent}

    /* TABLE */
    .tw{overflow-x:auto}
    table{width:100%;border-collapse:collapse;font-size:13px;min-width:780px}
    th{background:#fdf3ee;color:#6a3c3c;padding:11px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap}
    td{padding:12px;border-bottom:1px solid rgba(212,165,116,.1);color:#4a2c2c;vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:rgba(212,165,116,.04)}
    .bold{font-weight:700;color:#6a3c3c}
    .muted{font-size:11px;color:#9b8070}
    .total{font-weight:700;color:#6a3c3c}

    /* STATUS */
    .ss{padding:6px 10px;border-radius:18px;border:2px solid transparent;font-family:'Playfair Display',serif;font-size:11px;font-weight:700;cursor:pointer;outline:none;min-width:130px}
    .ss.pending{background:#fff3cd;color:#856404;border-color:#ffc107}
    .ss.preparing{background:#e8d5ff;color:#4a1c8c;border-color:#b58ee8}
    .ss.out_for_delivery{background:#cce5ff;color:#004085;border-color:#80bdff}
    .ss.delivered{background:#d4edda;color:#155724;border-color:#28a745}
    .ss.cancelled{background:#f8d7da;color:#721c24;border-color:#dc3545}
    .tick{font-size:12px;margin-left:4px;opacity:0;transition:opacity .3s}
    .tick.on{opacity:1}

    /* PAY BADGE */
    .pb{display:inline-block;padding:3px 10px;border-radius:18px;font-size:11px;font-weight:700}
    .pb.paid{background:#d4edda;color:#155724}
    .pb.pending{background:#fff3cd;color:#856404}
    .pb.failed{background:#f8d7da;color:#721c24}

    /* USER BADGE */
    .verified{display:inline-block;padding:3px 10px;border-radius:18px;font-size:11px;font-weight:700;background:#d4edda;color:#155724}
    .unverified{display:inline-block;padding:3px 10px;border-radius:18px;font-size:11px;font-weight:700;background:#fff3cd;color:#856404}

    .empty td{text-align:center;padding:40px;color:#b09080;font-size:16px}

    /* TOAST */
    #toast{position:fixed;bottom:24px;right:24px;background:#28a745;color:#fff;padding:12px 22px;border-radius:12px;font-size:14px;font-weight:600;opacity:0;pointer-events:none;transition:opacity .3s;z-index:9999;box-shadow:0 6px 20px rgba(0,0,0,.2)}
    #toast.on{opacity:1}
    #toast.err{background:#dc3545}

    .hidden{display:none}
  </style>
</head>
<body>

<nav>
  <a href="index.php" class="brand">Elegance Bakes <span>Admin</span></a>
  <div class="nav-r">
    <a href="index.php?tab=orders" class="nav-tab <?php echo ($page==='orders')?'active':''; ?>">📦 Orders</a>
    <a href="index.php?tab=users"  class="nav-tab <?php echo ($page==='users') ?'active':''; ?>">👥 Users</a>
    <a href="../index.html" class="nav-view" target="_blank">🌐 Site</a>
    <form method="POST" style="margin:0">
      <input type="hidden" name="_action" value="logout">
      <button type="submit" class="btn-lg">🚪 Logout</button>
    </form>
  </div>
</nav>

<div class="page">
  <h1 class="page-title">Admin Dashboard</h1>

  <!-- STATS -->
  <div class="stats">
    <div class="stat"><div class="stat-i">📦</div><div class="stat-v"><?php echo $totalOrders; ?></div><div class="stat-l">Total Orders</div></div>
    <div class="stat"><div class="stat-i">⏳</div><div class="stat-v"><?php echo $pendingCnt; ?></div><div class="stat-l">Pending</div></div>
    <div class="stat"><div class="stat-i">🍰</div><div class="stat-v"><?php echo $todayOrders; ?></div><div class="stat-l">Today's Orders</div></div>
    <div class="stat"><div class="stat-i">💰</div><div class="stat-v">₹<?php echo number_format($totalRev, 0); ?></div><div class="stat-l">Revenue (Paid)</div></div>
    <div class="stat"><div class="stat-i">👥</div><div class="stat-v"><?php echo $totalUsers; ?></div><div class="stat-l">Users</div></div>
  </div>

  <?php if ($page === 'orders'): ?>
  <!-- ── ORDERS ── -->
  <div class="card">
    <div class="card-hd">
      <h2>All Orders</h2>
      <div class="filters">
        <button class="fb active" onclick="filter(this,'all')">All</button>
        <button class="fb" onclick="filter(this,'pending')">Pending</button>
        <button class="fb" onclick="filter(this,'preparing')">Preparing</button>
        <button class="fb" onclick="filter(this,'out_for_delivery')">Out for Delivery</button>
        <button class="fb" onclick="filter(this,'delivered')">Delivered</button>
        <button class="fb" onclick="filter(this,'cancelled')">Cancelled</button>
      </div>
    </div>
    <div class="tw">
      <table id="tbl">
        <thead>
          <tr>
            <th>#</th><th>Customer</th><th>Cake</th><th>Qty</th>
            <th>Total</th><th>Payment</th><th>Delivery</th><th>Status</th><th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr class="empty"><td colspan="9">🧁 No orders yet!</td></tr>
          <?php else: foreach ($orders as $o):
            $ps   = isset($o['payment_status']) ? $o['payment_status'] : 'pending';
            $pico = ($ps==='paid') ? '🟢' : (($ps==='failed') ? '🔴' : '🟡');
            $dd   = $o['delivery_date'] ? date('d M Y', strtotime($o['delivery_date'])) : '—';
            $od   = date('d M Y', strtotime($o['order_date']));
            $cake = isset($o['cake_name']) && $o['cake_name'] ? $o['cake_name'] : 'Custom';
          ?>
          <tr data-status="<?php echo $o['status']; ?>">
            <td class="bold">#<?php echo $o['id']; ?></td>
            <td>
              <div class="bold"><?php echo htmlspecialchars(isset($o['first_name']) ? $o['first_name'] : 'Guest'); ?></div>
              <div class="muted"><?php echo htmlspecialchars(isset($o['email']) ? $o['email'] : ''); ?></div>
              <?php if (!empty($o['phone'])): ?><div class="muted">📞 <?php echo htmlspecialchars($o['phone']); ?></div><?php endif; ?>
            </td>
            <td style="max-width:160px"><?php echo htmlspecialchars($cake); ?></td>
            <td style="text-align:center"><?php echo (int)$o['quantity']; ?></td>
            <td class="total">₹<?php echo number_format($o['total_price'], 2); ?></td>
            <td>
              <span class="pb <?php echo $ps; ?>"><?php echo $pico; ?> <?php echo ucfirst($ps); ?></span>
              <?php if (!empty($o['razorpay_payment_id'])): ?>
                <div class="muted" style="margin-top:2px"><?php echo htmlspecialchars(substr($o['razorpay_payment_id'], 0, 16)) . '…'; ?></div>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;font-size:12px"><?php echo $dd; ?></td>
            <td>
              <select class="ss <?php echo $o['status']; ?>" onchange="upd(this,<?php echo $o['id']; ?>)">
                <?php foreach ($statuses as $s): ?>
                  <option value="<?php echo $s; ?>" <?php echo ($o['status']===$s)?'selected':''; ?>><?php echo ucwords(str_replace('_',' ',$s)); ?></option>
                <?php endforeach; ?>
              </select>
              <span class="tick" id="t<?php echo $o['id']; ?>">✅</span>
            </td>
            <td style="font-size:11px;color:#9b8070;white-space:nowrap"><?php echo $od; ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php elseif ($page === 'users'): ?>
  <!-- ── USERS ── -->
  <div class="card">
    <div class="card-hd">
      <h2>Registered Users</h2>
      <span style="color:#b09080;font-size:13px"><?php echo count($users); ?> users total</span>
    </div>
    <div class="tw">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Pincode</th><th>Status</th><th>Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr class="empty"><td colspan="8">👥 No users yet!</td></tr>
          <?php else: foreach ($users as $u): ?>
          <tr>
            <td class="bold"><?php echo $u['id']; ?></td>
            <td class="bold"><?php echo htmlspecialchars($u['first_name']); ?></td>
            <td class="muted"><?php echo htmlspecialchars($u['email']); ?></td>
            <td class="muted"><?php echo htmlspecialchars(isset($u['phone']) ? $u['phone'] : '—'); ?></td>
            <td style="font-size:11px;color:#7a5b5b;max-width:160px"><?php echo htmlspecialchars(isset($u['address']) ? substr($u['address'],0,40).'…' : '—'); ?></td>
            <td class="muted"><?php echo htmlspecialchars(isset($u['pincode']) ? $u['pincode'] : '—'); ?></td>
            <td>
              <?php if ($u['is_verified']): ?>
                <span class="verified">✅ Verified</span>
              <?php else: ?>
                <span class="unverified">⏳ Unverified</span>
              <?php endif; ?>
            </td>
            <td style="font-size:11px;color:#9b8070;white-space:nowrap"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<div id="toast"></div>

<script>
function upd(sel, id) {
  var st = sel.value;
  sel.className = 'ss ' + st;
  var fd = new FormData();
  fd.append('_action','update_status');
  fd.append('order_id', id);
  fd.append('status', st);
  fetch('index.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.success) {
        sel.closest('tr').dataset.status = st;
        var t = document.getElementById('t'+id);
        t.classList.add('on');
        setTimeout(function(){ t.classList.remove('on'); }, 2000);
        toast('✅ Order #'+id+' updated!');
      } else { toast('❌ '+(d.message||'Error'), true); }
    })
    .catch(function(){ toast('❌ Network error', true); });
}

function filter(btn, f) {
  document.querySelectorAll('.fb').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  document.querySelectorAll('#tbl tbody tr').forEach(function(r){
    if (r.classList.contains('empty')) return;
    r.style.display = (f==='all' || r.dataset.status===f) ? '' : 'none';
  });
}

function toast(msg, isErr) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'on' + (isErr ? ' err' : '');
  setTimeout(function(){ t.className=''; }, 3000);
}
</script>
</body>
</html>
<?php }
?>
