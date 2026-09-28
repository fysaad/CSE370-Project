-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 18, 2026 at 09:20 AM
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
-- Database: `krishidirect`
--

-- --------------------------------------------------------

--
-- Table structure for table `cold_storage`
--

CREATE TABLE `cold_storage` (
  `Storage_ID` int(11) NOT NULL,
  `Manager_ID` int(11) NOT NULL,
  `Facility_Name` varchar(255) NOT NULL,
  `District` varchar(100) NOT NULL,
  `Upazila` varchar(100) NOT NULL,
  `Max_Sack_Capacity` int(11) NOT NULL,
  `Max_Cubic_Meter_Capacity` int(11) NOT NULL,
  `Current_Sack_Count` int(11) DEFAULT 0,
  `Current_Cubic_Meter_Count` int(11) DEFAULT 0
) ;

--
-- Dumping data for table `cold_storage`
--

INSERT INTO `cold_storage` (`Storage_ID`, `Manager_ID`, `Facility_Name`, `District`, `Upazila`, `Max_Sack_Capacity`, `Max_Cubic_Meter_Capacity`, `Current_Sack_Count`, `Current_Cubic_Meter_Count`) VALUES
(1, 204, 'Kushtia Chill Zone', 'Kushtia', 'Kushtia Sadar', 50000, 10000, 0, 0),
(2, 205, 'Pabna Agro Preserve', 'Pabna', 'Ishwardi', 75000, 15000, 0, 0),
(3, 235, 'North Bengal Potato Storage', 'Kushtia', 'Kumarkhali', 100000, 20000, 0, 0),
(4, 244, 'Dhaka City Fresh Hub', 'Dhaka', 'Tejgaon', 20000, 4000, 0, 0),
(5, 809, 'Gopalganj Central Cold Storage', 'Gopalganj', 'Gopalganj Sadar', 50000, 10000, 20, 10);

-- --------------------------------------------------------

--
-- Table structure for table `dhaka_hub`
--

