<?php
// 1. SESSION & HEADER CONFIG
ini_set('session.save_path', '/tmp');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

ob_start(); 
error_reporting(0);
ini_set('display_errors', 0);
session_start();

header('Content-Type: application/json');

// 2. IMPORT PHPMAILER
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// 3. DATABASE CONNECTION
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$ssl_ca = __DIR__ . '/certs/ca.pem'; 

$action = $_GET['action'] ?? ''; 

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        @PDO::MYSQL_ATTR_SSL_CA => $ssl_ca,
        @PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    ob_clean();
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// 4. ROUTING & ACTIONS LOGIC
if ($action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $inputPassword = $data['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, role, password, full_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $userRecord = $stmt->fetch();

    if ($userRecord && ($inputPassword === $userRecord['password'] || md5($inputPassword) === $userRecord['password'])) {
        $_SESSION['user_id'] = $userRecord['id'];
        $_SESSION['role'] = strtolower($userRecord['role']);
        $_SESSION['full_name'] = $userRecord['full_name'];
        
        session_write_close(); 
        ob_clean();
        echo json_encode(['success' => true, 'role' => $userRecord['role']]);
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
    exit;
}
    
elseif ($action === 'get_profile') {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();

    ob_clean();
    echo json_encode([
        'success' => true, 
        'full_name' => $u['full_name'] ?? 'Staff Member'
    ]);
    exit;
}

elseif ($action === 'book') {
    $data = json_decode(file_get_contents('php://input'), true);

    $bookingDate = strtotime($data['date']);
    $today = strtotime(date('Y-m-d'));
    if ($bookingDate < $today) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'You cannot request a booking date in the past.']);
        exit;
    }

    $phone = trim($data['phone'] ?? '');
    if (!empty($phone)) {
        if (!preg_match('/^(?:\+639|639|09)\d{9}$/', $phone)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid Philippine phone number structure.']);
            exit;
        }
    }
    
    $token = bin2hex(random_bytes(16));
    $drink_set = $data['set'] ?? null;
    
    $stmt = $pdo->prepare("INSERT INTO bookings (token, name, email, phone, event_date, package, drink_set, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $success = $stmt->execute([$token, $data['name'], $data['email'], $data['phone'], $data['date'], $data['package'], $drink_set, $data['message']]);

    if ($success) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host='smtp.gmail.com';
            $mail->SMTPAuth=true;
            $mail->Username='spotph.13@gmail.com';
            $mail->Password='qigt ezeu lqvl enhm';
            $mail->SMTPSecure=PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port=587;

            $mail->setFrom('spotph.13@gmail.com', 'TheSpotPH');
            $mail->addAddress($data['email'], $data['name']);

            $trackingUrl = "https://www.thespotph.store/track.php?id=" . $token;
            $mail->isHTML(true);
            $mail->Subject = 'Your Booking Request Status - TheSpotPH';
            $mail->Body    = "
                <div style='font-family: sans-serif; max-width: 600px; border: 1px solid #eee; padding: 20px;'>
                    <h2 style='color: #cfc7b0;'>Hello " . htmlspecialchars($data['name']) . "!</h2>
                    <p>We've received your booking request for <strong>" . date("F j, Y", strtotime($data['date'])) . "</strong>.</p>
                    <p>You can track your request status using the button below:</p>
                    <a href='$trackingUrl' style='display:inline-block; padding:12px 25px; background:#cfc7b0; color:#111; text-decoration:none; border-radius:30px; font-weight:bold;'>Track My Ticket</a>
                    <p style='margin-top:20px; font-size:12px; color:#888;'>Reference ID: $token</p>
                </div>
            ";
            $mail->send();
        } catch (Exception $e) { }
    }

    ob_clean();
    echo json_encode(['success' => $success, 'token' => $token]);
    exit;
}

elseif ($action === 'get_calendar') {
    $type = $_GET['user_type'] ?? 'client';
    $user_id = $_SESSION['user_id'] ?? null;

    if ($type === 'admin' && ($_SESSION['role'] ?? '') === 'admin') {
        $stmt = $pdo->query("SELECT id, name as title, event_date as start, status FROM bookings");
        $res = $stmt->fetchAll();
    } elseif ($type === 'staff' && $user_id) {
        $stmt = $pdo->prepare("SELECT id, name as title, event_date as start, package, drink_set, message FROM bookings WHERE FIND_IN_SET(?, assigned_user_id) AND status = 'accepted'");
        $stmt->execute([$user_id]);
        $res = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query("SELECT event_date as start, 'Full' as title FROM bookings WHERE status = 'accepted'");
        $res = $stmt->fetchAll();
    }
    ob_clean();
    echo json_encode($res);
    exit;
}

elseif ($action === 'get_bookings') {
    if (($_SESSION['role'] ?? '') !== 'admin') { exit; }
    $stmt = $pdo->query("SELECT * FROM bookings ORDER BY created_at DESC");
    ob_clean();
    echo json_encode($stmt->fetchAll());
    exit;
}

