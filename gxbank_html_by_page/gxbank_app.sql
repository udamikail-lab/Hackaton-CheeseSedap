-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3307
-- Generation Time: May 10, 2026 at 12:10 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gxbank_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `account_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `account_type` varchar(50) NOT NULL,
  `business_name` varchar(150) DEFAULT NULL,
  `business_reg_no` varchar(50) DEFAULT NULL,
  `account_number` varchar(20) NOT NULL,
  `balance` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`account_id`, `user_id`, `account_type`, `business_name`, `business_reg_no`, `account_number`, `balance`) VALUES
(1, 1, 'Savings Account', NULL, NULL, '48734159', 3250.80),
(2, 2, 'Savings Account', NULL, NULL, '48482858', 9450.80),
(3, 3, 'Savings Account', NULL, NULL, '48978658', 1000.00),
(4, 4, 'Savings Account', NULL, NULL, '48801740', 31650.80),
(7, 4, 'Business Account', 'Syaqierin Enterprise', '2024234664', '56251473', 11424.44),
(8, 5, 'Savings Account', NULL, NULL, '48999999', 999999999.99);

-- --------------------------------------------------------

--
-- Table structure for table `bonus_pockets`
--

CREATE TABLE `bonus_pockets` (
  `pocket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pocket_name` varchar(100) NOT NULL,
  `target_amount` decimal(12,2) NOT NULL,
  `current_amount` decimal(12,2) DEFAULT 0.00,
  `deadline` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bonus_pockets`
--

INSERT INTO `bonus_pockets` (`pocket_id`, `user_id`, `pocket_name`, `target_amount`, `current_amount`, `deadline`) VALUES
(1, 1, 'Vacation Fund', 2000.00, 1500.00, '2026-12-31'),
(2, 1, 'Emergency Fund', 3000.00, 1750.00, '2026-11-30'),
(3, 2, 'Vacation Fund', 2000.00, 1500.00, '2026-12-31'),
(4, 2, 'Emergency Fund', 3000.00, 1750.00, '2026-11-30'),
(5, 2, 'KAHWIN', 400000.00, 10.00, '2032-12-30'),
(6, 3, 'Vacation Fund', 2000.00, 1500.00, '2026-12-31'),
(7, 3, 'Emergency Fund', 3000.00, 1750.00, '2026-11-30');

-- --------------------------------------------------------

--
-- Table structure for table `business_loans`
--

