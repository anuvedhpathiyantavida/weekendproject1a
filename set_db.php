<?php
// set_db.php

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "attendance_db";

// Connect to MySQL
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create Database if not exists
if ($conn->query("CREATE DATABASE IF NOT EXISTS $dbname") === TRUE) {
    echo "Database '$dbname' created or already exists.<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db($dbname);

// Create students table
$sql_students = "CREATE TABLE IF NOT EXISTS students (
    roll VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100)
)";
if ($conn->query($sql_students) === TRUE) {
    echo "Table 'students' created or already exists.<br>";
} else {
    echo "Error creating table 'students': " . $conn->error . "<br>";
}

// Create attendance table
$sql_attendance = "CREATE TABLE IF NOT EXISTS attendance (
    roll VARCHAR(20),
    date DATE,
    status CHAR(1),
    PRIMARY KEY (roll, date),
    FOREIGN KEY (roll) REFERENCES students(roll)
)";
if ($conn->query($sql_attendance) === TRUE) {
    echo "Table 'attendance' created or already exists.<br>";
} else {
    echo "Error creating table 'attendance': " . $conn->error . "<br>";
}

// Insert sample students if empty
$result = $conn->query("SELECT COUNT(*) as count FROM students");
if ($result) { // Check if query was successful
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $sql_insert_students = "INSERT INTO students (roll, name) VALUES
            ('TTC0824001', 'ABHIRAMI P V'),
            ('TTC0824002', 'ADHEENA M'),
            ('TTC0824003', 'ANUNANDHA P'),
            ('TTC0824004', 'DHIYA A K'),
            ('TTC0824005', 'DIYA M'),
            ('TTC0824006', 'SHIKHA M K'),
            ('TTC0824008', 'MUGDHA BALA M'),
            ('TTC0824009', 'AHAMAD RIYAN T'),
            ('TTC0824010', 'ARJUN KRISHNAN N P'),
            ('TTC0824011', 'BRINGESH P'),
            ('TTC0824012', 'DEEPKRISHNA P K'),
            ('TTC0824013', 'NEERAJ A'),
            ('TTC0824014', 'REVANTH S'),
            ('TTC0824015', 'VAISHAKH G K'),
            ('TTC0824016', 'VIDHU MANOHAR'),
            ('TTC0824017', 'ROSHITH K V'),
            ('TTC0824018', 'SANGEERTH SATHYAN'),
            ('TTC0824019', 'YADHUNANDH O T'),
            ('TTC0824020', 'ARJAV ANEESH'),
            ('TTC0824021', 'ASRITHA V'),
            ('TTC0824022', 'ANUVEDH P')
        ";
        if ($conn->query($sql_insert_students) === TRUE) {
            echo "Sample student data inserted successfully.<br>";
        } else {
            echo "Error inserting sample student data: " . $conn->error . "<br>";
        }
    } else {
        echo "Students table already contains data. Skipping sample data insertion.<br>";
    }
} else {
    echo "Error checking student count: " . $conn->error . "<br>";
}

$conn->close();
?>