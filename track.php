<?php
// 1. Database Connection (Aiven + Vercel Config)
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

// Since track.php is in the root and certs are in /api/certs/
$ssl_ca = __DIR__ . '/api/certs/ca.pem'; 

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => $ssl_ca,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
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
        }

        .container {
            text-align: center;
            width: 90%;
            max-width: 500px;
            padding: 20px 0;
        }

        .logo { 
            width: 130px; 
            margin-bottom: 30px; 
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
            opacity: 0.8; 
            line-height: 1.6; 
            margin: 10px 0;
        }

        strong { color: #cfc7b0; font-weight: 600; }

        .card { 
            background: #111827; 
            padding: 50px 30px; 
            border-radius: 20px; 
            border: 1px solid rgba(207, 199, 176, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .status {
            display: inline-block;
            margin: 25px 0;
            padding: 12px 40px;
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

        .reference {
            margin-top: 30px; 
            opacity: 0.5 !important; 
            font-size: 0.8rem;
            letter-spacing: 1px;
        }

        .footer-note { 
            font-size: 0.8rem; 
            color: #86806f; 
            margin-top: 40px; 
        }
    </style>
</head>
<body>

    <div class="container">
        <img src="assets/TheSpotPH.png" alt="TheSpotPH" class="logo">
        
        <div class="card">
            <h1>Hello, <?php echo htmlspecialchars($booking['name']); ?>!</h1>
            
            <p>Your booking for <strong><?php echo date("F j, Y", strtotime($booking['event_date'])); ?></strong> is currently:</p>
            
            <div class="status <?php echo $booking['status']; ?>">
                <?php echo $booking['status']; ?>
            </div>
            
            <p class="reference">Reference: <?php echo $token; ?></p>
        </div>

        <p class="footer-note">Questions? Contact us at spotph.13@gmail.com</p>
    </div>

</body>
</html>
