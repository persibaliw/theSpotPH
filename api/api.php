<?php
session_start();
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

//--- DATABASE CONNECTION ---
$host=getenv('DB_HOST');
$port=getenv('DB_PORT');
$db=getenv('DB_NAME');
$user=getenv('DB_USER');
$pass=getenv('DB_PASS');
$ssl_ca = __DIR__ . '/certs/ca.pem'; 

if (!file_exists($ssl_ca)) {
    die(json_encode(['success' => false, 'message' => 'SSL Certificate not found at: ' . $ssl_ca]));
}

// --- GET ACTION ---
$action = $_GET['action'] ?? ''; 

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA => $ssl_ca,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, 
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
// } catch (PDOException $e) {
//     die(json_encode(['success' => false, 'message' => 'Connection Error']));
// }
    } catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => $e->getMessage()]));
}

// --- ACTIONS ---

// Client: Submit Booking
if ($action === 'book') {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = bin2hex(random_bytes(16)); 
    
    $stmt = $pdo->prepare("INSERT INTO bookings (token, name, email, phone, event_date, package, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $success = $stmt->execute([
        $token, $data['name'], $data['email'], $data['phone'], 
        $data['date'], $data['package'], $data['message']
    ]);

    if ($success) {
        $trackLink = "https://thespotph.vercel.app/track.php?id=" . $token;
        $subject = "Your Booking Request - TheSpotPH";
        $message = "Hi " . $data['name'] . ",\n\nWe received your request! Track status: " . $trackLink;
        @mail($data['email'], $subject, $message, "From: no-reply@thespotph.com");
    }
    echo json_encode(['success' => $success, 'token' => $token]);
}

elseif ($action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    // Search for the user
    $stmt = $pdo->prepare("SELECT id, role, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // FIX: Check plain text (staff) AND MD5 (admin) from your SQL data
    if ($user && ($password === $user['password'] || md5($password) === $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        
        echo json_encode([
            'success' => true, 
            'role' => $user['role']
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid email or password'
        ]);
    }
}

// Admin: Fetch All Bookings
elseif ($action === 'get_bookings') {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        echo json_encode(['error' => 'Unauthorized']); exit;
    }
    $stmt = $pdo->query("SELECT * FROM bookings ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// Admin: Update Status
elseif ($action === 'update_status') {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        echo json_encode(['error' => 'Unauthorized']); exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $success = $stmt->execute([$data['status'], $data['id']]);
    echo json_encode(['success' => $success]);
}

// FIX 1: Multi-Role Calendar View (Handles Staff IDs string)
elseif ($action === 'get_calendar') {
    $type = $_GET['user_type'] ?? 'client';
    $user_id = $_SESSION['user_id'] ?? null;

    if ($type === 'admin' && ($_SESSION['role'] ?? '') === 'admin') {
        $stmt = $pdo->query("SELECT id, name as title, event_date as start, status FROM bookings");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } 
    elseif ($type === 'staff' && $user_id) {
        // USE FIND_IN_SET to check if this staff ID is inside the comma-separated string
        $stmt = $pdo->prepare("SELECT id, name as title, event_date as start, package, message 
                               FROM bookings 
                               WHERE FIND_IN_SET(?, assigned_user_id) AND status = 'accepted'");
        $stmt->execute([$user_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } 
    else {
        // Client view
        $stmt = $pdo->query("SELECT event_date as start, 'Full' as title FROM bookings WHERE status = 'accepted'");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}

// Admin: Get list of Staff
elseif ($action === 'get_staff') {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        echo json_encode(['error' => 'Unauthorized']); exit;
    }
    $stmt = $pdo->query("SELECT id, full_name as name FROM users WHERE role = 'staff'");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// FIX 2: Assign Multiple Staff
elseif ($action === 'assign_staff') {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        echo json_encode(['error' => 'Unauthorized']); exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    
    // We update the assigned_user_id column with the comma-separated string (e.g. "1,4")
    // Ensure your database column 'assigned_user_id' is set to TEXT or VARCHAR(255)
    $stmt = $pdo->prepare("UPDATE bookings SET assigned_user_id = ?, event_date = ? WHERE id = ?");
    $success = $stmt->execute([
        $data['staff_ids'], // From JS: selectedStaffIds.join(',')
        $data['date'], 
        $data['booking_id']
    ]);
    
    echo json_encode(['success' => $success]);
}

// Staff Update Account
elseif ($action === 'update_account') {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) { echo json_encode(['success' => false]); exit; }

    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, password = ? WHERE id = ?");
    $success = $stmt->execute([$data['name'], $data['password'], $user_id]);
    echo json_encode(['success' => $success]);
}

elseif ($action === 'get_profile') {
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'success' => true, 
            'full_name' => $user['full_name']
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
}

// Logout
elseif ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
}
?>
