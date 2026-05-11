GXBank Web Application - README
Deployment Link:https://cheesesedapgxbank.gamer.gd/gxbank_html_by_page/index.php

Project Overview
----------------
GXBank Web Application is a PHP and MySQL banking simulation project built for local XAMPP usage. The system includes user registration, login, personal banking, business banking, QR payment, FlexiCredit, Biz Flexi Loan, Bonus Pockets, Debit Card, and GX Protect insurance with fuzzy logic risk assessment.

This project is designed for testing and demonstration purposes only.

------------------------------------------------------------
1. SYSTEM REQUIREMENTS
------------------------------------------------------------

Required software:
- XAMPP
- PHP 8 or above
- MySQL / MariaDB
- phpMyAdmin
- Web browser such as Chrome, Edge, or Firefox

Recommended local setup:
- Apache: ON
- MySQL: ON

------------------------------------------------------------
2. PROJECT FOLDER SETUP
------------------------------------------------------------

Place the project folder inside:

C:\xampp\htdocs\

The folder should look like this:

C:\xampp\htdocs\gxbank_html_by_page\

Main project URL:

http://localhost/gxbank_html_by_page/index.php

------------------------------------------------------------
3. DATABASE SETUP
------------------------------------------------------------

Step 1:
Open phpMyAdmin:

http://localhost/phpmyadmin

Step 2:
Create a new database named:

gxbank_app

Step 3:
Click the gxbank_app database.

Step 4:
Go to the Import tab.

Step 5:
Choose the provided SQL file:

gxbank_app_final.sql

Step 6:
Click Go.

The SQL file contains:
- Table structure
- Sample users
- Sample accounts
- Product menu data
- Transactions
- FlexiCredit data
- Business loan data
- Insurance data
- Admin account

------------------------------------------------------------
4. DATABASE CONNECTION
------------------------------------------------------------

Database connection file:

gxbank_html_by_page/php/dbconn.php

Default XAMPP database connection usually uses:

Host: localhost
Username: root
Password: empty
Database: gxbank_app

Example:

<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "gxbank_app";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>

If your MySQL runs on a different port such as 3307, adjust the host:

$host = "localhost:3307";

------------------------------------------------------------
5. DEFAULT ADMIN ACCOUNT
------------------------------------------------------------

The system includes a default admin test account.

Login details:

Username: admin
Password: admin

Admin account number:

48999999

Admin balance:

RM999,999,999.99

Purpose of admin account:
The admin account is used as a high-balance testing account to distribute money to other users during project demonstration.

Example usage:
1. Login as admin.
2. Open GX Account.
3. Use Transfer feature.
4. Enter another user's account number.
5. Transfer test money to that user.

This makes it easier to test:
- Normal transfer
- QR payment
- Business account payment
- Bonus Pocket transfer
- Debit card usage
- Loan repayment
- Insurance premium payment

------------------------------------------------------------
6. SAMPLE USER ACCOUNTS
------------------------------------------------------------

The database may include sample users for testing.

You may test using existing users or register a new user from the Register page.

Important:
Each user must have a unique username and unique email address.

If a user tries to register using an existing username or email, the system will reject the registration.

------------------------------------------------------------
7. HOW TO NAVIGATE THE SYSTEM
------------------------------------------------------------

Start here:

http://localhost/gxbank_html_by_page/index.php

Login Page:
- Existing users can login using username/email and password.
- New users can click Register New User.

Register Page:
- User enters username, full name, age, email, and password.
- Full name is automatically stored in uppercase.
- After successful registration, user is redirected back to login page.

Dashboard:
After login, user will see the main dashboard with product buttons.

Main dashboard features:
- GX Account
- Bonus Pockets
- GX Debit Card
- FlexiCredit
- GX Biz Account
- GX Protect
- Biz Flexi Loan

User Profile Menu:
- Click the user icon at the top right.
- User can update information or logout.
- Logout will show confirmation popup before signing out.

