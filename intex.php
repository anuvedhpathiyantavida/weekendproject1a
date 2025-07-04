<?php
// Set timezone for accurate date handling
date_default_timezone_set('Asia/Kolkata'); // Setting to Indian Standard Time (IST) for Thalassery

// ---------- DB Setup ----------
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "attendance_db";

$conn = new mysqli($host, $user, $pass);
// Enable strict error reporting for MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($conn->connect_error) {
    // Log connection error instead of just dying, in a production environment
    error_log("Connection failed: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
}

// Create Database if not exists
try {
    $conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
    $conn->select_db($dbname);
} catch (mysqli_sql_exception $e) {
    error_log("Database selection/creation failed: " . $e->getMessage());
    die("Error setting up database.");
}

// Create student table
try {
    $conn->query("CREATE TABLE IF NOT EXISTS student (
        roll VARCHAR(20) PRIMARY KEY,
        name VARCHAR(100) NOT NULL
    )");
} catch (mysqli_sql_exception $e) {
    error_log("Error creating student table: " . $e->getMessage());
    die("Error setting up student table.");
}

// Create attendance table
// status CHAR(2) correctly accommodates 'p', 'a', 'hm', 'he'
try {
    $conn->query("CREATE TABLE IF NOT EXISTS attendance (
        roll VARCHAR(20),
        date DATE,
        status CHAR(2) NOT NULL,
        PRIMARY KEY (roll, date),
        FOREIGN KEY (roll) REFERENCES student(roll) ON DELETE CASCADE ON UPDATE CASCADE
    )");
} catch (mysqli_sql_exception $e) {
    error_log("Error creating attendance table: " . $e->getMessage());
    die("Error setting up attendance table.");
}

// Insert sample students if empty (only if the table is freshly created or empty)
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM student");
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $sampleStudents = [
            ['TTC0824001', 'ABHIRAMI P V'], ['TTC0824002', 'ADHEENA M'],
            ['TTC0824003', 'ANUNANDHA P'], ['TTC0824004', 'DHIYA A K'],
            ['TTC0824005', 'DIYA M'], ['TTC0824006', 'SHIKHA M K'],
            ['TTC0824008', 'MUGDHA BALA M'], ['TTC0824009', 'AHAMAD RIYAN T'],
            ['TTC0824010', 'ARJUN KRISHNAN N P'], ['TTC0824011', 'BRINGESH P'],
            ['TTC0824012', 'DEEPKRISHNA P K'], ['TTC0824013', 'NEERAJ A'],
            ['TTC0824014', 'REVANTH S'], ['TTC0824015', 'VAISHAKH G K'],
            ['TTC0824016', 'VIDHU MANOHAR'], ['TTC0824017', 'ROSHITH K V'],
            ['TTC0824018', 'SANGEERTH SATHYAN'], ['TTC0824019', 'YADHUNANDH O T'],
            ['TTC0824020', 'ARJAV ANEESH'], ['TTC0824021', 'ASRITHA V'],
            ['TTC0824022', 'ANUVEDH P'], ['TTC0824024', 'THEJUS J'],
            ['TTC0824025', 'AMAL ARAVIND'], ['TTC0824026', 'NAINEEKA B P'],
            ['TTC0824027', 'MUHAMMED AKMAL A'], ['TTC0824028', 'MUHAMMED SHAHAN K C'],
            ['TTC0824029', 'ADITHYAN A'], ['TTC0824031', 'NISTHUL K'],
            ['TTC0824032', 'RITHUNAND REJILESH'], ['TTC0824033', 'SINAN P K'],
            ['TTC0824034', 'MUHAMMED MEHAJABIN SEYYAF'], ['TTC0824035', 'ABHAY KRISHNA N P'],
            ['TTC0824036', 'FLAVIYUS CLEMENT'], ['TTC0824037', 'JOHAN JOSEPH'],
            ['TTC0824038', 'AMAL C V'], ['TTC0824039', 'GAUTHAM MAHESH']
        ];
        $stmt = $conn->prepare("INSERT INTO student (roll, name) VALUES (?, ?)");
        foreach ($sampleStudents as $student) {
            $stmt->bind_param("ss", $student[0], $student[1]);
            $stmt->execute();
        }
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Error inserting sample students: " . $e->getMessage());
    // Not critical to die here, but might show an empty list
}


