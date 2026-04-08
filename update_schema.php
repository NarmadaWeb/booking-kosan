<?php
require_once 'config/database.php';

$sql = "ALTER TABLE bookings 
        ADD COLUMN IF NOT EXISTS total_price DECIMAL(10,2) DEFAULT 0.00,
        ADD COLUMN IF NOT EXISTS duration_type VARCHAR(20) DEFAULT 'Daily'";

if ($conn->query($sql) === TRUE) {
    echo "Table 'bookings' updated successfully.";
} else {
    echo "Error updating table: " . $conn->error;
}
?>