------------------------------------------------------------
8. GX ACCOUNT
------------------------------------------------------------

GX Account is the main personal savings account.

Features:
- View balance
- Show/hide balance and account number
- Copy account number
- Transfer money to another account
- Receive money using QR request
- Scan and Pay using QR code
- View recent transactions

Navigation:
Dashboard -> GX Account

Important:
Transfers are recorded in transaction history for both sender and receiver.

------------------------------------------------------------
9. TRANSFER MONEY
------------------------------------------------------------

Transfer feature allows the user to transfer money to an existing account number in the database.

Steps:
1. Open GX Account.
2. Click Transfer.
3. Choose source account.
4. Enter recipient account number.
5. Enter amount.
6. Enter reason.
7. Continue.
8. Confirm recipient name, account number, amount, and reason.
9. Confirm transfer.

Supported source:
- Personal Savings Account
- Business Account
- FlexiCard Credit, if approved and active

If FlexiCard is locked, it cannot be used for transfer.

------------------------------------------------------------
10. RECEIVE MONEY / PAYMENT REQUEST QR
------------------------------------------------------------

Receive feature allows user to generate a QR payment request.

Steps:
1. Open GX Account.
2. Click Receive.
3. Enter requested amount.
4. Enter reason.
5. Generate QR.
6. Other user scans the QR using Scan & Pay.
7. Amount and reason will be fixed and cannot be changed.
8. Payer confirms payment.

This simulates a payment request feature like modern banking apps.

------------------------------------------------------------
11. SCAN & PAY
------------------------------------------------------------

Scan & Pay allows user to scan or upload a QR code.

Supported QR formats:
- GXACC|account_number
- GXREQ|account_number|amount|reason

Normal account QR:
User can enter amount and reason manually.

Payment request QR:
Amount and reason are auto-filled and locked.

Navigation:
GX Account -> Scan & Pay

------------------------------------------------------------
12. BONUS POCKETS
------------------------------------------------------------

Bonus Pockets is a savings goal feature.

Features:
- Create tabung / savings pocket
- Set target amount
- Set deadline
- Transfer money into tabung from main account
- Transfer money out from tabung to main account
- Delete tabung
- If tabung is deleted, remaining balance is returned to main account
- Transactions are recorded

Navigation:
Dashboard -> Bonus Pockets

Important:
Bonus Pocket does not use account number. It only transfers between main account and tabung.

------------------------------------------------------------
13. GX DEBIT CARD
------------------------------------------------------------

GX Debit Card allows user to create and manage debit card information.

Features:
- Create debit card
- Show card details
- Change card information
- Delete card
- Store card number, CVC, expiry date, status, limit, and spending

Navigation:
Dashboard -> GX Debit Card

------------------------------------------------------------
14. FLEXICREDIT
------------------------------------------------------------

FlexiCredit is a personal virtual credit facility.

Features:
- Apply FlexiCard
- AI rule-based approval
- Approved limit
- Available limit
- Outstanding balance
- Virtual Flexi account number
- Use FlexiCredit for transfer / QR payment
- Repay FlexiCredit from personal savings account
- Lock / unlock FlexiCard
- AI limit review based on repayment behaviour

Navigation:
Dashboard -> FlexiCredit

Important:
If FlexiCard is locked:
- User cannot transfer using FlexiCredit
- User cannot pay QR using FlexiCredit
- User can still repay outstanding balance

------------------------------------------------------------
15. GX BIZ ACCOUNT
------------------------------------------------------------

GX Biz Account allows user to create one or more business accounts.

Features:
- Create business account
- Each business account has its own account number
- Business account can receive money
- Business account can send transfer
- Business account can generate QR
- Delete business account
- If deleted, remaining business account balance returns to main account

Navigation:
Dashboard -> GX Biz Account

Business account is treated like a separate account under the same user.

------------------------------------------------------------
16. BIZ FLEXI LOAN
------------------------------------------------------------