// ---------- Handle New Student Addition (if 'add_student' button is clicked) ----------
if (isset($_POST['add_student'])) {
    $newRoll = trim($_POST['new_roll']);
    $newName = trim($_POST['new_name']);

    if (!empty($newRoll) && !empty($newName)) {
        try {
            $stmt = $conn->prepare("INSERT INTO student (roll, name) VALUES (?, ?)");
            $stmt->bind_param("ss", $newRoll, $newName);
            $stmt->execute();
            $stmt->close();
            // Using JavaScript alert for user feedback before redirecting
            echo "<script>alert('Student " . htmlspecialchars($newName) . " (Roll: " . htmlspecialchars($newRoll) . ") added successfully!');</script>";
            // Redirect to self to clear POST data and show updated view with new student
            echo "<script>window.location.href = '" . $_SERVER['PHP_SELF'] . "';</script>";
            exit; // Exit after sending script and redirect
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) { // MySQL error code for duplicate entry (PRIMARY KEY constraint)
                echo "<script>alert('Error: Student with Roll No. " . htmlspecialchars($newRoll) . " already exists.');</script>";
            } else {
                error_log("Error adding new student: " . $e->getMessage());
                echo "<script>alert('Error adding student: Database error occurred.');</script>";
            }
            // Do not exit here, allow the rest of the page to load, so alert is visible
        }
    } else {
        echo "<script>alert('Please provide both Roll No and Student Name for the new student.');</script>";
    }
}


// Get selected date (default to today)
// Use null coalescing operator for conciseness
$selectedDate = $_POST['date'] ?? $_GET['date'] ?? date("Y-m-d");

/**
 * Helper function to get status display text.
 * Note: This function is primarily for the index.php page's dropdown options
 * and the AJAX response lists. The summary.php will have its own display logic.
 * @param string $statusCode The short status code (p, a, hm, he, N/A)
 * @return string The full display text.
 */
function getStatusDisplayText($statusCode) {
    switch ($statusCode) {
        case 'p': return 'Present';
        case 'a': return 'Absent';
        case 'hm': return 'Half Day Morning';
        case 'he': return 'Half Day Evening';
        case 'N/A': return 'N/A'; // For cases where no record exists
        default: return $statusCode; // Fallback
    }
}


