<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT * FROM accounts WHERE user_id = ? AND account_type = 'Business Account' ORDER BY account_id DESC");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$business_result = mysqli_stmt_get_result($stmt);
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>GX Biz Account</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* { font-family: 'DM Sans', sans-serif; }
body { box-sizing: border-box; }
.biz-card { transition: transform .15s, border-color .15s, box-shadow .15s; }
.biz-card:hover { transform: translateY(-3px); border-color: rgba(99,102,241,.65); box-shadow: 0 12px 28px rgba(0,0,0,.25); }
</style>
</head>

<body class="h-full">
<div class="h-full w-full bg-slate-950 overflow-auto">
    <div class="max-w-md mx-auto px-5 py-8">

        <a href="/gxbank_html_by_page/dashboard.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Back</span>
        </a>

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white mb-1">GX Biz Account</h1>
                <p class="text-slate-400 text-sm">Manage your business accounts.</p>
            </div>

            <a href="/gxbank_html_by_page/pages/add-business.php"
               class="w-11 h-11 rounded-xl bg-indigo-500 hover:bg-indigo-600 flex items-center justify-center transition">
                <i data-lucide="plus" class="w-5 h-5 text-white"></i>
            </a>
        </div>

        <?php if(isset($_SESSION['success'])) { ?>
            <div class="bg-emerald-500/15 text-emerald-300 rounded-xl p-3 text-sm mb-4">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php } ?>

        <?php if(isset($_SESSION['error'])) { ?>
            <div class="bg-red-500/15 text-red-300 rounded-xl p-3 text-sm mb-4">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php } ?>

        <?php if (mysqli_num_rows($business_result) == 0) { ?>

            <div class="bg-slate-900 rounded-2xl p-6 border border-slate-800 text-center">
                <div class="w-16 h-16 rounded-2xl bg-indigo-500/15 flex items-center justify-center mx-auto mb-5">
                    <i data-lucide="briefcase" class="w-8 h-8 text-indigo-400"></i>
                </div>

                <h2 class="text-white text-xl font-bold mb-2">No Business Account Yet</h2>

                <p class="text-slate-400 text-sm mb-6">
                    Add a business account to receive payment using its own account number and QR code.
                </p>

                <a href="/gxbank_html_by_page/pages/add-business.php"
                   class="block w-full bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-3 rounded-xl transition">
                    Add Business
                </a>
            </div>

        <?php } else { ?>

            <a href="/gxbank_html_by_page/pages/add-business.php"
               class="block w-full text-center bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-3 rounded-xl transition mb-4">
                + Add Business
            </a>

            <div class="space-y-4">
                <?php while($biz = mysqli_fetch_assoc($business_result)) { 
                    $last4 = substr($biz['account_number'], -4);
                ?>
                    <a href="/gxbank_html_by_page/pages/biz-detail.php?account_id=<?php echo intval($biz['account_id']); ?>"
                       class="biz-card group block bg-slate-900 rounded-2xl p-5 border border-slate-800">

                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="w-11 h-11 rounded-xl bg-indigo-500/15 flex items-center justify-center mb-4">
                                    <i data-lucide="briefcase" class="w-5 h-5 text-indigo-400"></i>
                                </div>

                                <p class="text-white font-bold text-lg">
                                    <?php echo htmlspecialchars($biz['business_name']); ?>
                                </p>

                                <p class="text-slate-500 text-xs mt-1">
                                    Reg No: <?php echo htmlspecialchars($biz['business_reg_no']); ?>
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-slate-400 text-xs">Balance</p>
                                <p class="text-white font-bold">
                                    RM <?php echo number_format($biz['balance'], 2); ?>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-slate-800 flex justify-between items-center">
                            <div>
                                <p class="text-slate-500 text-xs">Account No.</p>
                                <p class="text-slate-300 text-sm font-medium">•••• <?php echo htmlspecialchars($last4); ?></p>
                            </div>

                            <div class="flex items-center gap-1 text-indigo-400 text-xs opacity-80 group-hover:opacity-100">
                                <span>Open</span>
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </div>
                        </div>

                        <div class="hidden group-hover:block mt-4 bg-slate-950 rounded-xl p-3 border border-slate-800">
                            <p class="text-slate-400 text-xs">
                                Click to view QR, full account number, recent transactions, and manage this business account.
                            </p>
                        </div>

                    </a>
                <?php } ?>
            </div>

        <?php } ?>

    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>