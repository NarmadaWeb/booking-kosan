<?php
require_once 'config/database.php';

$sql = "ALTER TABLE bookings 
        ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) DEFAULT NULL";

if ($conn->query($sql) === TRUE) {
    echo "Table 'bookings' updated successfully. Added 'full_name' column.";
} else {
    echo "Error updating table: " . $conn->error;
}
?>
