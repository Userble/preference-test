<?php
session_start();

// Security headers
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: no-referrer");

// IMPORTANT SETUP STEP - Enter YOUR database credentials here
$host = 'localhost';
$dbname = 'YOUR DB NAME';
$user = 'YOUR DB USERNAME';
$pass = 'YOUR DB PASSWORD';

// Create a new PDO instance
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    // Set error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set PDO to use prepared statements
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Create the table if it doesn't exist
    createPreferenceTestVotesTable($pdo);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo "Database connection failed.";
    exit;
}

// Function to create the table
function createPreferenceTestVotesTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS preference_test_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_id INT NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
}

// Function to get client IP address
function getUserIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip_list = explode(',', $_SERVER[$key]);
            foreach ($ip_list as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }
    return '0.0.0.0';
}

$ip_address = getUserIP();

// Sanitize and validate image ID
function validateImageId($id) {
    $valid_ids = [1, 2];
    return in_array($id, $valid_ids) ? $id : null;
}

// Initialize variables
$has_completed_test = isset($_SESSION['has_completed_test']) && $_SESSION['has_completed_test'] === true;
$message = '';

// Check if a vote has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['vote'])) {
    $image_id = validateImageId(intval($_POST['vote']));

    if (!$image_id) {
        $message = "Invalid vote selection.";
    } elseif ($has_completed_test) {
        $message = "You have already voted.";
    } else {
        // Check number of votes from this IP in the last hour
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM preference_test_votes WHERE ip_address = :ip AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute(['ip' => $ip_address]);
        $voteCount = $stmt->fetchColumn();

        if ($voteCount >= 3) {
            $message = "We have detected unusual voting patterns from your device. If you believe this is an error, please contact support@userble.org";
        } else {
            // Record the vote
            $stmt = $pdo->prepare("INSERT INTO preference_test_votes (image_id, ip_address) VALUES (:image_id, :ip)");
            $stmt->execute(['image_id' => $image_id, 'ip' => $ip_address]);
            $_SESSION['has_completed_test'] = true;
            $has_completed_test = true;
            session_regenerate_id(true);
            $message = "Thank you for your vote!";
        }
    }
}

// Get total votes for each image
$stmt = $pdo->query("SELECT image_id, COUNT(*) as votes FROM preference_test_votes GROUP BY image_id");
$votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$imageVotes = [1 => 0, 2 => 0];
foreach ($votes as $vote) {
    $imageVotes[$vote['image_id']] = $vote['votes'];
}

// Get total votes
$stmt = $pdo->query("SELECT COUNT(*) as total_votes FROM preference_test_votes");
$totalVotes = $stmt->fetchColumn();

// Calculate percentages
$percentages = [];
foreach ($imageVotes as $id => $count) {
    $percentages[$id] = $totalVotes ? round(($count / $totalVotes) * 100, 2) : 0;
}

