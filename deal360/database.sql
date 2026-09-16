CREATE DATABASE IF NOT EXISTS deal360 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE deal360;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  mobile VARCHAR(15) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('buyer','seller','worker','admin') DEFAULT 'buyer',
  email_verified TINYINT(1) DEFAULT 0,
  mobile_verified TINYINT(1) DEFAULT 0,
  email_otp VARCHAR(6) NULL,
  mobile_otp VARCHAR(6) NULL,
  otp_expiry DATETIME NULL,
  otp_attempts TINYINT DEFAULT 0,
  otp_sent_at DATETIME NULL,
  reset_otp VARCHAR(6) NULL,
  reset_otp_expiry DATETIME NULL,
  reset_attempts TINYINT DEFAULT 0,
  notify_emails TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
);

CREATE TABLE plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(50) NOT NULL,
  user_type ENUM('buyer','seller') NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  views_limit INT DEFAULT 0,
  daily_reset TINYINT(1) DEFAULT 1,
  ads_limit INT DEFAULT 0,
  featured_limit INT DEFAULT 0,
  can_enquire TINYINT(1) DEFAULT 0,
  UNIQUE KEY uq (code, user_type)
);

CREATE TABLE subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  user_type ENUM('buyer','seller') NOT NULL,
  plan_code VARCHAR(30) NOT NULL,
  views_used INT DEFAULT 0,
  ads_used INT DEFAULT 0,
  featured_used INT DEFAULT 0,
  valid_till DATE NOT NULL,
  status ENUM('active','expired') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE listings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  segment ENUM('property','vehicles','electronics') NOT NULL,
  category VARCHAR(50) NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  price DECIMAL(12,2) NOT NULL,
  price_unit VARCHAR(20) DEFAULT '',
  city VARCHAR(60),
  locality VARCHAR(100),
  pincode VARCHAR(10),
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  is_featured TINYINT(1) DEFAULT 0,
  is_featured_until DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_status_time (status, created_at)
);

CREATE TABLE listing_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  field_name VARCHAR(60) NOT NULL,
  field_value VARCHAR(255) NOT NULL,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

CREATE TABLE listing_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) DEFAULT 0,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

CREATE TABLE view_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  listing_id INT NOT NULL,
  viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_listing_time (listing_id, viewed_at),
  INDEX idx_user_time (user_id, viewed_at),
  INDEX idx_viewed (viewed_at)
);

CREATE TABLE enquiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  buyer_id INT NOT NULL,
  message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  INDEX idx_listing (listing_id)
);

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  user_type ENUM('buyer','seller') NOT NULL,
  plan_code VARCHAR(30) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  purpose ENUM('subscription','boost') DEFAULT 'subscription',
  meta VARCHAR(255) NULL,
  txn_id VARCHAR(100),
  rzp_order_id VARCHAR(64) NULL,
  rzp_payment_id VARCHAR(64) NULL,
  rzp_signature VARCHAR(200) NULL,
  status ENUM('pending','paid','failed') DEFAULT 'paid',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rzp_order (rzp_order_id),
  INDEX idx_paid_time (status, created_at)
);

CREATE TABLE workers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category VARCHAR(50) NOT NULL,
  rate_type ENUM('hour','day','job') NOT NULL,
  rate DECIMAL(10,2) NOT NULL,
  negotiable TINYINT(1) DEFAULT 0,
  experience VARCHAR(30),
  service_radius_km INT DEFAULT 5,
  availability VARCHAR(30),
  bio VARCHAR(500) DEFAULT NULL,
  city VARCHAR(60) DEFAULT NULL,
  pincode VARCHAR(10) DEFAULT NULL,
  rating_avg DECIMAL(2,1) DEFAULT 0,
  rating_count INT DEFAULT 0,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  id_proof VARCHAR(255),
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NULL,
  buyer_id INT NOT NULL,
  seller_id INT NOT NULL,
  worker_id INT NULL,
  last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_conv (listing_id, buyer_id),
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id) REFERENCES users(id),
  FOREIGN KEY (seller_id) REFERENCES users(id),
  INDEX idx_listing (listing_id)
);

CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender_id INT NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id)
);

CREATE TABLE worker_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NOT NULL,
  user_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment VARCHAR(500),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rev (worker_id, user_id),
  FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE boost_packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  days INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE featured_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  user_id INT NOT NULL,
  package_id INT NULL,
  source ENUM('package','plan_credit') NOT NULL,
  amount DECIMAL(10,2) DEFAULT 0,
  days INT NOT NULL,
  starts_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  txn_id VARCHAR(100),
  status ENUM('active','expired') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  INDEX idx_listing (listing_id)
);

CREATE TABLE email_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  email VARCHAR(150) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  type VARCHAR(40) NOT NULL,
  status ENUM('sent','failed','dev_only') NOT NULL,
  error_text VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_throttle (user_id, type, created_at),
  INDEX idx_type_time (type, created_at)
);

CREATE TABLE cron_runs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trigger_type VARCHAR(20) NOT NULL,
  tasks_done INT NOT NULL,
  results TEXT,
  duration_ms INT NOT NULL,
  ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE view_daily_stats (
  stat_date DATE NOT NULL,
  listing_id INT NOT NULL,
  views INT NOT NULL,
  PRIMARY KEY (stat_date, listing_id),
  INDEX idx_listing (listing_id)
);

INSERT INTO plans (code,name,user_type,price,views_limit,daily_reset,ads_limit,featured_limit,can_enquire) VALUES
('free','Free','buyer',0,5,1,0,0,0),
('silver','Silver','buyer',99,20,1,0,0,0),
('gold','Gold','buyer',299,50,1,0,0,1),
('platinum','Platinum','buyer',599,0,0,0,0,1),
('free','Free','seller',0,0,0,2,0,0),
('starter','Starter','seller',99,0,0,5,1,0),
('growth','Growth','seller',299,0,0,20,3,0),
('pro','Pro','seller',599,0,0,50,10,0),
('unlimited','Unlimited','seller',999,0,0,0,0,0);

INSERT INTO boost_packages (name, days, price) VALUES
('3-Day Boost',3,49.00),('7-Day Boost',7,89.00),('15-Day Boost',15,149.00),('30-Day Mega Boost',30,249.00);