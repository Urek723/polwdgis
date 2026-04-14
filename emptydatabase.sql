-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 14, 2026 at 11:06 AM
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
-- Database: `polwdgis`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` varchar(50) DEFAULT NULL,
  `details` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_messages`
--

CREATE TABLE `chatbot_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL,
  `sender` enum('user','bot') NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_sessions`
--

CREATE TABLE `chatbot_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_token` varchar(100) DEFAULT NULL,
  `consumer_id` int(10) UNSIGNED DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `communication_history`
--

CREATE TABLE `communication_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `consumer_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `channel` enum('System','Email','SMS','Phone','In-Person','Portal') DEFAULT 'System',
  `direction` enum('Inbound','Outbound') DEFAULT 'Outbound',
  `subject` varchar(200) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `related_request_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consumers`
--

CREATE TABLE `consumers` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` varchar(50) NOT NULL,
  `account_no` varchar(50) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('Residential','Commercial','Government') NOT NULL DEFAULT 'Residential',
  `status` enum('Active','Disconnected','Pending') NOT NULL DEFAULT 'Active',
  `address` text DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `municipal` varchar(100) DEFAULT NULL,
  `zone` varchar(50) DEFAULT NULL,
  `book` varchar(50) DEFAULT NULL,
  `service_connection_no` varchar(50) DEFAULT NULL,
  `contact_no` varchar(30) DEFAULT NULL,
  `secondary_no` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `male_users` smallint(6) DEFAULT 0,
  `female_users` smallint(6) DEFAULT 0,
  `total_users` smallint(6) DEFAULT 0,
  `is_senior` tinyint(1) DEFAULT 0,
  `meter_brand` varchar(100) DEFAULT NULL,
  `meter_number` varchar(100) DEFAULT NULL,
  `x_utm` decimal(14,4) DEFAULT NULL,
  `y_utm` decimal(14,4) DEFAULT NULL,
  `elevation` decimal(10,4) DEFAULT NULL,
  `latitude` decimal(11,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'linked system user',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `installation_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consumers_auth`
--

CREATE TABLE `consumers_auth` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `contact_number` varchar(30) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consumer_requests`
--

CREATE TABLE `consumer_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `consumer_id` int(10) UNSIGNED NOT NULL,
  `consumer_auth_id` int(10) UNSIGNED DEFAULT NULL,
  `request_type` enum('New Connection','Disconnection','Reconnection','Repair','Billing Dispute','Other') NOT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `latitude` decimal(11,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `location_text` varchar(255) DEFAULT NULL,
  `status` enum('Submitted','Under Review','In Progress','Resolved','Closed') DEFAULT 'Submitted',
  `priority` enum('Low','Normal','High') DEFAULT 'Normal',
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consumption_records`
--

CREATE TABLE `consumption_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `consumer_id` int(10) UNSIGNED NOT NULL,
  `meter_id` int(10) UNSIGNED DEFAULT NULL,
  `billing_month` date NOT NULL COMMENT 'YYYY-MM-01',
  `reading_prev` decimal(10,2) DEFAULT 0.00,
  `reading_curr` decimal(10,2) DEFAULT 0.00,
  `consumption_m3` decimal(10,2) GENERATED ALWAYS AS (`reading_curr` - `reading_prev`) STORED,
  `is_alert` tinyint(1) DEFAULT 0 COMMENT 'exceeding threshold',
  `alert_threshold` decimal(10,2) DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `csv_imports`
--

CREATE TABLE `csv_imports` (
  `id` int(10) UNSIGNED NOT NULL,
  `filename` varchar(255) NOT NULL,
  `table_target` varchar(100) DEFAULT NULL,
  `total_rows` int(11) DEFAULT 0,
  `imported_rows` int(11) DEFAULT 0,
  `failed_rows` int(11) DEFAULT 0,
  `status` enum('Pending','Processing','Completed','Failed') DEFAULT 'Pending',
  `error_log` text DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deterioration_alerts`
