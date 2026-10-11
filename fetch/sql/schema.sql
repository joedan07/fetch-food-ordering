-- Fetch — database schema (MySQL / InnoDB)
-- Run through install.php, or import this file in phpMyAdmin.

DROP TABLE IF EXISTS reviews, order_events, order_items, orders,
                     cart_items, cart_members, carts,
                     menu_items, categories, restaurants, users;

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80)  NOT NULL,
  email         VARCHAR(120) NOT NULL UNIQUE,
  phone         VARCHAR(20)  DEFAULT NULL,
  address       VARCHAR(255) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','restaurant','rider','admin') NOT NULL DEFAULT 'customer',
  restaurant_id INT DEFAULT NULL,                 -- set for restaurant owners
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE restaurants (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  name                VARCHAR(120) NOT NULL,
  cuisine             VARCHAR(80)  NOT NULL,
  tagline             VARCHAR(160) DEFAULT NULL,
  address             VARCHAR(255) DEFAULT NULL,
  phone               VARCHAR(20)  DEFAULT NULL,
  image               VARCHAR(120) DEFAULT NULL,
  is_open             TINYINT(1) NOT NULL DEFAULT 1,
  is_approved         TINYINT(1) NOT NULL DEFAULT 0,   -- admin lets it onto the platform
  max_parallel_orders INT NOT NULL DEFAULT 3,          -- how many orders it cooks at once
  prep_buffer_min     INT NOT NULL DEFAULT 2,          -- plating / packing slack
  commission_pct      DECIMAL(5,2) NOT NULL DEFAULT 8.00,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  name          VARCHAR(60) NOT NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE menu_items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  category_id   INT DEFAULT NULL,
  name          VARCHAR(120) NOT NULL,
  description   VARCHAR(255) DEFAULT NULL,
  price         DECIMAL(8,2) NOT NULL,
  is_veg        TINYINT(1) NOT NULL DEFAULT 1,
  prep_minutes  INT NOT NULL DEFAULT 12,          -- feeds the honest ETA
  is_available  TINYINT(1) NOT NULL DEFAULT 1,
  image         VARCHAR(120) DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id)   REFERENCES categories(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- group cart
CREATE TABLE carts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  code          CHAR(6) NOT NULL UNIQUE,          -- the share code friends type
  owner_user_id INT NOT NULL,
  restaurant_id INT NOT NULL,
  is_group      TINYINT(1) NOT NULL DEFAULT 0,
  status        ENUM('open','locked','ordered','abandoned') NOT NULL DEFAULT 'open',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_user_id) REFERENCES users(id)       ON DELETE CASCADE,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cart_members (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  cart_id   INT NOT NULL,
  user_id   INT DEFAULT NULL,                     -- null for a guest who joined by link
  name      VARCHAR(60) NOT NULL,
  is_owner  TINYINT(1) NOT NULL DEFAULT 0,
  seen_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- drives the "online now" dots
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cart_items (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  cart_id   INT NOT NULL,
  member_id INT NOT NULL,                         -- who it belongs to, for the split
  item_id   INT NOT NULL,
  qty       INT NOT NULL DEFAULT 1,
  note      VARCHAR(120) DEFAULT NULL,
  added_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cart_id)   REFERENCES carts(id)        ON DELETE CASCADE,
  FOREIGN KEY (member_id) REFERENCES cart_members(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id)   REFERENCES menu_items(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------------- orders
CREATE TABLE orders (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  code           VARCHAR(12) NOT NULL UNIQUE,     -- FT-1042, shown to humans
  restaurant_id  INT NOT NULL,
  user_id        INT NOT NULL,
  cart_id        INT DEFAULT NULL,
  rider_id       INT DEFAULT NULL,
  is_group       TINYINT(1) NOT NULL DEFAULT 0,
  status         ENUM('placed','accepted','preparing','ready','out_for_delivery','delivered','cancelled')
                 NOT NULL DEFAULT 'placed',
  subtotal       DECIMAL(10,2) NOT NULL,
  delivery_fee   DECIMAL(10,2) NOT NULL DEFAULT 0,
  total          DECIMAL(10,2) NOT NULL,
  address        VARCHAR(255) NOT NULL,
  payment_mode   ENUM('cod','upi') NOT NULL DEFAULT 'cod',
  -- the promise, kept so we can check it later
  cook_minutes   INT NOT NULL DEFAULT 0,
  queue_wait_min INT NOT NULL DEFAULT 0,
  load_pct       INT NOT NULL DEFAULT 0,
  promised_min   INT NOT NULL DEFAULT 0,
  promised_at    DATETIME NOT NULL,
  accepted_at    DATETIME DEFAULT NULL,
  ready_at       DATETIME DEFAULT NULL,
  delivered_at   DATETIME DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id),
  FOREIGN KEY (user_id)       REFERENCES users(id),
  FOREIGN KEY (rider_id)      REFERENCES users(id) ON DELETE SET NULL,
  INDEX (restaurant_id, status),
  INDEX (user_id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  order_id     INT NOT NULL,
  item_id      INT DEFAULT NULL,
  name         VARCHAR(120) NOT NULL,             -- snapshot: menus change later
  unit_price   DECIMAL(8,2) NOT NULL,
  qty          INT NOT NULL,
  member_name  VARCHAR(60) DEFAULT NULL,          -- who ordered it, for the split bill
  FOREIGN KEY (order_id) REFERENCES orders(id)     ON DELETE CASCADE,
  FOREIGN KEY (item_id)  REFERENCES menu_items(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_events (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  order_id   INT NOT NULL,
  status     VARCHAR(30) NOT NULL,
  note       VARCHAR(160) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  order_id      INT NOT NULL UNIQUE,
  restaurant_id INT NOT NULL,
  user_id       INT NOT NULL,
  stars         TINYINT NOT NULL,
  on_time       TINYINT(1) NOT NULL DEFAULT 1,
  comment       VARCHAR(255) DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id)      REFERENCES orders(id)      ON DELETE CASCADE,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE users
  ADD CONSTRAINT fk_users_restaurant FOREIGN KEY (restaurant_id)
  REFERENCES restaurants(id) ON DELETE SET NULL;
