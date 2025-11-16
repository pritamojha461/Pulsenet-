<?php
// index.php - Single-file PulseNet (Signup / Login / Profile / Upload / Mood log)
// Default DB credentials - change if needed
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // change if you have a password
$DB_NAME = 'pulsenet_db';

// ----- Simple helper: connect & create DB/tables if missing -----
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
if ($conn->connect_error) {
    die("DB Connection failed: ".$conn->connect_error);
}
// create database if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($DB_NAME);

// create users table
$conn->query("
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  dob DATE NULL,
  gender ENUM('Male','Female','Other') DEFAULT 'Other',
  city VARCHAR(100) NULL,
  goal VARCHAR(100) NULL,
  profile_pic VARCHAR(255) DEFAULT NULL,
  member_since DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// create mood_entries table
$conn->query("
CREATE TABLE IF NOT EXISTS mood_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  mood VARCHAR(50),
  thoughts TEXT,
  intensity INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Ensure uploads directory exists
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// ----- Session & simple flash messages -----
session_start();
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ----- Helper functions -----
function redirect($url){ header("Location: $url"); exit; }
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }

// ----- Handle POST actions: register, login, logout, save_profile, upload_photo, record_mood -----
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'register') {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $errors = [];
    if (!$name) $errors[] = "Full name required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email required.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm) $errors[] = "Passwords do not match.";
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s",$email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Email already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (full_name,email,password) VALUES (?,?,?)");
            $ins->bind_param("sss",$name,$email,$hash);
            if ($ins->execute()) {
                $_SESSION['user_id'] = $ins->insert_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['flash'] = "Welcome, $name! Your account was created.";
                redirect('index.php');
            } else {
                $errors[] = "Registration error.";
            }
            $ins->close();
        }
        $stmt->close();
    }
    $_SESSION['flash'] = implode(" ", $errors);
    redirect('index.php');
}

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash'] = "Valid email required.";
        redirect('index.php');
    }
    $stmt = $conn->prepare("SELECT id, full_name, password FROM users WHERE email = ?");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id,$name,$hash);
        $stmt->fetch();
        if (password_verify($password,$hash)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $name;
            $_SESSION['flash'] = "Welcome back, $name!";
            redirect('index.php');
        } else {
            $_SESSION['flash'] = "Incorrect credentials.";
            redirect('index.php');
        }
    } else {
        $_SESSION['flash'] = "No account with that email.";
        redirect('index.php');
    }
    $stmt->close();
}

if ($action === 'logout') {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash'] = "You have been logged out.";
    redirect('index.php');
}

if ($action === 'save_profile') {
    if (!isset($_SESSION['user_id'])) { $_SESSION['flash']="Not authorized."; redirect('index.php'); }
    $uid = $_SESSION['user_id'];
    $full = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dob = $_POST['dob'] ?? NULL;
    $gender = $_POST['gender'] ?? 'Other';
    $city = trim($_POST['city'] ?? '');
    $goal = trim($_POST['goal'] ?? '');
    if (!$full || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash'] = "Please provide valid name & email.";
        redirect('index.php');
    }
    // email uniqueness check
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si",$email,$uid);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['flash'] = "Email used by another account.";
        $stmt->close();
        redirect('index.php');
    }
    $stmt->close();
    $upd = $conn->prepare("UPDATE users SET full_name=?, email=?, dob=?, gender=?, city=?, goal=? WHERE id=?");
    $upd->bind_param("ssssssi",$full,$email,$dob,$gender,$city,$goal,$uid);
    if ($upd->execute()) $_SESSION['flash']="Profile updated.";
    else $_SESSION['flash']="Error updating profile.";
    $upd->close();
    redirect('index.php');
}

