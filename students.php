<?php
// students.php - Manage student records (Add, Edit, Delete) with AJAX

// ---------- DB Setup ----------
date_default_timezone_set('Asia/Kolkata');
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "attendance_db";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'add') {
        $roll = sanitize_input($_POST['roll']);
        $name = sanitize_input($_POST['name']);
        if (empty($roll) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Roll No. and Name are required.']);
            $conn->close();
            exit;
        }
        try {
            $stmt = $conn->prepare("INSERT INTO students (roll, name) VALUES (?, ?)");
            $stmt->bind_param("ss", $roll, $name);
            $stmt->execute();
            echo json_encode(['success' => true, 'roll' => $roll, 'name' => $name, 'message' => 'Student added successfully!']);
        } catch (mysqli_sql_exception $e) {
            echo json_encode(['success' => false, 'message' => ($e->getCode() == 1062 ? 'Duplicate Roll No.' : 'Database error: ' . $e->getMessage())]);
        } finally {
            if (isset($stmt)) $stmt->close();
            $conn->close();
            exit;
        }
    }

    if ($_POST['action'] === 'edit') {
        $roll = sanitize_input($_POST['roll']);
        $name = sanitize_input($_POST['name']);
        if (empty($roll) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Roll No. and Name are required.']);
            $conn->close();
            exit;
        }
        try {
            $stmt = $conn->prepare("UPDATE students SET name=? WHERE roll=?");
            $stmt->bind_param("ss", $name, $roll);
            $stmt->execute();
            echo json_encode(['success' => $stmt->affected_rows > 0, 'message' => $stmt->affected_rows > 0 ? 'Updated successfully.' : 'No changes made.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        } finally {
            if (isset($stmt)) $stmt->close();
            $conn->close();
            exit;
        }
    }

    if ($_POST['action'] === 'delete') {
        $roll = sanitize_input($_POST['roll']);
        if (empty($roll)) {
            echo json_encode(['success' => false, 'message' => 'Roll No. is required.']);
            $conn->close();
            exit;
        }
        try {
            $conn->begin_transaction();
            $stmt_att = $conn->prepare("DELETE FROM attendance WHERE roll=?");
            $stmt_att->bind_param("s", $roll);
            $stmt_att->execute();
            $stmt_att->close();
            $stmt_stu = $conn->prepare("DELETE FROM students WHERE roll=?");
            $stmt_stu->bind_param("s", $roll);
            $stmt_stu->execute();
            $stmt_stu->close();
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Deleted successfully.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        } finally {
            $conn->close();
            exit;
        }
    }
}

$students_result = $conn->query("SELECT * FROM student ORDER BY roll");
$students = $students_result ? $students_result->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Students</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f4f4; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 800px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        form { display: flex; gap: 10px; justify-content: center; margin-bottom: 20px; }
        input[type="text"] { padding: 10px; width: 150px; }
        button { padding: 10px 15px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .back-to-mark-btn {
            background-color: green; /* Green for "Mark Attendance" */
            margin-right: 25px; /* Space from clear button */
            margin-top: 10px;
        }
        .back-to-mark-btn:hover {
            background-color: #5a6268;
            margin-top: 10px;
        }
        button1, input[type="submit"] {
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
    </style>
</head>
<body>
    <div class="container">
        <h2>Student Management</h2>
        <h2>Add students</h2>

        <form method="POST">
            <input type="text" name="roll" placeholder="Roll No" required>
            <input type="text" name="name" placeholder="Name" required>
            <button type="submit" name="action" value="add">Add</button>
        </form>
        <table>
            <thead>
                <tr><th>Roll No</th><th>Name</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['roll']) ?></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td>
                            <!-- Add Edit/Delete buttons as needed using JS -->
                            <form style="display:inline;" method="POST">
                                <input type="hidden" name="roll" value="<?= htmlspecialchars($s['roll']) ?>">
                                <input type="hidden" name="name" value="<?= htmlspecialchars($s['name']) ?>">
                                <button type="submit" name="action" value="edit">Ok</button>
                            </form>
                            <form style="display:inline;" method="POST">
                                <input type="hidden" name="roll" value="<?= htmlspecialchars($s['roll']) ?>">
                                <button type="submit" name="action" value="delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="index.php" style="text-decoration: none;">
          <center>  <button1 type="submit" class="back-to-mark-btn"> back to Mark Attendance</button></center>
        </a>
    </div>
</body>
</html>