CREATE TABLE `dhaka_hub` (
  `Hub_ID` int(11) NOT NULL,
  `Hub_Name` varchar(255) NOT NULL,
  `Location_Address_Dhaka` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dhaka_hub`
--

INSERT INTO `dhaka_hub` (`Hub_ID`, `Hub_Name`, `Location_Address_Dhaka`) VALUES
(1, 'Tejgaon Central Hub', 'Plot 20, Tejgaon Industrial Area, Dhaka'),
(2, 'Gabtoli Distribution Point', 'Near Gabtoli Bus Terminal, Dhaka'),
(3, 'Jatrabari Wholesale Market Hub', 'Jatrabari, Dhaka'),
(4, 'Uttara Sorting Center', 'Sector 10, Uttara, Dhaka');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `Order_ID` int(11) NOT NULL,
  `Buyer_ID` int(11) NOT NULL,
  `Product_ID` int(11) NOT NULL,
  `Shipment_ID` int(11) DEFAULT NULL,
  `Order_Quantity` decimal(12,2) NOT NULL,
  `Total_Amount` decimal(12,2) NOT NULL,
  `Order_Status` enum('Pending','Paid-Escrow','In-Transit','Completed','Disputed') DEFAULT 'Pending',
  `Order_Date` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`Order_ID`, `Buyer_ID`, `Product_ID`, `Shipment_ID`, `Order_Quantity`, `Total_Amount`, `Order_Status`, `Order_Date`) VALUES
(1, 201, 1, NULL, 200.00, 5700.00, 'Completed', '2023-12-16 04:00:00'),
(2, 203, 1, NULL, 500.00, 14250.00, 'Paid-Escrow', '2023-12-14 05:00:00'),
(3, 207, 3, NULL, 500.00, 32500.00, 'In-Transit', '2023-12-14 03:00:00'),
(4, 211, 4, NULL, 1000.00, 62000.00, 'Completed', '2023-12-14 03:30:00'),
(5, 216, 8, NULL, 50.00, 6000.00, 'Completed', '2023-12-13 08:00:00'),
(6, 201, 6, NULL, 100.00, 3500.00, 'Completed', '2026-08-14 11:33:09'),
(7, 201, 8, NULL, 20.00, 2400.00, 'Completed', '2026-08-14 19:09:46'),
(8, 201, 9, NULL, 5.00, 60.00, 'Completed', '2026-08-15 19:00:31'),
(9, 201, 5, NULL, 50.00, 2250.00, 'Completed', '2026-08-15 19:19:21'),
(10, 248, 10, NULL, 5.00, 150.00, 'Completed', '2026-08-15 19:27:54'),
(11, 248, 11, NULL, 5.00, 150.00, 'Completed', '2026-08-15 19:27:54'),
(12, 248, 9, NULL, 5.00, 60.00, 'Completed', '2026-08-15 19:27:54'),
(13, 201, 9, NULL, 5.00, 60.00, 'Completed', '2026-08-15 19:40:57'),
(14, 201, 12, NULL, 20.00, 600.00, 'Completed', '2026-08-15 19:43:10'),
(15, 201, 13, NULL, 20.00, 600.00, 'Completed', '2026-08-15 19:43:10'),
(16, 201, 10, NULL, 10.00, 300.00, 'Completed', '2026-08-17 16:26:25'),
(17, 201, 1, NULL, 500.00, 14250.00, 'Completed', '2026-08-17 16:27:59'),
(18, 810, 15, NULL, 10.00, 299.70, 'Paid-Escrow', '2026-08-17 16:56:17'),
(19, 201, 15, NULL, 10.00, 299.70, 'Paid-Escrow', '2026-08-18 05:18:51'),
(20, 810, 6, NULL, 1000.00, 35000.00, 'Pending', '2026-08-18 05:30:56'),
(21, 810, 15, NULL, 10.00, 299.70, 'Paid-Escrow', '2026-08-18 06:39:31');

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `Payment_ID` int(11) NOT NULL,
  `Order_ID` int(11) NOT NULL,
  `Transaction_MFS_ID` varchar(255) NOT NULL,
  `Amount` decimal(12,2) NOT NULL,
  `Payment_Status` enum('Held-in-Escrow','Released','Refunded') DEFAULT 'Held-in-Escrow',
  `Paid_At` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`Payment_ID`, `Order_ID`, `Transaction_MFS_ID`, `Amount`, `Payment_Status`, `Paid_At`) VALUES
(1, 2, 'BKASH_TRX_A1B2C3D4', 14250.00, 'Held-in-Escrow', '2023-12-14 05:15:00'),
(2, 3, 'NAGAD_TRX_E5F6G7H8', 32500.00, 'Held-in-Escrow', '2023-12-14 03:10:00'),
(3, 4, 'BKASH_TRX_I9J0K1L2', 62000.00, 'Released', '2023-12-14 03:45:00'),
(4, 5, 'NAGAD_TRX_M3N4O5P6', 6000.00, 'Released', '2023-12-13 08:10:00'),
(5, 6, 'BKASH_TRX_5D47C946', 3500.00, 'Released', '2026-08-14 11:33:16'),
(6, 1, 'BKASH_TRX_9E2E6FA9', 5700.00, 'Released', '2026-08-14 11:33:23'),
(7, 7, 'NAGAD_TRX_0054BAE3', 2400.00, 'Released', '2026-08-14 19:09:58'),
(8, 8, 'BKASH_TRX_85a62d5d0c2b', 60.00, 'Released', '2026-08-15 19:00:31'),
(9, 9, 'NAGAD_TRX_abc477025648', 2250.00, 'Released', '2026-08-15 19:19:21'),
(10, 10, 'BKASH_TRX_813e34f25c1c', 150.00, 'Released', '2026-08-15 19:27:54'),
(11, 11, 'BKASH_TRX_84fde0a5e7aa', 150.00, 'Released', '2026-08-15 19:27:54'),
(12, 12, 'BKASH_TRX_48c9bd9dfb2b', 60.00, 'Released', '2026-08-15 19:27:54'),
(13, 13, 'NAGAD_TRX_06a63f73d442', 60.00, 'Released', '2026-08-15 19:40:57'),
(14, 14, 'NAGAD_TRX_676879c7a051', 600.00, 'Released', '2026-08-15 19:43:10'),
(15, 15, 'NAGAD_TRX_f85f68214dd7', 600.00, 'Released', '2026-08-15 19:43:10'),
(16, 16, 'BKASH_TRX_d42e229196ac', 300.00, 'Released', '2026-08-17 16:26:25'),
(17, 17, 'NAGAD_TRX_02e52c863abb', 14250.00, 'Released', '2026-08-17 16:27:59'),
(18, 18, 'BKASH_TRX_7B3292E9', 299.70, 'Held-in-Escrow', '2026-08-17 16:56:29'),
(19, 19, 'BKASH_TRX_1A34D35A', 299.70, 'Held-in-Escrow', '2026-08-18 05:18:55'),
(20, 21, 'BKASH_TRX_27214456', 299.70, 'Held-in-Escrow', '2026-08-18 06:39:37');

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `Product_ID` int(11) NOT NULL,
  `Farmer_ID` int(11) NOT NULL,
  `Crop_Name` varchar(255) NOT NULL,
  `Total_Quantity` decimal(12,2) NOT NULL,
  `Available_Quantity` decimal(12,2) NOT NULL,
  `Minimum_Order_Quantity` decimal(12,2) NOT NULL,
  `Price_Per_KG` decimal(10,2) NOT NULL,
  `Harvest_Date` date NOT NULL,
  `Expiry_Date` date NOT NULL,
  `Listing_Date` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`Product_ID`, `Farmer_ID`, `Crop_Name`, `Total_Quantity`, `Available_Quantity`, `Minimum_Order_Quantity`, `Price_Per_KG`, `Harvest_Date`, `Expiry_Date`, `Listing_Date`) VALUES
(1, 206, 'Diamond Potato', 5000.00, 4300.00, 500.00, 28.50, '2023-12-01', '2024-05-01', '2026-08-11 02:49:08'),
(2, 208, 'Granola Potato', 12000.00, 12000.00, 1000.00, 26.00, '2023-12-05', '2024-05-05', '2026-08-11 02:49:08'),
(3, 209, 'Local Onion', 3000.00, 2500.00, 200.00, 65.00, '2023-11-20', '2024-02-20', '2026-08-11 02:49:08'),
(4, 210, 'Nasik Onion', 8000.00, 8000.00, 500.00, 62.00, '2023-11-25', '2024-02-25', '2026-08-11 02:49:08'),
(5, 212, 'Brinjal (Eggplant)', 1000.00, 950.00, 50.00, 45.00, '2023-12-10', '2023-12-25', '2026-08-11 02:49:08'),
(6, 215, 'Cauliflower', 2000.00, 700.00, 100.00, 35.00, '2023-12-08', '2023-12-20', '2026-08-11 02:49:08'),
(7, 220, 'Tomato', 4000.00, 4000.00, 100.00, 55.00, '2023-12-15', '2024-01-05', '2026-08-11 02:49:08'),
(8, 224, 'Green Chili', 500.00, 430.00, 20.00, 120.00, '2023-12-12', '2023-12-30', '2026-08-11 02:49:08'),
(9, 206, 'Fulkopi', 1000.00, 985.00, 5.00, 12.00, '2026-08-15', '2026-08-28', '2026-08-14 18:34:31'),
(10, 206, 'Potato', 500.00, 485.00, 5.00, 30.00, '2026-08-15', '2026-08-29', '2026-08-15 19:16:42'),
(11, 255, 'Potato', 500.00, 495.00, 5.00, 30.00, '2026-08-15', '2026-08-29', '2026-08-15 19:25:55'),
(12, 224, 'Onion', 500.00, 480.00, 20.00, 30.00, '2026-08-15', '2026-08-29', '2026-08-15 19:42:22'),
(13, 224, 'Cabbage', 500.00, 480.00, 20.00, 30.00, '2026-08-15', '2026-08-29', '2026-08-15 19:42:32'),
(14, 206, 'KachaKola', 550.00, 550.00, 40.00, 60.00, '2026-08-17', '2026-09-05', '2026-08-17 16:42:21'),
(15, 808, 'Potol', 500.00, 470.00, 5.00, 29.97, '2026-08-17', '2026-09-05', '2026-08-17 16:55:27');

-- --------------------------------------------------------

--
-- Table structure for table `shipment`
--

CREATE TABLE `shipment` (
  `Shipment_ID` int(11) NOT NULL,
  `Driver_ID` int(11) NOT NULL,
  `Farmer_ID` int(11) DEFAULT NULL,
  `Hub_ID` int(11) NOT NULL,
  `Total_Pooled_Weight` decimal(12,2) DEFAULT 0.00,
  `Truck_Plate_Number` varchar(20) NOT NULL,
  `Shipment_Status` enum('Pooling','Dispatched','Arrived_At_Hub') DEFAULT 'Pooling',
  `Dispatched_At` timestamp NULL DEFAULT NULL,
  `Arrived_At_Hub_At` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipment`
--

INSERT INTO `shipment` (`Shipment_ID`, `Driver_ID`, `Farmer_ID`, `Hub_ID`, `Total_Pooled_Weight`, `Truck_Plate_Number`, `Shipment_Status`, `Dispatched_At`, `Arrived_At_Hub_At`) VALUES
(1, 202, 206, 1, 0.00, 'Dhaka-Metro-T-112233', 'Pooling', NULL, NULL),
(2, 213, 208, 1, 5200.00, 'Sylhet-Metro-T-44556', 'Dispatched', '2023-12-15 02:00:00', NULL),
(3, 222, 209, 2, 7100.00, 'Pabna-Metro-T-778899', 'Arrived_At_Hub', '2023-12-14 03:00:00', '2023-12-14 11:00:00'),
(4, 225, 210, 3, 4800.00, 'Chitta-Metro-T-33445', 'Arrived_At_Hub', '2023-12-13 04:00:00', '2023-12-14 00:00:00'),
(5, 230, 221, 4, 6500.00, 'Barisal-Metro-T-9988', 'Dispatched', '2023-12-16 01:30:00', NULL),
(6, 236, 240, 1, 0.00, 'Dinajpur-Metro-T-554', 'Pooling', NULL, NULL),
(7, 260, 256, 2, 8200.00, 'Kushtia-Metro-T-1234', 'Arrived_At_Hub', '2023-12-15 00:00:00', '2023-12-15 09:30:00'),
(8, 278, 258, 3, 4100.00, 'Jessore-Metro-T-6543', 'Dispatched', '2023-12-16 04:15:00', NULL),
(9, 288, 270, 4, 9300.00, 'Rajshahi-Metro-T-789', 'Arrived_At_Hub', '2023-12-14 06:00:00', '2023-12-14 15:45:00');

-- --------------------------------------------------------

--
-- Table structure for table `storage_booking`
--

CREATE TABLE `storage_booking` (
  `Booking_ID` int(11) NOT NULL,
  `Storage_ID` int(11) NOT NULL,
  `Farmer_ID` int(11) NOT NULL,
  `Sacks_To_Store` int(11) NOT NULL,
  `Cubic_Meters_To_Occupy` int(11) NOT NULL,
  `Booking_Date` date NOT NULL,
  `Status` enum('Active','Checked-Out') DEFAULT 'Active'
) ;

--
-- Dumping data for table `storage_booking`
--

INSERT INTO `storage_booking` (`Booking_ID`, `Storage_ID`, `Farmer_ID`, `Sacks_To_Store`, `Cubic_Meters_To_Occupy`, `Booking_Date`, `Status`) VALUES
(1, 1, 206, 1000, 200, '2023-12-05', 'Active'),
(2, 3, 210, 2000, 400, '2023-12-07', 'Active'),
(3, 2, 217, 1500, 300, '2023-12-08', 'Active'),
(4, 3, 206, 1000, 96, '2026-08-14', 'Active'),
(5, 3, 206, 100, 2, '2026-08-14', ''),
(6, 1, 206, 500, 50, '2026-08-17', ''),
(7, 1, 808, 5000, 50, '2026-08-17', ''),
(8, 5, 808, 500, 10, '2026-08-17', 'Active'),
(9, 5, 206, 100, 10, '2026-08-18', '');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `User_ID` int(11) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Phone_Number` varchar(20) NOT NULL,
  `Role` varchar(255) NOT NULL,
  `District` varchar(100) NOT NULL,
  `Upazila` varchar(100) NOT NULL,
  `NID_Number` varchar(17) DEFAULT NULL,
  `UAO_Verification_Token` varchar(100) DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  `Password` varchar(255) NOT NULL DEFAULT '1234'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `Name`, `Phone_Number`, `Role`, `District`, `Upazila`, `NID_Number`, `UAO_Verification_Token`, `Created_At`, `Password`) VALUES
(201, 'Mia Ariful Miah', '01543015624', 'Buyer', 'Comilla', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(202, 'Khan Rubel Uddin', '01472561022', 'Truck Driver', 'Dhaka', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(203, 'Sarker Alamgir Ali', '01931025140', 'Buyer', 'Noakhali', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(204, 'Mohammad Alim Rahman', '01311365958', 'Manager', 'Kushtia', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(205, 'Chowdhury Shahid Islam', '01342057229', 'Manager', 'Pabna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(206, 'Sarker Shahid Ali', '01489047632', 'Farmer', 'Kushtia', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(207, 'Abdul Karim Rahman', '01712892132', 'Buyer', 'Rangpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(208, 'Mia Biplob Islam', '01858474206', 'Farmer', 'Comilla', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(209, 'Kazi Ariful Ahmed', '01827596821', 'Farmer', 'Sylhet', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(210, 'Mohammad Sujon Ahmed', '01713044237', 'Farmer', 'Rangpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(211, 'Abdul Nazmul Islam', '01696869862', 'Buyer', 'Jessore', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(212, 'Mohammad Alim Islam', '01798860528', 'Farmer', 'Barisal', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(213, 'Begum Jamal Uddin', '01415594870', 'Truck Driver', 'Sylhet', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(214, 'Mohammad Tariq Ahmed', '01585435204', 'Buyer', 'Noakhali', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(215, 'Mia Kabir Khan', '01624232191', 'Farmer', 'Rajshahi', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(216, 'Abdul Kamal Khan', '01311728434', 'Buyer', 'Dhaka', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(217, 'Mia Nazmul Rahman', '01736928039', 'Farmer', 'Pabna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(218, 'Kazi Tanvir Ahmed', '01480110300', 'Farmer', 'Barisal', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(219, 'Mohammad Hasan Khan', '01720882729', 'Farmer', 'Bogra', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(220, 'Sarker Kamal Miah', '01446612772', 'Farmer', 'Khulna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(221, 'Begum Rubel Khan', '01774751921', 'Farmer', 'Dinajpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(222, 'Kazi Rubel Chowdhury', '01324888165', 'Truck Driver', 'Pabna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(223, 'Kazi Rafiq Rahman', '01450097203', 'Farmer', 'Khulna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(224, 'Mia Alim Chowdhury', '01318534355', 'Farmer', 'Dhaka', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(225, 'Khan Kamal Ahmed', '01659207179', 'Truck Driver', 'Noakhali', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(226, 'Abdul Sujon Hossain', '01719543261', 'Farmer', 'Jessore', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(227, 'Begum Jamal Uddin', '01541763184', 'Truck Driver', 'Dhaka', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(228, 'Begum Hasan Ali', '01332567482', 'Buyer', 'Mymensingh', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(229, 'Md. Tariq Khan', '01692963302', 'Buyer', 'Khulna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(230, 'Sarker Ariful Chowdhury', '01455340495', 'Truck Driver', 'Barisal', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(231, 'Mohammad Alamgir Khan', '01943928238', 'Buyer', 'Comilla', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(232, 'Begum Rubel Hossain', '01335589779', 'Farmer', 'Dinajpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(233, 'Md. Rubel Miah', '01894248750', 'Farmer', 'Mymensingh', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(234, 'Mohammad Hasan Uddin', '01789794176', 'Buyer', 'Jessore', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(235, 'Khan Ariful Ahmed', '01571179291', 'Manager', 'Kushtia', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(236, 'Sheikh Sujon Ahmed', '01678986939', 'Truck Driver', 'Dinajpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(237, 'Kazi Jamal Rahman', '01789923089', 'Buyer', 'Khulna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(238, 'Abdul Karim Ali', '01370329330', 'Truck Driver', 'Barisal', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(239, 'Abdul Jahangir Uddin', '01361967269', 'Buyer', 'Noakhali', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(240, 'Chowdhury Rahim Rahman', '01376804720', 'Farmer', 'Khulna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(241, 'Khan Tariq Chowdhury', '01848859673', 'Buyer', 'Chittagong', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(242, 'Kazi Jamal Chowdhury', '01349128785', 'Farmer', 'Sylhet', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(243, 'Sarker Ariful Ali', '01838034280', 'Farmer', 'Pabna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(244, 'Md. Tanvir Miah', '01748627526', 'Manager', 'Dhaka', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(245, 'Mohammad Hasan Islam', '01383099316', 'Farmer', 'Pabna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(246, 'Abdul Biplob Uddin', '01954505317', 'Farmer', 'Jessore', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(247, 'Md. Ariful Islam', '01925282155', 'Buyer', 'Barisal', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(248, 'Mohammad Nazmul Ahmed', '01477407267', 'Buyer', 'Kushtia', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(249, 'Sheikh Rafiq Rahman', '01311757659', 'Farmer', 'Noakhali', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(250, 'Chowdhury Rafiq Hossain', '01825342575', 'Buyer', 'Pabna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(251, 'Chowdhury Rahim Miah', '01851041967', 'Farmer', 'Comilla', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(252, 'Khan Biplob Miah', '01351149880', 'Farmer', 'Chittagong', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(253, 'Sarker Kabir Miah', '01448095793', 'Buyer', 'Mymensingh', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(254, 'Chowdhury Karim Rahman', '01918477258', 'Buyer', 'Rangpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(255, 'Abdul Alim Ahmed', '01724573960', 'Farmer', 'Barisal', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(256, 'Sheikh Hasan Hossain', '01370251493', 'Farmer', 'Pabna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(257, 'Begum Tariq Khan', '01612970721', 'Buyer', 'Dinajpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(258, 'Kazi Alamgir Khan', '01763410446', 'Farmer', 'Kushtia', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(259, 'Md. Kabir Ahmed', '01740594338', 'Buyer', 'Jessore', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(260, 'Kazi Karim Rahman', '01374538473', 'Truck Driver', 'Kushtia', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(261, 'Md. Tariq Ali', '01635562541', 'Farmer', 'Jessore', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(262, 'Chowdhury Karim Khan', '01358983541', 'Buyer', 'Rangpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(263, 'Abdul Sujon Khan', '01916508963', 'Farmer', 'Rangpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(264, 'Khan Jahangir Islam', '01978737979', 'Farmer', 'Dinajpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(265, 'Sheikh Karim Khan', '01625032029', 'Manager', 'Dinajpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(266, 'Mia Nazmul Ali', '01571073730', 'Buyer', 'Dinajpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(267, 'Begum Sujon Islam', '01767196097', 'Manager', 'Dinajpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(268, 'Kazi Jahangir Miah', '01685463473', 'Farmer', 'Dhaka', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(269, 'Chowdhury Alamgir Ahmed', '01894567296', 'Truck Driver', 'Kushtia', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(270, 'Md. Rafiq Islam', '01960262388', 'Farmer', 'Pabna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(271, 'Mohammad Nazmul Ali', '01791896198', 'Buyer', 'Dinajpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(272, 'Mia Alim Uddin', '01557760841', 'Buyer', 'Pabna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(273, 'Begum Sabbir Rahman', '01911265535', 'Truck Driver', 'Barisal', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(274, 'Khan Tanvir Miah', '01396304926', 'Buyer', 'Chittagong', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(275, 'Sarker Rubel Islam', '01719938756', 'Manager', 'Jessore', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(276, 'Begum Nazmul Rahman', '01595792986', 'Buyer', 'Noakhali', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(277, 'Kazi Karim Ali', '01670936851', 'Farmer', 'Mymensingh', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(278, 'Abdul Ariful Ahmed', '01760694656', 'Truck Driver', 'Jessore', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(279, 'Kazi Tariq Miah', '01667797336', 'Manager', 'Kushtia', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(280, 'Md. Jahangir Hossain', '01821490700', 'Buyer', 'Rangpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(281, 'Md. Tariq Miah', '01976228727', 'Farmer', 'Khulna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(282, 'Md. Jamal Uddin', '01569680937', 'Buyer', 'Sylhet', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(283, 'Begum Alim Chowdhury', '01925855406', 'Farmer', 'Khulna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(284, 'Mia Rafiq Islam', '01359564722', 'Farmer', 'Dhaka', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(285, 'Kazi Rahim Uddin', '01945458828', 'Farmer', 'Noakhali', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(286, 'Mia Alim Rahman', '01782872707', 'Farmer', 'Jessore', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(287, 'Abdul Kamal Rahman', '01833173700', 'Truck Driver', 'Kushtia', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(288, 'Mohammad Sumon Ahmed', '01940381809', 'Truck Driver', 'Rajshahi', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(289, 'Sarker Tariq Rahman', '01915049751', 'Farmer', 'Jessore', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(290, 'Abdul Rahim Hossain', '01873362050', 'Buyer', 'Sylhet', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(291, 'Kazi Alamgir Uddin', '01481615353', 'Buyer', 'Jessore', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(292, 'Mia Rahim Chowdhury', '01910955347', 'Buyer', 'Pabna', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(293, 'Kazi Sujon Uddin', '01848543801', 'Buyer', 'Kushtia', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(294, 'Chowdhury Hasan Ahmed', '01595485246', 'Buyer', 'Comilla', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(295, 'Begum Tanvir Hossain', '01546874813', 'Truck Driver', 'Kushtia', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(296, 'Mia Ariful Hossain', '01311602864', 'Buyer', 'Bogra', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(297, 'Abdul Sabbir Islam', '01393179110', 'Manager', 'Chittagong', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(298, 'Begum Tariq Islam', '01434213403', 'Farmer', 'Dhaka', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(299, 'Kazi Biplob Rahman', '01491832228', 'Manager', 'Dinajpur', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(300, 'Abdul Tanvir Hossain', '01882617008', 'Buyer', 'Bogra', '', NULL, NULL, '2026-08-11 02:49:08', '1234'),
(808, 'FYAZ AHMAD SAAD', '01518697381', 'Farmer', 'Tangail', '', NULL, NULL, '2026-08-17 16:47:28', '$2y$10$ASqtryCkQOcjJREpsS5F.eGgyqPcnHjxwwdZifCjQXcgijInBxVIO'),
(809, 'Naim Ahmed', '01873672978', 'Manager', 'Gopalganj', '', NULL, NULL, '2026-08-17 16:51:24', '$2y$10$nPSDMa/ieq.vzK/dftBSzuFROnIxtziRuUWKnv0vFTg.E6MUCg7jy'),
(810, 'Nusrat Upama', '01932512153', 'Buyer', 'Dhaka', '', NULL, NULL, '2026-08-17 16:53:53', '$2y$10$BNVUUpBd/hQXe3sA/NtIm.A1YZj/QVrNRBN9x586nJxN7qRwCTAOK');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cold_storage`
--
ALTER TABLE `cold_storage`
  ADD PRIMARY KEY (`Storage_ID`),
  ADD KEY `fk_storage_manager` (`Manager_ID`);

--
-- Indexes for table `dhaka_hub`
--
ALTER TABLE `dhaka_hub`
  ADD PRIMARY KEY (`Hub_ID`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`Order_ID`),
  ADD KEY `fk_order_buyer` (`Buyer_ID`),
  ADD KEY `fk_order_product` (`Product_ID`),
  ADD KEY `fk_order_shipment` (`Shipment_ID`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`Payment_ID`),
  ADD UNIQUE KEY `Order_ID` (`Order_ID`),
  ADD UNIQUE KEY `Transaction_MFS_ID` (`Transaction_MFS_ID`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`Product_ID`),
  ADD KEY `fk_product_farmer` (`Farmer_ID`);

--
-- Indexes for table `shipment`
--
ALTER TABLE `shipment`
  ADD PRIMARY KEY (`Shipment_ID`),
  ADD KEY `fk_shipment_driver` (`Driver_ID`),
  ADD KEY `fk_shipment_hub` (`Hub_ID`),
  ADD KEY `fk_shipment_farmer` (`Farmer_ID`);

--
-- Indexes for table `storage_booking`
--
ALTER TABLE `storage_booking`
  ADD PRIMARY KEY (`Booking_ID`),
  ADD KEY `fk_booking_storage` (`Storage_ID`),
  ADD KEY `fk_booking_farmer` (`Farmer_ID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `Phone_Number` (`Phone_Number`),
  ADD UNIQUE KEY `NID_Number` (`NID_Number`),
  ADD UNIQUE KEY `UAO_Verification_Token` (`UAO_Verification_Token`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cold_storage`
--
ALTER TABLE `cold_storage`
  MODIFY `Storage_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dhaka_hub`
--
ALTER TABLE `dhaka_hub`
  MODIFY `Hub_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `Order_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `Payment_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `Product_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shipment`
--
ALTER TABLE `shipment`
  MODIFY `Shipment_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `storage_booking`
--
ALTER TABLE `storage_booking`
  MODIFY `Booking_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=811;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cold_storage`
--
ALTER TABLE `cold_storage`
  ADD CONSTRAINT `fk_storage_manager` FOREIGN KEY (`Manager_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_buyer` FOREIGN KEY (`Buyer_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_product` FOREIGN KEY (`Product_ID`) REFERENCES `product` (`Product_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_shipment` FOREIGN KEY (`Shipment_ID`) REFERENCES `shipment` (`Shipment_ID`) ON DELETE SET NULL;

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `fk_payment_order` FOREIGN KEY (`Order_ID`) REFERENCES `orders` (`Order_ID`) ON DELETE CASCADE;

--
-- Constraints for table `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `fk_product_farmer` FOREIGN KEY (`Farmer_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `shipment`
--
ALTER TABLE `shipment`
  ADD CONSTRAINT `fk_shipment_driver` FOREIGN KEY (`Driver_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shipment_farmer` FOREIGN KEY (`Farmer_ID`) REFERENCES `users` (`User_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_shipment_hub` FOREIGN KEY (`Hub_ID`) REFERENCES `dhaka_hub` (`Hub_ID`) ON DELETE CASCADE;

--
-- Constraints for table `storage_booking`
--
ALTER TABLE `storage_booking`
  ADD CONSTRAINT `fk_booking_farmer` FOREIGN KEY (`Farmer_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_storage` FOREIGN KEY (`Storage_ID`) REFERENCES `cold_storage` (`Storage_ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
