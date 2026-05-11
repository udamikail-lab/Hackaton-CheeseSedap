<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];
$policy_id = intval($_GET['policy_id'] ?? 0);

if ($policy_id <= 0) {
    header("Location: /gxbank_html_by_page/pages/insurance.php");
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM insurance_policies WHERE policy_id = ? AND user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $policy_id, $user_id);
mysqli_stmt_execute($stmt);
$policy = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$policy) {
    $_SESSION['error'] = "Policy not found.";
    header("Location: /gxbank_html_by_page/pages/insurance.php");
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM accounts WHERE user_id = ? AND account_type = 'Savings Account' LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$main_account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$main_account) {
    die("Main account not found.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pay_premium'])) {
    if ($policy['policy_status'] != "active") {
        $_SESSION['error'] = "Only active policy can pay premium.";
    } elseif ($policy['monthly_premium'] <= 0) {
        $_SESSION['error'] = "No premium payable for this policy.";
    } elseif ($main_account['balance'] < $policy['monthly_premium']) {
        $_SESSION['error'] = "Insufficient main account balance.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            $amount = floatval($policy['monthly_premium']);

            $stmt = mysqli_prepare($conn, "UPDATE accounts SET balance = balance - ? WHERE account_id = ?");
            mysqli_stmt_bind_param($stmt, "di", $amount, $main_account['account_id']);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($conn, "INSERT INTO insurance_payments
                (policy_id, account_id, amount, payment_status, notes)
                VALUES (?, ?, ?, 'paid', ?)");
            $notes = "Monthly premium paid for " . $policy['plan_name'];
            mysqli_stmt_bind_param($stmt, "iids", $policy_id, $main_account['account_id'], $amount, $notes);
            mysqli_stmt_execute($stmt);

            $transaction_name = "Insurance Premium: " . $policy['plan_name'];
            $transaction_type = "expense";
            $reason = "Monthly insurance premium payment.";

            $stmt = mysqli_prepare($conn, "INSERT INTO transactions
                (account_id, transaction_name, transaction_type, amount, transfer_reason)
                VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issds", $main_account['account_id'], $transaction_name, $transaction_type, $amount, $reason);
            mysqli_stmt_execute($stmt);

            $next_due_date = date("Y-m-d", strtotime($policy['next_due_date'] . " +1 month"));

            $stmt = mysqli_prepare($conn, "UPDATE insurance_policies SET next_due_date = ? WHERE policy_id = ?");
            mysqli_stmt_bind_param($stmt, "si", $next_due_date, $policy_id);
            mysqli_stmt_execute($stmt);

            mysqli_commit($conn);

            $_SESSION['success'] = "Insurance premium paid successfully.";
            header("Location: /gxbank_html_by_page/pages/insurance-detail.php?policy_id=" . $policy_id);
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Premium payment failed.";
        }
    }

    header("Location: /gxbank_html_by_page/pages/insurance-detail.php?policy_id=" . $policy_id);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_policy'])) {
    if ($policy['policy_status'] == "cancelled") {
        $_SESSION['error'] = "Policy already cancelled.";
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE insurance_policies SET policy_status = 'cancelled' WHERE policy_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $policy_id, $user_id);
        mysqli_stmt_execute($stmt);

        $_SESSION['success'] = "Policy cancelled successfully.";
    }

    header("Location: /gxbank_html_by_page/pages/insurance-detail.php?policy_id=" . $policy_id);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM insurance_policies WHERE policy_id = ? AND user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $policy_id, $user_id);
mysqli_stmt_execute($stmt);
$policy = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$stmt = mysqli_prepare($conn, "SELECT * FROM insurance_payments WHERE policy_id = ? ORDER BY payment_date DESC LIMIT 6");
mysqli_stmt_bind_param($stmt, "i", $policy_id);
mysqli_stmt_execute($stmt);
$payments = mysqli_stmt_get_result($stmt);

$total_loading = floatval($policy['total_loading_rate'] ?? 0);
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Insurance Detail</title>

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

        <a href="/gxbank_html_by_page/pages/insurance.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Insurance List</span>
        </a>

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

        <div class="bg-gradient-to-br from-sky-500 to-sky-700 rounded-2xl p-6 relative overflow-hidden mb-4">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>

            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <p class="text-sky-100 text-sm font-medium">GX Protect</p>
                    <span class="<?php echo $policy['policy_status'] == 'active' ? 'bg-green-500/20 text-green-100' : 'bg-red-500/20 text-red-100'; ?> text-xs px-3 py-1 rounded-full">
                        <?php echo strtoupper($policy['policy_status']); ?>
                    </span>
                </div>

                <h1 class="text-white text-2xl font-bold mt-2">
                    <?php echo htmlspecialchars($policy['plan_name']); ?>
                </h1>

                <p class="text-white text-3xl font-bold tracking-tight mt-4">
                    RM <?php echo number_format($policy['coverage_amount'], 2); ?>
                </p>

                <p class="text-sky-100 text-xs mt-2">
                    Coverage Amount
                </p>

                <div class="mt-5 border-t border-white/20 pt-4 grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-sky-100 text-xs">Final Premium</p>
                        <p class="text-white font-semibold">
                            RM <?php echo number_format($policy['monthly_premium'], 2); ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-sky-100 text-xs">Next Due</p>
                        <p class="text-white font-semibold">
                            <?php echo $policy['next_due_date'] ? htmlspecialchars($policy['next_due_date']) : '-'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 rounded-xl p-4 border border-slate-800 mb-4">
            <p class="text-slate-300 font-medium text-sm mb-3">Premium Breakdown</p>

            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-400">Base Premium</span>
                    <span class="text-white font-medium">RM <?php echo number_format($policy['base_premium'], 2); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Smoker Loading</span>
                    <span class="<?php echo floatval($policy['smoker_loading_rate']) > 0 ? 'text-amber-400' : 'text-green-400'; ?> font-medium">
                        +<?php echo number_format($policy['smoker_loading_rate'], 2); ?>%
                    </span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Illness Loading</span>
                    <span class="<?php echo floatval($policy['illness_loading_rate']) > 0 ? 'text-amber-400' : 'text-green-400'; ?> font-medium">
                        +<?php echo number_format($policy['illness_loading_rate'], 2); ?>%
                    </span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Fuzzy Risk Loading</span>
                    <span class="<?php echo floatval($policy['fuzzy_loading_rate']) > 0 ? 'text-amber-400' : 'text-green-400'; ?> font-medium">
                        +<?php echo number_format($policy['fuzzy_loading_rate'], 2); ?>%
                    </span>
                </div>

                <div class="border-t border-slate-800 my-2"></div>

                <div class="flex justify-between">
                    <span class="text-slate-300 font-semibold">Total Loading</span>
                    <span class="text-white font-bold">+<?php echo number_format($total_loading, 2); ?>%</span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-300 font-semibold">Final Monthly Premium</span>
                    <span class="text-sky-400 font-bold">RM <?php echo number_format($policy['monthly_premium'], 2); ?></span>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 rounded-xl p-4 border border-slate-800 mb-4">
            <p class="text-slate-300 font-medium text-sm mb-3">Fuzzy Risk Result</p>

            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-400">Risk Score</span>
                    <span class="text-white font-medium"><?php echo number_format($policy['fuzzy_risk_score'], 2); ?>/100</span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Risk Level</span>
                    <span class="text-white font-medium"><?php echo htmlspecialchars($policy['fuzzy_risk_level']); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Approval</span>
                    <span class="text-white font-medium"><?php echo strtoupper($policy['approval_status']); ?></span>
                </div>
            </div>

            <div class="w-full bg-slate-700 rounded-full h-2 mt-4">
                <div class="<?php echo $policy['fuzzy_risk_score'] < 40 ? 'bg-green-500' : ($policy['fuzzy_risk_score'] < 70 ? 'bg-amber-500' : 'bg-red-500'); ?> h-2 rounded-full" style="width: <?php echo min(100, $policy['fuzzy_risk_score']); ?>%"></div>
            </div>
        </div>

        <div class="bg-slate-900 rounded-xl p-4 border border-slate-800 mb-4">
            <p class="text-slate-300 font-medium text-sm mb-3">Policy Information</p>

            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-400">Age</span>
                    <span class="text-white font-medium"><?php echo intval($policy['age']); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Income</span>
                    <span class="text-white font-medium">RM <?php echo number_format($policy['monthly_income'], 2); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Smoking</span>
                    <span class="text-white font-medium">
                        <?php echo $policy['smoking_status'] == 'smoker' ? 'Smoker' : 'Non-smoker'; ?>
                    </span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Existing Illness</span>
                    <span class="text-white font-medium">
                        <?php echo $policy['existing_illness'] == 'yes' ? 'Yes' : 'No'; ?>
                    </span>
                </div>

                <div class="border-t border-slate-800 my-2"></div>

                <div class="flex justify-between gap-3">
                    <span class="text-slate-400">Beneficiary</span>
                    <span class="text-white font-medium text-right"><?php echo htmlspecialchars($policy['beneficiary_name']); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Relationship</span>
                    <span class="text-white font-medium"><?php echo htmlspecialchars($policy['beneficiary_relationship']); ?></span>
                </div>
            </div>
        </div>

        <?php if ($policy['policy_status'] == 'active') { ?>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <form method="POST">
                    <button type="submit" name="pay_premium"
                        class="w-full bg-sky-500 hover:bg-sky-600 text-white font-semibold py-3 rounded-xl transition">
                        Pay Premium
                    </button>
                </form>

                <form method="POST" onsubmit="return confirm('Cancel this insurance policy?');">
                    <button type="submit" name="cancel_policy"
                        class="w-full bg-red-500/15 hover:bg-red-500/25 text-red-400 font-semibold py-3 rounded-xl transition">
                        Cancel
                    </button>
                </form>
            </div>
        <?php } ?>

        <div class="bg-slate-900 rounded-xl p-4 border border-slate-800">
            <p class="text-slate-300 font-medium text-sm mb-3">Premium Payment History</p>

            <div class="space-y-2">
                <?php if (mysqli_num_rows($payments) == 0) { ?>
                    <p class="text-slate-500 text-sm">No premium payment yet.</p>
                <?php } ?>

                <?php while($pay = mysqli_fetch_assoc($payments)) { ?>
                    <div class="flex justify-between items-start text-sm border-b border-slate-800 pb-2 last:border-b-0">
                        <div>
                            <span class="text-slate-300 block">Premium Payment</span>
                            <span class="text-slate-500 text-xs">
                                <?php echo htmlspecialchars($pay['notes']); ?>
                            </span>

                            <span class="text-slate-600 text-xs block mt-1">
                                <?php echo date("d M Y, h:i A", strtotime($pay['payment_date'])); ?>
                            </span>
                        </div>

                        <span class="text-red-400 font-medium">
                            -RM <?php echo number_format($pay['amount'], 2); ?>
                        </span>
                    </div>
                <?php } ?>
            </div>
        </div>

    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>