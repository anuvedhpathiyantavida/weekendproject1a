<?php
// Set timezone for accurate date handling
date_default_timezone_set('Asia/Kolkata'); // Setting to Indian Standard Time (IST) for Thalassery

// ---------- DB Setup ----------
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "attendance_db";

$conn = new mysqli($host, $user, $pass, $dbname);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable strict error reporting

if ($conn->connect_error) {
    // Log connection error instead of just dying, in a production environment
    error_log("Connection failed: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
}

// ---------- Handle Clear All Data Request ----------
if (isset($_POST['clear_all_data'])) {
    if (isset($_POST['confirm_clear']) && $_POST['confirm_clear'] === 'yes') {
        try {
            $conn->query("TRUNCATE TABLE attendance");
            header("Location: " . $_SERVER['PHP_SELF'] . "?message=All attendance data cleared successfully.");
            exit;
        } catch (mysqli_sql_exception $e) {
            error_log("Error clearing attendance data: " . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=Failed to clear attendance data.");
            exit;
        }
    } else {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=Clear data action not confirmed.");
        exit;
    }
}


// ---------- Handle Filter Selection ----------
$filter = $_GET['filter'] ?? 'single'; // Default to 'single'
$today = date("Y-m-d");

$startDate = $today;
$endDate = $today;
$selectedDate = $today;
$title = "Attendance Summary"; // Default title

if ($filter === 'custom') {
    // Default to start of current month and today's date for custom range
    $startDate = $_GET['from'] ?? date('Y-m-01');
    $endDate = $_GET['to'] ?? $today;
    $title = "Attendance Summary for Custom Range: " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
} elseif ($filter === 'single') {
    $selectedDate = $_GET['specific_date'] ?? $today;
    $title = "Attendance Summary on " . htmlspecialchars($selectedDate);
}

// Validate dates (basic validation)
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $startDate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $endDate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $selectedDate)) {
    die("Invalid date format provided.");
}

// Ensure start date is not after end date for custom range
if ($filter === 'custom' && $startDate > $endDate) {
    list($startDate, $endDate) = [$endDate, $startDate]; // Swap if dates are inverted
    // Optionally, inform user or set a default valid range
    $title .= " (Adjusted Date Range)";
}