// Determine which image to display (for zoom view)
$displayImage = null;
if (isset($_GET['image'])) {
    $displayImage = validateImageId(intval($_GET['image']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Image Voting</title>
    <!-- Include Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
    <style>
        /* Reset and basic styling */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f9f9f9;
            color: #333;
        }
        .title {
            padding: 0 0 50px 0;
            text-align: center;
        }
        h1 {
            margin-bottom: 10px;
        }
        .container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 0 20px;
            text-align: center;
        }
        .message {
            margin-bottom: 20px;
            font-size: 1.2em;
            color: #2c3e50;
        }
        .cta {
            margin-top: 20px;
            font-size: 1.1em;
            color: #2c3e50;
        }
        .cta a {
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }
        .cta a:hover {
            text-decoration: underline;
        }
        .image-grid {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
        }
        .image-container {
            position: relative;
            flex: 1 1 50%;
            max-width: 50%;
            padding: 10px;
        }
        /* Image Wrapper */
        .image-wrapper {
            position: relative;
            width: 100%;
            cursor: pointer;
        }
        .image-wrapper img {
            width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .image-wrapper:hover img {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.2);
        }
        /* Overlay */
        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.4);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .image-wrapper:hover .overlay {
            opacity: 1;
        }
        /* Magnifying Glass Icon */
        .magnifier-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            fill: #fff;
            width: 48px;
            height: 48px;
        }
        .vote-button {
            margin-top: 15px;
            padding: 12px 25px;
            background-color: #3498db;
            border: none;
            color: #fff;
            font-size: 1em;
            border-radius: 25px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.3s;
        }
        .vote-button:hover,
        .vote-button:focus {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        .vote-button:focus {
            outline: none;
        }
        .vote-button[disabled] {
            background-color: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }
        .total-votes {
            margin-top: 10px;
            font-size: 0.9em;
            color: #7f8c8d;
        }
        .navigation {
            margin-top: 30px;
        }
        .navigation a {
            margin: 0 15px;
            color: #3498db;
            text-decoration: none;
            font-size: 1em;
            transition: color 0.3s;
        }
        .navigation a:hover {
            color: #2980b9;
        }
        @media (max-width: 800px) {
            .image-container {
                flex: 1 1 100%;
                max-width: 100%;
            }
        }
        /* Detail view adjustments */
        .detail-view .image-container {
            max-width: 100%;
            flex: none;
            margin: 0 auto;
        }
        .detail-view .image-container img {
            max-width: 100%;
            height: auto;
        }
        .button-group {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
        }
        .nav-button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 50px;
            height: 50px;
            background-color: #e0e0e0;
            border: none;
            color: #333;
            font-size: 1.5em;
            border-radius: 50%;
            text-decoration: none;
            margin: 0 15px;
            transition: background-color 0.3s, transform 0.3s;
            cursor: pointer;
        }
        .nav-button:hover,
        .nav-button:focus {
            background-color: #d5d5d5;
            transform: translateY(-2px);
        }
        .nav-button:focus {
            outline: none;
        }
        .placeholder {
            width: 50px;
            height: 50px;
            margin: 0 15px;
        }
        /* Adjusted vote-button for detail view */
        .button-group .vote-button {
            margin: 0 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <?php if ($has_completed_test): ?>
    <div class="title">   
        <h1>Thank you for your vote!</h1>
        <p class="cta">Interested in getting paid to complete usability tests? <a href="https://userble.com/become-a-tester" target="_blank">Become a tester for Userble</a>.</p>
    </div>
    <?php else: ?>
        <div class="title">
            <h1>Preference Test</h1>
            <p>Please select the image you prefer from below</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($message)) { echo "<p class='message'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>"; } ?>

    <?php if ($displayImage): ?>
        <div class="image-grid detail-view">
            <div class="image-container">
                <img src="image<?php echo $displayImage; ?>.png" alt="Image Option <?php echo $displayImage; ?>">

                <div class="button-group">
                    <?php if ($displayImage > 1): ?>
                        <a href="?image=<?php echo $displayImage - 1; ?>" class="nav-button prev-button" aria-label="Previous Image">
                            <!-- Left Arrow SVG -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                                <path fill="#333" d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                            </svg>
                        </a>
                    <?php else: ?>
                        <span class="nav-button placeholder"></span>
                    <?php endif; ?>

                    <form method="post" style="display:inline;">
                        <?php if (!$has_completed_test): ?>
                            <button class="vote-button" type="submit" name="vote" value="<?php echo $displayImage; ?>">Vote for this option</button>
                        <?php else: ?>
                            <button class="vote-button" disabled>You have already voted</button>
                        <?php endif; ?>
                    </form>

                    <?php if ($displayImage < 2): ?>
                        <a href="?image=<?php echo $displayImage + 1; ?>" class="nav-button next-button" aria-label="Next Image">
                            <!-- Right Arrow SVG -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                                <path fill="#333" d="M8.59 16.59L10 18l6-6-6-6-1.41 1.41L13.17 12z"/>
                            </svg>
                        </a>
                    <?php else: ?>
                        <span class="nav-button placeholder"></span>
                    <?php endif; ?>
                </div>
                <p class="total-votes">Total votes: <?php echo $imageVotes[$displayImage]; ?> (<?php echo $percentages[$displayImage]; ?>%)</p>
            </div>
        </div>
        <div class="navigation">
            <a href="index.php">Back to all images</a>
        </div>
    <?php else: ?>
        <div class="image-grid">
            <div class="image-container">
                <div class="image-wrapper">
                    <a href="?image=1" aria-label="View image in detail">
                        <img src="image1.png" alt="First Image Option">
                        <div class="overlay">
                            <!-- Magnifying Glass SVG Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="magnifier-icon" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" viewBox="0 0 20 20"><g fill="#ffffff"><path d="M9 6a.75.75 0 0 1 .75.75v1.5h1.5a.75.75 0 0 1 0 1.5h-1.5v1.5a.75.75 0 0 1-1.5 0v-1.5h-1.5a.75.75 0 0 1 0-1.5h1.5v-1.5A.75.75 0 0 1 9 6"></path><path fill-rule="evenodd" d="M2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9m7-5.5a5.5 5.5 0 1 0 0 11a5.5 5.5 0 0 0 0-11" clip-rule="evenodd"></path></g></svg>                        
                        </div>
                    </a>
                </div>
                <?php if (!$has_completed_test): ?>
                    <form method="post">
                        <button class="vote-button" type="submit" name="vote" value="1">Choose this image</button>
                    </form>
                <?php else: ?>
                    <button class="vote-button" disabled>You have already voted</button>
                <?php endif; ?>
                <p class="total-votes">Total votes: <?php echo $imageVotes[1]; ?> (<?php echo $percentages[1]; ?>%)</p>
            </div>
            <div class="image-container">
                <div class="image-wrapper">
                    <a href="?image=2" aria-label="View image in detail">
                        <img src="image2.png" alt="Second image option">
                        <div class="overlay">
                            <!-- Magnifying Glass SVG Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="magnifier-icon" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" viewBox="0 0 20 20"><g fill="#ffffff"><path d="M9 6a.75.75 0 0 1 .75.75v1.5h1.5a.75.75 0 0 1 0 1.5h-1.5v1.5a.75.75 0 0 1-1.5 0v-1.5h-1.5a.75.75 0 0 1 0-1.5h1.5v-1.5A.75.75 0 0 1 9 6"></path><path fill-rule="evenodd" d="M2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9m7-5.5a5.5 5.5 0 1 0 0 11a5.5 5.5 0 0 0 0-11" clip-rule="evenodd"></path></g></svg>
                        </div>
                    </a>
                </div>
                <?php if (!$has_completed_test): ?>
                    <form method="post">
                        <button class="vote-button" type="submit" name="vote" value="2">Choose this image</button>
                    </form>
                <?php else: ?>
                    <button class="vote-button" disabled>You have already voted</button>
                <?php endif; ?>
                <p class="total-votes">Total votes: <?php echo $imageVotes[2]; ?> (<?php echo $percentages[2]; ?>%)</p>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
