<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total_business FROM accounts WHERE user_id = ? AND account_type = 'Business Account'");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$business_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$stmt = mysqli_prepare($conn, "SELECT bl.*, a.business_name, a.business_reg_no, a.account_number, a.balance
    FROM business_loans bl
    JOIN accounts a ON bl.business_account_id = a.account_id
    WHERE bl.user_id = ?
    ORDER BY bl.loan_id DESC");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$loans = mysqli_stmt_get_result($stmt);

$stmt = mysqli_prepare($conn, "SELECT
    COALESCE(SUM(CASE WHEN loan_status = 'active' THEN approved_amount ELSE 0 END), 0) AS total_approved,
    COALESCE(SUM(CASE WHEN loan_status = 'active' THEN outstanding_amount ELSE 0 END), 0) AS total_outstanding,
    COUNT(*) AS total_loans
    FROM business_loans
    WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Biz Flexi Loan</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* { font-family: 'DM Sans', sans-serif; }
body { box-sizing: border-box; }
.loan-card { transition: transform .15s, border-color .15s, box-shadow .15s; }
.loan-card:hover { transform: translateY(-3px); border-color: rgba(168,85,247,.65); box-shadow: 0 12px 28px rgba(0,0,0,.25); }
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
                <h1 class="text-2xl font-bold text-white mb-1">Biz Flexi Loan</h1>
                <p class="text-slate-400 text-sm">Business financing tied to your business account.</p>
            </div>

            <?php if (intval($business_count['total_business']) > 0) { ?>
                <a href="/gxbank_html_by_page/pages/apply-biz-loan.php"
                   class="w-11 h-11 rounded-xl bg-purple-500 hover:bg-purple-600 flex items-center justify-center transition">
                    <i data-lucide="plus" class="w-5 h-5 text-white"></i>
                </a>
            <?php } ?>
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

        <?php if (intval($business_count['total_business']) == 0) { ?>

            <div class="bg-slate-900 rounded-2xl p-6 border border-slate-800 text-center">
                <div class="w-16 h-16 rounded-2xl bg-purple-500/15 flex items-center justify-center mx-auto mb-5">
                    <i data-lucide="briefcase" class="w-8 h-8 text-purple-400"></i>
                </div>

                <h2 class="text-white text-xl font-bold mb-2">No Business Account</h2>

                <p class="text-slate-400 text-sm mb-6">
                    You need to create a business account before applying for Biz Flexi Loan.
                </p>

                <a href="/gxbank_html_by_page/pages/biz-account.php"
                   class="block w-full bg-purple-500 hover:bg-purple-600 text-white font-semibold py-3 rounded-xl transition">
                    Create Business Account
                </a>
            </div>

        <?php } else { ?>

            <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-2xl p-6 mb-4 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>

                <div class="relative z-10">
                    <p class="text-purple-100 text-sm font-medium">Total Outstanding</p>
                    <p class="text-white text-3xl font-bold mt-2">
                        RM <?php echo number_format($summary['total_outstanding'], 2); ?>
                    </p>

                    <div class="grid grid-cols-2 gap-3 mt-5 border-t border-white/20 pt-4">
                        <div>
                            <p class="text-purple-100 text-xs">Approved Facility</p>
                            <p class="text-white font-semibold">
                                RM <?php echo number_format($summary['total_approved'], 2); ?>
                            </p>
                        </div>

                        <div>
                            <p class="text-purple-100 text-xs">Applications</p>
                            <p class="text-white font-semibold">
                                <?php echo intval($summary['total_loans']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <a href="/gxbank_html_by_page/pages/apply-biz-loan.php"
               class="block w-full text-center bg-purple-500 hover:bg-purple-600 text-white font-semibold py-3 rounded-xl transition mb-4">
                + Apply Biz Flexi Loan
            </a>

            <?php if (mysqli_num_rows($loans) == 0) { ?>

                <div class="bg-slate-900 rounded-2xl p-6 border border-slate-800 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-purple-500/15 flex items-center justify-center mx-auto mb-5">
                        <i data-lucide="landmark" class="w-8 h-8 text-purple-400"></i>
                    </div>

                    <h2 class="text-white text-xl font-bold mb-2">No Loan Yet</h2>
                    <p class="text-slate-400 text-sm">
                        Apply for business working capital financing.
                    </p>
                </div>

            <?php } else { ?>

                <div class="space-y-4">
                    <?php while($loan = mysqli_fetch_assoc($loans)) { ?>
                        <a href="/gxbank_html_by_page/pages/biz-loan-detail.php?loan_id=<?php echo intval($loan['loan_id']); ?>"
                           class="loan-card group block bg-slate-900 rounded-2xl p-5 border border-slate-800">

                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="w-11 h-11 rounded-xl bg-purple-500/15 flex items-center justify-center mb-4">
                                        <i data-lucide="briefcase" class="w-5 h-5 text-purple-400"></i>
                                    </div>

                                    <p class="text-white font-bold text-lg">
                                        <?php echo htmlspecialchars($loan['business_name']); ?>
                                    </p>

                                    <p class="text-slate-500 text-xs mt-1">
                                        AI Score <?php echo intval($loan['ai_score']); ?> · <?php echo htmlspecialchars($loan['risk_level']); ?>
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="text-slate-400 text-xs">Outstanding</p>
                                    <p class="text-white font-bold">
                                        RM <?php echo number_format($loan['outstanding_amount'], 2); ?>
                                    </p>

                                    <p class="<?php echo $loan['loan_status'] == 'active' ? 'text-green-400' : ($loan['loan_status'] == 'rejected' ? 'text-red-400' : 'text-slate-400'); ?> text-xs mt-2">
                                        <?php echo strtoupper($loan['loan_status']); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-slate-800 flex justify-between items-center">
                                <div>
                                    <p class="text-slate-500 text-xs">Approved Amount</p>
                                    <p class="text-slate-300 text-sm font-medium">
                                        RM <?php echo number_format($loan['approved_amount'], 2); ?>
                                    </p>
                                </div>

                                <div class="flex items-center gap-1 text-purple-400 text-xs opacity-80 group-hover:opacity-100">
                                    <span>Open</span>
                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                </div>
                            </div>

                        </a>
                    <?php } ?>
                </div>

            <?php } ?>

        <?php } ?>

    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>