<?php
// Database connection details
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "attendance_db";

// Create a new database connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    // If connection fails, terminate script and display error
    die("Connection failed: " . $conn->connect_error);
}

/**
 * Sanitizes input data to prevent XSS.
 * Removes whitespace, strips slashes, and converts special characters to HTML entities.
 * @param string $data The input string to sanitize.
 * @return string The sanitized string.
 */
function sanitize_input($data) {
    $data = trim($data); // Remove whitespace from the beginning and end of string
    $data = stripslashes($data); // Remove backslashes
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); // Convert special characters to HTML entities
    return $data;
}

// --- Handle Form Submissions ---

// Logic for adding a new student
if (isset($_POST['add'])) {
    // Sanitize and retrieve input data
    $roll = sanitize_input($_POST['roll']);
    $name = sanitize_input($_POST['name']);

    // Prepare an SQL statement to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO students (roll, name) VALUES (?, ?)");

    if ($stmt) {
        // Bind parameters to the prepared statement ('ss' indicates two string parameters)
        $stmt->bind_param("ss", $roll, $name);
        // Execute the statement
        if ($stmt->execute()) {
            // Success message (optional, can be improved with redirects/session messages)
            // echo "<p style='color: green;'>Student added successfully!</p>";
        } else {
            // Error message if execution fails
            echo "<p style='color: red;'>Error adding student: " . $stmt->error . "</p>";
        }
        $stmt->close(); // Close the prepared statement
    } else {
        // Error message if statement preparation fails
        echo "<p style='color: red;'>Error preparing statement: " . $conn->error . "</p>";
    }
}

// Logic for updating an existing student's name
// The button name for edit is now 'edit'
if (isset($_POST['edit'])) {
    // Sanitize and retrieve input data
    $roll = sanitize_input($_POST['roll']);
    $name = sanitize_input($_POST['name']);

    // Prepare an SQL statement
    $stmt = $conn->prepare("UPDATE students SET name=? WHERE roll=?");

    if ($stmt) {
        // Bind parameters ('ss' indicates two string parameters)
        $stmt->bind_param("ss", $name, $roll);
        // Execute the statement
        if ($stmt->execute()) {
            // Success
            // echo "<p style='color: green;'>Student updated successfully!</p>";
        } else {
            // Error message
            echo "<p style='color: red;'>Error updating student: " . $stmt->error . "</p>";
        }
        $stmt->close(); // Close the prepared statement
    } else {
        // Error message
        echo "<p style='color: red;'>Error preparing statement: " . $conn->error . "</p>";
    }
}

// Logic for deleting a student
if (isset($_POST['delete'])) {
    // Sanitize and retrieve input data
    $roll = sanitize_input($_POST['roll']);

    // First, delete related records from the 'attendance' table (if foreign key constraints exist)
    $stmt_att = $conn->prepare("DELETE FROM attendance WHERE roll=?");
    if ($stmt_att) {
        $stmt_att->bind_param("s", $roll); // 's' for one string parameter
        if (!$stmt_att->execute()) {
            echo "<p style='color: red;'>Error deleting attendance records: " . $stmt_att->error . "</p>";
        }
        $stmt_att->close();
    } else {
        echo "<p style='color: red;'>Error preparing attendance delete statement: " . $conn->error . "</p>";
    }

    // Then, delete the student from the 'students' table
    $stmt_stu = $conn->prepare("DELETE FROM students WHERE roll=?");
    if ($stmt_stu) {
        $stmt_stu->bind_param("s", $roll); // 's' for one string parameter
        if ($stmt_stu->execute()) {
            // Success
            // echo "<p style='color: green;'>Student deleted successfully!</p>";
        } else {
            // Error message
            echo "<p style='color: red;'>Error deleting student: " . $stmt_stu->error . "</p>";
        }
        $stmt_stu->close(); // Close the prepared statement
    } else {
        echo "<p style='color: red;'>Error preparing student delete statement: " . $conn->error . "</p>";
    }
}

// --- Fetch Students for Display ---
// Query to select all students, ordered by roll number
$students_result = $conn->query("SELECT * FROM students ORDER BY roll");