// ---------- Handle AJAX Request (for live updates of individual students) ----------
// Check for AJAX request header and required POST parameters
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' &&
    isset($_POST['roll']) && isset($_POST['status_value']) && isset($_POST['current_date'])
) {
    $roll = $_POST['roll'];
    $status_value = $_POST['status_value'];
    $date_value = $_POST['current_date'];

    // Validate the incoming status value
    if (!in_array($status_value, ['p', 'a', 'hm', 'he'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value provided.']);
        exit;
    }

    // Update/Insert attendance for the specific student and date using prepared statements
    try {
        $stmt = $conn->prepare("REPLACE INTO attendance (roll, date, status) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $roll, $date_value, $status_value);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("AJAX attendance update failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error during update.']);
        exit;
    }

    // Recalculate counts and lists for the current date for the AJAX response
    $counts = [
        'absent' => 0,
        'half_day_morning' => 0,
        'half_day_evening' => 0,
        'absent_list' => '',
        'half_day_morning_list' => '',
        'half_day_evening_list' => ''
    ];

    $absentStudents = [];
    $halfDayMorningStudents = [];
    $halfDayEveningStudents = [];

    try {
        $stmt = $conn->prepare("SELECT s.roll, s.name, a.status FROM student s LEFT JOIN attendance a ON s.roll = a.roll AND a.date = ? ORDER BY s.roll");
        $stmt->bind_param("s", $date_value);
        $stmt->execute();
        $attendanceQueryResult = $stmt->get_result();

        while ($row = $attendanceQueryResult->fetch_assoc()) {
            if ($row['status'] == 'a') {
                $counts['absent']++;
                $absentStudents[] = $row;
            } elseif ($row['status'] == 'hm') {
                $counts['half_day_morning']++;
                $halfDayMorningStudents[] = $row;
            } elseif ($row['status'] == 'he') {
                $counts['half_day_evening']++;
                $halfDayEveningStudents[] = $row;
            }
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("AJAX summary fetch failed: " . $e->getMessage());
        // For AJAX, if counts fail, we still want to indicate the main update was successful
        // A more robust system would fetch counts via a separate AJAX call if they are critical
    }


    // Build HTML for lists
    if (!empty($absentStudents)) {
        $counts['absent_list'] .= '<h3>Absent Students List:</h3><ul>';
        foreach ($absentStudents as $s) {
            $counts['absent_list'] .= '<li>' . htmlspecialchars($s['roll']) . ' - ' . htmlspecialchars($s['name']) . '</li>';
        }
        $counts['absent_list'] .= '</ul>';
    }

    if (!empty($halfDayMorningStudents)) {
        $counts['half_day_morning_list'] .= '<h3>Half Day Morning Leave Students List:</h3><ul>';
        foreach ($halfDayMorningStudents as $s) {
            $counts['half_day_morning_list'] .= '<li>' . htmlspecialchars($s['roll']) . ' - ' . htmlspecialchars($s['name']) . '</li>';
        }
        $counts['half_day_morning_list'] .= '</ul>';
    }

    if (!empty($halfDayEveningStudents)) {
        $counts['half_day_evening_list'] .= '<h3>Half Day Evening Leave Students List:</h3><ul>';
        foreach ($halfDayEveningStudents as $s) {
            $counts['half_day_evening_list'] .= '<li>' . htmlspecialchars($s['roll']) . ' - ' . htmlspecialchars($s['name']) . '</li>';
        }
        $counts['half_day_evening_list'] .= '</ul>';
    }

    // Send JSON response and exit
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'counts' => $counts]);
    exit;
}

// ---------- Handle Full Form Submission (if not AJAX and update button is clicked) ----------
// This block processes the form when the "Update Attendance" button is clicked or date changed
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !isset($_POST['add_student'])) {
    $logFile = fopen("attendance_log.txt", "a");
    if ($logFile === false) {
        error_log("Could not open attendance_log.txt for writing.");
    }
    $timestamp = date("Y-m-d H:i:s");

    foreach ($_POST['status'] as $roll => $value) {
        // Validate status value to prevent unexpected data
        if (!in_array($value, ['p', 'a', 'hm', 'he'])) {
            error_log("Invalid status value received for roll $roll: $value");
            continue; // Skip invalid entries
        }

        try {
            // Using prepared statement for full form submission
            $stmt = $conn->prepare("REPLACE INTO attendance (roll, date, status) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $roll, $selectedDate, $value);
            $stmt->execute();
            $stmt->close();
            if ($logFile) {
                fwrite($logFile, "[$timestamp] Date: $selectedDate | Roll: " . $roll . " | Status: " . $value . "\n");
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Full form attendance update failed for roll $roll: " . $e->getMessage());
            // Optionally, accumulate errors and display to user
        }
    }

    if ($logFile) {
        fclose($logFile);
    }
    echo "<script>alert('Attendance Updated for " . htmlspecialchars($selectedDate) . "');</script>";
    // Redirect to self to clear POST data and show updated view
    header("Location: " . $_SERVER['PHP_SELF'] . "?date=" . urlencode($selectedDate));
    exit;
}

// ---------- Fetch Students and Initial Attendance Data for Page Load ----------
$allStudents = [];
try {
    $students_query_result = $conn->query("SELECT * FROM student ORDER BY roll");
    if ($students_query_result) {
        $allStudents = $students_query_result->fetch_all(MYSQLI_ASSOC);
        $students_query_result->free(); // Free result set
    }
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching students: " . $e->getMessage());
    die("Error loading student data.");
}


// Fetch current attendance status for the selected date to populate dropdowns
$attendanceData = [];
try {
    $stmt = $conn->prepare("SELECT roll, status FROM attendance WHERE date = ?");
    $stmt->bind_param("s", $selectedDate);
    $stmt->execute();
    $current_attendance_result = $stmt->get_result();
    while ($row = $current_attendance_result->fetch_assoc()) {
        $attendanceData[$row['roll']] = $row['status'];
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching current attendance: " . $e->getMessage());
    // Continue with empty attendanceData, table will show 'Present'
}

// Recalculate counts based on fetched attendance data for initial page load
$absentCount = 0;
$halfDayMorningCount = 0;
$halfDayEveningCount = 0;

$absentStudentsList = [];
$halfDayMorningStudentsList = [];
$halfDayEveningStudentsList = [];

foreach ($allStudents as $student) {
    $roll = $student['roll'];
    $name = $student['name'];
    $status = $attendanceData[$roll] ?? 'p'; // Default to present if no record

    if ($status == 'a') {
        $absentCount++;
        $absentStudentsList[] = ['roll' => $roll, 'name' => $name];
    } elseif ($status == 'hm') {
        $halfDayMorningCount++;
        $halfDayMorningStudentsList[] = ['roll' => $roll, 'name' => $name];
    } elseif ($status == 'he') {
        $halfDayEveningCount++;
        $halfDayEveningStudentsList[] = ['roll' => $roll, 'name' => $name];
    }
}

// Build initial HTML for lists
$initialAbsentListHtml = '';
if (!empty($absentStudentsList)) {
    $initialAbsentListHtml .= '<h3>Absent Students List:</h3><ul>';
    foreach ($absentStudentsList as $s) {
        $initialAbsentListHtml .= '<li>' . htmlspecialchars($s['roll']) . ' - ' . htmlspecialchars($s['name']) . '</li>';
    }
    $initialAbsentListHtml .= '</ul>';
}

$initialHalfDayMorningListHtml = '';
if (!empty($halfDayMorningStudentsList)) {
    $initialHalfDayMorningListHtml .= '<h3>Half Day Morning Leave Students List:</h3><ul>';
    foreach ($halfDayMorningStudentsList as $s) {
        $initialHalfDayMorningListHtml .= '<li>' . htmlspecialchars($s['roll']) . ' - ' . htmlspecialchars($s['name']) . '</li>';
    }
    $initialHalfDayMorningListHtml .= '</ul>';
}

$initialHalfDayEveningListHtml = '';
if (!empty($halfDayEveningStudentsList)) {
    $initialHalfDayEveningListHtml .= '<h3>Half Day Evening Leave Students List:</h3><ul>';
    foreach ($halfDayEveningStudentsList as $s) {
        $initialHalfDayEveningListHtml .= '<li>' . htmlspecialchars($s['roll']) . ' - ' . htmlspecialchars($s['name']) . '</li>';
    }
    $initialHalfDayEveningListHtml .= '</ul>';
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* General Body Styling */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background-color: #eef2f7; /* Light blue-gray background */
            color: #333;
            line-height: 1.6;
            margin: 0;
        }

        /* Headings */
        h2, h3 {
            color: #2c3e50; /* Dark blue-gray for headings */
            margin-top: 30px;
            margin-bottom: 15px;
            text-align: center;
        }

        h2 {
            font-size: 2.2em;
            border-bottom: 2px solid #3498db; /* Blue underline */
            padding-bottom: 10px;
            display: inline-block; /* To make border-bottom fit text */
            margin-left: auto;
            margin-right: auto;
            display: block; /* To center the inline-block */
            max-width: fit-content;
        }

        h3 {
            font-size: 1.5em;
            color: #34495e; /* Slightly lighter heading color */
        }

        /* Form and Inputs */
        form {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: 30px auto;
        }

        #addStudentForm {
            margin-top: 40px;
            background-color: #f8fcfd; /* Lighter background for this form */
            border: 1px solid #d4eaf7; /* Light border */
            padding: 25px;
        }

        label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 10px;
        }

        input[type="date"],
        select,
        input[type="text"] { /* Added text input here */
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1em;
            width: calc(100% - 24px); /* Full width minus padding */
            margin-bottom: 15px;
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
            -webkit-appearance: none; /* Remove default arrow for selects */
            -moz-appearance: none;
            appearance: none;
            background-color: #f8f8f8;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input[type="date"]:focus,
        select:focus,
        input[type="text"]:focus { /* Added text input here */
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            outline: none;
        }

        /* Custom arrow for select */
        select {
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cpath%20fill%3D%22%23333%22%20d%3D%22M201.999%2099.999L128%20173.999l-73.999-74%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
            padding-right: 30px; /* Make space for the custom arrow */
        }

        /* Table Styling */
        table {
            border-collapse: collapse;
            width: 100%;
            max-width: 850px;
            margin: 25px auto;
            background-color: #ffffff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-radius: 8px;
            overflow: hidden; /* Ensures rounded corners apply to content */
        }

        th, td {
            border: 1px solid #e0e0e0; /* Lighter border for a softer look */
            padding: 12px 15px;
            text-align: left; /* Align text to left for better readability */
        }

        th {
            background-color: #3498db; /* Blue header */
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9; /* Subtle striping */
        }

        tr:hover {
            background-color: #f0f0f0; /* Light hover effect */
        }

        /* Select within table cells */
        td select {
            width: 100%;
            padding: 8px;
            margin-bottom: 0; /* Override default margin */
            font-size: 0.95em;
        }

        /* Button Group */
        .button-group {
            margin-top: 30px;
            display: flex;
            justify-content: center; /* Center buttons */
            gap: 15px; /* Space between buttons */
            flex-wrap: wrap; /* Allow buttons to wrap on smaller screens */
        }

        button, input[type="submit"] {
            background-color: #28a745; /* Green for update */
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-decoration: none; /* For button links */
            display: inline-flex; /* For consistent alignment */
            align-items: center;
            justify-content: center;
        }

        button:hover, input[type="submit"]:hover {
            background-color: #218838; /* Darker green on hover */
            transform: translateY(-2px); /* Slight lift effect */
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .clear-btn {
            background-color: #ffc107; /* Yellow for clear */
            color: #333;
        }

        .clear-btn:hover {
            background-color: #e0a800; /* Darker yellow on hover */
        }

        .button-link button {
            background-color: #007bff; /* Blue for view details */
        }

        .button-link button:hover {
            background-color: #0056b3; /* Darker blue on hover */
        }

        /* Attendance Counts Section */
        #attendanceCounts {
            margin-top: 40px;
            padding: 20px;
            background-color: #f0f8ff; /* Very light blue */
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        #attendanceCounts h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.2em;
            color: #444;
        }

        #attendanceCounts span {
            font-weight: bold;
            color: #007bff; /* Bright blue for counts */
            font-size: 1.3em;
        }

        /* Attendance Lists Section */
        #attendanceLists {
            margin-top: 30px;
            display: flex;
            flex-wrap: wrap; /* Allow lists to wrap */
            gap: 25px; /* Space between list containers */
            justify-content: center; /* Center the list blocks */
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        #attendanceLists > div {
            flex: 1; /* Each list container takes equal space */
            min-width: 280px; /* Minimum width for responsiveness */
            padding: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            box-sizing: border-box; /* Important for flex-basis with padding */
        }

        #attendanceLists h3 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.3em;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        #attendanceLists ul {
            list-style-type: none; /* Remove default bullets */
            padding-left: 0;
            margin: 0;
        }

        #attendanceLists li {
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
            font-size: 0.95em;
            color: #555;
        }

        #attendanceLists li:last-child {
            margin-bottom: 0;
        }

        /* Custom bullet points for lists */
        #attendanceLists li::before {
            content: "\2022"; /* Unicode for a bullet point */
            color: #3498db; /* Blue bullet */
            font-weight: bold;
            display: inline-block;
            width: 1em;
            margin-left: -1em;
            position: absolute;
            left: 0;
        }

        /* Status Colors for Select Elements */
        select.present { color: green; border-color: green; }
        select.absent { color: red; border-color: red; }
        select.halfday-morning { color: #FFA500; border-color: #FFA500; } /* Orange */
        select.halfday-evening { color: #DAA520; border-color: #DAA520; } /* Goldenrod */


        /* Responsive Adjustments */
        @media (max-width: 768px) {
            body { padding: 15px; }
            h2 { font-size: 1.8em; }
            h3 { font-size: 1.3em; }
            form, table { margin: 20px auto; padding: 20px; }
            th, td { padding: 10px; }
            .button-group { flex-direction: column; align-items: stretch; gap: 10px; }
            button, input[type="submit"] { width: 100%; margin-right: 0; }
            #attendanceLists { flex-direction: column; gap: 15px; }
            #attendanceLists > div { min-width: unset; width: 100%; }
        }
    </style>
    <script>
        // Function to update the color of the select element based on its value
        function updateSelectColor(selectElement) {
            selectElement.classList.remove('present', 'absent', 'halfday-morning', 'halfday-evening');
            if (selectElement.value === 'a') {
                selectElement.classList.add('absent');
            } else if (selectElement.value === 'hm') {
                selectElement.classList.add('halfday-morning');
            } else if (selectElement.value === 'he') {
                selectElement.classList.add('halfday-evening');
            } else {
                selectElement.classList.add('present'); // Default for 'p'
            }
        }

        // Asynchronously send attendance update for a single student and refresh counts/lists
        async function sendAttendanceUpdate(roll, statusValue, currentDate) {
            const formData = new FormData();
            formData.append('roll', roll);
            formData.append('status_value', statusValue);
            formData.append('current_date', currentDate);

            try {
                const response = await fetch(window.location.href, { // Send to the same PHP script
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Indicate AJAX request
                    },
                    body: formData
                });

                const data = await response.json(); // Parse JSON response

                if (data.success) {
                    // Update counts
                    document.getElementById('absentCount').textContent = data.counts.absent;
                    document.getElementById('halfDayMorningCount').textContent = data.counts.half_day_morning;
                    document.getElementById('halfDayEveningCount').textContent = data.counts.half_day_evening;

                    // Update lists
                    document.getElementById('absentListContainer').innerHTML = data.counts.absent_list;
                    document.getElementById('halfDayMorningListContainer').innerHTML = data.counts.half_day_morning_list;
                    document.getElementById('halfDayEveningListContainer').innerHTML = data.counts.half_day_evening_list;
                } else {
                    console.error('Error updating attendance:', data.message);
                    alert('Error updating attendance: ' + (data.message || 'Unknown error.'));
                }
            } catch (error) {
                console.error('Network error during attendance update:', error);
                
            }
        }

        // Function to clear all attendance for the current date (set to Present)
        async function clearAttendance() {
            if (!confirm('Are you sure you want to clear attendance for this date (set all to Present)?')) {
                return;
            }

            const selects = document.querySelectorAll("select[name^='status']");
            const currentDate = document.querySelector('input[name="date"]').value;
            const updatesPromises = [];
            let studentsCleared = 0;

            // Collect all students who are NOT already present
            const studentsToUpdate = [];
            selects.forEach(select => {
                if (select.value !== 'p') {
                    const roll = select.name.replace('status[', '').replace(']', '');
                    studentsToUpdate.push({select: select, roll: roll});
                }
            });

            if (studentsToUpdate.length > 0) {
                // Perform updates and collect promises
                studentsToUpdate.forEach(student => {
                    student.select.value = 'p'; // Reset value in UI immediately
                    updateSelectColor(student.select); // Update color in UI immediately
                    updatesPromises.push(sendAttendanceUpdate(student.roll, 'p', currentDate));
                    studentsCleared++;
                });

                // Await all updates to complete. This ensures the final count is accurate.
                await Promise.all(updatesPromises);
                alert(`Attendance cleared for ${studentsCleared} students for ${currentDate}.`);
            } else {
                alert('All students are already marked Present for this date.');
            }
        }


        // Initialize on page load
        window.onload = function () {
            // Apply initial colors and set up change listeners for all select elements
            const selects = document.querySelectorAll("select[name^='status']");
            selects.forEach(select => {
                updateSelectColor(select); // Set initial color based on loaded value
                select.addEventListener('change', () => {
                    updateSelectColor(select); // Update color when value changes
                    const roll = select.name.replace('status[', '').replace(']', '');
                    const statusValue = select.value;
                    const currentDate = document.querySelector('input[name="date"]').value;
                    sendAttendanceUpdate(roll, statusValue, currentDate);
                });
            });

            // Add event listener for date change to trigger a full form submission
            const dateInput = document.querySelector('input[name="date"]');
            dateInput.addEventListener('change', () => {
                // Submit the main attendance form normally when date changes to reload the page with new date
                dateInput.closest('form').submit();
            });

             // Event listener for the "Clear Attendance" button
            const clearButton = document.getElementById('clearAttendanceBtn');
            if (clearButton) {
                clearButton.addEventListener('click', clearAttendance);
            }
        };
    </script>
</head>
<body>

    <h2>Student Attendance Register</h2>
    <h3>DICE-2</h3>

    

    <form method="post">
        <label><strong>Select Date:</strong>
            <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" required>
        </label><br><br>


        <table>
            <thead>
                <tr>
                    <th>Roll No</th>
                    <th>Name</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($allStudents)): ?>
                    <?php foreach ($allStudents as $row):
                        $roll = $row['roll'];
                        // Default status is 'p' (Present) if not set in attendanceData
                        $status = $attendanceData[$roll] ?? 'p';
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($roll) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td>
                                <select name="status[<?= htmlspecialchars($roll) ?>]">
                                    <option value="p" <?= ($status === 'p') ? 'selected' : '' ?>>Present</option>
                                    <option value="a" <?= ($status === 'a') ? 'selected' : '' ?>>Absent</option>
                                    <option value="hm" <?= ($status === 'hm') ? 'selected' : '' ?>>Half Day Morning</option>
                                    <option value="he" <?= ($status === 'he') ? 'selected' : '' ?>>Half Day Evening</option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No students found. Please add students using the form above.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table><br>

        <div class="button-group">
            <input type="submit" value="Update Attendance">
            <button type="button" id="clearAttendanceBtn" class="clear-btn">Clear Attendance (All Present)</button>
            <a href="summary.php" class="button-link">
            <button type="button">View Attendance Summary</button>
            </a>
            <a href="students.php" class="button-link">
            <button type="button">Edit students</button> 
            </a>
        </div>
    </form>

    <div id="attendanceCounts">
        <h3>Total Absent Students on <?= htmlspecialchars($selectedDate) ?>: <span id="absentCount"><?= $absentCount ?></span></h3>
    </div>

    <div id="attendanceLists">
        <div id="absentListContainer">
            <?= $initialAbsentListHtml ?>
        </div>

        <div id="halfDayMorningListContainer">
            <?= $initialHalfDayMorningListHtml ?>
        </div>

        <div id="halfDayEveningListContainer">
            <?= $initialHalfDayEveningListHtml ?>
        </div>
    </div>

</body>
</html>

<?php
$conn->close();
?>