--

CREATE TABLE `deterioration_alerts` (
  `id` int(10) UNSIGNED NOT NULL,
  `pipeline_id` int(10) UNSIGNED DEFAULT NULL,
  `infrastructure_id` int(10) UNSIGNED DEFAULT NULL,
  `alert_type` varchar(100) DEFAULT NULL,
  `severity` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `description` text DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `age_years` int(11) DEFAULT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_by` int(10) UNSIGNED DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_templates`
--

CREATE TABLE `document_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `template_content` longtext DEFAULT NULL COMMENT 'HTML template with {{placeholders}}',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergency_incidents`
--

CREATE TABLE `emergency_incidents` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` enum('Pipeline Break','Pump Failure','Contamination','Flood','Other') NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `severity` enum('Low','Medium','High','Critical') DEFAULT 'High',
  `status` enum('Open','Responding','Resolved') DEFAULT 'Open',
  `latitude` decimal(11,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `location_text` text DEFAULT NULL,
  `reported_by` int(10) UNSIGNED DEFAULT NULL,
  `assigned_team` text DEFAULT NULL,
  `response_notes` text DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `generated_documents`
--

CREATE TABLE `generated_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `template_id` int(10) UNSIGNED DEFAULT NULL,
  `consumer_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `generated_by` int(10) UNSIGNED DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `infrastructure`
--

CREATE TABLE `infrastructure` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` enum('pumping_station','reservoir','valve','hydrant','blowoff','meter_chamber','other') NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `latitude` decimal(11,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `installation_date` date DEFAULT NULL,
  `last_inspection` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `quantity_in_stock` decimal(10,2) DEFAULT 0.00,
  `reorder_level` decimal(10,2) DEFAULT 0.00,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `supplier` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `transaction_type` enum('In','Out','Adjustment') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `reference` varchar(100) DEFAULT NULL COMMENT 'work order or PO number',
  `notes` text DEFAULT NULL,
  `transacted_by` int(10) UNSIGNED DEFAULT NULL,
  `transacted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_schedule`
--

CREATE TABLE `maintenance_schedule` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `frequency` enum('Daily','Weekly','Monthly','Quarterly','Annual','Once') NOT NULL,
  `next_due` date NOT NULL,
  `infrastructure_id` int(10) UNSIGNED DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `consumer_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('interruption','alert','reminder','message','system') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parcels`
--

CREATE TABLE `parcels` (
  `id` int(10) UNSIGNED NOT NULL,
  `parcel_code` varchar(50) DEFAULT NULL,
  `owner_name` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `area_sqm` decimal(12,2) DEFAULT NULL,
  `boundary_geojson` longtext DEFAULT NULL COMMENT 'GeoJSON polygon',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pipelines`
--

CREATE TABLE `pipelines` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `pipeline_type` enum('Transmission','Distribution','Service Line') NOT NULL DEFAULT 'Distribution',
  `material` enum('PVC','HDPE','Steel','GI','GIP','PE','SSP','CLCC Steel','PVC-O','UPBC','other') NOT NULL,
  `diameter_mm` int(11) DEFAULT NULL,
  `pressure_class` enum('Low','Medium','High','Very High') DEFAULT 'Medium',
  `length_m` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive','rehabilitation','new') NOT NULL DEFAULT 'active',
  `installation_date` date DEFAULT NULL,
  `installation_year` year(4) DEFAULT NULL,
  `last_inspection_date` date DEFAULT NULL,
  `condition_rating` enum('Excellent','Good','Fair','Poor','Critical') DEFAULT 'Good',
  `path_geojson` longtext DEFAULT NULL COMMENT 'GeoJSON LineString',
  `barangay` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `flow_rate_lps` decimal(8,2) DEFAULT NULL COMMENT 'Litres per second design flow rate',
  `operating_pressure_bar` decimal(6,2) DEFAULT NULL,
  `max_pressure_bar` decimal(6,2) DEFAULT NULL,
  `coating` varchar(100) DEFAULT NULL COMMENT 'Pipe coating/lining type',
  `joint_type` varchar(100) DEFAULT NULL,
  `zone_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Distribution zone',
  `is_flagged` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flagged for inspection/maintenance',
  `flag_reason` text DEFAULT NULL,
  `status_change_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Auto-tracked status change count'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `pipelines`
--
DELIMITER $$
CREATE TRIGGER `trg_pipeline_before_update` BEFORE UPDATE ON `pipelines` FOR EACH ROW BEGIN
  -- Log status changes
  IF OLD.status != NEW.status THEN
    INSERT INTO pipeline_history
      (pipeline_id, change_type, field_changed, old_value, new_value, reason)
    VALUES
      (OLD.id, 'status_change', 'status', OLD.status, NEW.status, 'Trigger-logged');
    SET NEW.status_change_count = OLD.status_change_count + 1;
  END IF;

  -- Log material changes
  IF OLD.material != NEW.material THEN
    INSERT INTO pipeline_history
      (pipeline_id, change_type, field_changed, old_value, new_value, reason)
    VALUES
      (OLD.id, 'material_change', 'material', OLD.material, NEW.material, 'Trigger-logged');
  END IF;

  -- Log diameter changes
  IF OLD.diameter_mm != NEW.diameter_mm THEN
    INSERT INTO pipeline_history
      (pipeline_id, change_type, field_changed, old_value, new_value, reason)
    VALUES
      (OLD.id, 'diameter_change', 'diameter_mm',
       CAST(OLD.diameter_mm AS CHAR), CAST(NEW.diameter_mm AS CHAR), 'Trigger-logged');
  END IF;

  -- Log condition rating changes
  IF (OLD.condition_rating IS NULL AND NEW.condition_rating IS NOT NULL)
     OR (OLD.condition_rating != NEW.condition_rating) THEN
    INSERT INTO pipeline_history
      (pipeline_id, change_type, field_changed, old_value, new_value, reason)
    VALUES
      (OLD.id, 'other', 'condition_rating',
       COALESCE(OLD.condition_rating, 'NULL'),
       COALESCE(NEW.condition_rating, 'NULL'), 'Trigger-logged');
  END IF;

  -- Log pressure class changes
  IF (OLD.pressure_class IS NULL AND NEW.pressure_class IS NOT NULL)
     OR OLD.pressure_class != NEW.pressure_class THEN
    INSERT INTO pipeline_history
      (pipeline_id, change_type, field_changed, old_value, new_value, reason)
    VALUES
      (OLD.id, 'other', 'pressure_class',
       COALESCE(OLD.pressure_class,'NULL'),
       COALESCE(NEW.pressure_class,'NULL'), 'Trigger-logged');
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `pipeline_history`
--

CREATE TABLE `pipeline_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `pipeline_id` int(10) UNSIGNED NOT NULL,
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `change_type` enum('status_change','material_change','diameter_change','path_change','other') DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `field_changed` varchar(100) DEFAULT NULL COMMENT 'Which specific field was changed',
  `session_id` varchar(100) DEFAULT NULL COMMENT 'Session/transaction grouping',
  `ip_address` varchar(45) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional context as JSON' CHECK (json_valid(`metadata`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pipeline_inspection_checklist`
--

CREATE TABLE `pipeline_inspection_checklist` (
  `id` int(10) UNSIGNED NOT NULL,
  `pipeline_id` int(10) UNSIGNED NOT NULL,
  `inspected_at` datetime NOT NULL DEFAULT current_timestamp(),
  `inspector_id` int(10) UNSIGNED DEFAULT NULL,
  `no_visible_leaks` tinyint(1) DEFAULT 0,
  `corrosion_observed` tinyint(1) DEFAULT 0,
  `joint_integrity_ok` tinyint(1) DEFAULT 0,
  `pressure_within_range` tinyint(1) DEFAULT 0,
  `valve_accessible` tinyint(1) DEFAULT 0,
  `cathodic_protection` tinyint(1) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `overall_rating` enum('Excellent','Good','Fair','Poor','Critical') DEFAULT 'Good'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pipeline_maintenance_events`
--

CREATE TABLE `pipeline_maintenance_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `pipeline_id` int(10) UNSIGNED NOT NULL,
  `event_type` enum('Inspection','Repair','Replacement','Cleaning','Pressure Test','Leak Detection','Valve Operation','Other') NOT NULL,
  `event_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `cost_php` decimal(12,2) DEFAULT NULL,
  `work_order_id` int(10) UNSIGNED DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `performed_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'users.id',
  `next_due_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pipeline_zones`
--

CREATE TABLE `pipeline_zones` (
  `id` int(10) UNSIGNED NOT NULL,
  `zone_code` varchar(20) NOT NULL,
  `zone_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#0057ff' COMMENT 'Hex color for map display',
  `barangays` text DEFAULT NULL COMMENT 'Comma-separated barangay list',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(150) NOT NULL,
  `role` enum('Admin','Staff','Consumer') NOT NULL DEFAULT 'Staff',
  `section` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_pipeline_risk_assessment`
-- (See below for the actual view)
--
CREATE TABLE `v_pipeline_risk_assessment` (
`id` int(10) unsigned
,`name` varchar(150)
,`pipeline_type` enum('Transmission','Distribution','Service Line')
,`material` enum('PVC','HDPE','Steel','GI','GIP','PE','SSP','CLCC Steel','PVC-O','UPBC','other')
,`diameter_mm` int(11)
,`status` enum('active','inactive','rehabilitation','new')
,`condition_rating` enum('Excellent','Good','Fair','Poor','Critical')
,`is_flagged` tinyint(1)
,`barangay` varchar(100)
,`installation_date` date
,`age_years` int(5)
,`status_change_count` smallint(5) unsigned
,`total_history_events` bigint(21)
,`status_changes_6mo` decimal(22,0)
,`last_change_date` datetime
,`risk_score` int(9) unsigned
);

-- --------------------------------------------------------

--
-- Table structure for table `water_interruptions`
--

CREATE TABLE `water_interruptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `affected_barangays` text DEFAULT NULL COMMENT 'comma-separated',
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `status` enum('Scheduled','Ongoing','Resolved') DEFAULT 'Scheduled',
  `notification_sent` tinyint(1) DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `water_meters`
--

CREATE TABLE `water_meters` (
  `id` int(10) UNSIGNED NOT NULL,
  `consumer_id` int(10) UNSIGNED NOT NULL,
  `meter_no` varchar(80) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `latitude` decimal(11,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('active','defective','removed') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_orders`
--

CREATE TABLE `work_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('Mainline','Serviceline','Pump','Valve','Reservoir','Electrical','Other') NOT NULL,
  `priority` enum('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
  `status` enum('Pending','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `location` text DEFAULT NULL,
  `latitude` decimal(11,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `downtime_minutes` int(11) DEFAULT 0,
  `cause` text DEFAULT NULL,
  `resolution` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_checklist`
--

CREATE TABLE `work_order_checklist` (
  `id` int(10) UNSIGNED NOT NULL,
  `work_order_id` int(10) UNSIGNED NOT NULL,
  `item` varchar(300) NOT NULL,
  `is_done` tinyint(1) DEFAULT 0,
  `done_by` int(10) UNSIGNED DEFAULT NULL,
  `done_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_order_updates`
--

CREATE TABLE `work_order_updates` (
  `id` int(10) UNSIGNED NOT NULL,
  `work_order_id` int(10) UNSIGNED NOT NULL,
  `note` text NOT NULL,
  `status_change` varchar(50) DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure for view `v_pipeline_risk_assessment`
--
DROP TABLE IF EXISTS `v_pipeline_risk_assessment`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pipeline_risk_assessment`  AS SELECT `p`.`id` AS `id`, `p`.`name` AS `name`, `p`.`pipeline_type` AS `pipeline_type`, `p`.`material` AS `material`, `p`.`diameter_mm` AS `diameter_mm`, `p`.`status` AS `status`, `p`.`condition_rating` AS `condition_rating`, `p`.`is_flagged` AS `is_flagged`, `p`.`barangay` AS `barangay`, `p`.`installation_date` AS `installation_date`, year(current_timestamp()) - year(`p`.`installation_date`) AS `age_years`, `p`.`status_change_count` AS `status_change_count`, count(`h`.`id`) AS `total_history_events`, sum(case when `h`.`change_type` = 'status_change' and `h`.`changed_at` >= current_timestamp() - interval 6 month then 1 else 0 end) AS `status_changes_6mo`, max(`h`.`changed_at`) AS `last_change_date`, coalesce(year(current_timestamp()) - year(`p`.`installation_date`),0) * 2 + `p`.`status_change_count` * 5 + CASE `p`.`condition_rating` WHEN 'Critical' THEN 50 WHEN 'Poor' THEN 30 WHEN 'Fair' THEN 15 WHEN 'Good' THEN 5 ELSE 0 END+ CASE `p`.`material` WHEN 'Steel' THEN 10 WHEN 'GI' THEN 8 WHEN 'HDPE' THEN 2 ELSE 5 END AS `risk_score` FROM (`pipelines` `p` left join `pipeline_history` `h` on(`h`.`pipeline_id` = `p`.`id`)) WHERE `p`.`installation_date` is not null GROUP BY `p`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chatbot_messages`
--
ALTER TABLE `chatbot_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `chatbot_sessions`
--
ALTER TABLE `chatbot_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`);

--
-- Indexes for table `communication_history`
--
ALTER TABLE `communication_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consumer_id` (`consumer_id`);

--
-- Indexes for table `consumers`
--
ALTER TABLE `consumers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_id` (`account_id`),
  ADD KEY `idx_consumers_latlon` (`latitude`,`longitude`);

--
-- Indexes for table `consumers_auth`
--
ALTER TABLE `consumers_auth`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_number` (`account_number`);

--
-- Indexes for table `consumer_requests`
--
ALTER TABLE `consumer_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consumer_id` (`consumer_id`);

--
-- Indexes for table `consumption_records`
--
ALTER TABLE `consumption_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consumer_id` (`consumer_id`);

--
-- Indexes for table `csv_imports`
--
ALTER TABLE `csv_imports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `deterioration_alerts`
--
ALTER TABLE `deterioration_alerts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `document_templates`
--
ALTER TABLE `document_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `emergency_incidents`
--
ALTER TABLE `emergency_incidents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `generated_documents`
--
ALTER TABLE `generated_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `infrastructure`
--
ALTER TABLE `infrastructure`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcels`
--
ALTER TABLE `parcels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `parcel_code` (`parcel_code`);

--
-- Indexes for table `pipelines`
--
ALTER TABLE `pipelines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pipelines_type_status` (`pipeline_type`,`status`),
  ADD KEY `idx_pipelines_material` (`material`),
  ADD KEY `idx_pipelines_flagged` (`is_flagged`);

--
-- Indexes for table `pipeline_history`
--
ALTER TABLE `pipeline_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pipeline_id` (`pipeline_id`),
  ADD KEY `idx_ph_field_changed` (`field_changed`,`changed_at`);

--
-- Indexes for table `pipeline_inspection_checklist`
--
ALTER TABLE `pipeline_inspection_checklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pipeline_id` (`pipeline_id`);

--
-- Indexes for table `pipeline_maintenance_events`
--
ALTER TABLE `pipeline_maintenance_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pipeline_id` (`pipeline_id`),
  ADD KEY `event_date` (`event_date`);

--
-- Indexes for table `pipeline_zones`
--
ALTER TABLE `pipeline_zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `zone_code` (`zone_code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `water_interruptions`
--
ALTER TABLE `water_interruptions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `water_meters`
--
ALTER TABLE `water_meters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `meter_no` (`meter_no`),
  ADD KEY `consumer_id` (`consumer_id`);

--
-- Indexes for table `work_orders`
--
ALTER TABLE `work_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `work_order_checklist`
--
ALTER TABLE `work_order_checklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_order_id` (`work_order_id`);

--
-- Indexes for table `work_order_updates`
--
ALTER TABLE `work_order_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_order_id` (`work_order_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chatbot_messages`
--
ALTER TABLE `chatbot_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chatbot_sessions`
--
ALTER TABLE `chatbot_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `communication_history`
--
ALTER TABLE `communication_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consumers`
--
ALTER TABLE `consumers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consumers_auth`
--
ALTER TABLE `consumers_auth`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consumer_requests`
--
ALTER TABLE `consumer_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consumption_records`
--
ALTER TABLE `consumption_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `csv_imports`
--
ALTER TABLE `csv_imports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deterioration_alerts`
--
ALTER TABLE `deterioration_alerts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_templates`
--
ALTER TABLE `document_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emergency_incidents`
--
ALTER TABLE `emergency_incidents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generated_documents`
--
ALTER TABLE `generated_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `infrastructure`
--
ALTER TABLE `infrastructure`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parcels`
--
ALTER TABLE `parcels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pipelines`
--
ALTER TABLE `pipelines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pipeline_history`
--
ALTER TABLE `pipeline_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pipeline_inspection_checklist`
--
ALTER TABLE `pipeline_inspection_checklist`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pipeline_maintenance_events`
--
ALTER TABLE `pipeline_maintenance_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pipeline_zones`
--
ALTER TABLE `pipeline_zones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `water_interruptions`
--
ALTER TABLE `water_interruptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `water_meters`
--
ALTER TABLE `water_meters`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_orders`
--
ALTER TABLE `work_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_checklist`
--
ALTER TABLE `work_order_checklist`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_order_updates`
--
ALTER TABLE `work_order_updates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chatbot_messages`
--
ALTER TABLE `chatbot_messages`
  ADD CONSTRAINT `chatbot_messages_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `chatbot_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `communication_history`
--
ALTER TABLE `communication_history`
  ADD CONSTRAINT `communication_history_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`);

--
-- Constraints for table `consumer_requests`
--
ALTER TABLE `consumer_requests`
  ADD CONSTRAINT `consumer_requests_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`);

--
-- Constraints for table `consumption_records`
--
ALTER TABLE `consumption_records`
  ADD CONSTRAINT `consumption_records_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `generated_documents`
--
ALTER TABLE `generated_documents`
  ADD CONSTRAINT `generated_documents_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `document_templates` (`id`);

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `pipeline_history`
--
ALTER TABLE `pipeline_history`
  ADD CONSTRAINT `pipeline_history_ibfk_1` FOREIGN KEY (`pipeline_id`) REFERENCES `pipelines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pipeline_inspection_checklist`
--
ALTER TABLE `pipeline_inspection_checklist`
  ADD CONSTRAINT `pic_pipeline_fk` FOREIGN KEY (`pipeline_id`) REFERENCES `pipelines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pipeline_maintenance_events`
--
ALTER TABLE `pipeline_maintenance_events`
  ADD CONSTRAINT `pme_pipeline_fk` FOREIGN KEY (`pipeline_id`) REFERENCES `pipelines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `water_meters`
--
ALTER TABLE `water_meters`
  ADD CONSTRAINT `water_meters_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_checklist`
--
ALTER TABLE `work_order_checklist`
  ADD CONSTRAINT `work_order_checklist_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_order_updates`
--
ALTER TABLE `work_order_updates`
  ADD CONSTRAINT `work_order_updates_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
