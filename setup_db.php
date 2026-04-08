<?php
require_once 'config/database.php';

$sql = "
-- Tabel users
CREATE TABLE IF NOT EXISTS users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- Tabel rooms
CREATE TABLE IF NOT EXISTS rooms (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    description TEXT,
    image_main VARCHAR(255),
    image_wardrobe VARCHAR(255),
    image_bed VARCHAR(255),
    image_kitchen VARCHAR(255),
    image_bathroom VARCHAR(255),
    image_other VARCHAR(255),
    price_per_day DECIMAL(10,2) DEFAULT 0.00,
    price_per_week DECIMAL(10,2) DEFAULT 0.00,
    price_per_month DECIMAL(10,2) DEFAULT 0.00,
    price_per_year DECIMAL(10,2) DEFAULT 0.00,
    floor INT(11) DEFAULT 1,
    PRIMARY KEY (id)
);

-- Tabel bookings
CREATE TABLE IF NOT EXISTS bookings (
    id INT(11) NOT NULL AUTO_INCREMENT,
    room_id INT(11) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    guests INT(11) NOT NULL,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash',
    payment_details TEXT,
    payment_proof VARCHAR(255),
    PRIMARY KEY (id),
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Tabel messages
CREATE TABLE IF NOT EXISTS messages (
    id INT(11) NOT NULL AUTO_INCREMENT,
    sender_id INT(11) NOT NULL,
    receiver_id INT(11) NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    sender_type VARCHAR(50),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);
";

// Execute Schema Creation
if ($conn->multi_query($sql)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Tables created successfully.<br>";
} else {
    echo "Error creating tables: " . $conn->error . "<br>";
}

// Clean previous data to avoid duplicates if re-running (Optional but good for clean state)
// $conn->query("TRUNCATE TABLE messages");
// $conn->query("TRUNCATE TABLE bookings");
// $conn->query("TRUNCATE TABLE rooms");
// $conn->query("DELETE FROM users WHERE id IN (1,2,3,4)");

// Insert Data
// We use INSERT IGNORE to avoid errors if data exists
$data_sql = "
INSERT IGNORE INTO users (id, username, password, role, created_at) VALUES
(1, 'admin',    '\$2y$12\$aZnG4Na2gcqVMD51iSBD.OyR7XlnUBx5JftfikfYnk0QeUvjSnVoy', 'admin', '2025-09-22 09:02:55'),
(2, 'udin',     '\$2y$12\$Fcp2/zjOM53niHbUBgy4vOzhlnz/B2n9HhjnD1WD/3dh8GlqjvzQO', 'user',  '2025-09-22 12:40:21'),
(3, 'deo123',   'y2.WmxLT6cxOt8Iv2Em6RslcjnA6oUS8fy',                           'user',  '2025-09-27 12:54:52'),
(4, 'bagas123', '\$2y$12\$/vYJKFKD1OMhFLDozTvivuic6Fr3PItEtYOc1tdyZq0NU.vtSPxZG', 'user',  '2025-09-30 10:25:13');

INSERT IGNORE INTO rooms (id, name, location, description, image_main, image_wardrobe, image_bed, image_kitchen, image_bathroom, image_other, price_per_day, price_per_week, price_per_month, price_per_year, floor) VALUES
(1,  'Kamar Kos 1',  'Lokasi strategis', 'Kamar kos ini menawarkan kenyamanan maksimal.', 'image/4.jpg', 'image/lemari.jpg', 'image/5.jpg', 'image/dapur.jpg', 'image/mandi.jpg', 'image/3.jpg', 100000.00, 600000.00, 2000000.00, 20000000.00, 1),
(2,  'Kamar Kos 2',  'Lokasi strategis', 'Kamar kos ini menawarkan kenyamanan maksimal.', 'image/4.jpg', 'image/lemari.jpg', 'image/5.jpg', 'image/dapur.jpg', 'image/mandi.jpg', 'image/3.jpg', 100000.00, 600000.00, 2000000.00, 20000000.00, 1),
(3,  'Kamar Kos 3',  'Lokasi strategis', 'Kamar kos ini menawarkan kenyamanan maksimal.', 'image/4.jpg', 'image/lemari.jpg', 'image/5.jpg', 'image/dapur.jpg', 'image/mandi.jpg', 'image/3.jpg', 100000.00, 600000.00, 2000000.00, 20000000.00, 1);

INSERT IGNORE INTO bookings (id, room_id, user_name, check_in_date, check_out_date, guests, status, created_at, payment_method, payment_details, payment_proof) VALUES
(10, 2,  'deo bagas',    '2025-09-24', '2025-10-01', 1, 'Confirmed', '2025-09-24 08:45:10', 'Cash', '', NULL);
";

if ($conn->multi_query($data_sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Data inserted successfully.<br>";
} else {
    echo "Error inserting data: " . $conn->error . "<br>";
}

echo "Database setup completed with User Schema.";
?>
