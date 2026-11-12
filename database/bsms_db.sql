-- Bakery Shop Management System Database Dump
-- Cloud-Ready (Aiven Cloud MySQL & Render Compatible)
-- With Inline Primary Keys & Constraints

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for table `category_list`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `category_list` (
  `category_id` int(30) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `description` text NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `delete_flag` tinyint(1) NOT NULL DEFAULT 0,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `date_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `category_list` (`category_id`, `name`, `description`, `status`, `delete_flag`, `date_created`, `date_updated`) VALUES
(1, 'Sample 101', 'This is a sample Category 101', 1, 0, '2022-02-14 09:16:23', '2022-02-14 09:18:40'),
(2, 'Sample 102', 'This is a sample Category 102', 1, 0, '2022-02-14 09:19:04', NULL),
(3, 'Sample 103', 'This is a sample Category 103', 1, 0, '2022-02-14 09:19:11', NULL),
(4, 'Sample 104', 'This is a sample Category 104', 1, 0, '2022-02-14 09:19:18', NULL),
(5, 'Sample 105', 'This is a sample Category 105', 1, 0, '2022-02-14 09:19:24', NULL),
(6, 'Sample 106', 'This is a sample Category 106', 1, 0, '2022-02-14 09:19:30', NULL),
(7, 'Sample 107', 'This is a sample Category 107', 1, 0, '2022-02-14 09:19:37', NULL),
(8, 'Sample 108', 'This is a sample Category 108', 1, 0, '2022-02-14 09:19:43', NULL),
(9, 'Sample 109', 'This is a sample Category 109', 1, 0, '2022-02-14 09:19:49', NULL),
(10, 'Sample 110', 'This is a sample Category 110', 1, 0, '2022-02-14 09:19:55', NULL),
(11, 'Sample 111', 'This is a sample Category 111', 0, 1, '2022-02-14 09:20:11', '2022-02-14 09:23:14')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- --------------------------------------------------------
-- Table structure for table `product_list`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_list` (
  `product_id` int(30) NOT NULL AUTO_INCREMENT,
  `product_code` varchar(100) NOT NULL,
  `category_id` int(30) NOT NULL,
  `name` text NOT NULL,
  `description` text NOT NULL,
  `price` double NOT NULL DEFAULT 0,
  `alert_restock` double NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `delete_flag` tinyint(1) NOT NULL DEFAULT 0,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `date_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `product_list` (`product_id`, `product_code`, `category_id`, `name`, `description`, `price`, `alert_restock`, `status`, `delete_flag`, `date_created`, `date_updated`) VALUES
(1, '23141506', 1, 'Product 101', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.', 10, 20, 1, 0, '2022-02-14 09:42:00', '2022-02-14 11:52:16'),
(2, '123456', 2, 'Product 102', 'Cras eget maximus nunc, id hendrerit dui. Donec auctor mauris ac augue aliquam gravida vel sit amet libero. Proin tempor eu augue id aliquet.', 15, 20, 1, 0, '2022-02-14 09:42:00', '2022-02-14 11:52:23'),
(3, '231415', 2, 'Product 103', 'Vivamus commodo purus a dolor pretium interdum. Pellentesque bibendum lacus sed tortor mollis varius. Etiam sed odio felis. Nam nec erat eu metus feugiat aliquam. Aenean id semper ex. Nulla nec euismod tellus. Maecenas pellentesque ipsum sit amet augue scelerisque ultrices.', 45, 50, 1, 0, '2022-02-14 09:42:00', '2022-02-14 11:52:32'),
(4, '123654789', 3, 'Product 104', 'Sed sit amet pharetra metus, sed posuere nibh. Nam at sapien enim. Pellentesque pretium scelerisque turpis, in rhoncus sapien tempor sed.', 20, 30, 1, 0, '2022-02-14 09:42:00', '2022-02-14 11:52:43'),
(5, '987545', 3, 'Product 105', 'Curabitur imperdiet cursus auctor. Donec tristique nulla non porta lobortis. Nulla malesuada sapien lacus, nec rhoncus leo porta vitae. Pellentesque semper rhoncus tellus a pulvinar. Morbi nec tortor ut lorem laoreet vulputate', 50, 30, 1, 0, '2022-02-14 09:46:59', '2022-02-14 11:52:50'),
(6, '5489879', 6, 'Product 105', 'Fusce dui augue, porttitor at est a, commodo lacinia mauris. Etiam quis nulla maximus, fermentum tortor quis, suscipit neque. Curabitur leo ligula, tristique eu placerat sit amet, euismod non ligula.', 50, 0, 1, 1, '2022-02-14 09:47:22', '2022-02-14 09:48:32')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- --------------------------------------------------------
-- Table structure for table `stock_list`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_list` (
  `stock_id` int(30) NOT NULL AUTO_INCREMENT,
  `product_id` int(30) NOT NULL,
  `quantity` double NOT NULL DEFAULT 0,
  `expiry_date` datetime NOT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`stock_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `stock_list` (`stock_id`, `product_id`, `quantity`, `expiry_date`, `date_added`) VALUES
(1, 1, 20, '2022-02-17 00:00:00', '2022-02-14 02:04:29'),
(2, 2, 200, '2022-02-18 00:00:00', '2022-02-14 02:05:18'),
(3, 3, 350, '2022-02-18 00:00:00', '2022-02-14 02:05:33'),
(4, 4, 500, '2022-02-25 00:00:00', '2022-02-14 02:05:45'),
(5, 5, 100, '2022-02-18 00:00:00', '2022-02-14 02:06:48')
ON DUPLICATE KEY UPDATE `quantity`=VALUES(`quantity`);

-- --------------------------------------------------------
-- Table structure for table `transaction_list`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transaction_list` (
  `transaction_id` int(30) NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(100) NOT NULL,
  `total` double NOT NULL DEFAULT 0,
  `tendered_amount` double NOT NULL DEFAULT 0,
  `change` double NOT NULL DEFAULT 0,
  `user_id` int(30) DEFAULT 1,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`transaction_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `transaction_list` (`transaction_id`, `receipt_no`, `total`, `tendered_amount`, `change`, `user_id`, `date_added`) VALUES
(1, '1644804759', 205, 300, 95, 1, '2022-02-14 02:12:39'),
(2, '1644804881', 1000, 1000, 0, 1, '2022-02-14 02:14:41'),
(4, '1644806318', 125, 150, 25, NULL, '2022-02-14 02:38:38'),
(5, '1644807473', 85, 100, 15, 2, '2022-02-14 02:57:53')
ON DUPLICATE KEY UPDATE `receipt_no`=VALUES(`receipt_no`);

-- --------------------------------------------------------
-- Table structure for table `transaction_items`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transaction_items` (
  `item_id` int(30) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(30) NOT NULL,
  `product_id` int(30) NOT NULL,
  `quantity` double NOT NULL DEFAULT 0,
  `price` double NOT NULL DEFAULT 0,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`item_id`),
  KEY `product_id` (`product_id`),
  KEY `transaction_id` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `transaction_items` (`item_id`, `transaction_id`, `product_id`, `quantity`, `price`, `date_added`) VALUES
(1, 1, 1, 4, 10, '2022-02-14 02:12:39'),
(2, 1, 2, 2, 15, '2022-02-14 02:12:39'),
(3, 1, 4, 2, 20, '2022-02-14 02:12:39'),
(4, 1, 3, 1, 45, '2022-02-14 02:12:39'),
(5, 1, 5, 1, 50, '2022-02-14 02:12:39'),
(6, 2, 5, 20, 50, '2022-02-14 02:14:41'),
(7, 4, 3, 1, 45, '2022-02-14 02:38:38'),
(8, 4, 5, 1, 50, '2022-02-14 02:38:38'),
(9, 4, 2, 2, 15, '2022-02-14 02:38:38'),
(10, 5, 2, 1, 15, '2022-02-14 02:57:53'),
(11, 5, 5, 1, 50, '2022-02-14 02:57:53'),
(12, 5, 1, 2, 10, '2022-02-14 02:57:53')
ON DUPLICATE KEY UPDATE `quantity`=VALUES(`quantity`);

-- --------------------------------------------------------
-- Table structure for table `user_list`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_list` (
  `user_id` int(30) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `type` int(30) NOT NULL DEFAULT 1,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `user_list` (`user_id`, `fullname`, `username`, `password`, `type`, `status`, `date_created`) VALUES
(1, 'Administrator', 'admin', '0192023a7bbd73250516f069df18b500', 1, 1, '2022-02-14 00:44:30'),
(2, 'Claire Blake', 'cblake', 'cd74fae0a3adf459f73bbf187607ccea', 0, 1, '2022-02-14 02:29:23'),
(3, 'Mark Cooper', 'mcooper', '0c4635c5af0f173c26b0d85b6c9b398b', 1, 1, '2022-02-14 02:29:58')
ON DUPLICATE KEY UPDATE `password`=VALUES(`password`);

SET FOREIGN_KEY_CHECKS = 1;
