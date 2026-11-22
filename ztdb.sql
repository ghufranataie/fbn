-- ============================================================
-- shippingdb — full bootstrap
-- ============================================================
DROP DATABASE IF EXISTS u825845895_fbndatabase;
CREATE DATABASE u825845895_fbndatabase
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE u825845895_fbndatabase;

-- ============================================================
-- TABLE: subscription
-- ============================================================
DROP TABLE IF EXISTS subscription;
CREATE TABLE subscription (
  subID         tinyint unsigned PRIMARY KEY AUTO_INCREMENT,
  subKey        varchar(256),
  subExpireDate date,
  subEntryDate  timestamp
) ENGINE = InnoDB;

INSERT INTO subscription
VALUES (1, '$argon2id$v=19$m=65536,t=4,p=1$hHCIM/NRIVi3XaqapJLpAw$1mJ44wbOzg264Dza3xUBb4aOYir79UJSWGQntqYKBNo', '2028-02-28', curdate());

-- ============================================================
-- TABLE: companyProfile
-- ============================================================
DROP TABLE IF EXISTS companyProfile;
CREATE TABLE companyProfile (
  comID         int PRIMARY KEY AUTO_INCREMENT,
  comName       varchar(256),
  comLicenseNo  varchar(30),
  comSlogan     varchar(100),
  comDetails    varchar(256),
  comPhone      varchar(40),
  comEmail      varchar(100),
  comWebsite    varchar(100),
  comLogo       MEDIUMBLOB,
  comOwner      bigint,
  comAddress    bigint,
  comFB         varchar(256),
  comInsta      varchar(256),
  comWhatsapp   varchar(20),
  comVerify     tinyint,
  comLocalCcy   varchar(3),
  comTimeZone   varchar(50),
  comEntryDate  datetime
) ENGINE = InnoDB;

INSERT INTO companyProfile
  (comName, comLicenseNo, comPhone, comEmail, comWebsite, comLogo,
   comOwner, comAddress, comLocalCcy, comTimeZone, comEntryDate)
VALUES
  ('Zaitoon Soft', '', '', '', NULL, NULL, 1, 1, 'USD', 'Asia/Kabul', curdate());

-- ============================================================
-- TABLE: users
-- ============================================================
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  usrID         int PRIMARY KEY AUTO_INCREMENT,
  usrName       varchar(100) UNIQUE,
  usrPass       varchar(256),
  usrOwner      bigint NOT NULL,
  usrRole       tinyint,
  usrStatus     TINYINT,
  usrBranch     int,
  usrEmail      varchar(100) UNIQUE,
  usrToken      varchar(256),
  usrFCP        TINYINT,
  usrALFCounter smallint DEFAULT 0,
  usrEntryDate  datetime
) ENGINE = InnoDB;

INSERT INTO users
  (usrName, usrPass, usrOwner, usrRole, usrStatus, usrBranch,
   usrEmail, usrToken, usrFCP, usrEntryDate)
VALUES
  ('3ufran', '$argon2id$v=19$m=65536,t=4,p=1$UTR8x+j3ZIjqx9oFyjV1dg$/hEv5DhkkYGw/xpYl5moRIHFL1dQEABwxUuYeDeOjl4', 1, 1, 1, 1000, 'info@zaitoonsoft.com', 'verified', 0, curdate());

