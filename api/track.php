<?php
// 1. Database Connection (Aiven + Vercel Config)
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

$ssl_ca = __DIR__ . '/certs/ca.pem';

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
    die("<h1 style='color: white; text-align: center; font-family: sans-serif;'>Service Unavailable</h1>");
}

// 2. Fetch Booking Data
$token = $_GET['id'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE token = ?");
$stmt->execute([$token]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    die("<h1 style='color: white; text-align: center; margin-top: 50px; font-family: sans-serif;'>Invalid Tracking Link</h1>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Status — TheSpotPH</title>
    <link rel="shortcut icon" href="assets/favicon.jpg" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #182339; 
            color: #ffffff; 
            font-family: 'Outfit', sans-serif; 
            margin: 0;
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px 0;
        }

        .container {
            text-align: center;
            width: 90%;
            max-width: 550px;
        }

        .logo { 
            width: 130px; 
            margin-bottom: 20px; 
            filter: drop-shadow(0 5px 15px rgba(0,0,0,0.3));
        }

        h1 { 
            font-family: 'Cormorant Garamond', serif; 
            color: #cfc7b0; 
            font-size: 2.2rem; 
            margin: 0 0 15px 0; 
            line-height: 1.2;
        }

        p { 
            font-weight: 300;
            opacity: 0.9; 
            line-height: 1.6; 
            margin: 10px 0;
        }

        strong { color: #cfc7b0; font-weight: 600; }

        .card { 
            background: #111827; 
            padding: 40px 30px; 
            border-radius: 20px; 
            border: 1px solid rgba(207, 199, 176, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: left;
        }

        .status-container {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px dashed rgba(207, 199, 176, 0.2);
            padding-bottom: 25px;
        }

        .status {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 35px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-size: 0.85rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .pending  { background: #86806f; color: #ffffff; }
        .accepted { background: #4caf50; color: #ffffff; }
        .declined { background: #e05252; color: #ffffff; }

        /* Receipt Breakdown Layout */
        .receipt-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem;
            color: #cfc7b0;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.95rem;
        }

        .receipt-row .label {
            color: #86806f;
            font-weight: 400;
        }

        .receipt-row .value {
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }

        .receipt-message-block {
            margin-top: 15px;
            padding-top: 10px;
        }

        .receipt-message-block .message-text {
            background: rgba(255, 255, 255, 0.03);
            padding: 15px;
            border-radius: 8px;
            font-style: italic;
            font-size: 0.9rem;
            margin-top: 5px;
            color: rgba(255, 255, 255, 0.8);
            border-left: 3px solid #cfc7b0;
        }

        .reference {
            margin-top: 30px; 
            text-align: center;
            opacity: 0.4; 
            font-size: 0.75rem;
            letter-spacing: 1px;
        }

        .footer-note { 
            font-size: 0.8rem; 
            color: #86806f; 
            margin-top: 30px; 
        }
    </style>
</head>
<body>

    <div class="container">
        <img src="assets/TheSpotPH.png" alt="TheSpotPH" class="logo">
        
        <div class="card">
            <div class="status-container">
                <h1>Hello, <?php echo htmlspecialchars($booking['name']); ?>!</h1>
                <p>Your request has been saved. Your quote is currently:</p>
                <div class="status <?php echo strtolower($booking['status']); ?>">
                    <?php echo htmlspecialchars($booking['status']); ?>
                </div>
            </div>

            <div class="receipt-title">Quote Summary</div>
            
            <div class="receipt-row">
                <span class="label">Client Name</span>
                <span class="value"><?php echo htmlspecialchars($booking['name']); ?></span>
            </div>

            <div class="receipt-row">
                <span class="label">Email Address</span>
                <span class="value"><?php echo htmlspecialchars($booking['email']); ?></span>
            </div>

            <div class="receipt-row">
                <span class="label">Phone Number</span>
                <span class="value"><?php echo htmlspecialchars($booking['phone'] ?: 'N/A'); ?></span>
            </div>

            <div class="receipt-row">
                <span class="label">Event Date</span>
                <span class="value"><strong><?php echo date("F j, Y", strtotime($booking['event_date'])); ?></strong></span>
            </div>

            <div class="receipt-row">
                <span class="label">Selected Package</span>
                <span class="value" style="color: #cfc7b0; font-weight: 600;"><?php echo htmlspecialchars($booking['package']); ?></span>
            </div>

            <div class="receipt-row">
                <span class="label">Selected Package</span>
                <span class="value" style="color: #cfc7b0; font-weight: 600;"><?php echo htmlspecialchars($booking['package']); ?></span>
            </div>

            <div class="receipt-row">
                <span class="label">Drink Set Menu</span>
                <span class="value" style="color: #cfc7b0; font-weight: 600;"><?php echo htmlspecialchars($booking['drink_set'] ?? 'Not Specified'); ?></span>
            </div>

            <?php if (!empty($booking['message'])): ?>
            <div class="receipt-message-block">
                <span class="label">Special Instructions / Event Details:</span>
                <div class="message-text">"<?php echo nl2br(htmlspecialchars($booking['message'])); ?>"</div>
            </div>
            <?php endif; ?>
            
            <p class="reference">Ticket Token: <?php echo htmlspecialchars($token); ?></p>
        </div>

        <p class="footer-note">Questions? Contact us at spotph.13@gmail.com</p>
    </div>

</body>
</html>