elseif ($action === 'get_staff') {
    if (($_SESSION['role'] ?? '') !== 'admin') { 
        ob_clean();
        echo json_encode(['error' => 'Unauthorized', 'debug_role' => ($_SESSION['role'] ?? 'none')]);
        exit; 
    }
    // Updated to pull email layout metadata securely
    $stmt = $pdo->query("SELECT id, full_name as name, email FROM users WHERE LOWER(role) = 'staff'");
    $results = $stmt->fetchAll();
    ob_clean();
    echo json_encode($results);
    exit;
}

elseif ($action === 'create_staff') {
    if (($_SESSION['role'] ?? '') !== 'admin') { 
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized access block.']);
        exit; 
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $plainPassword = trim($data['password'] ?? '');

    if (empty($name) || empty($email) || empty($plainPassword)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'All profile data fields are mandatory.']);
        exit;
    }

    // Email duplication protection
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'An operational account under this email already exists.']);
        exit;
    }

    $hashedPassword = md5($plainPassword);

    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'staff')");
    $success = $stmt->execute([$name, $email, $hashedPassword]);

    ob_clean();
    echo json_encode(['success' => $success]);
    exit;
}

elseif ($action === 'assign_staff') {
    if (($_SESSION['role'] ?? '') !== 'admin') { exit; }
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE bookings SET assigned_user_id = ?, event_date = ? WHERE id = ?");
    $success = $stmt->execute([$data['staff_ids'], $data['date'], $data['booking_id']]);
    ob_clean();
    echo json_encode(['success' => $success]);
    exit;
}

elseif ($action === 'update_status') {
    if (($_SESSION['role'] ?? '') !== 'admin') { 
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit; 
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $status = $data['status'] ?? ''; 
    $bookingId = $data['id'] ?? null;

    if (!$bookingId || empty($status)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required data.']);
        exit;
    }

    $fetchStmt = $pdo->prepare("SELECT name, email, event_date, token FROM bookings WHERE id = ?");
    $fetchStmt->execute([$bookingId]);
    $booking = $fetchStmt->fetch();

    if (!$booking) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $success = $stmt->execute([$status, $bookingId]);

    if ($success) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'spotph.13@gmail.com';
            $mail->Password   = 'qigt ezeu lqvl enhm';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('spotph.13@gmail.com', 'TheSpotPH');
            $mail->addAddress($booking['email'], $booking['name']);

            $formattedDate = date("F j, Y", strtotime($booking['event_date']));
            $trackingUrl   = "https://www.thespotph.store/track.php?id=" . $booking['token'];
            
            $statusTitle = ucfirst($status); 
            $statusColor = ($status === 'accepted') ? '#2e7d32' : '#c62828'; 

            if ($status === 'accepted') {
                $messageBody = "
                    <p>Great news! We have reviewed your request and your booking quote for <strong>$formattedDate</strong> has been <strong>ACCEPTED</strong>.</p>
                    <p>Our team will reach out shortly to finalize the setup details and coordinate the next steps regarding your downpayment reservation.</p>
                ";
            } else {
                $messageBody = "
                    <p>Thank you for reaching out to us. We regret to inform you that your booking request for <strong>$formattedDate</strong> has been <strong>DECLINED</strong> due to schedule conflicts or capacity limits.</p>
                    <p>We hope we can serve you at a future event!</p>
                ";
            }

            $mail->isHTML(true);
            $mail->Subject = "Booking Request Update: $statusTitle - TheSpotPH";
            $mail->Body    = "
                <div style='font-family: sans-serif; max-width: 600px; border: 1px solid #eee; padding: 20px;'>
                    <h2 style='color: #cfc7b0;'>Hello " . htmlspecialchars($booking['name']) . "!</h2>
                    <p style='font-size: 16px; font-weight: bold; color: $statusColor;'>Status Update: $statusTitle</p>
                    $messageBody
                    <p>You can view your real-time live booking tracking page link details here:</p>
                    <a href='$trackingUrl' style='display:inline-block; padding:12px 25px; background:#cfc7b0; color:#111; text-decoration:none; border-radius:30px; font-weight:bold;'>View Booking Status</a>
                    <p style='margin-top:20px; font-size:12px; color:#888;'>Reference ID: " . $booking['token'] . "</p>
                </div>
            ";

            $mail->send();
        } catch (Exception $e) { }
    }

    ob_clean();
    echo json_encode(['success' => $success]);
    exit;
}

elseif ($action === 'delete_booking') {
    if (($_SESSION['role'] ?? '') !== 'admin') { 
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit; 
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $booking_id = $data['id'] ?? null;

    if (!$booking_id) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
    $success = $stmt->execute([$booking_id]);

    ob_clean();
    echo json_encode(['success' => $success]);
    exit;
}

// Safe Staff Account Deletion
elseif ($action === 'delete_staff') {
    if (($_SESSION['role'] ?? '') !== 'admin') { 
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit; 
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $staff_id = $data['id'] ?? null;

    if (!$staff_id) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing account identifier.']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND LOWER(role) = 'staff'");
    $success = $stmt->execute([$staff_id]);

    ob_clean();
    echo json_encode(['success' => $success]);
    exit;
}

elseif ($action === 'logout') {
    session_destroy();
    ob_clean();
    echo json_encode(['success' => true]);
    exit;
}