if ($action === 'upload_photo') {
    if (!isset($_SESSION['user_id'])) { $_SESSION['flash']="Not authorized."; redirect('index.php'); }
    $uid = $_SESSION['user_id'];
    if (!isset($_FILES['profile_pic'])) { $_SESSION['flash']="No file uploaded."; redirect('index.php'); }
    $file = $_FILES['profile_pic'];
    if ($file['error'] !== UPLOAD_ERR_OK) { $_SESSION['flash']="Upload error."; redirect('index.php'); }
    $allowed = ['image/jpeg','image/png','image/webp'];
    if (!in_array($file['type'],$allowed) || $file['size'] > 2*1024*1024) {
        $_SESSION['flash'] = "Only JPG/PNG/WEBP under 2MB allowed.";
        redirect('index.php');
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = 'u'.$uid.'_'.time().'.'.$ext;
    $target = $uploadDir.'/'.$newName;
    if (move_uploaded_file($file['tmp_name'],$target)) {
        $rel = 'uploads/'.$newName;
        $stmt = $conn->prepare("UPDATE users SET profile_pic=? WHERE id=?");
        $stmt->bind_param("si",$rel,$uid);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash'] = "Profile photo updated.";
    } else {
        $_SESSION['flash'] = "Could not save file.";
    }
    redirect('index.php');
}

if ($action === 'record_mood') {
    if (!isset($_SESSION['user_id'])) { $_SESSION['flash']="Not authorized."; redirect('index.php'); }
    $uid = $_SESSION['user_id'];
    $mood = trim($_POST['mood'] ?? '');
    $thoughts = trim($_POST['thoughts'] ?? '');
    $intensity = (int)($_POST['intensity'] ?? 0);
    if (!$mood || !$thoughts) { $_SESSION['flash'] = "Please select mood and add a note."; redirect('index.php'); }
    $ins = $conn->prepare("INSERT INTO mood_entries (user_id,mood,thoughts,intensity) VALUES (?,?,?,?)");
    $ins->bind_param("issi",$uid,$mood,$thoughts,$intensity);
    if ($ins->execute()) $_SESSION['flash']="Mood saved. Thank you.";
    else $_SESSION['flash']="Could not save mood.";
    $ins->close();
    redirect('index.php');
}

// ----- Data for rendering -----
$user = null;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id,full_name,email,dob,gender,city,goal,profile_pic,member_since FROM users WHERE id = ?");
    $stmt->bind_param("i",$uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
    if ($user && !$user['profile_pic']) {
        $user['profile_pic'] = 'https://ui-avatars.com/api/?name='.urlencode($user['full_name']).'&size=150&background=6366f1&color=fff';
    }
    // stats
    $stmt = $conn->prepare("SELECT COUNT(*) as moods, COUNT(DISTINCT DATE(created_at)) as streak FROM mood_entries WHERE user_id = ?");
    $stmt->bind_param("i",$uid);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $moods_logged = (int)($stats['moods'] ?? 0);
    $streak = (int)($stats['streak'] ?? 0);
} else {
    $moods_logged = 0; $streak = 0;
}

// fetch recent moods for display
$recent_moods = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT created_at,mood,intensity,thoughts FROM mood_entries WHERE user_id = ? ORDER BY created_at DESC LIMIT 8");
    $stmt->bind_param("i",$uid);
    $stmt->execute();
    $recent_moods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// close connection at end of script (but we'll still use conn in page if needed)
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>PulseNet — Single File App</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    /* ========== Embedded CSS (based on your theme) ========== */
    :root{
      --primary:#6366f1; --secondary:#ec4899; --bg:#f8fafc; --text:#374151; --muted:#6b7280;
      --card: rgba(255,255,255,0.85); --border:#e5e7eb;
    }
    *{box-sizing:border-box}
    body{font-family:Inter,Segoe UI,system-ui,Arial;background:linear-gradient(135deg,#f3e8ff 0%, #f8fafc 100%);color:var(--text);margin:0;padding:20px}
    .container{max-width:1100px;margin:0 auto}
    .navbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:26px;padding:14px 20px;border-radius:14px;background:var(--card);box-shadow:0 10px 30px rgba(0,0,0,0.06);border:1px solid rgba(255,255,255,0.6)}
    .navbar h1{font-size:22px;margin:0;background:linear-gradient(90deg,var(--primary),var(--secondary));-webkit-background-clip:text;color:transparent;font-weight:900}
    .nav-links{display:flex;gap:14px;align-items:center}
    .nav-links a, .nav-links button{background:none;border:none;color:var(--muted);text-decoration:none;font-weight:600;cursor:pointer;padding:8px}
    .auth-card{max-width:420px;margin:20px auto;padding:20px;border-radius:12px;background:var(--card);border:1px solid var(--border);box-shadow:0 12px 30px rgba(0,0,0,0.04)}
    form label{display:block;margin:8px 0 6px;font-weight:700}
    input[type=text],input[type=email],input[type=password],input[type=date],select,textarea{width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-size:14px}
    button.primary{display:inline-block;padding:12px 18px;border-radius:10px;border:none;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;font-weight:800;cursor:pointer;margin-top:12px}
    .grid{display:grid;grid-template-columns:1fr 380px;gap:24px}
    .card{background:var(--card);padding:18px;border-radius:14px;border:1px solid var(--border);box-shadow:0 10px 30px rgba(0,0,0,0.04)}
    .profile-image{width:120px;height:120px;border-radius:14px;object-fit:cover;border:6px solid rgba(255,255,255,0.7)}
    .profile-header{display:flex;gap:18px;align-items:center}
    .stats{display:flex;gap:16px;margin-top:12px}
    .stat{background:linear-gradient(180deg,rgba(99,102,241,0.08),rgba(236,72,153,0.04));padding:10px 12px;border-radius:10px;text-align:center}
    .stat .num{font-weight:800;color:var(--primary);font-size:18px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:10px;border-bottom:1px solid #f1f5f9;text-align:left;font-size:14px}
    .intensity{height:12px;border-radius:8px;background:linear-gradient(90deg,var(--primary),var(--secondary))}
    .small{font-size:13px;color:var(--muted)}
    .circle-img{width:32px;height:32px;border-radius:8px;object-fit:cover;margin-right:6px}
    .flex{display:flex;align-items:center;gap:8px}
    .upload-label{display:inline-block;padding:8px;border-radius:8px;border:1px dashed var(--border);cursor:pointer;font-weight:700;color:var(--muted);margin-top:8px}
    .danger{background:linear-gradient(90deg,#fed7d7,#fee2e2);padding:12px;border-radius:10px}
    .flash{background:#e6fffa;padding:10px;border-radius:8px;border:1px solid #bdecdc;margin-bottom:12px;color:#064e3b}
    @media (max-width:900px){ .grid{grid-template-columns:1fr; } .nav-links{display:none} }
  </style>
</head>
<body>
  <div class="container">
    <nav class="navbar">
      <h1>🫀 PulseNet</h1>
      <div class="nav-links">
        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="#profile">Profile</a>
          <form method="post" style="display:inline">
            <input type="hidden" name="action" value="logout">
            <button class="small" type="submit">Logout</button>
          </form>
        <?php else: ?>
          <a href="#auth">Login / Sign up</a>
        <?php endif; ?>
      </div>
    </nav>

    <?php if($flash): ?>
      <div class="flash"><?php echo e($flash); ?></div>
    <?php endif; ?>

    <div class="grid">
      <!-- LEFT: Main content (profile / mood / history) -->
      <div>
        <?php if (!isset($_SESSION['user_id'])): ?>
          <!-- AUTH CARD (Login + Register) -->
          <div id="auth" class="auth-card card">
            <h2 style="margin-top:0">Welcome to PulseNet</h2>
            <p class="small">Sign up or login to manage your mental wellness profile.</p>

            <!-- LOGIN -->
            <hr style="margin:12px 0">
            <h3 style="margin-bottom:6px">Login</h3>
            <form method="post" action="index.php">
              <input type="hidden" name="action" value="login">
              <label for="login_email">Email</label>
              <input id="login_email" name="email" type="email" required>
              <label for="login_pass">Password</label>
              <input id="login_pass" name="password" type="password" required>
              <button class="primary" type="submit">Login</button>
            </form>

            <!-- REGISTER -->
            <hr style="margin:18px 0">
            <h3 style="margin-bottom:6px">Create account</h3>
            <form method="post" action="index.php" onsubmit="return validateRegister()">
              <input type="hidden" name="action" value="register">
              <label for="reg_name">Full name</label>
              <input id="reg_name" name="full_name" type="text" required>
              <label for="reg_email">Email</label>
              <input id="reg_email" name="email" type="email" required>
              <div style="display:flex;gap:8px">
                <div style="flex:1">
                  <label for="reg_pass">Password</label>
                  <input id="reg_pass" name="password" type="password" required>
                </div>
                <div style="flex:1">
                  <label for="reg_confirm">Confirm</label>
                  <input id="reg_confirm" name="confirm_password" type="password" required>
                </div>
              </div>
              <button class="primary" type="submit">Sign up</button>
            </form>
          </div>

          <!-- Public mindfulness preview / join circles CTA -->
          <div class="card" style="margin-top:18px">
            <h3>Explore Mindfulness</h3>
            <p class="small">Try breathing exercises and guided meditations after signing up.</p>
            <div style="margin-top:12px">
              <a href="mindfulness.html" style="font-weight:700;color:var(--primary)">Open Mindfulness →</a>
            </div>
          </div>

        <?php else: ?>
          <!-- PROFILE & MOOD LOG (for logged-in user) -->
          <section id="profile" class="card">
            <div class="profile-header">
              <img src="<?php echo e($user['profile_pic']); ?>" alt="profile" class="profile-image">
              <div>
                <h2 style="margin:0"><?php echo e($user['full_name']); ?></h2>
                <div class="small">Member since <?php echo date("F Y", strtotime($user['member_since'])); ?></div>
                <div class="stats">
                  <div class="stat"><div class="num"><?php echo $moods_logged; ?></div><div class="small">Moods</div></div>
                  <div class="stat"><div class="num"><?php echo $streak; ?></div><div class="small">Day streak</div></div>
                </div>
              </div>
            </div>

            <hr style="margin:12px 0">

            <!-- Upload photo form -->
            <form id="photoForm" method="post" enctype="multipart/form-data" style="margin-bottom:12px">
              <input type="hidden" name="action" value="upload_photo">
              <label class="upload-label">
                Change profile photo
                <input type="file" name="profile_pic" accept="image/*" style="display:none" onchange="document.getElementById('photoForm').submit()">
              </label>
            </form>

            <!-- Edit profile form -->
            <form method="post" action="index.php" style="margin-top:12px">
              <input type="hidden" name="action" value="save_profile">
              <label>Full name</label>
              <input name="full_name" value="<?php echo e($user['full_name']); ?>">
              <label>Email</label>
              <input name="email" value="<?php echo e($user['email']); ?>">
              <label>DOB</label>
              <input type="date" name="dob" value="<?php echo e($user['dob']); ?>">
              <label>Gender</label>
              <select name="gender">
                <option value="Male" <?php if($user['gender']=='Male') echo 'selected';?>>Male</option>
                <option value="Female" <?php if($user['gender']=='Female') echo 'selected';?>>Female</option>
                <option value="Other" <?php if($user['gender']=='Other') echo 'selected';?>>Other</option>
              </select>
              <label>City</label>
              <input name="city" value="<?php echo e($user['city']); ?>">
              <label>What do you need?</label>
              <select name="goal">
                <?php $opts=['Reduce Anxiety','Better Sleep','Stress Management','General Wellness','Mindfulness'];
                foreach($opts as $o){ $sel = ($user['goal']==$o)?'selected':''; echo "<option value=\"".e($o)."\" $sel>".e($o)."</option>"; } ?>
              </select>
              <button class="primary" type="submit" style="margin-right:8px">Save Profile</button>
            </form>

            <!-- Mood logging -->
            <hr style="margin:12px 0">
            <h4 style="margin:6px 0">Quick Mood Check</h4>
            <form method="post" action="index.php">
              <input type="hidden" name="action" value="record_mood">
              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <select name="mood" required style="min-width:160px">
                  <option value="">Select mood</option>
                  <option>Happy</option><option>Sad</option><option>Anxious</option><option>Calm</option><option>Stressed</option>
                </select>
                <input type="number" name="intensity" min="0" max="100" placeholder="Intensity 0-100" style="width:120px">
              </div>
              <label style="margin-top:8px">Notes</label>
              <textarea name="thoughts" rows="2" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border)"></textarea>
              <button class="primary" type="submit">Save Mood</button>
            </form>

          </section>

          <!-- Mood history -->
          <section class="card" style="margin-top:18px">
            <h3 style="margin-top:0">Recent Moods</h3>
            <?php if(empty($recent_moods)): ?>
              <div class="small">No mood logs yet. Use Quick Mood Check above to save your first entry.</div>
            <?php else: ?>
              <table>
                <thead><tr><th>Date</th><th>Mood</th><th>Intensity</th><th>Notes</th></tr></thead>
                <tbody>
                  <?php foreach($recent_moods as $r): ?>
                    <tr>
                      <td><?php echo e(date("Y-m-d", strtotime($r['created_at']))); ?></td>
                      <td><?php echo e($r['mood']); ?></td>
                      <td style="width:220px"><div class="intensity" style="width:<?php echo (int)$r['intensity']; ?>%"></div></td>
                      <td><?php echo e(mb_substr($r['thoughts'],0,80)); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </section>

        <?php endif; ?>
      </div>

      <!-- RIGHT: sidebar with circles/team/achievements -->
      <aside>
        <div class="card">
          <h3 style="margin-top:0">Support Circles (India)</h3>
          <div style="margin-top:8px">
            <div class="small" style="margin-bottom:8px"><strong>Anxiety & Stress Group</strong><br><span class="small">Riya Sharma — Delhi • 54 members</span></div>
            <div class="small" style="margin-bottom:8px"><strong>Students Mental Wellness</strong><br><span class="small">Rahul Verma — Mumbai • 102 members</span></div>
            <div class="small" style="margin-bottom:8px"><strong>Sleep & Wellness</strong><br><span class="small">Dr. Meena Iyer — Chennai • 37 members</span></div>
          </div>
          <div style="margin-top:12px">
            <a href="support-circles.html" style="font-weight:700;color:var(--primary)">Browse circles →</a>
          </div>
        </div>

        <div class="card" style="margin-top:14px">
          <h3 style="margin-top:0">Team</h3>
          <div class="small"><strong>Riya Sharma</strong><br>Psychology Graduate — Delhi</div><hr>
          <div class="small"><strong>Rahul Verma</strong><br>Student Counselor — Mumbai</div><hr>
          <div class="small"><strong>Dr. Meena Iyer</strong><br>Sleep Specialist — Chennai</div>
        </div>

        <div class="card" style="margin-top:14px">
          <h3 style="margin-top:0">Danger Zone</h3>
          <div class="danger small">
            <strong>Export or Delete</strong><br>
            Export or remove your data from this demo. Use carefully.
            <div style="margin-top:8px">
              <form method="post" onsubmit="return confirm('Delete your account? This cannot be undone.')">
                <input type="hidden" name="action" value="delete_account">
                <button type="button" class="primary" onclick="alert('Export feature not implemented in demo')">Export Data</button>
                <button type="submit" style="background:#ef4444;color:#fff;border:none;padding:10px;border-radius:8px;margin-top:8px">Delete Account</button>
              </form>
            </div>
          </div>
        </div>

      </aside>
    </div>

  </div>

  <script>
    // Small client-side register validation
    function validateRegister(){
      var p=document.getElementById('reg_pass').value;
      var c=document.getElementById('reg_confirm').value;
      if (p.length < 6){ alert('Password must be 6+ chars'); return false; }
      if (p !== c){ alert('Passwords do not match'); return false; }
      return true;
    }
  </script>
</body>
</html>
