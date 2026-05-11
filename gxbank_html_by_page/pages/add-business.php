<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];
$error = "";

function generateBusinessAccountNumber($conn) {
    do {
        $account_number = "56" . rand(100000, 999999);

        $stmt = mysqli_prepare($conn, "SELECT account_id FROM accounts WHERE account_number = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $account_number);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        $exists = mysqli_stmt_num_rows($stmt) > 0;
    } while ($exists);

    return $account_number;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $business_name = trim($_POST['business_name'] ?? '');
    $business_reg_no = trim($_POST['business_reg_no'] ?? '');

    if ($business_name == "" || $business_reg_no == "") {
        $error = "Please fill in business name and registration number.";
    } else {
        $business_account_number = generateBusinessAccountNumber($conn);
        $balance = 0.00;

        $stmt = mysqli_prepare($conn, "INSERT INTO accounts 
            (user_id, account_type, business_name, business_reg_no, account_number, balance)
            VALUES (?, 'Business Account', ?, ?, ?, ?)");

        mysqli_stmt_bind_param($stmt, "isssd", $user_id, $business_name, $business_reg_no, $business_account_number, $balance);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Business account added successfully.";
            header("Location: /gxbank_html_by_page/pages/biz-account.php");
            exit();
        } else {
            $error = "Failed to add business account: " . mysqli_error($conn);
        }
    }
}
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Add Business</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* { font-family: 'DM Sans', sans-serif; }
body { box-sizing: border-box; }
</style>
</head>

<body class="h-full">
<div class="h-full w-full bg-slate-950 overflow-auto">
    <div class="max-w-md mx-auto px-5 py-8">

        <a href="/gxbank_html_by_page/pages/biz-account.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Back</span>
        </a>

        <h1 class="text-2xl font-bold text-white mb-2">Add Business</h1>
        <p class="text-slate-400 text-sm mb-6">
            Create a new business account with its own account number.
        </p>

        <?php if($error != "") { ?>
            <div class="bg-red-500/15 text-red-300 rounded-xl p-3 text-sm mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php } ?>

        <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800">
            <form method="POST" class="space-y-4">

                <div>
                    <label class="text-slate-300 text-sm font-medium">Business Name</label>
                    <input type="text" name="business_name" required
                        placeholder="Example: ABC Enterprise"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-indigo-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Business Registration No.</label>
                    <input type="text" name="business_reg_no" required
                        placeholder="Example: 202401001234"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-indigo-400">
                </div>

                <button type="submit"
                    class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-3 rounded-xl transition">
                    Create Business Account
                </button>

            </form>
        </div>

    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>