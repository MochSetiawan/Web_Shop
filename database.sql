-- Create Database
CREATE DATABASE IF NOT EXISTS shopverse;
USE shopverse;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    postal_code VARCHAR(20),
    country VARCHAR(50) DEFAULT 'Indonesia',
    role ENUM('customer', 'vendor', 'admin') DEFAULT 'customer',
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    profile_image VARCHAR(255) DEFAULT 'default.jpg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Vendors Table - FIXED with correct column names
CREATE TABLE vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    shop_name VARCHAR(100) NOT NULL,   -- IMPORTANT: This was causing the issues
    name VARCHAR(100) NOT NULL,        -- ADDED this for compatibility with code
    description TEXT,
    logo VARCHAR(255),
    banner VARCHAR(255),
    location VARCHAR(255),             -- ADDED for compatibility
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    commission_rate DECIMAL(5,2) DEFAULT 10.00,
    balance DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Categories Table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    image VARCHAR(255),
    parent_id INT DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Products Table - FIXED with status default 'active'
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    short_description VARCHAR(255),
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2),
    quantity INT NOT NULL DEFAULT 0,
    sku VARCHAR(50) UNIQUE,
    featured BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active', -- DEFAULT ACTIVE important
    image VARCHAR(255),                          -- ADDED for compatibility
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Product Images Table
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    is_main BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Orders Table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL,
    shipping_amount DECIMAL(10,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    shipping_address TEXT NOT NULL,
    shipping_city VARCHAR(50) NOT NULL,
    shipping_state VARCHAR(50) NOT NULL,
    shipping_postal_code VARCHAR(20) NOT NULL,
    shipping_country VARCHAR(50) NOT NULL DEFAULT 'Indonesia',
    payment_method ENUM('credit_card', 'paypal', 'bank_transfer', 'cash_on_delivery') NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order Items Table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    vendor_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- Cart Table
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, product_id)
);

-- Reviews Table
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved', -- CHANGED to approved by default
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Wishlist Items Table - ADDED
CREATE TABLE wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, product_id)
);

-- Vendor Reviews Table - ADDED
CREATE TABLE vendor_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Coupons Table
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    type ENUM('percentage', 'fixed') NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) DEFAULT 0,
    starts_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    max_uses INT DEFAULT NULL,
    used_count INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, email, password, full_name, role) VALUES 
('admin', 'admin@shopverse.com', '$2y$10$sLPzqA6.0FpKn9Z7OwqhOOLURDSUVSPQKUZgeh3Wpt5JaJkkL1P2W', 'Admin User', 'admin');
-- Default password: admin123

-- Insert sample users
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('johndoe', 'john@example.com', '$2y$10$sLPzqA6.0FpKn9Z7OwqhOOLURDSUVSPQKUZgeh3Wpt5JaJkkL1P2W', 'John Doe', 'customer', 'active'),
('janesmith', 'jane@example.com', '$2y$10$sLPzqA6.0FpKn9Z7OwqhOOLURDSUVSPQKUZgeh3Wpt5JaJkkL1P2W', 'Jane Smith', 'vendor', 'active'),
('bobwilson', 'bob@example.com', '$2y$10$sLPzqA6.0FpKn9Z7OwqhOOLURDSUVSPQKUZgeh3Wpt5JaJkkL1P2W', 'Bob Wilson', 'vendor', 'active');
-- Default password: admin123

-- Insert sample categories
INSERT INTO categories (name, slug, description, status) VALUES
('Electronics', 'electronics', 'Electronic devices and gadgets', 'active'),
('Fashion', 'fashion', 'Clothing, shoes, and accessories', 'active'),
('Home & Living', 'home-living', 'Home decor and furniture', 'active'),
('Books & Stationery', 'books-stationery', 'Books, stationery, and office supplies', 'active'),
('Beauty & Health', 'beauty-health', 'Beauty products and health supplements', 'active'),
('Sports & Outdoors', 'sports-outdoors', 'Sports equipment and outdoor gear', 'active'),
('Toys & Kids', 'toys-kids', 'Toys and children products', 'active');

-- Insert sample vendors WITH BOTH name AND shop_name
INSERT INTO vendors (user_id, name, shop_name, description, location, status) VALUES
(2, 'Jane\'s Shop', 'Jane\'s Shop', 'Quality electronics and gadgets', 'Jakarta, Indonesia', 'active'),
(3, 'Bob\'s Emporium', 'Bob\'s Emporium', 'Fashion items for all ages', 'Surabaya, Indonesia', 'active');

-- Insert sample products with all required fields
INSERT INTO products (vendor_id, category_id, name, slug, description, short_description, price, sale_price, quantity, featured, status, image) VALUES
(1, 1, 'Wireless Headphones', 'wireless-headphones', 'High quality wireless headphones with noise cancellation.', 'Premium wireless headphones', 129.99, 99.99, 50, TRUE, 'active', 'headphones-1.jpg'),
(1, 1, 'Smartphone X', 'smartphone-x', 'Latest smartphone with advanced features and high-resolution camera.', 'Latest smartphone model', 899.99, NULL, 20, TRUE, 'active', 'smartphone-1.jpg'),
(2, 2, 'Men\'s Casual Shirt', 'mens-casual-shirt', 'Comfortable casual shirt made from 100% cotton.', 'Comfortable casual shirt', 39.99, 29.99, 100, FALSE, 'active', 'shirt-1.jpg'),
(2, 2, 'Women\'s Dress', 'womens-dress', 'Elegant dress for special occasions.', 'Elegant women\'s dress', 79.99, 59.99, 30, TRUE, 'active', 'dress-1.jpg');