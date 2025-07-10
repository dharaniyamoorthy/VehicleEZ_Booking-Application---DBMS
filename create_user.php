<?php
include 'db_connection.php';

// Function to create users
function createUser($conn, $user_id, $username, $password, $is_admin = false) {
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = $is_admin ? 'admin' : 'customer';
    
    // Insert into users table
    $sql = "INSERT INTO users (user_id, username, password, role) VALUES (?, ?, ?, ?)";
    
    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $user_id, $username, $hashed_password, $role);
    
    if ($stmt->execute()) {
        echo "User $username created successfully as $role!<br>";
    } else {
        echo "Error creating user $username: " . $stmt->error . "<br>";
    }
    
    $stmt->close();
}

// Create regular user
createUser($conn, '021', 'Dharaniya', 'papi');

// Create admin user
createUser($conn, '07', 'Dharaniya Moorthy', 'papi@9363', true);

$conn->close();
?>