-- ============================================================
-- TABLE: userTokens
-- ============================================================
DROP TABLE IF EXISTS userTokens;
CREATE TABLE userTokens (
  utkUserID    int      NOT NULL PRIMARY KEY,
  utkTokenHash char(64) NOT NULL,
  utkExpiresAt datetime NOT NULL,
  utkCreatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  utkLastUsed  timestamp NULL,
  UNIQUE KEY uq_token   (utkTokenHash),
  INDEX      idx_expiry (utkExpiresAt),
  CONSTRAINT fk_token_user
    FOREIGN KEY (utkUserID) REFERENCES users (usrID)
    ON DELETE CASCADE
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: roles
-- ============================================================
DROP TABLE IF EXISTS roles;
CREATE TABLE roles (
  rolID     tinyint PRIMARY KEY AUTO_INCREMENT,
  rolName   varchar(50),
  rolStatus tinyint DEFAULT 1
) ENGINE = InnoDB;

INSERT INTO roles (rolName, rolStatus) VALUES
  ('Super', 1), ('Admin', 1), ('CEO', 1), ('Manager', 1);

-- ============================================================
-- TABLE: roleMainGroup
-- ============================================================
DROP TABLE IF EXISTS roleMainGroup;
CREATE TABLE roleMainGroup (
  rmgID   tinyint unsigned PRIMARY KEY,
  rmgName varchar(30)
) ENGINE = InnoDB;

INSERT INTO roleMainGroup (rmgID, rmgName) VALUES
  (1,  'Dashboard'),
  (2,  'Finance'),
  (3,  'Journal'),
  (4,  'Stakeholders'),
  (5,  'HR'),
  (6,  'Transport'),
  (7,  'Projects'),
  (8,  'Orders'),
  (9,  'Settings'),
  (10, 'Reports'),
  (11, 'CRUD');

-- ============================================================
-- TABLE: roleSubGroup
-- ============================================================
DROP TABLE IF EXISTS roleSubGroup;
CREATE TABLE roleSubGroup (
  rsgID        tinyint unsigned PRIMARY KEY,
  rsgMainGroup tinyint unsigned,
  rsgName      varchar(30),
  rsgStatus    tinyint DEFAULT 1
) ENGINE = InnoDB;

INSERT INTO roleSubGroup (rsgID, rsgName, rsgMainGroup) VALUES
(1,  'Dashboard', 1), (2,  'Counts', 1), (3,  'Exchange Rate Graph', 1), (4,  'Daily Transactions Graph', 1), (5,  'Daily Transaction Totals', 1), (6,  'Digital Clock', 1), (7,  'Exchange Rates', 1), (8,  'Profit Loss Graph', 1), (9,  'Reminder Notifications', 1), (10, 'Finance', 1), (11, 'Currency Tab', 1), (12, 'Currency', 1), (13, 'Exchange rates', 1), (14, 'GL Accounts', 1), (15, 'Payroll', 1), (16, 'EOY Operation', 1), (17, 'Reminders', 1), (18, 'Journal', 1), (19, 'All Transactions', 1), (20, 'Authorized Transaction', 1), (21, 'Pending Transaction', 1), (22, 'Cash Deposit', 1), (23, 'Cash Withdraw', 1), (24, 'Income Entry', 1), (25, 'Expense Entry', 1), (26, 'GL Debit', 1), (27, 'GL Credit', 1), (28, 'FT Single Account', 1), (29, 'FT Multi Account', 1), (30, 'FX Transactions', 1), (31, 'Stakeholders', 1), (32, 'New Individual', 1), (33, 'Accounts', 1), (34, 'Users', 1), (35, 'HR - Human Resource', 1), (36, 'Employees', 1), (37, 'Attendance', 1), (38, 'All Users', 1), (39, 'Overview', 1), (40, 'Permissions', 1), (41, 'User Log', 1), (42, 'Transport', 1), (43, 'Shipping', 1), (44, 'Drivers', 1), (45, 'Vehicles', 1), (46, 'Projects', 1), (47, 'New Project', 1), (48, 'Modify Project', 1), (49, 'Project Services', 1), (50, 'Project Payments', 1), (51, 'Inventory', 1), (52, 'Orders Tab', 1), (53, 'Estimate Tab', 1), (54, 'Goods Shift Tab', 1), (55, 'Adjustment Tab', 1), (56, 'Purchase', 1), (57, 'Sale', 1), (58, 'Estimate', 1), (59, 'Goods Shift', 1), (60, 'Adjustment', 1), (61, 'Find Invoice', 1), (62, 'Settings', 1), (63, 'General Tab', 1), (64, 'System Settings', 1), (65, 'Password Change', 1), (66, 'User Profile', 1), (67, 'Roles and Permissions', 1), (68, 'Company Tab', 1), (69, 'Profile', 1), (70, 'Branches', 1), (71, 'Storage', 1), (72, 'Transaction Type', 1), (73, 'Stock', 1), (74, 'Products', 1), (75, 'Category', 1), (76, 'Backup', 1), (77, 'About', 1), (78, 'Reports', 1), (79, 'Account Statement Single Date', 1), (80, 'GL Statement Single Date', 1), (81, 'GL Statement Periodic Date', 1), (82, 'Creditors', 1), (83, 'Debtors', 1), (84, 'Stock Availability', 1), (85, 'Product Movement', 1), (86, 'Purchase Invoices', 1), (87, 'Sale Invoices', 1), (88, 'Estimate', 1), (89, 'Goods Shift', 1), (90, 'Adjustment', 1), (91, 'All Invoices', 1), (92, 'Cash Balance All Branch', 1), (93, 'Cash Balance Single Branch', 1), (94, 'Exchange Rates', 1), (95, 'Trial Balance', 1), (96, 'Transaction Details', 1), (97, 'Transactions Report', 1), (98, 'All Balances', 1), (99, 'Users', 1), (100, 'User Role and Permission', 1), (101, 'User Log', 1), (102, 'Shippings', 1), (103, 'Vehicles', 1), (104, 'Drivers', 1), (105, 'Stakeholders', 1), (106, 'Employees', 1), (107, 'Stakeholders Account', 1), (108, 'GL Accounts', 1), (109, 'Currencies', 1), (110, 'Attendance', 1), (111, 'Reminders', 1), (112, 'Payroll', 1), (113, 'Balance Sheet', 1), (114, 'All Projects', 1), (115, 'Project Services Reports', 1), (116, 'Create', 1), (117, 'Read', 1), (118, 'Update', 1), (119, 'Delete', 1);

-- ============================================================
-- TABLE: rolePermission
-- ============================================================
DROP TABLE IF EXISTS rolePermission;
CREATE TABLE rolePermission (
  rpID         smallint unsigned PRIMARY KEY AUTO_INCREMENT,
  rpRoleID     tinyint,
  rpRoleNameID tinyint unsigned,
  rpStatus     tinyint DEFAULT 0
) ENGINE = InnoDB;

INSERT INTO rolePermission (rpRoleID, rpRoleNameID, rpStatus) VALUES
(1, 1, 1), (1, 2, 1), (1, 3, 1), (1, 4, 1), (1, 5, 1), (1, 6, 1), (1, 7, 1), (1, 8, 1), (1, 9, 1), (1, 10, 1), (1, 11, 1), (1, 12, 1), (1, 13, 1), (1, 14, 1), (1, 15, 1), (1, 16, 1), (1, 17, 1), (1, 18, 1), (1, 19, 1), (1, 20, 1), (1, 21, 1), (1, 22, 1), (1, 23, 1), (1, 24, 1), (1, 25, 1), (1, 26, 1), (1, 27, 1), (1, 28, 1), (1, 29, 1), (1, 30, 1), (1, 31, 1), (1, 32, 1), (1, 33, 1), (1, 34, 1), (1, 35, 1), (1, 36, 1), (1, 37, 1), (1, 38, 1), (1, 39, 1), (1, 40, 1), (1, 41, 1), (1, 42, 1), (1, 43, 1), (1, 44, 1), (1, 45, 1), (1, 46, 1), (1, 47, 1), (1, 48, 1), (1, 49, 1), (1, 50, 1), (1, 51, 1), (1, 52, 1), (1, 53, 1), (1, 54, 1), (1, 55, 1), (1, 56, 1), (1, 57, 1), (1, 58, 1), (1, 59, 1), (1, 60, 1), (1, 61, 1), (1, 62, 1), (1, 63, 1), (1, 64, 1), (1, 65, 1), (1, 66, 1), (1, 67, 1), (1, 68, 1), (1, 69, 1), (1, 70, 1), (1, 71, 1), (1, 72, 1), (1, 73, 1), (1, 74, 1), (1, 75, 1), (1, 76, 1), (1, 77, 1), (1, 78, 1), (1, 79, 1), (1, 80, 1), (1, 81, 1), (1, 82, 1), (1, 83, 1), (1, 84, 1), (1, 85, 1), (1, 86, 1), (1, 87, 1), (1, 88, 1), (1, 89, 1), (1, 90, 1), (1, 91, 1), (1, 92, 1), (1, 93, 1), (1, 94, 1), (1, 95, 1), (1, 96, 1), (1, 97, 1), (1, 98, 1), (1, 99, 1), (1, 100, 1), (1, 101, 1), (1, 102, 1), (1, 103, 1), (1, 104, 1), (1, 105, 1), (1, 106, 1), (1, 107, 1), (1, 108, 1), (1, 109, 1), (1, 110, 1), (1, 111, 1), (1, 112, 1), (1, 113, 1), (1, 114, 1), (1, 115, 1), (1, 116, 1), (1, 117, 1), (1, 118, 1), (1, 119, 1),
(2, 1, 1), (2, 2, 1), (2, 3, 1), (2, 4, 1), (2, 5, 1), (2, 6, 1), (2, 7, 1), (2, 8, 1), (2, 9, 1), (2, 10, 1), (2, 11, 1), (2, 12, 1), (2, 13, 1), (2, 14, 1), (2, 15, 1), (2, 16, 1), (2, 17, 1), (2, 18, 1), (2, 19, 1), (2, 20, 1), (2, 21, 1), (2, 22, 1), (2, 23, 1), (2, 24, 1), (2, 25, 1), (2, 26, 1), (2, 27, 1), (2, 28, 1), (2, 29, 1), (2, 30, 1), (2, 31, 1), (2, 32, 1), (2, 33, 1), (2, 34, 1), (2, 35, 1), (2, 36, 1), (2, 37, 1), (2, 38, 1), (2, 39, 1), (2, 40, 1), (2, 41, 1), (2, 42, 1), (2, 43, 1), (2, 44, 1), (2, 45, 1), (2, 46, 1), (2, 47, 1), (2, 48, 1), (2, 49, 1), (2, 50, 1), (2, 51, 1), (2, 52, 1), (2, 53, 1), (2, 54, 1), (2, 55, 1), (2, 56, 1), (2, 57, 1), (2, 58, 1), (2, 59, 1), (2, 60, 1), (2, 61, 1), (2, 62, 1), (2, 63, 1), (2, 64, 1), (2, 65, 1), (2, 66, 1), (2, 67, 1), (2, 68, 1), (2, 69, 1), (2, 70, 1), (2, 71, 1), (2, 72, 1), (2, 73, 1), (2, 74, 1), (2, 75, 1), (2, 76, 1), (2, 77, 1), (2, 78, 1), (2, 79, 1), (2, 80, 1), (2, 81, 1), (2, 82, 1), (2, 83, 1), (2, 84, 1), (2, 85, 1), (2, 86, 1), (2, 87, 1), (2, 88, 1), (2, 89, 1), (2, 90, 1), (2, 91, 1), (2, 92, 1), (2, 93, 1), (2, 94, 1), (2, 95, 1), (2, 96, 1), (2, 97, 1), (2, 98, 1), (2, 99, 1), (2, 100, 1), (2, 101, 1), (2, 102, 1), (2, 103, 1), (2, 104, 1), (2, 105, 1), (2, 106, 1), (2, 107, 1), (2, 108, 1), (2, 109, 1), (2, 110, 1), (2, 111, 1), (2, 112, 1), (2, 113, 1), (2, 114, 1), (2, 115, 1), (2, 116, 1), (2, 117, 1), (2, 118, 1), (2, 119, 1),
(3, 1, 1), (3, 2, 1), (3, 3, 1), (3, 4, 1), (3, 5, 1), (3, 6, 1), (3, 7, 1), (3, 8, 1), (3, 9, 1), (3, 10, 1), (3, 11, 1), (3, 12, 0), (3, 13, 1), (3, 14, 0), (3, 15, 0), (3, 16, 0), (3, 17, 1), (3, 18, 1), (3, 19, 1), (3, 20, 1), (3, 21, 1), (3, 22, 1), (3, 23, 1), (3, 24, 1), (3, 25, 1), (3, 26, 1), (3, 27, 1), (3, 28, 1), (3, 29, 1), (3, 30, 1), (3, 31, 1), (3, 32, 1), (3, 33, 1), (3, 34, 1), (3, 35, 1), (3, 36, 1), (3, 37, 1), (3, 38, 0), (3, 39, 0), (3, 40, 0), (3, 41, 0), (3, 42, 1), (3, 43, 1), (3, 44, 1), (3, 45, 1), (3, 46, 1), (3, 47, 1), (3, 48, 1), (3, 49, 1), (3, 50, 1), (3, 51, 1), (3, 52, 1), (3, 53, 1), (3, 54, 1), (3, 55, 1), (3, 56, 1), (3, 57, 1), (3, 58, 1), (3, 59, 1), (3, 60, 1), (3, 61, 1), (3, 62, 1), (3, 63, 1), (3, 64, 1), (3, 65, 1), (3, 66, 1), (3, 67, 0), (3, 68, 0), (3, 69, 0), (3, 70, 0), (3, 71, 1), (3, 72, 0), (3, 73, 0), (3, 74, 0), (3, 75, 0), (3, 76, 0), (3, 77, 1), (3, 78, 1), (3, 79, 1), (3, 80, 1), (3, 81, 0), (3, 82, 1), (3, 83, 1), (3, 84, 1), (3, 85, 1), (3, 86, 1), (3, 87, 1), (3, 88, 1), (3, 89, 1), (3, 90, 1), (3, 91, 1), (3, 92, 1), (3, 93, 1), (3, 94, 1), (3, 95, 1), (3, 96, 1), (3, 97, 1), (3, 98, 1), (3, 99, 1), (3, 100, 1), (3, 101, 1), (3, 102, 1), (3, 103, 1), (3, 104, 1), (3, 105, 1), (3, 106, 1), (3, 107, 1), (3, 108, 1), (3, 109, 1), (3, 110, 1), (3, 111, 1), (3, 112, 1), (3, 113, 1), (3, 114, 1), (3, 115, 1), (3, 116, 1), (3, 117, 1), (3, 118, 1), (3, 119, 1),
(4, 1, 1), (4, 2, 1), (4, 3, 1), (4, 4, 1), (4, 5, 1), (4, 6, 1), (4, 7, 1), (4, 8, 1), (4, 9, 1), (4, 10, 1), (4, 11, 1), (4, 12, 0), (4, 13, 1), (4, 14, 0), (4, 15, 0), (4, 16, 0), (4, 17, 1), (4, 18, 1), (4, 19, 1), (4, 20, 1), (4, 21, 1), (4, 22, 1), (4, 23, 1), (4, 24, 1), (4, 25, 1), (4, 26, 1), (4, 27, 1), (4, 28, 1), (4, 29, 1), (4, 30, 1), (4, 31, 1), (4, 32, 1), (4, 33, 1), (4, 34, 0), (4, 35, 1), (4, 36, 0), (4, 37, 0), (4, 38, 0), (4, 39, 0), (4, 40, 0), (4, 41, 0), (4, 42, 1), (4, 43, 1), (4, 44, 1), (4, 45, 1), (4, 46, 1), (4, 47, 1), (4, 48, 1), (4, 49, 1), (4, 50, 1), (4, 51, 1), (4, 52, 1), (4, 53, 1), (4, 54, 1), (4, 55, 1), (4, 56, 1), (4, 57, 1), (4, 58, 1), (4, 59, 1), (4, 60, 1), (4, 61, 1), (4, 62, 1), (4, 63, 0), (4, 64, 1), (4, 65, 1), (4, 66, 1), (4, 67, 0), (4, 68, 0), (4, 69, 0), (4, 70, 0), (4, 71, 1), (4, 72, 0), (4, 73, 0), (4, 74, 0), (4, 75, 0), (4, 76, 0), (4, 77, 1), (4, 78, 1), (4, 79, 1), (4, 80, 1), (4, 81, 0), (4, 82, 1), (4, 83, 1), (4, 84, 1), (4, 85, 1), (4, 86, 1), (4, 87, 1), (4, 88, 1), (4, 89, 1), (4, 90, 1), (4, 91, 0), (4, 92, 0), (4, 93, 0), (4, 94, 1), (4, 95, 0), (4, 96, 1), (4, 97, 1), (4, 98, 0), (4, 99, 0), (4, 100, 0), (4, 101, 0), (4, 102, 1), (4, 103, 1), (4, 104, 1), (4, 105, 1), (4, 106, 0), (4, 107, 0), (4, 108, 0), (4, 109, 0), (4, 110, 0), (4, 111, 1), (4, 112, 0), (4, 113, 1), (4, 114, 0), (4, 115, 0), (4, 116, 1), (4, 117, 1), (4, 118, 1), (4, 119, 1);

-- ============================================================
-- TABLE: userPermissions
-- ============================================================
DROP TABLE IF EXISTS userPermissions;
CREATE TABLE userPermissions (
  uprID     int PRIMARY KEY AUTO_INCREMENT,
  uprUserID int,
  uprRole   tinyint unsigned,
  uprStatus tinyint
) ENGINE = InnoDB;

INSERT INTO userPermissions (uprUserID, uprRole, uprStatus)
SELECT u.usrID, rsg.rsgID, rp.rpStatus
FROM users u
JOIN roles         r   ON r.rolID          = u.usrRole
JOIN rolePermission rp ON rp.rpRoleID      = r.rolID
JOIN roleSubGroup  rsg ON rsg.rsgID        = rp.rpRoleNameID
ORDER BY u.usrID;

-- ============================================================
-- TABLE: resetPassword
-- ============================================================
DROP TABLE IF EXISTS resetPassword;
CREATE TABLE resetPassword (
  rstID       bigint PRIMARY KEY AUTO_INCREMENT,
  rstUserID   int,
  rstOTP      int,
  rstExpiry   datetime,
  rstEntryTime timestamp,
  rstStatus   tinyint DEFAULT 1
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: checklist
-- ============================================================
DROP TABLE IF EXISTS checklist;
CREATE TABLE checklist (
  chkID       bigint PRIMARY KEY AUTO_INCREMENT,
  chkUser     int     NOT NULL,
  chkPersonal tinyint NOT NULL,
  chkCompany  tinyint NOT NULL,
  chkBranch   tinyint NOT NULL
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: currency
-- ============================================================
DROP TABLE IF EXISTS currency;
CREATE TABLE currency (
  ccyCode        varchar(3) PRIMARY KEY,
  ccyName        varchar(20) UNIQUE,
  ccySymbol      varchar(2),
  ccyCountry     varchar(30),
  ccyCountryCode varchar(2),
  ccyLocalName   varchar(20),
  ccyStatus      tinyint DEFAULT 1
) ENGINE = InnoDB;

INSERT INTO currency
  (ccyCode, ccyName, ccySymbol, ccyCountry, ccyStatus, ccyCountryCode, ccyLocalName)
VALUES
  ('AED', 'UAE Dirham',             'د.', 'United Arab Emirates', 0, 'AE', 'درهم'),
  ('AFN', 'Afghani',                '؋',  'Afghanistan',          1, 'AF', 'افغانی'),
  ('AUD', 'Australian Dollar',      '$',  'Australia',            0, 'AU', 'دالر آسترالیا'),
  ('BRL', 'Brazilian Real',         'R$', 'Brazil',               0, 'BR', 'رئال برازیل'),
  ('CAD', 'Canadian Dollar',        '$',  'Canada',               0, 'CA', 'دالر کانادایی'),
  ('CHF', 'Swiss Franc',            'Fr', 'Swiss',                0, 'CH', 'فرانک سویس'),
  ('CNY', 'Chinese Yuan',           '¥',  'China',                0, 'CN', 'یوان'),
  ('DKK', 'Danish Krone',           'kr', 'Denmark',              0, 'DK', 'کرون دنمارک'),
  ('EUR', 'Euro',                   '€',  'European Union',       0, 'EU', 'یورو'),
  ('GBP', 'British Pound',          '£',  'United Kingdom',       0, 'GB', 'پوند بریتانیا'),
  ('INR', 'Indian Rupee',           '₹',  'India',                0, 'IN', 'رپیه هندی'),
  ('IRR', 'Iranian Rial',           'IR', 'Iran',                 0, 'IR', 'ريال'),
  ('JPY', 'Japanese Yen',           '¥',  'Japan',                0, 'JP', 'ین ژاپن'),
  ('KRW', 'South Korean Won',       '₩',  'South Korea',          0, 'KR', 'وان کوریایی جنوبی'),
  ('MYR', 'Malaysian Ringgit',      'RM', 'Malaysia',             0, 'MY', 'رینگیت مالیزیا'),
  ('NOK', 'Norwegian Krone',        'kr', 'Norway',               0, 'NO', 'کرون ناروی'),
  ('PKR', 'Pakistani Rupee',        'Rs', 'Pakistan',             0, 'PK', 'رپیه پاکستانی'),
  ('RUB', 'Russian Ruble',          '₽',  'Russia',               0, 'RU', 'روبل'),
  ('SAR', 'Saudi Riyal',            '﷼',  'Saudi Arabia',         0, 'SA', 'ریال'),
  ('SEK', 'Swedish Krona',          'kr', 'Sweden',               0, 'SE', 'کرون سویدن'),
  ('SGD', 'Singapore Dollar',       '$',  'Singapore',            0, 'SG', 'دالر سنگاپور'),
  ('TRY', 'Turkish Lira',           '₺',  'Turkeye',              0, 'TR', 'لیره ترکیه'),
  ('USD', 'US Dollar',              '$',  'United States',        1, 'US', 'دالر امریکایی'),
  ('ZAR', 'South African Rand',     'R',  'South Africa',         0, 'ZA', 'رند افریقای جنوبی');

-- ============================================================
-- TABLE: ccyRate
-- ============================================================
DROP TABLE IF EXISTS ccyRate;
CREATE TABLE ccyRate (
  crID       bigint PRIMARY KEY AUTO_INCREMENT,
  crFrom     varchar(3),
  crTo       varchar(3),
  crExchange decimal(16,10),
  crDate     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

INSERT INTO ccyRate (crFrom, crTo, crExchange) VALUES
  ('USD', 'USD', 1),
  ('AFN', 'AFN', 1);

-- ============================================================
-- TABLE: trnTypes
-- ============================================================
DROP TABLE IF EXISTS trnTypes;
CREATE TABLE trnTypes (
  trntCode    varchar(4) PRIMARY KEY,
  trntName    varchar(30),
  trntDetails varchar(255)
) ENGINE = InnoDB;

INSERT INTO trnTypes (trntCode, trntName, trntDetails) VALUES
  ('BULK', 'Bulk insertion',         'Used to do bulk transactions such as new entries to the system.'),
  ('GLAT', 'Asset Entries',          'This Transaction is used for Entries of Assets to the System'),
  ('CHWL', 'Cash Withdraw',          'Cash withdraw which always effects cash in vault as credit'),
  ('CHDP', 'Cash Deposit',           'Cash Deposit to Account and the Cash in the Vault always effects as debit.'),
  ('INCM', 'Post Income',            'Cash Deposit to Income Account and the Cash in the Vault always effects as debit.'),
  ('XPNS', 'Post Expense',           'Cash Withdraw from Expense Account and the Cash in the Vault always effects as credit.'),
  ('GLDR', 'GL Debit',               'Post a debit into GL Account and the Cash in the Vault always effects as credit.'),
  ('GLCR', 'GL Credit',              'Post a credit into GL Account and the Cash in the Vault always effects as debit.'),
  ('ATAT', 'Fund Transfer',          'select multiple account as debit or credit to transfer balance between them.'),
  ('SLRY', 'Post Salary',            'Salary Posting transaction to employee accounts'),
  ('SALE', 'Sale',                   'Cash Deposit to Account and the Cash in the Vault always effects as debit.'),
  ('PRCH', 'Purchase',               'Cash Deposit to Account and the Cash in the Vault always effects as debit.'),
  ('CRFX', 'Cross Currency',         'Cross Currency is used for money exchange between two different currencies'),
  ('TRPT', 'Transport Entry',        'This type of transaction is used for shipping and transport income and expnese entries'),
  ('PLCL', 'Profit & Loss Closing',  'Journal entries made at the end of an accounting period'),
  ('CRGO', 'Transport & Cargo',      'Loading & unloading of goods');

-- ============================================================
-- TABLE: transactions
-- ============================================================
DROP TABLE IF EXISTS transactions;
CREATE TABLE transactions (
  trnID         bigint UNIQUE AUTO_INCREMENT,
  trnReference  varchar(19) PRIMARY KEY,
  trnType       varchar(4),
  trnStatus     tinyint,
  trnStateText  ENUM('Pending', 'Authorized', 'Reversed', 'Deleted'),
  trnMaker      int,
  trnAuthorizer int NULL,
  trnEntryDate  datetime
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: trnDetails
-- ============================================================
DROP TABLE IF EXISTS trnDetails;
CREATE TABLE trnDetails (
  trdID        bigint PRIMARY KEY AUTO_INCREMENT,
  trdReference varchar(19),
  trdCcy       varchar(3),
  trdBranch    int,
  trdAccount   int NOT NULL,
  trdDrCr      varchar(2),
  trdAmount    decimal(17,4),
  trdNarration varchar(255),
  trdEntryDate timestamp(3)
) ENGINE = InnoDB;


-- ============================================================
-- TABLE: Hawala Sarafi
-- ============================================================
DROP TABLE IF EXISTS demandDraft;
CREATE TABLE demandDraft (
  ddID          bigint PRIMARY KEY AUTO_INCREMENT,
  ddType        enum('Walkin', 'Account'),
  ddNumber      varchar(20),
  ddTrnRef      varchar(19),
  ddSender      varchar(50),
  ddSendCcy     varchar(3),
  ddSendAmount  decimal(17,4),

  ddReceiver    varchar(50),
  ddStatus      ENUM('pending', 'liquidat') DEFAULT 'pending',
  ddEntryDate   timestamp(3)
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: personal
-- ============================================================
DROP TABLE IF EXISTS personal;
CREATE TABLE personal (
  perID       bigint PRIMARY KEY AUTO_INCREMENT,
  perCode     varchar(30) UNIQUE,
  perLock     tinyint(1) NOT NULL DEFAULT 0,
  perName     varchar(50) NOT NULL,
  perLastName varchar(50),
  perGender   ENUM('Male', 'Female', 'Other'),
  perDoB      date,
  perENIDNo   varchar(13),
  perAddress  bigint,
  perPhone    varchar(15),
  perEmail    varchar(50),
  perPhoto    varchar(256)
) ENGINE = InnoDB;

INSERT INTO personal
  (perID, perName, perLastName, perGender, perDoB, perENIDNo, perAddress, perPhone, perEmail)
VALUES
  (1, 'Cash', '', 'Male', '2000-03-20', '', 2, '', '');

-- ============================================================
-- TABLE: employees
-- ============================================================
DROP TABLE IF EXISTS employees;
CREATE TABLE employees (
  empID           int PRIMARY KEY AUTO_INCREMENT,
  empPersonal     bigint,
  empSalAccount   int,
  empEmail        varchar(50),
  empHireDate     date,
  empDepartment   ENUM('Executive Management','HR','Operation','Finance','IT','Audit','Procurement','Marketing','Sales','Customer Service','Legal'),
  empPosition     varchar(20),
  empSalCalcBase  ENUM('Monthly','Hourly'),
  empPmntMethod   ENUM('Monthly','Weekly','Daily'),
  empSalary       decimal(17,4),
  empTaxInfo      varchar(30),
  empFingerprint  blob,
  empStatus       tinyint DEFAULT 1,
  empEndDate      date
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: employeeAttendence
-- ============================================================
DROP TABLE IF EXISTS employeeAttendence;
CREATE TABLE employeeAttendence (
  emaID        BIGINT PRIMARY KEY AUTO_INCREMENT,
  emaEmployee  INT NOT NULL,
  emaDate      DATE NOT NULL,
  emaCheckedIn TIME NULL,
  emaCheckedOut TIME NULL,
  emaWorkHours DECIMAL(4,1) NULL,
  emaStatus    ENUM('Present','Absent','Late','Leave') NOT NULL DEFAULT 'Present',
  emaCompleted TINYINT(1) NOT NULL DEFAULT 0,
  emaEntryTime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_emp_date (emaEmployee, emaDate)
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: payroll
-- ============================================================
DROP TABLE IF EXISTS payroll;
CREATE TABLE payroll (
  prlID        int PRIMARY KEY AUTO_INCREMENT,
  prlEmployee  int,
  prlMonthYear varchar(7),
  prlTrnRef    varchar(19),
  prlEntryTime timestamp
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: accountDetails
-- ============================================================
DROP TABLE IF EXISTS accountDetails;
CREATE TABLE accountDetails (
  actID         int PRIMARY KEY AUTO_INCREMENT,
  actAccount    INT NOT NULL UNIQUE,
  actCurrency   varchar(3),
  actCreditLimit decimal(17,4) DEFAULT '999999999999.9999',
  actSignatory  bigint,
  actCompany    int,
  actStatus     tinyint DEFAULT b'1'
) ENGINE = InnoDB;

INSERT INTO accountDetails (actAccount, actCurrency, actSignatory, actCompany, actStatus)
VALUES (500000, 'USD', 1, 1, 1);

-- ============================================================
-- TABLE: accountCategory
-- ============================================================
DROP TABLE IF EXISTS accountCategory;
CREATE TABLE accountCategory (
  acgID       tinyint PRIMARY KEY,
  acgName     varchar(25),
  acgCategory tinyint
) ENGINE = InnoDB;

INSERT INTO accountCategory (acgID, acgName, acgCategory) VALUES
  (1,  'Current Asset',           1),
  (2,  'Fixed Asset',             1),
  (3,  'Intangible Asset',        1),
  (4,  'Current Liability',       2),
  (5,  'Long-Term Liability',     2),
  (6,  'Owner’s Equity',          2),
  (7,  'Shareholders',            2),
  (8,  'Stakeholders',            2),
  (9,  'Operating Revenue',       3),
  (10, 'Non-Operating Revenue',   3),
  (11, 'Operating Expense',       4),
  (12, 'Non-Operating Expense',   4);

-- ============================================================
-- TABLE: accounts
-- ============================================================
DROP TABLE IF EXISTS accounts;
CREATE TABLE accounts (
  accNumber   int PRIMARY KEY,
  accCategory tinyint,
  accName     varchar(30)
) ENGINE = InnoDB;

INSERT INTO accounts (accNumber, accCategory, accName) VALUES
  (10101010, 1,  'Cash In Vault'),
  (10101011, 1,  'Products'),
  (10101012, 1,  'Equipment'),
  (10101013, 1,  'Receivable'),
  (10101014, 2,  'Building'),
  (10101015, 2,  'Land'),
  (10101016, 2,  'Vehicle'),
  (10101017, 2,  'Machinery'),
  (10101018, 1,  'Petty Cash'),
  (10101019, 1,  'Inventory'),
  (10101020, 1,  'Petty Cash-Transport'),
  (10101021, 1,  'Receivable-Transport'),
  (10101022, 1,  'Azizi Bank'),
  (10101023, 1,  'AIB Bank'),
  (10101024, 1,  'Maiwand Bank'),
  (10101025, 1,  'New Kabul Bank'),
  (10101026, 1,  'Fund With Sarafi'),
  (10101027, 3,  'Software'),
  (20202020, 4,  'Payable'),
  (20202021, 6,  'Capital'),
  (20202022, 4,  'Note Payable'),
  (20202023, 4,  'Income Tax Payable'),
  (20202024, 4,  'Transport Payable'),
  (20202025, 4,  'Cross Currency Adjustment'),
  (20202026, 6,  'Retained Earnings'),
  (20202027, 4,  'Uneaned Revenue Increases'),
  (30303030, 10, 'Income'),
  (30303031, 9,  'Sales Revenue'),
  (30303032, 9,  'Service Revenue'),
  (30303033, 9,  'Transport Revenue'),
  (30303034, 9,  'Cross Currency Income'),
  (30303035, 9,  'Income On Rent'),
  (30303036, 9,  'Projects Income'),
  (40404040, 12, 'Expenses'),
  (40404041, 11, 'Rent Expenses'),
  (40404042, 11, 'MISC Expenses'),
  (40404043, 11, 'Vehicle Expenses'),
  (40404044, 11, 'Supplies Expenses'),
  (40404045, 11, 'Salary Expenses'),
  (40404046, 11, 'Utilities Expenses'),
  (40404047, 11, 'Database Expenses'),
  (40404048, 11, 'Transportation Expenses'),
  (40404049, 11, 'Repairs and Maintenance'),
  (40404050, 11, 'Loss on Sales'),
  (40404051, 11, 'Fuel for Vehicle'),
  (40404052, 11, 'Cross Currency Loss'),
  (40404053, 11, 'Discount On Sales'),
  (40404054, 11, 'Expense On Purchase (Imports)'),
  (500000,   8,  'Admin');

-- ============================================================
-- TABLE: branch
-- ============================================================
DROP TABLE IF EXISTS branch;
CREATE TABLE branch (
  brcID        int PRIMARY KEY AUTO_INCREMENT,
  brcCompany   int,
  brcName      varchar(50),
  brcAddress   bigint,
  brcPhone     varchar(15),
  brcStatus    tinyint DEFAULT 1,
  brcEntryDate datetime
) AUTO_INCREMENT = 1000, ENGINE = InnoDB;

INSERT INTO branch (brcCompany, brcName, brcAddress, brcPhone, brcEntryDate)
VALUES (1, 'Main', 1, '', curdate());

-- ============================================================
-- TABLE: branchAuthLimit
-- ============================================================
DROP TABLE IF EXISTS branchAuthLimit;
CREATE TABLE branchAuthLimit (
  balID         int PRIMARY KEY AUTO_INCREMENT,
  balBranch     int,
  balCurrency   varchar(3),
  balLimitAmount decimal(13,0)
) ENGINE = InnoDB;

INSERT INTO branchAuthLimit (balID, balBranch, balCurrency, balLimitAmount)
VALUES (NULL, 1000, 'USD', '1000000');

-- ============================================================
-- TABLE: address
-- ============================================================
DROP TABLE IF EXISTS address;
CREATE TABLE address (
  addID      bigint PRIMARY KEY AUTO_INCREMENT,
  addName    varchar(50),
  addCity    varchar(30),
  addProvince varchar(30),
  addCountry varchar(30),
  addZipCode varchar(30),
  addMailing tinyint DEFAULT 0
) ENGINE = InnoDB;

INSERT INTO address (addID, addName, addCity, addProvince, addCountry, addZipCode, addMailing) VALUES
  (1, NULL, 'Mazar-e Sharif', 'Balkh', 'Afghanistan', '1701', 1),
  (2, NULL, 'Mazar-e Sharif', 'Balkh', 'Afghanistan', '1701', 1);

-- ============================================================
-- TABLE: productCategory
-- ============================================================
DROP TABLE IF EXISTS productCategory;
CREATE TABLE productCategory (
  pcID          smallint(6) PRIMARY KEY AUTO_INCREMENT,
  pcName        varchar(30),
  pcDescription varchar(256),
  pcStatus      tinyint DEFAULT 1
) ENGINE = InnoDB;

INSERT INTO productCategory (pcName, pcDescription) VALUES
  ('Misc', 'All types of items');

-- ============================================================
-- TABLE: product
-- ============================================================
DROP TABLE IF EXISTS product;
CREATE TABLE product (
  proID      int UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  proName    varchar(50),
  proCode    char(13) UNIQUE,
  proUnit    varchar(20),
  proBrand   varchar(20),
  proMadeIn  varchar(50),
  proModel   varchar(20),
  proGrade   char(1),
  proCategory smallint,
  proDetails varchar(1000),
  proLSNqty  smallint,
  proColor   varchar(20),
  proWidth   DECIMAL(13,3),
  proLength  DECIMAL(13,3),
  proWeight  DECIMAL(13,3),
  proBreadth DECIMAL(13,3),
  proSPP     DECIMAL(28,4),
  proStatus  tinyint DEFAULT 1
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: productImages
-- ============================================================
DROP TABLE IF EXISTS productImages;
CREATE TABLE productImages (
  piID       int UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  piName     varchar(100),
  piAltText  varchar(100),
  piProID    int unsigned,
  piDateTime TIMESTAMP
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: orders
-- ============================================================
DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
  ordID        bigint PRIMARY KEY AUTO_INCREMENT,
  ordTrnRef    varchar(19),
  ordBranch    int,
  ordPersonal  bigint,
  ordName      varchar(30),
  ordxRef      varchar(20),
  ordEntryDate datetime,
  ordRemarks   varchar(250),
  ordStatus    enum('Authorized', 'Pending', 'Deleted') DEFAULT 'Pending'
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: vehicles
-- ============================================================
DROP TABLE IF EXISTS vehicles;
CREATE TABLE vehicles (
  vclID           smallint unsigned PRIMARY KEY AUTO_INCREMENT,
  vclModel        varchar(20),
  vclYear         varchar(4),
  vclTransmission ENUM('Gear', 'Auto'),
  vclEnginPower   varchar(50),
  vclBodyType     varchar(20),
  vclDriveType    ENUM('Chain', 'Belt', 'Shaft'),
  vclStatus       tinyint DEFAULT 1
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: storages
-- ============================================================
DROP TABLE IF EXISTS storages;
CREATE TABLE storages (
  stgID       smallint PRIMARY KEY AUTO_INCREMENT,
  stgName     varchar(30),
  stgDetails  varchar(200),
  stgLocation varchar(30),
  stgStatus   tinyint DEFAULT 1
) ENGINE = InnoDB;

INSERT INTO storages (stgName, stgDetails, stgLocation, stgStatus) VALUES
  ('Store', 'Primary storage', 'Main Branch', 1);

-- ============================================================
-- TABLE: stock
-- ============================================================
DROP TABLE IF EXISTS stock;
CREATE TABLE stock (
  stkID             bigint PRIMARY KEY AUTO_INCREMENT,
  stkOrder          bigint,
  stkProduct        int UNSIGNED,
  stkEntryType      varchar(3),
  stkStorage        smallint,
  stkExpiryDate     date,
  stkQuantity       int,
  stkQtyInBatch     int,
  stkPurPrice       decimal(21,8),
  stkLandedPurPrice decimal(21,8),
  stkDiscount       decimal(21,8),
  stkSalePrice      DECIMAL(21,8)
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: tempStock
-- ============================================================
DROP TABLE IF EXISTS tempStock;
CREATE TABLE tempStock (
  tstID        int PRIMARY KEY AUTO_INCREMENT,
  tstOrder     bigint,
  tstProduct   int UNSIGNED,
  tstStorage   smallint,
  tstQuantity  decimal(17,3),
  tstPurPrice  decimal(17,4),
  tstSalePrice decimal(17,4)
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: reminders
-- ============================================================
DROP TABLE IF EXISTS reminders;
CREATE TABLE reminders (
  rmdID        bigint PRIMARY KEY AUTO_INCREMENT,
  rmdBranch    int,
  rmdName      varchar(20),
  rmdAccount   int,
  rmdAmount    decimal(17,4),
  rmdDetails   varchar(250),
  rmdAlertDate date,
  rmdStatus    tinyint DEFAULT 0
) ENGINE = InnoDB;

-- ============================================================
-- TABLE: userActivityLog
-- ============================================================
DROP TABLE IF EXISTS userActivityLog;
CREATE TABLE userActivityLog (
  ualID      bigint PRIMARY KEY AUTO_INCREMENT,
  ualUser    int,
  ualType    varchar(50),
  ualDetails varchar(256),
  ualIP      varchar(60),
  ualDevice  varchar(256),
  ualTiming  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE batch (
    batID             INT AUTO_INCREMENT PRIMARY KEY,
    batRef            VARCHAR(50)   NOT NULL,
    batOrder          BIGINT        NOT NULL,
    batProduct        INT UNSIGNED  NOT NULL,
    batStorage        SMALLINT      NOT NULL,
    batItemsPerBox    INT           NOT NULL DEFAULT 1,
    batReceivedItems  DECIMAL(18,6) NOT NULL,
    batRemainingItems DECIMAL(18,6) NOT NULL,
    batPurPrice       DECIMAL(21,8) NOT NULL,
    batLandedPurPrice DECIMAL(21,8) NOT NULL,
    batEntryDate      DATETIME(3)   NOT NULL,
    batStatus         TINYINT       NOT NULL DEFAULT 1,
    UNIQUE KEY uq_batch_ref (batRef),
    KEY ix_batch_fifo (batProduct, batStorage, batStatus, batID),
    CONSTRAINT fk_batch_order   FOREIGN KEY (batOrder)   REFERENCES orders(ordID),
    CONSTRAINT fk_batch_product FOREIGN KEY (batProduct) REFERENCES product(proID),
    CONSTRAINT fk_batch_storage FOREIGN KEY (batStorage) REFERENCES storages(stgID)
) ENGINE=InnoDB;

CREATE TABLE batch_consumption (
    bcnID             INT AUTO_INCREMENT PRIMARY KEY,
    bcnBatch          INT           NOT NULL,
    bcnStock          BIGINT        NOT NULL,
    bcnOrder          BIGINT        NOT NULL,
    bcnItems          DECIMAL(18,6) NOT NULL,
    bcnPurPrice       DECIMAL(21,8) NOT NULL,
    bcnLandedPurPrice DECIMAL(21,8) NOT NULL,
    bcnEntryDate      DATETIME(3)   NOT NULL,
    UNIQUE KEY uq_stock_batch (bcnStock, bcnBatch),
    KEY ix_bcn_batch (bcnBatch),
    KEY ix_bcn_order (bcnOrder),
    CONSTRAINT fk_bcn_batch FOREIGN KEY (bcnBatch) REFERENCES batch(batID),
    CONSTRAINT fk_bcn_stock FOREIGN KEY (bcnStock) REFERENCES stock(stkID),
    CONSTRAINT fk_bcn_order FOREIGN KEY (bcnOrder) REFERENCES orders(ordID)
) ENGINE=InnoDB;


-- ============================================================
-- TABLE: shipmentTbl
-- ============================================================


DROP TABLE IF EXISTS shipmentTbl;
CREATE TABLE shipmentTbl (
  shpId          int(11) AUTO_INCREMENT PRIMARY KEY,
  portName       varchar(150) NOT NULL,
  containerNo    varchar(100) NOT NULL,
  sequenceNo     varchar(50)  DEFAULT NULL,
  loadingDate    date         NOT NULL,
  unloadingDate  date         DEFAULT NULL,
  description    text         DEFAULT NULL,
  entryDate      datetime     NOT NULL DEFAULT current_timestamp(),
  updatedAt      datetime     DEFAULT NULL ON UPDATE current_timestamp(),
  createdBy      int(11)      NOT NULL,
  status         enum('pending','shipped','delivered') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: shipmentItemsTbl
-- ============================================================
DROP TABLE IF EXISTS shipmentItemsTbl;
CREATE TABLE shipmentItemsTbl (
  shpItmId        int(11) AUTO_INCREMENT PRIMARY KEY,
  shpId           int(11)       NOT NULL,
  itemDescription text          NOT NULL,
  accountNo       int(11)       NOT NULL,
  qty             int(11)       NOT NULL DEFAULT 0,
  cbm             decimal(12,4) DEFAULT 0,
  weight          decimal(12,3) DEFAULT 0,
  ratePerCbm      decimal(12,4) DEFAULT 0,
  ratePerWeight   decimal(12,4) DEFAULT 0,
  extraCharges    decimal(12,2) NOT NULL DEFAULT 0.00,
  status          enum('pending', 'delivered') NOT NULL DEFAULT 'pending',
  payReference    int(11)       DEFAULT NULL,
  entryDate       datetime      NOT NULL DEFAULT current_timestamp(),
  updatedAt       datetime      DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: shipmentItemsTbl
-- ============================================================
DROP TABLE IF EXISTS shippingPayments;
CREATE TABLE shippingPayments (
  sPayId int(11) AUTO_INCREMENT PRIMARY KEY,
  sPayShipping int(11) NOT NULL,
  aPayTrnReference varchar(19) NOT NULL,
  sPayType enum('in', 'out')
) ENGINE=InnoDB;


-- ============================================================
-- FOREIGN KEY CONSTRAINTS
-- ============================================================
ALTER TABLE shipmentTbl ADD FOREIGN KEY (createdBy)  REFERENCES users  (usrID);
ALTER TABLE shipmentItemsTbl ADD FOREIGN KEY (shpId) REFERENCES shipmentTbl (shpId);
ALTER TABLE shipmentItemsTbl ADD FOREIGN KEY (accountNo) REFERENCES accounts (accNumber);
ALTER TABLE shipmentItemsTbl ADD FOREIGN KEY (payReference) REFERENCES shippingPayments (sPayId);
ALTER TABLE shippingPayments ADD FOREIGN KEY (sPayShipping) REFERENCES shipmentTbl (shpId);
ALTER TABLE shippingPayments ADD FOREIGN KEY (aPayTrnReference) REFERENCES transactions (trnReference);

ALTER TABLE companyProfile ADD FOREIGN KEY (comAddress)  REFERENCES address  (addID);
ALTER TABLE companyProfile ADD FOREIGN KEY (comLocalCcy) REFERENCES currency (ccyCode);
ALTER TABLE companyProfile ADD FOREIGN KEY (comOwner)    REFERENCES personal (perID);

ALTER TABLE users ADD FOREIGN KEY (usrOwner)  REFERENCES personal (perID);
ALTER TABLE users ADD FOREIGN KEY (usrBranch) REFERENCES branch   (brcID);
ALTER TABLE users ADD FOREIGN KEY (usrRole)   REFERENCES roles    (rolID);

ALTER TABLE roleSubGroup  ADD FOREIGN KEY (rsgMainGroup) REFERENCES roleMainGroup (rmgID);
ALTER TABLE rolePermission ADD FOREIGN KEY (rpRoleID)     REFERENCES roles         (rolID);
ALTER TABLE rolePermission ADD FOREIGN KEY (rpRoleNameID) REFERENCES roleSubGroup  (rsgID);

ALTER TABLE userPermissions ADD FOREIGN KEY (uprUserID) REFERENCES users        (usrID);
ALTER TABLE userPermissions ADD FOREIGN KEY (uprRole)   REFERENCES roleSubGroup (rsgID);

ALTER TABLE resetPassword ADD FOREIGN KEY (rstUserID) REFERENCES users (usrID);
ALTER TABLE checklist     ADD FOREIGN KEY (chkUser)   REFERENCES users (usrID);

ALTER TABLE ccyRate ADD FOREIGN KEY (crFrom) REFERENCES currency (ccyCode);
ALTER TABLE ccyRate ADD FOREIGN KEY (crTo)   REFERENCES currency (ccyCode);

ALTER TABLE transactions ADD FOREIGN KEY (trnMaker)      REFERENCES users    (usrID);
ALTER TABLE transactions ADD FOREIGN KEY (trnAuthorizer) REFERENCES users    (usrID);
ALTER TABLE transactions ADD FOREIGN KEY (trnType)       REFERENCES trnTypes (trntCode);

ALTER TABLE trnDetails ADD FOREIGN KEY (trdCcy)       REFERENCES currency     (ccyCode);
ALTER TABLE trnDetails ADD FOREIGN KEY (trdBranch)    REFERENCES branch       (brcID);
ALTER TABLE trnDetails ADD FOREIGN KEY (trdAccount)   REFERENCES accounts     (accNumber);
ALTER TABLE trnDetails ADD FOREIGN KEY (trdReference) REFERENCES transactions (trnReference);

ALTER TABLE accountDetails ADD FOREIGN KEY (actAccount)   REFERENCES accounts       (accNumber);
ALTER TABLE accountDetails ADD FOREIGN KEY (actSignatory) REFERENCES personal       (perID);
ALTER TABLE accountDetails ADD FOREIGN KEY (actCurrency)  REFERENCES currency       (ccyCode);
ALTER TABLE accountDetails ADD FOREIGN KEY (actCompany)   REFERENCES companyProfile (comID);

ALTER TABLE accounts ADD FOREIGN KEY (accCategory) REFERENCES accountCategory (acgID);

ALTER TABLE personal ADD FOREIGN KEY (perAddress) REFERENCES address (addID);

ALTER TABLE employees ADD FOREIGN KEY (empSalAccount) REFERENCES accounts (accNumber);
ALTER TABLE employees ADD FOREIGN KEY (empPersonal)   REFERENCES personal (perID);

ALTER TABLE employeeAttendence ADD FOREIGN KEY (emaEmployee) REFERENCES employees (empID);

ALTER TABLE payroll ADD FOREIGN KEY (prlTrnRef)   REFERENCES transactions (trnReference);
ALTER TABLE payroll ADD FOREIGN KEY (prlEmployee) REFERENCES employees    (empID);

ALTER TABLE branch ADD FOREIGN KEY (brcCompany) REFERENCES companyProfile (comID);
ALTER TABLE branch ADD FOREIGN KEY (brcAddress) REFERENCES address        (addID);

ALTER TABLE branchAuthLimit ADD FOREIGN KEY (balBranch)   REFERENCES branch   (brcID);
ALTER TABLE branchAuthLimit ADD FOREIGN KEY (balCurrency) REFERENCES currency (ccyCode);

ALTER TABLE product ADD FOREIGN KEY (proCategory) REFERENCES productCategory (pcID);

ALTER TABLE productImages ADD FOREIGN KEY (piProID) REFERENCES product (proID);

ALTER TABLE orders ADD FOREIGN KEY (ordTrnRef)   REFERENCES transactions (trnReference);
ALTER TABLE orders ADD FOREIGN KEY (ordPersonal) REFERENCES personal     (perID);
ALTER TABLE orders ADD FOREIGN KEY (ordBranch)   REFERENCES branch       (brcID);

ALTER TABLE stock ADD FOREIGN KEY (stkProduct) REFERENCES product  (proID);
ALTER TABLE stock ADD FOREIGN KEY (stkOrder)   REFERENCES orders   (ordID);
ALTER TABLE stock ADD FOREIGN KEY (stkStorage) REFERENCES storages (stgID);

ALTER TABLE tempStock ADD FOREIGN KEY (tstProduct) REFERENCES product  (proID);
ALTER TABLE tempStock ADD FOREIGN KEY (tstOrder)   REFERENCES orders   (ordID);
ALTER TABLE tempStock ADD FOREIGN KEY (tstStorage) REFERENCES storages (stgID);

ALTER TABLE reminders ADD FOREIGN KEY (rmdAccount) REFERENCES accounts (accNumber);

ALTER TABLE userActivityLog ADD FOREIGN KEY (ualUser) REFERENCES users (usrID);

-- ============================================================
-- FUNCTIONS
-- ============================================================
DROP FUNCTION IF EXISTS generate_ref;
DELIMITER $$
CREATE FUNCTION generate_ref(
  brch  VARCHAR(4) CHARSET utf8mb4,
  tType VARCHAR(4) CHARSET utf8mb4
)
RETURNS VARCHAR(19) CHARSET utf8mb4
READS SQL DATA
BEGIN
  DECLARE doy     varchar(3);
  DECLARE cYear   varchar(2);
  DECLARE lastRef varchar(19);
  DECLARE cdoy    varchar(3);

  SET doy   = LPAD(DAYOFYEAR(UTC_DATE()), 3, '000');
  SET cYear = RIGHT(YEAR(UTC_DATE()), 2);

  SELECT RIGHT(trnReference, 9)
    INTO lastRef
    FROM transactions
    ORDER BY trnID DESC
    LIMIT 1;

  SET cdoy = LEFT(lastRef, 3);

  IF cdoy = doy THEN
    SET lastRef = RIGHT(lastRef, 6) + 1;
    RETURN CONCAT(brch, tType, cYear, doy, LPAD(lastRef, 6, '000000'));
  ELSE
    SET lastRef = 1;
    RETURN CONCAT(brch, tType, cYear, doy, LPAD(lastRef, 6, '000000'));
  END IF;
END$$
DELIMITER ;

DROP FUNCTION IF EXISTS getRate;
DELIMITER $$
CREATE FUNCTION getRate(fromCcy varchar(3), toCcy varchar(3))
RETURNS decimal(12,6)
DETERMINISTIC
BEGIN
  RETURN (
    SELECT crExchange
    FROM ccyRate
    WHERE crFrom = fromCcy AND crTo = toCcy
    ORDER BY crID DESC
    LIMIT 1
  );
END$$
DELIMITER ;

-- ============================================================
-- VIEWS
-- ============================================================

-- View: Available Products
CREATE OR REPLACE VIEW vw_availableProducts AS
with recent_sales as (select `r`.`stkProduct` AS `proID`,json_arrayagg(json_object('date',date_format(`r`.`ordEntryDate`,'%Y-%m-%d %H:%i:%s'),'customer',`r`.`customerName`,'currency',`r`.`baseCurrency`,'order_id',cast(`r`.`ordID` as signed),'quantity',cast(`r`.`stkQuantity` * `r`.`stkQtyInBatch` as signed),'batch_qty',cast(`r`.`stkQtyInBatch` as signed),'order_type','Sale','unit_price',cast(round(`r`.`stkSalePrice`,0) as signed))) AS `recent_txn` from (select `s`.`stkProduct` AS `stkProduct`,`s`.`stkQuantity` AS `stkQuantity`,`s`.`stkQtyInBatch` AS `stkQtyInBatch`,`s`.`stkSalePrice` AS `stkSalePrice`,`o`.`ordID` AS `ordID`,`o`.`ordEntryDate` AS `ordEntryDate`,concat(coalesce(`p`.`perName`,''),' ',coalesce(`p`.`perLastName`,'')) AS `customerName`,`cp`.`comLocalCcy` AS `baseCurrency`,row_number() over ( partition by `s`.`stkProduct` order by `o`.`ordEntryDate` desc,`s`.`stkID` desc) AS `rn` from (((`u825845895_fbndatabase`.`stock` `s` join `u825845895_fbndatabase`.`orders` `o` on(`o`.`ordID` = `s`.`stkOrder`)) left join `u825845895_fbndatabase`.`personal` `p` on(`p`.`perID` = `o`.`ordPersonal`)) join (select `u825845895_fbndatabase`.`companyProfile`.`comLocalCcy` AS `comLocalCcy` from `u825845895_fbndatabase`.`companyProfile` limit 1) `cp`) where `s`.`stkEntryType` = 'OUT' and `s`.`stkOrder` is not null) `r` where `r`.`rn` <= 15 group by `r`.`stkProduct`)select `p`.`proID` AS `proID`,`p`.`proName` AS `proName`,`p`.`proCode` AS `proCode`,`p`.`proGrade` AS `proGrade`,`p`.`proUnit` AS `proUnit`,`p`.`proBrand` AS `proBrand`,`p`.`proCategory` AS `proCategory`,`p`.`proModel` AS `proModel`,`p`.`proMadeIn` AS `proMadeIn`,`p`.`proColor` AS `proColor`,`p`.`proDetails` AS `proDetails`,`p`.`proLSNqty` AS `proLSNqty`,`p`.`proStatus` AS `proStatus`,`b`.`batStorage` AS `stkStorage`,`st`.`stgName` AS `stgName`,max(`b`.`batItemsPerBox`) AS `stkQtyInBatch`,sum(`b`.`batRemainingItems`) AS `available`,sum(`b`.`batRemainingItems`) AS `available_Item`,round((select `b2`.`batPurPrice` from `u825845895_fbndatabase`.`batch` `b2` where `b2`.`batProduct` = `p`.`proID` and `b2`.`batStorage` = `b`.`batStorage` and `b2`.`batStatus` = 1 order by `b2`.`batID` desc limit 1),8) AS `recent_PurPrice`,round(sum(`b`.`batRemainingItems` * `b`.`batPurPrice`) / nullif(sum(`b`.`batRemainingItems`),0),8) AS `average_price`,round(sum(`b`.`batRemainingItems` * `b`.`batLandedPurPrice`) / nullif(sum(`b`.`batRemainingItems`),0),8) AS `average_landed_price`,(select `b3`.`batRef` from `u825845895_fbndatabase`.`batch` `b3` where `b3`.`batProduct` = `p`.`proID` and `b3`.`batStorage` = `b`.`batStorage` and `b3`.`batStatus` = 1 and `b3`.`batRemainingItems` > 0 order by `b3`.`batID` limit 1) AS `fifo_next_batch_ref`,(select `b6`.`batRemainingItems` from `u825845895_fbndatabase`.`batch` `b6` where `b6`.`batProduct` = `p`.`proID` and `b6`.`batStorage` = `b`.`batStorage` and `b6`.`batStatus` = 1 and `b6`.`batRemainingItems` > 0 order by `b6`.`batID` limit 1) AS `fifo_next_batch_remaining`,(select `b7`.`batEntryDate` from `u825845895_fbndatabase`.`batch` `b7` where `b7`.`batProduct` = `p`.`proID` and `b7`.`batStorage` = `b`.`batStorage` and `b7`.`batStatus` = 1 and `b7`.`batRemainingItems` > 0 order by `b7`.`batID` limit 1) AS `fifo_next_batch_entry`,(select `b3`.`batPurPrice` from `u825845895_fbndatabase`.`batch` `b3` where `b3`.`batProduct` = `p`.`proID` and `b3`.`batStorage` = `b`.`batStorage` and `b3`.`batStatus` = 1 and `b3`.`batRemainingItems` > 0 order by `b3`.`batID` limit 1) AS `fifo_next_unit_cost`,(select `b4`.`batLandedPurPrice` from `u825845895_fbndatabase`.`batch` `b4` where `b4`.`batProduct` = `p`.`proID` and `b4`.`batStorage` = `b`.`batStorage` and `b4`.`batStatus` = 1 and `b4`.`batRemainingItems` > 0 order by `b4`.`batID` limit 1) AS `fifo_next_unit_landed`,round(sum(`b`.`batRemainingItems` * `b`.`batPurPrice`),4) AS `fifo_cogs_total`,`p`.`proSPP` AS `sell_price`,`rs`.`recent_txn` AS `recent_txn` from (((`u825845895_fbndatabase`.`batch` `b` join `u825845895_fbndatabase`.`product` `p` on(`p`.`proID` = `b`.`batProduct`)) join `u825845895_fbndatabase`.`storages` `st` on(`st`.`stgID` = `b`.`batStorage`)) left join `recent_sales` `rs` on(`rs`.`proID` = `p`.`proID`)) where `b`.`batStatus` = 1 group by `p`.`proID`,`p`.`proName`,`p`.`proCode`,`p`.`proGrade`,`p`.`proUnit`,`p`.`proBrand`,`p`.`proCategory`,`p`.`proModel`,`p`.`proMadeIn`,`p`.`proColor`,`p`.`proDetails`,`p`.`proLSNqty`,`p`.`proStatus`,`b`.`batStorage`,`st`.`stgName`,`p`.`proSPP`,`rs`.`recent_txn` having `available` > 0

-- View: Stock Value Report
CREATE OR REPLACE VIEW vw_stockValueReport AS
WITH stock_ordered AS (
  SELECT
    stkID, stkProduct, stkStorage, stkQtyInBatch, stkEntryType,
    stkQuantity, stkPurPrice, stkLandedPurPrice,
    stkQuantity * stkQtyInBatch AS batch_total,
    SUM(CASE WHEN stkEntryType = 'IN' THEN  stkQuantity * stkQtyInBatch
             ELSE                            -stkQuantity * stkQtyInBatch END)
      OVER (PARTITION BY stkProduct ORDER BY stkID)                       AS running_balance,
    SUM(CASE WHEN stkEntryType = 'IN' THEN  stkQuantity * stkQtyInBatch
             ELSE                            -stkQuantity * stkQtyInBatch END)
      OVER (PARTITION BY stkProduct, stkStorage, stkQtyInBatch)            AS batch_balance,
    SUM(CASE WHEN stkEntryType = 'IN' THEN  stkQuantity * stkQtyInBatch
             ELSE                            -stkQuantity * stkQtyInBatch END)
      OVER (PARTITION BY stkProduct)                                       AS total_balance
  FROM stock
),
remaining_batches AS (
  SELECT
    stkID, stkProduct, stkStorage, stkQtyInBatch,
    stkPurPrice, stkLandedPurPrice, batch_total, batch_balance, total_balance,
    CASE WHEN stkEntryType = 'IN' AND batch_balance > 0 THEN batch_balance
         ELSE 0 END AS remaining_qty
  FROM stock_ordered
  WHERE stkEntryType = 'IN'
),
batch_value AS (
  SELECT
    stkProduct, stkStorage, stkQtyInBatch,
    SUM(remaining_qty)                     AS remaining_items,
    SUM(remaining_qty * stkPurPrice)       AS purchase_value,
    SUM(remaining_qty * stkLandedPurPrice) AS landed_value,
    COUNT(DISTINCT stkPurPrice)            AS price_variations_in_batch
  FROM remaining_batches
  WHERE remaining_qty > 0
  GROUP BY stkProduct, stkStorage, stkQtyInBatch
),
product_totals AS (
  SELECT
    stkProduct,
    SUM(purchase_value) AS total_purchase_value,
    SUM(landed_value)   AS total_landed_value,
    SUM(remaining_items) AS total_items,
    COUNT(*)            AS total_batches
  FROM batch_value
  GROUP BY stkProduct
)
SELECT
  p.proID, p.proName, p.proCode, p.proCategory, p.proBrand, p.proUnit, p.proSPP,
  st.stgID   AS storage_id,
  st.stgName AS storage_name,
  bv.stkQtyInBatch           AS batch_size,
  bv.remaining_items         AS items_in_batch,
  bv.price_variations_in_batch,
  ROUND(bv.purchase_value, 4) AS batch_purchase_value,
  ROUND(bv.landed_value,   4) AS batch_landed_value,
  ROUND(bv.purchase_value / NULLIF(bv.remaining_items, 0), 4) AS batch_avg_price,
  ROUND(pt.total_purchase_value, 4) AS product_total_purchase_value,
  ROUND(pt.total_landed_value,   4) AS product_total_landed_value,
  pt.total_items   AS product_total_items,
  pt.total_batches AS product_total_batches,
  ROUND(bv.purchase_value * (1 + COALESCE(p.proSPP, 0) / 100.0), 4) AS batch_expected_selling,
  ROUND(pt.total_purchase_value * (1 + COALESCE(p.proSPP, 0) / 100.0), 4) AS product_expected_selling
FROM product p
JOIN batch_value     bv ON bv.stkProduct = p.proID
JOIN product_totals  pt ON pt.stkProduct = p.proID
JOIN storages        st ON st.stgID      = bv.stkStorage
ORDER BY p.proName, st.stgName, bv.stkQtyInBatch;

-- View: Stock Availability
CREATE OR REPLACE VIEW vw_stockAvailability AS
SELECT
  p.proName, p.proID, p.proCode, st.stgName, sk.stkStorage,
  SUM(CASE WHEN stkEntryType = 'IN' THEN stkQuantity
           WHEN stkEntryType = 'OUT' THEN -stkQuantity END) AS available_quantity,
  COALESCE(
    CASE WHEN SUM(CASE WHEN stkEntryType='IN' THEN stkQuantity ELSE -stkQuantity END) = 0 THEN 0
         ELSE SUM(CASE WHEN stkEntryType='IN' THEN stkQuantity * stkPurPrice END) END
    / SUM(CASE WHEN stkEntryType = 'IN' THEN stkQuantity
               WHEN stkEntryType = 'OUT' THEN -stkQuantity END), 0) AS pricePerUnit,
  CASE WHEN SUM(CASE WHEN stkEntryType='IN' THEN stkQuantity ELSE -stkQuantity END) = 0 THEN 0
       ELSE SUM(CASE WHEN stkEntryType='IN' THEN stkQuantity * stkPurPrice END) END AS total
FROM stock sk
JOIN product  p ON p.proID = sk.stkProduct
JOIN storages st ON st.stgID = sk.stkStorage
GROUP BY p.proName, p.proID, p.proCode, st.stgName, sk.stkStorage;

-- View: Product In/Out
CREATE OR REPLACE VIEW vw_productInOut AS
SELECT
  s.stkOrder  AS orderID,
  o.ordPersonal AS perID,
  CONCAT(pr.perName, ' ', pr.perLastName) AS fullname,
  s.stkProduct AS productID,
  p.proName    AS productName,
  s.stkStorage AS storageID,
  g.stgName    AS storageName,
  s.stkEntryType AS entryType,
  DATE(o.ordEntryDate) AS entryDate,
  s.stkQuantity AS quantity,
  CASE WHEN s.stkEntryType = 'IN' THEN s.stkPurPrice ELSE s.stkSalePrice END AS price
FROM stock s
JOIN product  p ON p.proID   = s.stkProduct
JOIN orders   o ON o.ordID   = s.stkOrder
JOIN storages g ON g.stgID   = s.stkStorage
JOIN personal pr ON pr.perID = o.ordPersonal;

-- View: All Orders
CREATE OR REPLACE VIEW vw_allOrders AS
SELECT
  od.ordID, od.ordName, od.ordPersonal,
  CONCAT(pr.perName, ' ', pr.perLastName) AS fullName,
  br.brcName AS ordBranchName, od.ordBranch, od.ordTrnRef, od.ordxRef,
  CASE
    WHEN MAX(sk.stkEntryType) = 'IN'  THEN SUM(sk.stkQuantity * sk.stkQtyInBatch * sk.stkPurPrice)
    WHEN MAX(sk.stkEntryType) = 'OUT' THEN SUM(sk.stkQuantity * sk.stkQtyInBatch * sk.stkSalePrice)
    WHEN od.ordName = 'Estimate'      THEN SUM(tsk.tstQuantity * tsk.tstSalePrice)
    ELSE 0
  END AS total_bill,
  DATE(od.ordEntryDate) AS timing
FROM orders od
LEFT JOIN transactions tr ON tr.trnReference = od.ordTrnRef
LEFT JOIN personal     pr ON pr.perID        = od.ordPersonal
JOIN branch            br ON br.brcID        = od.ordBranch
LEFT JOIN stock        sk ON sk.stkOrder     = od.ordID
LEFT JOIN tempStock    tsk ON tsk.tstOrder   = od.ordID
GROUP BY od.ordID, od.ordPersonal, od.ordName, od.ordBranch, od.ordTrnRef,
         od.ordxRef, pr.perName, pr.perLastName, br.brcName, od.ordEntryDate;

-- View: Personal Accounts
CREATE OR REPLACE VIEW vw_personalAccounts AS
SELECT
  p.perID,
  CONCAT(p.perName, ' ', p.perLastName) AS fullName,
  p.perPhone,
  ac.accNumber,
  ac.accName,
  ad.actCurrency AS accCurrency,
  ad.actStatus   AS accStatus,
  CASE WHEN ad.actCreditLimit = 999999999999.9999 THEN 'Unlimited'
       ELSE ad.actCreditLimit END AS accLimit,
  SUM(CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount ELSE -td.trdAmount END) AS accBalance
FROM trnDetails td
JOIN accounts       ac ON ac.accNumber  = td.trdAccount
JOIN accountDetails ad ON ad.actAccount = ac.accNumber
JOIN personal       p  ON p.perID       = ad.actSignatory
GROUP BY ac.accNumber, ac.accName, ad.actCurrency, ad.actStatus,
         ad.actCreditLimit, p.perID, p.perName, p.perLastName, p.perPhone;

-- View: Exchange Rate Report
CREATE OR REPLACE VIEW vw_exchangeRate AS
SELECT
  DATE(t.crDate)          AS rate_date,
  cf.ccyCode              AS from_code,
  cf.ccyCountryCode       AS from_countryCode,
  cf.ccyName              AS from_name,
  cf.ccyLocalName         AS from_localName,
  cf.ccySymbol            AS from_symbol,
  cf.ccyCountry           AS from_country,
  ct.ccyCode              AS to_code,
  ct.ccyCountryCode       AS to_countryCode,
  ct.ccyName              AS to_name,
  ct.ccyLocalName         AS to_localName,
  ct.ccySymbol            AS to_symbol,
  ct.ccyCountry           AS to_country,
  t.crExchange,
  AVG(t.crExchange)       AS avg_rate
FROM ccyRate t
JOIN currency cf ON cf.ccyCode = t.crFrom
JOIN currency ct ON ct.ccyCode = t.crTo
GROUP BY DATE(t.crDate), t.crFrom, t.crTo,
         cf.ccyCode, cf.ccyCountryCode, cf.ccyName, cf.ccyLocalName, cf.ccySymbol, cf.ccyCountry,
         ct.ccyCode, ct.ccyCountryCode, ct.ccyName, ct.ccyLocalName, ct.ccySymbol, ct.ccyCountry,
         t.crExchange
ORDER BY rate_date DESC, t.crFrom, t.crTo;

-- View: User Report
CREATE OR REPLACE VIEW vw_userReport AS
SELECT
  tr.trnReference AS reference,
  tr.trnType      AS typeCode,
  trntName        AS typeName,
  tr.trnStatus    AS status,
  tr.trnStateText AS statusText,
  mk.usrName      AS maker,
  ck.usrName      AS checker,
  td.trdCcy       AS currency,
  SUM(CASE WHEN td.trdDrCr = 'Cr' THEN trdAmount ELSE trdAmount END) / 2 AS amount,
  tr.trnEntryDate AS timing
FROM transactions tr
LEFT JOIN users    mk ON mk.usrID = tr.trnMaker
LEFT JOIN users    ck ON ck.usrID = tr.trnAuthorizer
JOIN trnDetails    td ON td.trdReference = tr.trnReference
JOIN trnTypes      tp ON tp.trntCode     = tr.trnType
GROUP BY trnReference, trntName, trnStatus, trnStateText,
         mk.usrName, ck.usrName, tr.trnType, tr.trnEntryDate;

-- View: All Accounts with Balances
CREATE OR REPLACE VIEW vw_allAccountwithBalances AS
SELECT
  td.trdAccount, ac.accName, td.trdCcy, td.trdBranch, ag.acgName, ag.acgID,
  SUM(CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount ELSE -td.trdAmount END) AS balance
FROM trnDetails td
JOIN transactions    tr ON tr.trnReference = td.trdReference
JOIN accounts        ac ON ac.accNumber    = td.trdAccount
JOIN accountCategory ag ON ag.acgID        = ac.accCategory
LEFT JOIN accountDetails ad ON ad.actAccount = ac.accNumber
LEFT JOIN personal      p   ON p.perID       = ad.actSignatory
WHERE ac.accCategory = 8
GROUP BY td.trdAccount, ac.accName, td.trdCcy, td.trdBranch, ag.acgName, ag.acgID
UNION ALL
SELECT
  td.trdAccount, ac.accName, td.trdCcy, td.trdBranch, ag.acgName, ag.acgID,
  SUM(CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount ELSE -td.trdAmount END) AS balance
FROM trnDetails td
JOIN transactions    tr ON tr.trnReference = td.trdReference
JOIN accounts        ac ON ac.accNumber    = td.trdAccount
JOIN accountCategory ag ON ag.acgID        = ac.accCategory
LEFT JOIN accountDetails ad ON ad.actAccount = ac.accNumber
LEFT JOIN personal      p   ON p.perID       = ad.actSignatory
WHERE ac.accCategory != 8
GROUP BY td.trdAccount, ac.accName, td.trdCcy, td.trdBranch, ag.acgName, ag.acgID;

-- View: All Transaction Reports
CREATE OR REPLACE VIEW vw_alltransactionreports AS
SELECT
  tr.trnReference AS reference,
  tr.trnType      AS typeCode,
  tp.trntName     AS trnType,
  tr.trnStatus    AS trnStatus,
  tr.trnStateText AS statusText,
  mk.usrName      AS maker,
  ck.usrName      AS checker,
  GROUP_CONCAT(DISTINCT td.trdCcy SEPARATOR ', ') AS currency,
  SUM(CASE WHEN td.trdDrCr = 'Dr' THEN td.trdAmount ELSE 0 END) AS actual_amount,
  ROUND(SUM(CASE WHEN td.trdDrCr = 'Dr' THEN td.trdAmount * getRate(td.trdCcy, 'USD') ELSE 0 END), 4) AS sys_equivalent,
  (SELECT trdNarration FROM trnDetails WHERE trdReference = tr.trnReference LIMIT 1) AS narration,
  tr.trnEntryDate AS timing
FROM transactions tr
LEFT JOIN users mk ON mk.usrID = tr.trnMaker
LEFT JOIN users ck ON ck.usrID = tr.trnAuthorizer
JOIN trnDetails td ON td.trdReference = tr.trnReference
JOIN trnTypes   tp ON tp.trntCode     = tr.trnType
GROUP BY tr.trnReference, tr.trnType, tp.trntName, tr.trnStatus, tr.trnStateText,
         mk.usrName, ck.usrName, tr.trnEntryDate;

-- View: Expense Report
CREATE OR REPLACE VIEW vw_expenseReport AS
SELECT
  DATE(tr.trnEntryDate) AS transaction_date,
  tr.trnReference       AS reference,
  td.trdAccount         AS account_number,
  ac.accName            AS account_name,
  ag.acgName            AS expense_category,
  td.trdCcy             AS currency,
  td.trdAmount          AS expense_amount,
  ROUND(td.trdAmount * getRate(td.trdCcy, 'USD'), 4) AS usd_equivalent,
  td.trdNarration       AS narration,
  b.brcName             AS branch,
  mu.usrName            AS maker,
  tr.trnStateText       AS status,
  tr.trnEntryDate       AS full_datetime
FROM trnDetails td
JOIN transactions    tr ON tr.trnReference = td.trdReference
JOIN accounts        ac ON ac.accNumber    = td.trdAccount
JOIN accountCategory ag ON ag.acgID        = ac.accCategory
LEFT JOIN branch     b  ON b.brcID         = td.trdBranch
LEFT JOIN users      mu ON mu.usrID        = tr.trnMaker
WHERE ac.accCategory IN (11, 12)
  AND tr.trnStateText IN ('Authorized', 'Pending')
ORDER BY tr.trnEntryDate DESC;

-- View: Attendance to Payroll
CREATE OR REPLACE VIEW vw_attendenceToPayroll AS
SELECT
  ea.emaEmployee,
  CONCAT(p.perName, ' ', p.perLastName) AS fullName,
  DATE_FORMAT(ea.emaDate, '%Y-%m')      AS work_month,
  em.empSalAccount, em.empSalary, ad.actCurrency, em.empSalCalcBase,
  COUNT(DISTINCT ea.emaDate)   AS days,
  SUM(ea.emaWorkHours)         AS hoursInMonth,
  SUM(TIME_TO_SEC(TIMEDIFF(ea.emaCheckedOut, ea.emaCheckedIn)) / 3600) AS workedHours,
  COALESCE(pr.isPaid, 0)       AS payment
FROM employeeAttendence ea
JOIN employees       em ON em.empID        = ea.emaEmployee
JOIN personal        p  ON p.perID         = em.empPersonal
JOIN accounts        a  ON a.accNumber     = em.empSalAccount
JOIN accountDetails  ad ON ad.actAccount   = a.accNumber
LEFT JOIN (
  SELECT prlEmployee, prlMonthYear, 1 AS isPaid
  FROM payroll
  GROUP BY prlEmployee, prlMonthYear
) pr ON pr.prlEmployee   = em.empID
    AND pr.prlMonthYear  = DATE_FORMAT(ea.emaDate, '%Y-%m')
GROUP BY ea.emaEmployee, work_month, em.empSalAccount, em.empSalary,
         ad.actCurrency, em.empSalCalcBase, p.perName, p.perLastName;

-- ============================================================
-- EVENTS
-- ============================================================
CREATE EVENT delete_old_login_logs
ON SCHEDULE EVERY 1 DAY
DO
  DELETE FROM userActivityLog
  WHERE ualType = 'User Login'
    AND ualTiming < NOW() - INTERVAL 30 DAY;

DELIMITER $$
CREATE EVENT checkSubscription
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
  IF EXISTS (SELECT 1 FROM subscription WHERE subExpireDate <= CURDATE()) THEN
    UPDATE subscription
    SET subKey = 'Expired'
    ORDER BY subID DESC
    LIMIT 1;
  END IF;
END$$
DELIMITER ;

-- ============================================================
-- STORED PROCEDURE: sp_income_statement
-- ============================================================
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_income_statement$$
CREATE PROCEDURE sp_income_statement(
  IN p_start_date DATE,
  IN p_end_date   DATE
)
BEGIN
  DECLARE v_base_currency VARCHAR(3);

  SELECT comLocalCcy
    INTO v_base_currency
    FROM companyProfile
    LIMIT 1;

  SELECT
    'INCOME'        AS type,
    ac.accNumber    AS account_number,
    ag.acgName      AS category,
    ac.accName      AS account_name,
    td.trdCcy       AS currency,
    SUM(td.trdAmount) AS amount_original,
    ROUND(SUM(td.trdAmount * getRate(td.trdCcy, v_base_currency)), 2) AS amount_base,
    v_base_currency AS base_currency,
    getRate(td.trdCcy, v_base_currency) AS exchange_rate_used
  FROM trnDetails td
  JOIN transactions    tr ON tr.trnReference = td.trdReference
  JOIN accounts        ac ON ac.accNumber    = td.trdAccount
  JOIN accountCategory ag ON ag.acgID        = ac.accCategory
  WHERE ac.accCategory IN (9, 10)
    AND tr.trnStateText = 'Authorized'
    AND DATE(tr.trnEntryDate) BETWEEN p_start_date AND p_end_date
    AND td.trdDrCr = 'Cr'
  GROUP BY ac.accNumber, ag.acgName, ac.accName, td.trdCcy

  UNION ALL

  SELECT
    'EXPENSE'       AS type,
    ac.accNumber    AS account_number,
    ag.acgName      AS category,
    ac.accName      AS account_name,
    td.trdCcy       AS currency,
    SUM(td.trdAmount) AS amount_original,
    ROUND(SUM(td.trdAmount * getRate(td.trdCcy, v_base_currency)), 2) AS amount_base,
    v_base_currency AS base_currency,
    getRate(td.trdCcy, v_base_currency) AS exchange_rate_used
  FROM trnDetails td
  JOIN transactions    tr ON tr.trnReference = td.trdReference
  JOIN accounts        ac ON ac.accNumber    = td.trdAccount
  JOIN accountCategory ag ON ag.acgID        = ac.accCategory
  WHERE ac.accCategory IN (11, 12)
    AND tr.trnStateText = 'Authorized'
    AND DATE(tr.trnEntryDate) BETWEEN p_start_date AND p_end_date
    AND td.trdDrCr = 'Dr'
  GROUP BY ac.accNumber, ag.acgName, ac.accName, td.trdCcy

  UNION ALL

  SELECT
    'NET PROFIT' AS type,
    0            AS account_number,
    ''           AS category,
    'Total'      AS account_name,
    ''           AS currency,
    0            AS amount_original,
    ROUND(
      (
        SELECT COALESCE(SUM(td.trdAmount * getRate(td.trdCcy, v_base_currency)), 0)
        FROM trnDetails td
        JOIN transactions tr ON tr.trnReference = td.trdReference
        JOIN accounts     ac ON ac.accNumber    = td.trdAccount
        WHERE ac.accCategory IN (9, 10)
          AND tr.trnStateText = 'Authorized'
          AND DATE(tr.trnEntryDate) BETWEEN p_start_date AND p_end_date
          AND td.trdDrCr = 'Cr'
      ) - (
        SELECT COALESCE(SUM(td.trdAmount * getRate(td.trdCcy, v_base_currency)), 0)
        FROM trnDetails td
        JOIN transactions tr ON tr.trnReference = td.trdReference
        JOIN accounts     ac ON ac.accNumber    = td.trdAccount
        WHERE ac.accCategory IN (11, 12)
          AND tr.trnStateText = 'Authorized'
          AND DATE(tr.trnEntryDate) BETWEEN p_start_date AND p_end_date
          AND td.trdDrCr = 'Dr'
      ),
    2) AS amount_base,
    v_base_currency AS base_currency,
    0 AS exchange_rate_used

  ORDER BY FIELD(type, 'INCOME', 'EXPENSE', 'NET PROFIT'), category, account_name;
END$$
DELIMITER ;