Biz Flexi Loan is a business financing feature tied to a business account.

Requirement:
User must have at least one business account before applying.

Features:
- Apply loan for selected business account
- AI rule-based approval
- Requested amount
- Approved amount
- Outstanding amount
- Business revenue and expense assessment
- Interest rate
- Monthly repayment
- Loan disbursement into selected business account
- Repayment from business account balance
- Loan transaction history

Navigation:
Dashboard -> Biz Flexi Loan

Flow:
1. User applies loan for business account.
2. System calculates AI score.
3. If approved, loan amount is disbursed into the business account.
4. Business account balance increases.
5. User can repay loan from the business account.

------------------------------------------------------------
17. GX PROTECT INSURANCE
------------------------------------------------------------

GX Protect is an insurance module using fuzzy logic risk assessment.

Features:
- Apply insurance plan
- Choose Basic Protect, Family Protect, or Premium Protect
- Enter age, monthly income, smoking status, existing illness, beneficiary
- Fuzzy logic calculates risk score
- System decides approval status
- Premium loading applies for smoker, illness, and medium fuzzy risk
- User can pay premium from main account
- User can cancel policy
- Payment history is recorded

Navigation:
Dashboard -> GX Protect

Premium loading logic:
- Non-smoker: no smoker loading
- Smoker: +25% loading
- Existing illness: +35% loading
- Medium fuzzy risk: +20% loading
- High risk: rejected

This module demonstrates fuzzy logic decision-making in insurance approval.

------------------------------------------------------------
18. TESTING MONEY FLOW
------------------------------------------------------------

Recommended testing flow:

1. Login as admin.
2. Transfer money to a normal user.
3. Login as normal user.
4. Test GX Account transfer.
5. Create Bonus Pocket and transfer money into it.
6. Create Business Account.
7. Transfer money to Business Account.
8. Apply Biz Flexi Loan.
9. Apply FlexiCredit.
10. Generate Receive QR.
11. Scan QR using another account.
12. Apply GX Protect insurance.
13. Pay insurance premium.

To test two users at the same time:
- Use Chrome for User A.
- Use Incognito or another browser for User B.

Using two accounts in the same normal browser session may conflict because PHP sessions are shared by browser cookies.

------------------------------------------------------------
19. IMPORTANT NOTES FOR DEMO
------------------------------------------------------------

This project is for educational and demonstration purposes only.

The system simulates banking features, but it is not connected to any real bank or payment system.

Admin account is intentionally given a very large balance for testing money distribution.

All AI and fuzzy logic features are simplified and hardcoded for project demonstration:
- FlexiCredit uses rule-based credit scoring.
- Biz Flexi Loan uses rule-based business loan approval.
- GX Protect uses fuzzy logic risk assessment.

------------------------------------------------------------
20. COMMON ISSUES
------------------------------------------------------------

Issue:
Page shows Not Found.

Cause:
Wrong page URL or file not placed inside the correct folder.

Fix:
Make sure project folder is inside:
C:\xampp\htdocs\gxbank_html_by_page\

Issue:
Database connection failed.

Cause:
Wrong database username, password, database name, or port.

Fix:
Check:
gxbank_html_by_page/php/dbconn.php

Issue:
Duplicate username or email.

Cause:
Username or email already exists.

Fix:
Use a new username and email.

Issue:
Cannot login using two users in same browser.

Cause:
PHP session is shared across browser tabs.

Fix:
Use Incognito or another browser.

------------------------------------------------------------
21. FINAL SUBMISSION FILES
------------------------------------------------------------

Recommended submission structure:

GXBank_Project_Submission/
- gxbank_html_by_page/
- gxbank_app_final.sql
- README.txt

Setup order:
1. Copy project folder to htdocs.
2. Create gxbank_app database.
3. Import SQL file.
4. Check dbconn.php.
5. Open index.php.
6. Login or register.

------------------------------------------------------------
END OF README
------------------------------------------------------------