// Check if the query was successful
if (!$students_result) {
    echo "<p style='color: red;'>Error fetching students: " . $conn->error . "</p>";
    // Create an empty mysqli_result object to prevent errors in the while loop if query failed
    $students_result = new mysqli_result();
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
    <style>
        /* Basic body and typography styling */
        body {
            font-family: 'Inter', Arial, sans-serif; /* Using Inter font as per instructions */
            padding: 20px;
            background-color: #f8f9fa; /* Light background */
            color: #343a40; /* Darker text for readability */
            line-height: 1.6;
        }

        /* Headings styling */
        h2, h3 {
            color: #007bff; /* Primary blue color */
            margin-bottom: 15px;
        }

        /* Form input styling */
        input[type=text] {
            padding: 10px;
            width: 250px; /* Fixed width for consistency */
            margin: 8px 5px 8px 0;
            border: 1px solid #ced4da; /* Light grey border */
            border-radius: 5px; /* Rounded corners */
            box-sizing: border-box; /* Include padding and border in element's total width */
            font-size: 1rem;
        }

        /* General button styling */
        button {
            padding: 10px 20px;
            font-size: 1rem;
            border: none;
            border-radius: 5px; /* Rounded corners */
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease; /* Smooth transitions */
            margin-right: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Subtle shadow */
        }

        button:hover {
            transform: translateY(-1px); /* Slight lift on hover */
        }

        /* Specific button colors */
        .add-btn { background-color: #28a745; color: white; } /* Green */
        .add-btn:hover { background-color: #218838; }

        .edit-btn { background-color: #ffc107; color: #343a40; } /* Yellow */
        .edit-btn:hover { background-color: #e0a800; }

        .delete-btn { background-color: #dc3545; color: white; } /* Red */
        .delete-btn:hover { background-color: #c82333; }

        .back-btn { background-color: #6c757d; color: white; } /* Grey */
        .back-btn:hover { background-color: #5a6268; }

        /* Table styling */
        table {
            border-collapse: collapse;
            width: 90%; /* Responsive width */
            max-width: 900px; /* Max width for larger screens */
            margin-top: 25px;
            background-color: #ffffff; /* White background */
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); /* More prominent shadow */
            border-radius: 8px; /* Rounded table corners */
            overflow: hidden; /* Ensures rounded corners apply to content */
        }

        th, td {
            border: 1px solid #dee2e6; /* Light grey border */
            padding: 12px 15px;
            text-align: left;
        }

        th {
            background-color: #007bff; /* Primary blue header */
            color: white;
            font-weight: bold;
            text-transform: uppercase;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2; /* Zebra striping for rows */
        }

        tr:hover {
            background-color: #e9ecef; /* Highlight row on hover */
        }

        /* Actions column styling */
        td:last-child {
            text-align: center;
            white-space: nowrap; /* Prevent buttons from wrapping */
        }

        /* Link styling for back button */
        a {
            text-decoration: none; /* Remove underline from links */
        }

        /* --- Modal Styles --- */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* High z-index to appear on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.6); /* Black with more opacity */
            display: flex; /* Use flexbox for centering */
            align-items: center; /* Center vertically */
            justify-content: center; /* Center horizontally */
        }

        .modal-content {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px; /* More rounded corners */
            box-shadow: 0 8px 25px rgba(0,0,0,0.25); /* Stronger shadow */
            text-align: center;
            max-width: 400px; /* Max width for modal */
            width: 90%; /* Responsive width */
            animation: fadeIn 0.3s ease-out; /* Fade-in animation */
        }

        .modal-content h4 {
            margin-top: 0;
            color: #343a40;
            font-size: 1.5rem;
            margin-bottom: 20px;
        }

        .modal-content p {
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        .modal-buttons button {
            margin: 0 10px;
            padding: 12px 25px;
            font-size: 1rem;
        }

        /* Animation for modal */
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>
    <h2>Manage Students</h2>

    <!-- Form for adding a new student -->
    <form method="post">
        <input type="text" name="roll" placeholder="Roll No" required aria-label="Roll Number">
        <input type="text" name="name" placeholder="Student Name" required aria-label="Student Name">
        <button type="submit" name="add" class="add-btn">Add Student</button>
    </form>
    
    <!-- Link back to the main attendance page -->
    <a href="index.php"><button class="back-btn">← Back to Attendance</button></a>

    <h3>Existing Students</h3>
    <table>
        <thead>
            <tr>
                <th>Roll No</th>
                <th>Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Loop through each student fetched from the database
            while ($row = $students_result->fetch_assoc()): 
            ?>
            <tr>
                <!-- Form for each student row to handle edit/delete actions -->
                <form method="post" class="student-form">
                    <td>
                        <!-- Hidden input to pass the roll number for update/delete -->
                        <input type="hidden" name="roll" value="<?= htmlspecialchars($row['roll']) ?>">
                        <!-- Display the roll number -->
                        <?= htmlspecialchars($row['roll']) ?>
                    </td>
                    <td>
                        <!-- Input field for student name, pre-filled with current name -->
                        <input type="text" name="name" value="<?= htmlspecialchars($row['name']) ?>" required aria-label="Edit Student Name">
                    </td>
                    <td>
                        <!-- Edit button: submits the form with 'edit' action -->
                        <button type="submit" name="edit" class="edit-btn">Edit</button>
                        <!-- Delete button: triggers the custom confirmation modal -->
                        <button type="button" class="delete-btn" data-roll="<?= htmlspecialchars($row['roll']) ?>">Delete</button>
                    </td>
                </form>
            </tr>
            <?php endwhile; ?>
            <?php if ($students_result->num_rows === 0): ?>
                <tr>
                    <td colspan="3" style="text-align: center;">No students found. Add a new student above!</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Custom Confirmation Modal HTML Structure -->
    <div id="deleteConfirmationModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h4>Confirm Deletion</h4>
            <p>Are you sure you want to delete student with Roll No: <span id="modalRollNo" style="font-weight: bold; color: #dc3545;"></span>?</p>
            <div class="modal-buttons">
                <button id="confirmDeleteBtn" class="delete-btn">Confirm</button>
                <button id="cancelDeleteBtn" class="back-btn">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Ensure the DOM is fully loaded before running JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Get all delete buttons that have a 'data-roll' attribute
            const deleteButtons = document.querySelectorAll('.delete-btn[data-roll]');
            // Get the modal elements
            const modal = document.getElementById('deleteConfirmationModal');
            const modalRollNoSpan = document.getElementById('modalRollNo');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            
            let studentRollToDelete = null; // Variable to store the roll number of the student to be deleted

            // Add click event listener to each delete button
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Get the roll number from the 'data-roll' attribute of the clicked button
                    studentRollToDelete = this.dataset.roll;
                    // Display the roll number in the modal
                    modalRollNoSpan.textContent = studentRollToDelete;
                    // Show the modal
                    modal.style.display = 'flex'; // Use flex to center the modal content
                });
            });

            // Add click event listener to the 'Cancel' button in the modal
            cancelDeleteBtn.addEventListener('click', function() {
                modal.style.display = 'none'; // Hide the modal
                studentRollToDelete = null; // Clear the stored roll number
            });

            // Add click event listener to the 'Confirm' button in the modal
            confirmDeleteBtn.addEventListener('click', function() {
                if (studentRollToDelete) {
                    // Create a temporary hidden form to submit the delete request
                    const form = document.createElement('form');
                    form.method = 'post';
                    form.style.display = 'none'; // Hide the form

                    // Create a hidden input for the roll number
                    const rollInput = document.createElement('input');
                    rollInput.type = 'hidden';
                    rollInput.name = 'roll';
                    rollInput.value = studentRollToDelete;
                    form.appendChild(rollInput);

                    // Create a hidden input to signal the delete action to PHP
                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete';
                    deleteInput.value = '1'; // A value to indicate a delete action
                    form.appendChild(deleteInput);

                    // Append the form to the body and submit it
                    document.body.appendChild(form);
                    form.submit();
                }
                modal.style.display = 'none'; // Hide the modal after submission
            });

            // Close the modal if the user clicks outside of the modal content
            window.addEventListener('click', function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                    studentRollToDelete = null;
                }
            });
        });
    </script>
</body>
</html>