CREATE TABLE `business_loans` (
  `loan_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `business_account_id` int(11) NOT NULL,
  `requested_amount` decimal(12,2) NOT NULL,
  `approved_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outstanding_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monthly_revenue` decimal(12,2) NOT NULL,
  `monthly_expense` decimal(12,2) NOT NULL,
  `business_age_months` int(11) NOT NULL,
  `requested_term_months` int(11) NOT NULL,
  `approved_term_months` int(11) DEFAULT 0,
  `interest_rate` decimal(5,2) DEFAULT 0.00,
  `monthly_payment` decimal(12,2) DEFAULT 0.00,
  `purpose` varchar(255) NOT NULL,
  `ai_score` int(11) DEFAULT 0,
  `risk_level` varchar(50) DEFAULT NULL,
  `approval_status` enum('approved','reduced_approved','rejected') NOT NULL,
  `loan_status` enum('active','rejected','fully_paid','closed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business_loans`
--

INSERT INTO `business_loans` (`loan_id`, `user_id`, `business_account_id`, `requested_amount`, `approved_amount`, `outstanding_amount`, `monthly_revenue`, `monthly_expense`, `business_age_months`, `requested_term_months`, `approved_term_months`, `interest_rate`, `monthly_payment`, `purpose`, `ai_score`, `risk_level`, `approval_status`, `loan_status`, `created_at`) VALUES
(1, 4, 7, 100000.00, 0.00, 0.00, 10000.00, 5000.00, 5, 36, 0, 0.00, 0.00, 'Rebuild', 5, 'Rejected Risk', 'rejected', 'rejected', '2026-05-10 06:59:10'),
(2, 4, 7, 10000.00, 0.00, 0.00, 5000.00, 1000.00, 5, 6, 0, 0.00, 0.00, 'Rebuild', 40, 'Rejected Risk', 'rejected', 'rejected', '2026-05-10 06:59:40'),
(3, 4, 7, 10000.00, 10000.00, 9424.44, 10000.00, 100.00, 20, 18, 18, 4.50, 575.56, 'Rebuild', 85, 'Low Risk', 'approved', 'active', '2026-05-10 07:00:06');

-- --------------------------------------------------------

--
-- Table structure for table `business_loan_transactions`
--

CREATE TABLE `business_loan_transactions` (
  `loan_txn_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `business_account_id` int(11) NOT NULL,
  `transaction_type` enum('disbursement','repayment') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `transaction_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business_loan_transactions`
--

INSERT INTO `business_loan_transactions` (`loan_txn_id`, `loan_id`, `business_account_id`, `transaction_type`, `amount`, `notes`, `transaction_date`) VALUES
(1, 3, 7, 'disbursement', 10000.00, 'Biz Flexi Loan disbursed to business account.', '2026-05-10 15:00:06'),
(2, 3, 7, 'repayment', 575.56, 'Business loan repayment.', '2026-05-10 15:00:40');

-- --------------------------------------------------------

--
-- Table structure for table `credit_facilities`
--

CREATE TABLE `credit_facilities` (
  `credit_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `facility_type` enum('personal','business') NOT NULL,
  `approved_limit` decimal(12,2) DEFAULT 0.00,
  `available_limit` decimal(12,2) DEFAULT 0.00,
  `current_loan` decimal(12,2) DEFAULT 0.00,
  `interest_rate` decimal(5,2) DEFAULT 3.50,
  `monthly_payment` decimal(12,2) DEFAULT 0.00,
  `remaining_term` int(11) DEFAULT 0,
  `application_reason` varchar(255) DEFAULT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `credit_facilities`
--

INSERT INTO `credit_facilities` (`credit_id`, `user_id`, `facility_type`, `approved_limit`, `available_limit`, `current_loan`, `interest_rate`, `monthly_payment`, `remaining_term`, `application_reason`, `status`, `created_at`) VALUES
(1, 4, 'personal', 10000.00, 10000.00, 0.00, 3.50, 0.00, 0, 'Extra money', 'closed', '2026-05-09 15:34:13');

-- --------------------------------------------------------

--
-- Table structure for table `debit_cards`
--

CREATE TABLE `debit_cards` (
  `card_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `card_number` varchar(20) NOT NULL,
  `cvc` varchar(3) NOT NULL DEFAULT '000',
  `expiry_month` int(11) DEFAULT NULL,
  `expiry_year` int(11) DEFAULT NULL,
  `card_status` enum('active','locked') DEFAULT 'active',
  `monthly_limit` decimal(12,2) DEFAULT 10000.00,
  `monthly_spending` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `debit_cards`
--

INSERT INTO `debit_cards` (`card_id`, `user_id`, `card_number`, `cvc`, `expiry_month`, `expiry_year`, `card_status`, `monthly_limit`, `monthly_spending`) VALUES
(1, 3, '4821', '000', 12, 2028, 'active', 10000.00, 2845.32),
(5, 4, '4555889063485863', '330', 12, 2030, 'active', 10000.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `flexi_cards`
--

CREATE TABLE `flexi_cards` (
  `flexi_card_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `flexi_account_number` varchar(30) DEFAULT NULL,
  `requested_limit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `approved_limit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `available_limit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outstanding_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monthly_income` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monthly_commitment` decimal(12,2) NOT NULL DEFAULT 0.00,
  `employment_type` varchar(50) NOT NULL,
  `credit_score_grade` varchar(50) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `ai_score` int(11) DEFAULT 0,
  `application_status` enum('approved','reduced_approved','rejected') NOT NULL,
  `card_status` enum('active','locked','closed') DEFAULT 'active',
  `on_time_payment_count` int(11) DEFAULT 0,
  `late_payment_count` int(11) DEFAULT 0,
  `last_limit_review` date DEFAULT NULL,
  `limit_increase_eligible` enum('yes','no') DEFAULT 'no',
  `limit_review_score` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `flexi_cards`
--

INSERT INTO `flexi_cards` (`flexi_card_id`, `user_id`, `flexi_account_number`, `requested_limit`, `approved_limit`, `available_limit`, `outstanding_balance`, `monthly_income`, `monthly_commitment`, `employment_type`, `credit_score_grade`, `purpose`, `ai_score`, `application_status`, `card_status`, `on_time_payment_count`, `late_payment_count`, `last_limit_review`, `limit_increase_eligible`, `limit_review_score`, `created_at`) VALUES
(1, 4, '78000001', 10900.00, 0.00, 0.00, 0.00, 1000.00, 500.00, 'Permanent Employee', 'Excellent', 'saja', 20, 'rejected', 'active', 0, 0, NULL, 'no', 0, '2026-05-09 16:34:26'),
(2, 4, '78000002', 10000.00, 10000.00, 10000.00, 0.00, 10000.00, 6000.00, 'Permanent Employee', 'Excellent', 'saja', 80, 'approved', 'closed', 0, 0, '2026-05-10', 'no', 15, '2026-05-09 16:34:56'),
(3, 2, '78382155', 10000.00, 0.00, 0.00, 0.00, 1000.00, 200.00, 'Unemployed', 'Poor', 'saja', -75, 'rejected', 'active', 0, 0, NULL, 'no', 0, '2026-05-10 05:30:53'),
(4, 2, '78446302', 1000000.00, 1000000.00, 990000.00, 10000.00, 10000000.00, 10000.00, 'Permanent Employee', 'Excellent', 'saja', 130, 'approved', 'active', 0, 0, NULL, 'no', 0, '2026-05-10 05:31:13');

-- --------------------------------------------------------

--
-- Table structure for table `flexi_card_transactions`
--

CREATE TABLE `flexi_card_transactions` (
  `flexi_txn_id` int(11) NOT NULL,
  `flexi_card_id` int(11) NOT NULL,
  `transaction_name` varchar(150) NOT NULL,
  `transaction_type` enum('spend','repayment','limit_increase') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `payment_status` enum('on_time','late','not_applicable') DEFAULT 'not_applicable',
  `transaction_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `flexi_card_transactions`
--

INSERT INTO `flexi_card_transactions` (`flexi_txn_id`, `flexi_card_id`, `transaction_name`, `transaction_type`, `amount`, `category`, `notes`, `payment_status`, `transaction_date`) VALUES
(1, 4, 'Transfer to SYAQIERIN', 'spend', 10000.00, 'Transfer', 'Payment', 'not_applicable', '2026-05-10 13:32:27');

-- --------------------------------------------------------

--
-- Table structure for table `gxbank_products`
--

CREATE TABLE `gxbank_products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `page_url` varchar(150) NOT NULL,
  `icon` varchar(50) NOT NULL,
  `bg_class` varchar(100) NOT NULL,
  `text_class` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gxbank_products`
--

INSERT INTO `gxbank_products` (`product_id`, `product_name`, `product_type`, `description`, `page_url`, `icon`, `bg_class`, `text_class`) VALUES
(1, 'GX Account', 'Savings Account', 'Main savings account for daily banking.', 'pages/gx-account.php', 'wallet', 'bg-blue-500/15', 'text-blue-400'),
(2, 'Bonus Pockets', 'Savings Goal', 'Create saving pockets with target and progress tracking.', 'pages/bonus-pockets.php', 'piggy-bank', 'bg-amber-500/15', 'text-amber-400'),
(3, 'GX Debit Card', 'Debit Card', 'Debit card linked to user account.', 'pages/debit-card.php', 'credit-card', 'bg-purple-500/15', 'text-purple-400'),
(4, 'FlexiCredit', 'Credit Facility', 'Personal flexible credit facility.', 'pages/flexi-credit.php', 'banknote', 'bg-rose-500/15', 'text-rose-400'),
(6, 'GX Biz Account', 'Business Account', 'Business banking account.', 'pages/biz-account.php', 'briefcase', 'bg-indigo-500/15', 'text-indigo-400'),
(8, 'GX Protect', 'Insurance', 'Fuzzy logic insurance protection', '/gxbank_html_by_page/pages/insurance.php', 'shield-check', 'bg-sky-500/15', 'text-sky-400'),
(9, 'Biz Flexi Loan', 'Business Financing', 'Business working capital loan', '/gxbank_html_by_page/pages/biz-loan.php', 'briefcase', 'bg-purple-500/15', 'text-purple-400');

-- --------------------------------------------------------

--
-- Table structure for table `insurance_payments`
--

CREATE TABLE `insurance_payments` (
  `payment_id` int(11) NOT NULL,
  `policy_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_status` enum('paid','failed') DEFAULT 'paid',
  `payment_date` datetime DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `insurance_policies`
--

CREATE TABLE `insurance_policies` (
  `policy_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `coverage_amount` decimal(12,2) NOT NULL,
  `base_premium` decimal(12,2) NOT NULL,
  `smoker_loading_rate` decimal(5,2) DEFAULT 0.00,
  `illness_loading_rate` decimal(5,2) DEFAULT 0.00,
  `fuzzy_loading_rate` decimal(5,2) DEFAULT 0.00,
  `total_loading_rate` decimal(5,2) DEFAULT 0.00,
  `monthly_premium` decimal(12,2) NOT NULL,
  `beneficiary_name` varchar(150) NOT NULL,
  `beneficiary_relationship` varchar(100) NOT NULL,
  `smoking_status` enum('non_smoker','smoker') NOT NULL,
  `existing_illness` enum('no','yes') NOT NULL,
  `monthly_income` decimal(12,2) NOT NULL,
  `age` int(11) NOT NULL,
  `fuzzy_risk_score` decimal(6,2) DEFAULT 0.00,
  `fuzzy_risk_level` varchar(50) DEFAULT NULL,
  `approval_status` enum('approved','approved_higher_premium','rejected') NOT NULL,
  `policy_status` enum('active','rejected','cancelled','lapsed') DEFAULT 'active',
  `next_due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `insurance_policies`
--

INSERT INTO `insurance_policies` (`policy_id`, `user_id`, `plan_name`, `coverage_amount`, `base_premium`, `smoker_loading_rate`, `illness_loading_rate`, `fuzzy_loading_rate`, `total_loading_rate`, `monthly_premium`, `beneficiary_name`, `beneficiary_relationship`, `smoking_status`, `existing_illness`, `monthly_income`, `age`, `fuzzy_risk_score`, `fuzzy_risk_level`, `approval_status`, `policy_status`, `next_due_date`, `created_at`) VALUES
(1, 4, 'Basic Protect', 50000.00, 20.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'AZLI BIN UDA', 'Father', 'smoker', 'yes', 1000.00, 22, 85.00, 'High Risk', 'rejected', 'rejected', NULL, '2026-05-10 06:51:32');

-- --------------------------------------------------------

--
-- Table structure for table `payment_requests`
--

CREATE TABLE `payment_requests` (
  `request_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('unpaid','paid') DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pocket_transactions`
--

CREATE TABLE `pocket_transactions` (
  `pocket_txn_id` int(11) NOT NULL,
  `pocket_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `transaction_type` enum('transfer_in','transfer_out','delete_return') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `transaction_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `transaction_name` varchar(100) NOT NULL,
  `transaction_type` enum('income','expense','transfer') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `transfer_reason` varchar(255) DEFAULT NULL,
  `transaction_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `account_id`, `transaction_name`, `transaction_type`, `amount`, `transfer_reason`, `transaction_date`) VALUES
(1, 1, 'Coffee Shop', 'expense', 12.50, NULL, '2026-05-09 21:56:02'),
(2, 1, 'Salary Deposit', 'income', 5000.00, NULL, '2026-05-09 21:56:02'),
(3, 1, 'Online Transfer', 'expense', 250.00, NULL, '2026-05-09 21:56:02'),
(4, 2, 'Coffee Shop', 'expense', 12.50, NULL, '2026-05-09 22:03:23'),
(5, 2, 'Salary Deposit', 'income', 5000.00, NULL, '2026-05-09 22:03:23'),
(6, 2, 'Online Transfer', 'expense', 250.00, NULL, '2026-05-09 22:03:23'),
(7, 3, 'Coffee Shop', 'expense', 12.50, NULL, '2026-05-09 22:35:01'),
(8, 3, 'Salary Deposit', 'income', 5000.00, NULL, '2026-05-09 22:35:01'),
(9, 3, 'Online Transfer', 'expense', 250.00, NULL, '2026-05-09 22:35:01'),
(10, 1, 'Transfer to SYQIERIN', 'transfer', 1000.00, 'Saja', '2026-05-09 23:26:27'),
(11, 4, 'Transfer from UDA MIKAIL BIN AZLI', 'income', 1000.00, 'Saja', '2026-05-09 23:26:27'),
(12, 1, 'Transfer to SYQIERIN', 'transfer', 5000.00, 'Personal transfer', '2026-05-09 23:29:41'),
(13, 4, 'Transfer from UDA MIKAIL BIN AZLI', 'income', 5000.00, 'Personal transfer', '2026-05-09 23:29:41'),
(14, 4, 'Transfer to Business Account', 'transfer', 1000.00, 'Capital', '2026-05-09 23:41:40'),
(17, 4, 'Transfer from Business Account', 'income', 200.00, 'saja', '2026-05-09 23:41:49'),
(18, 1, 'Transfer to SYQIERIN', 'transfer', 200.00, 'Services', '2026-05-09 23:59:46'),
(20, 1, 'Transfer to HAZIM ENTERPRISE', 'transfer', 3000.00, 'Payment', '2026-05-10 00:07:30'),
(22, 4, 'Returned balance from HAZIM ENTERPRISE', 'income', 4000.00, 'Business account deleted. Remaining balance returned to main account.', '2026-05-10 00:21:08'),
(23, 3, 'Transfer to SYQIERIN', 'transfer', 12450.80, 'Payment', '2026-05-10 00:41:42'),
(24, 4, 'Transfer from AFDAL AIMAN', 'income', 12450.80, 'Payment', '2026-05-10 00:41:42'),
(25, 4, 'Transfer to Tabung: KAHWIN', 'expense', 5000.00, 'Money transferred from main account into tabung.', '2026-05-10 00:46:47'),
(26, 4, 'Returned balance from Tabung: KAHWIN', 'income', 5000.00, 'Tabung deleted. Remaining balance returned to main account.', '2026-05-10 00:46:58'),
(27, 4, 'QR Payment to AFDAL AIMAN', 'transfer', 1000.00, 'hutang', '2026-05-10 09:28:07'),
(28, 3, 'QR Payment from SYAQIERIN', 'income', 1000.00, 'hutang', '2026-05-10 09:28:07'),
(29, 2, 'QR Payment to Syaqierin Enterprise', 'transfer', 2000.00, 'Baju', '2026-05-10 13:29:20'),
(30, 7, 'QR Payment from HAZIM', 'income', 2000.00, 'Baju', '2026-05-10 13:29:20'),
(31, 2, 'QR Payment to SYAQIERIN', 'transfer', 1000.00, 'hutang', '2026-05-10 13:30:21'),
(32, 4, 'QR Payment from HAZIM', 'income', 1000.00, 'hutang', '2026-05-10 13:30:21'),
(33, 4, 'Transfer from FlexiCard 78446302', 'income', 10000.00, 'Payment', '2026-05-10 13:32:27'),
(34, 7, 'Biz Flexi Loan Disbursement', 'income', 10000.00, 'Approved business loan disbursed. AI score: 85.', '2026-05-10 15:00:06'),
(35, 7, 'Biz Flexi Loan Repayment', 'expense', 575.56, 'Business loan repayment.', '2026-05-10 15:00:40');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `full_name`, `age`, `email`, `password`, `created_at`) VALUES
(1, 'udamikail', 'UDA MIKAIL BIN AZLI', 22, 'udamikail@gmail.com', '$2y$10$sd4XAIgk4mKHGS4NUXXuC.0tHHLypoiH416G/hd/ujcn9GDuLbFTe', '2026-05-09 13:56:02'),
(2, 'hazim', 'HAZIM', 23, 'hazim@gmail.com', '$2y$10$jbG4t9oH.LzFF12jJRAhMe9vZjbyUc9jY/W.ra.EkGD1VfQE.v.hm', '2026-05-09 14:03:23'),
(3, 'afdal', 'AFDAL AIMAN', 23, 'afdalaiman@gmail.com', '$2y$10$hZcI1NQQX.Fg4SX42QSb..Ema96gsCE.ezTV1or6JwvTFnmHMIeIG', '2026-05-09 14:35:01'),
(4, 'syaqierin', 'SYAQIERIN', 22, 'syaqierin@gmail.com', '$2y$10$lXWTeB2H7EHpDF3zgeYq9uJMM02uaw5dpFxSVsqyYNUAChpTSbhOS', '2026-05-09 14:37:45'),
(5, 'admin', 'GXBank Admin', 99, 'admin@gxbank.com', '$2y$12$lj8UE2S9V8O2FEHvjVZO9.VDo3pX.2OiwdmNSXceaPWiYKVqyOO4G', '2026-05-10 05:56:09');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `bonus_pockets`
--
ALTER TABLE `bonus_pockets`
  ADD PRIMARY KEY (`pocket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `business_loans`
--
ALTER TABLE `business_loans`
  ADD PRIMARY KEY (`loan_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `business_account_id` (`business_account_id`);

--
-- Indexes for table `business_loan_transactions`
--
ALTER TABLE `business_loan_transactions`
  ADD PRIMARY KEY (`loan_txn_id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `business_account_id` (`business_account_id`);

--
-- Indexes for table `credit_facilities`
--
ALTER TABLE `credit_facilities`
  ADD PRIMARY KEY (`credit_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `debit_cards`
--
ALTER TABLE `debit_cards`
  ADD PRIMARY KEY (`card_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `flexi_cards`
--
ALTER TABLE `flexi_cards`
  ADD PRIMARY KEY (`flexi_card_id`),
  ADD UNIQUE KEY `flexi_account_number` (`flexi_account_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `flexi_card_transactions`
--
ALTER TABLE `flexi_card_transactions`
  ADD PRIMARY KEY (`flexi_txn_id`),
  ADD KEY `flexi_card_id` (`flexi_card_id`);

--
-- Indexes for table `gxbank_products`
--
ALTER TABLE `gxbank_products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `insurance_payments`
--
ALTER TABLE `insurance_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `policy_id` (`policy_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `insurance_policies`
--
ALTER TABLE `insurance_policies`
  ADD PRIMARY KEY (`policy_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payment_requests`
--
ALTER TABLE `payment_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `pocket_transactions`
--
ALTER TABLE `pocket_transactions`
  ADD PRIMARY KEY (`pocket_txn_id`),
  ADD KEY `pocket_id` (`pocket_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `bonus_pockets`
--
ALTER TABLE `bonus_pockets`
  MODIFY `pocket_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `business_loans`
--
ALTER TABLE `business_loans`
  MODIFY `loan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `business_loan_transactions`
--
ALTER TABLE `business_loan_transactions`
  MODIFY `loan_txn_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `credit_facilities`
--
ALTER TABLE `credit_facilities`
  MODIFY `credit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `debit_cards`
--
ALTER TABLE `debit_cards`
  MODIFY `card_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `flexi_cards`
--
ALTER TABLE `flexi_cards`
  MODIFY `flexi_card_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `flexi_card_transactions`
--
ALTER TABLE `flexi_card_transactions`
  MODIFY `flexi_txn_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `gxbank_products`
--
ALTER TABLE `gxbank_products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `insurance_payments`
--
ALTER TABLE `insurance_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `insurance_policies`
--
ALTER TABLE `insurance_policies`
  MODIFY `policy_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payment_requests`
--
ALTER TABLE `payment_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pocket_transactions`
--
ALTER TABLE `pocket_transactions`
  MODIFY `pocket_txn_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `bonus_pockets`
--
ALTER TABLE `bonus_pockets`
  ADD CONSTRAINT `bonus_pockets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `business_loans`
--
ALTER TABLE `business_loans`
  ADD CONSTRAINT `business_loans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `business_loans_ibfk_2` FOREIGN KEY (`business_account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE;

--
-- Constraints for table `business_loan_transactions`
--
ALTER TABLE `business_loan_transactions`
  ADD CONSTRAINT `business_loan_transactions_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `business_loans` (`loan_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `business_loan_transactions_ibfk_2` FOREIGN KEY (`business_account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE;

--
-- Constraints for table `credit_facilities`
--
ALTER TABLE `credit_facilities`
  ADD CONSTRAINT `credit_facilities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `debit_cards`
--
ALTER TABLE `debit_cards`
  ADD CONSTRAINT `debit_cards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `flexi_cards`
--
ALTER TABLE `flexi_cards`
  ADD CONSTRAINT `flexi_cards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `flexi_card_transactions`
--
ALTER TABLE `flexi_card_transactions`
  ADD CONSTRAINT `flexi_card_transactions_ibfk_1` FOREIGN KEY (`flexi_card_id`) REFERENCES `flexi_cards` (`flexi_card_id`) ON DELETE CASCADE;

--
-- Constraints for table `insurance_payments`
--
ALTER TABLE `insurance_payments`
  ADD CONSTRAINT `insurance_payments_ibfk_1` FOREIGN KEY (`policy_id`) REFERENCES `insurance_policies` (`policy_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `insurance_payments_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE;

--
-- Constraints for table `insurance_policies`
--
ALTER TABLE `insurance_policies`
  ADD CONSTRAINT `insurance_policies_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_requests`
--
ALTER TABLE `payment_requests`
  ADD CONSTRAINT `payment_requests_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE;

--
-- Constraints for table `pocket_transactions`
--
ALTER TABLE `pocket_transactions`
  ADD CONSTRAINT `pocket_transactions_ibfk_1` FOREIGN KEY (`pocket_id`) REFERENCES `bonus_pockets` (`pocket_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pocket_transactions_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