// ---------- Fetch all students ----------
$students_query_result = null;
try {
    $students_query_result = $conn->query("SELECT * FROM student ORDER BY roll");
    if (!$students_query_result) {
        throw new mysqli_sql_exception("Query failed: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching students for summary: " . $e->getMessage());
    die("Error loading student data for summary.");
}


$summary = [];

while ($row = $students_query_result->fetch_assoc()) {
    $roll = $row['roll'];
    $name = $row['name'];

    if ($filter === 'single') {
        // Use prepared statement for security
        $stmt = $conn->prepare("SELECT status FROM attendance WHERE roll = ? AND date = ?");
        $stmt->bind_param("ss", $roll, $selectedDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;
        $status = $data['status'] ?? 'N/A'; // N/A if no record
        $summary[] = [
            'roll' => $roll,
            'name' => $name,
            'status' => $status
        ];
        $stmt->close();
    } else { // 'custom' filter
        // Use prepared statement for security for custom range counts
        $stmt = $conn->prepare("SELECT
            SUM(CASE WHEN status = 'p' THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN status = 'a' THEN 1 ELSE 0 END) AS absent,
            SUM(CASE WHEN status = 'hm' THEN 1 ELSE 0 END) AS half_day_morning,
            SUM(CASE WHEN status = 'he' THEN 1 ELSE 0 END) AS half_day_evening
            FROM attendance
            WHERE roll = ? AND date BETWEEN ? AND ?");
        $stmt->bind_param("sss", $roll, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        $present = $data['present'] ?? 0;
        $absent = $data['absent'] ?? 0;
        $halfDayMorning = $data['half_day_morning'] ?? 0;
        $halfDayEvening = $data['half_day_evening'] ?? 0;

        // Only add student to summary if they have any attendance record in the range
        // Or if you want to show all students regardless, remove this if condition
        if ($present > 0 || $absent > 0 || $halfDayMorning > 0 || $halfDayEvening > 0) {
            $summary[] = [
                'roll' => $roll,
                'name' => $name,
                'present' => $present,
                'absent' => $absent,
                'half_day_morning' => $halfDayMorning,
                'half_day_evening' => $halfDayEvening
            ];
        }
    }
}
$students_query_result->free(); // Free the student result set

?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Summary</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* General Body Styling */
        body {
            font-family: 'Roboto', Arial, sans-serif;
            padding: 20px;
            background-color: #f8f9fa; /* Lighter background */
            color: #343a40; /* Darker text for contrast */
            line-height: 1.6;
            margin: 0;
        }

        /* Headings */
        h2 {
            color: #007bff; /* Primary blue for main heading */
            margin-bottom: 25px;
            font-size: 2.5em;
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 15px;
            display: inline-block;
            margin-left: auto;
            margin-right: auto;
            display: block;
            max-width: fit-content;
            font-weight: 700;
        }

        /* Messages (Success/Error) */
        .message-success, .message-error {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 1em;
            font-weight: 500;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Filter Form */
        .filter-form {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            max-width: 800px;
            margin: 30px auto;
            display: flex;
            flex-wrap: wrap; /* Allow wrapping for responsiveness */
            align-items: flex-end; /* Align items to the bottom */
            gap: 15px; /* Space between elements */
        }

        .filter-form label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px; /* Adjust margin for inline elements */
            flex-basis: 100%; /* Label takes full width */
        }

        .filter-form select,
        .filter-form input[type="date"] {
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #ced4da;
            font-size: 1em;
            flex-grow: 1; /* Allows inputs to grow and fill space */
            min-width: 150px; /* Minimum width for inputs */
            box-sizing: border-box;
            background-color: #fdfdfe;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .filter-form input[type="date"]:focus,
        .filter-form select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            outline: none;
        }

        .filter-form div {
            display: flex;
            gap: 10px; /* Space between date inputs */
            flex-wrap: wrap; /* Allow date inputs to wrap */
            align-items: center;
            flex-grow: 1; /* Allows the date containers to grow */
        }

        /* Table Styling */
        table {
            border-collapse: separate; /* Use separate to allow border-radius on cells */
            border-spacing: 0;
            width: 100%;
            max-width: 1000px;
            margin: 30px auto;
            background-color: #ffffff;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-radius: 12px;
            overflow: hidden; /* Ensures rounded corners apply to content */
        }

        th, td {
            border: 1px solid #e9ecef; /* Lighter borders */
            padding: 15px 12px;
            text-align: center;
            vertical-align: middle;
        }

        th {
            background-color: #007bff;
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9em;
            letter-spacing: 0.05em;
        }

        /* Specific border-radius for table corners */
        th:first-child { border-top-left-radius: 12px; }
        th:last-child { border-top-right-radius: 12px; }

        tr:nth-child(even) {
            background-color: #f2f7fc; /* Subtle striping */
        }

        tr:hover {
            background-color: #e6f2ff; /* Light blue hover effect */
        }

        /* Status specific colors for single date view */
        .status-p { color: #28a745; font-weight: 600; } /* Green */
        .status-a { color: #dc3545; font-weight: 600; } /* Red */
        .status-hm { color: #ffc107; font-weight: 600; } /* Yellow/Orange */
        .status-he { color: #fd7e14; font-weight: 600; } /* Darker Orange */
        .status-na { color: #6c757d; font-weight: 400; } /* Gray for N/A */


        /* Buttons */
        button, input[type="submit"] {
            background-color: #28a745; /* Green for primary action (Apply Filter) */
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-decoration: none; /* For button links */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
        }

        button:hover, input[type="submit"]:hover {
            background-color: #218838; /* Darker green on hover */
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .filter-form button[type="submit"] {
            background-color: #007bff; /* Blue for filter button */
            flex-grow: 0; /* Don't let it grow */
        }

        .filter-form button[type="submit"]:hover {
            background-color: #0056b3;
        }

        .back-to-mark-btn {
            background-color: green; /* Green for "Mark Attendance" */
            margin-right: 15px; /* Space from clear button */
        }
        .back-to-mark-btn:hover {
            background-color: #5a6268;
        }

        .danger-btn {
            background-color: #dc3545; /* Red for "Clear All Data" */
            margin-top: 0px; /* Space from table */
            font-weight: 100;
        }
        .danger-btn:hover {
            background-color: #c82333;
        }

        /* No Data Message */
        .no-data {
            margin-top: 30px;
            color: #dc3545;
            font-weight: bold;
            padding: 20px;
            background-color: #fff3f3;
            border: 1px solid #dc3545;
            border-radius: 8px;
            text-align: center;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        /* Button group at the bottom */
        .action-buttons {
            margin-top: 40px;
            display: flex;
            justify-content: center;
            gap: 20px; /* Space between buttons */
            flex-wrap: wrap;
        }


        /* Responsive Adjustments */
        @media (max-width: 768px) {
            body { padding: 15px; }
            h2 { font-size: 2em; padding-bottom: 10px; }

            .filter-form {
                flex-direction: column;
                align-items: stretch; /* Stretch items to fill width */
                padding: 15px;
                gap: 10px; /* Smaller gap for mobile */
            }
            .filter-form label,
            .filter-form select,
            .filter-form input[type="date"],
            .filter-form div,
            .filter-form button {
                width: 100%; /* Full width for inputs and buttons */
                margin-right: 0;
            }
            .filter-form div { flex-direction: column; gap: 10px; } /* Stack date inputs vertically */

            table {
                width: 100%;
                margin: 20px auto;
                font-size: 0.9em;
            }
            th, td { padding: 10px 8px; }

            .action-buttons {
                flex-direction: column;
                gap: 15px;
            }
            .action-buttons button, .action-buttons input[type="submit"] {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            h2 { font-size: 1.8em; }
            .message-success, .message-error, .no-data {
                padding: 10px;
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>

    <?php
    if (isset($_GET['message'])) {
        echo '<div class="message-success">' . htmlspecialchars($_GET['message']) . '</div>';
    }
    if (isset($_GET['error'])) {
        echo '<div class="message-error">' . htmlspecialchars($_GET['error']) . '</div>';
    }
    ?>

    <h2><?= $title ?></h2>

    <form class="filter-form" method="get">
        <label for="filter"><strong>Select View:</strong></label>
        <select name="filter" id="filter" onchange="toggleDateInputs()">
            <option value="single" <?= $filter == 'single' ? 'selected' : '' ?>>Specific Date</option>
            <option value="custom" <?= $filter == 'custom' ? 'selected' : '' ?>>Custom Range</option>
        </select>

        <div id="singleDate" style="display:<?= $filter == 'single' ? 'flex' : 'none' ?>;">
            <label for="specific_date" style="flex-basis: auto; margin-bottom: 0;">Date:</label>
            <input type="date" name="specific_date" id="specific_date" value="<?= htmlspecialchars($_GET['specific_date'] ?? $today) ?>">
        </div>

        <div id="dateRange" style="display:<?= $filter == 'custom' ? 'flex' : 'none' ?>;">
            <label for="from" style="flex-basis: auto; margin-bottom: 0;">From:</label>
            <input type="date" name="from" id="from" value="<?= htmlspecialchars($_GET['from'] ?? date('Y-m-01')) ?>">
            <label for="to" style="flex-basis: auto; margin-bottom: 0;">To:</label>
            <input type="date" name="to" id="to" value="<?= htmlspecialchars($_GET['to'] ?? $today) ?>">
        </div>
        
        <button type="submit">Apply Filter</button>
    </form>

    <?php if (count($summary) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Roll No</th>
                    <th>Name</th>
                    <?php if ($filter === 'single'): ?>
                        <th>Status</th>
                    <?php else: // Custom Range ?>
                        <th>Total Present</th>
                        <th>Total Absent</th>
                        <th>Total Half Day Morning</th>
                        <th>Total Half Day Evening</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($summary as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['roll']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <?php if ($filter === 'single'):
                            $displayStatus = '';
                            $statusClass = 'status-' . strtolower($row['status']);
                            switch ($row['status']) {
                                case 'p':
                                    $displayStatus = 'Present';
                                    break;
                                case 'a':
                                    $displayStatus = 'Absent';
                                    break;
                                case 'hm':
                                    $displayStatus = '#'; // Changed to show distinct half-day types
                                    break;
                                case 'he':
                                    $displayStatus = 'Evening'; // Changed to show distinct half-day types
                                    break;
                                case 'N/A':
                                    $displayStatus = 'N/A';
                                    $statusClass = 'status-na';
                                    break;
                                default:
                                    $displayStatus = $row['status']; // Fallback
                                    break;
                            }
                        ?>
                            <td class="<?= $statusClass ?>">
                                <?= $displayStatus ?>
                            </td>
                        <?php else: ?>
                            <td class="status-p"><?= $row['present'] ?></td>
                            <td class="status-a"><?= $row['absent'] ?></td>
                            <td class="status-hm"><?= $row['half_day_morning'] ?></td>
                            <td class="status-he"><?= $row['half_day_evening'] ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">No attendance records found for the selected period.</div>
    <?php endif; ?>

    <div class="action-buttons">
        <a href="intex.php" style="text-decoration: none;">
            <button type="button" class="back-to-mark-btn">Mark Attendance</button>
        </a>

        <form method="post" onsubmit="return confirm('WARNING: This will delete ALL attendance data permanently. This action cannot be undone. Are you absolutely sure?');" style="display:inline-block;">
            <input type="hidden" name="clear_all_data" value="1">
            <input type="hidden" name="confirm_clear" value="yes">
            <button type="submit" class="danger-btn">Clear All Attendance Data</button>
        </form>
    </div>

    <script>
    function toggleDateInputs() {
        const filter = document.getElementById('filter').value;
        document.getElementById('dateRange').style.display = filter === 'custom' ? 'flex' : 'none';
        document.getElementById('singleDate').style.display = filter === 'single' ? 'flex' : 'none';
    }

    // Call on page load to ensure correct initial display
    window.onload = toggleDateInputs;
    </script>

</body>
</html>
<?php
$conn->close();
?>
