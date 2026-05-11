<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
include "../php/dbconn.php";
$user_id = $_SESSION['user_id'];
?>
<?php
$stmt = mysqli_prepare($conn, "SELECT * FROM credit_facilities WHERE user_id = ? AND facility_type = 'business' LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$loan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<title>GX Biz FlexiLoan</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* { font-family: 'DM Sans', sans-serif; }
body { box-sizing: border-box; }
.feature-btn { transition: transform 0.15s, box-shadow 0.15s; }
.feature-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
.feature-btn:active { transform: translateY(0); }
</style>

</head>
<body class="h-full">
<div class="h-full w-full bg-slate-950 overflow-auto">
    <div class="max-w-md mx-auto px-5 py-8">
        <a href="../dashboard.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Back</span>
        </a>

        <h1 class="text-2xl font-bold text-white mb-6">GX Biz FlexiLoan</h1>

        <div class="space-y-4">
            <div class="bg-gradient-to-br from-teal-500/20 to-teal-600/20 rounded-xl p-4 border border-teal-500/30">
                <p class="text-teal-200 text-sm">Available Loan</p>
                <p class="text-white text-2xl font-bold mt-1">RM <?php echo number_format($loan['available_limit'], 2); ?></p>
                <p class="text-teal-300 text-xs mt-2">Based on business turnover</p>
            </div>

            <div class="bg-slate-900 rounded-xl p-4 border border-slate-800">
                <p class="text-slate-300 font-medium text-sm mb-4">Current Loan</p>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-slate-400 text-sm">Loan Amount</span>
                        <span class="text-white font-medium">RM <?php echo number_format($loan['current_loan'], 2); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400 text-sm">Monthly Payment</span>
                        <span class="text-white font-medium">RM <?php echo number_format($loan['monthly_payment'], 2); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400 text-sm">Remaining Term</span>
                        <span class="text-white font-medium"><?php echo $loan['remaining_term']; ?> months</span>
                    </div>
                </div>
            </div>

            <button class="w-full bg-teal-500 hover:bg-teal-600 text-white font-medium py-3 rounded-lg transition">
                Apply for Additional Loan
            </button>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
