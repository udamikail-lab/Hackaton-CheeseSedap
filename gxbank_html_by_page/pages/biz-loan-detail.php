<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];
$loan_id = intval($_GET['loan_id'] ?? 0);

if ($loan_id <= 0) {
    header("Location: /gxbank_html_by_page/pages/biz-loan.php");
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT bl.*, a.business_name, a.business_reg_no, a.account_number, a.balance
    FROM business_loans bl
    JOIN accounts a ON bl.business_account_id = a.account_id
    WHERE bl.loan_id = ? AND bl.user_id = ?
    LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $loan_id, $user_id);
mysqli_stmt_execute($stmt);
$loan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$loan) {
    $_SESSION['error'] = "Loan not found.";
    header("Location: /gxbank_html_by_page/pages/biz-loan.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['repay_loan'])) {
    $repayment_amount = floatval($_POST['repayment_amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($loan['loan_status'] != "active") {
        $_SESSION['error'] = "Only active loan can be repaid.";
    } elseif ($repayment_amount <= 0) {
        $_SESSION['error'] = "Please enter valid repayment amount.";
    } elseif ($repayment_amount > $loan['outstanding_amount']) {
        $_SESSION['error'] = "Repayment amount cannot exceed outstanding balance.";
    } elseif ($repayment_amount > $loan['balance']) {
        $_SESSION['error'] = "Insufficient business account balance.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            $new_outstanding = $loan['outstanding_amount'] - $repayment_amount;
            $new_status = $new_outstanding <= 0.01 ? "fully_paid" : "active";

            $stmt = mysqli_prepare($conn, "UPDATE accounts SET balance = balance - ? WHERE account_id = ?");
            mysqli_stmt_bind_param($stmt, "di", $repayment_amount, $loan['business_account_id']);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($conn, "UPDATE business_loans SET outstanding_amount = ?, loan_status = ? WHERE loan_id = ?");
            mysqli_stmt_bind_param($stmt, "dsi", $new_outstanding, $new_status, $loan_id);
            mysqli_stmt_execute($stmt);

            $txn_type = "repayment";
            $loan_notes = $notes != "" ? $notes : "Business loan repayment.";

            $stmt = mysqli_prepare($conn, "INSERT INTO business_loan_transactions
                (loan_id, business_account_id, transaction_type, amount, notes)
                VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iisds", $loan_id, $loan['business_account_id'], $txn_type, $repayment_amount, $loan_notes);
            mysqli_stmt_execute($stmt);

            $transaction_name = "Biz Flexi Loan Repayment";
            $transaction_type = "expense";
            $reason = $loan_notes;

            $stmt = mysqli_prepare($conn, "INSERT INTO transactions
                (account_id, transaction_name, transaction_type, amount, transfer_reason)
                VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issds", $loan['business_account_id'], $transaction_name, $transaction_type, $repayment_amount, $reason);
            mysqli_stmt_execute($stmt);

            mysqli_commit($conn);

            $_SESSION['success'] = "Loan repayment successful.";
            header("Location: /gxbank_html_by_page/pages/biz-loan-detail.php?loan_id=" . $loan_id);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Loan repayment failed.";
        }
    }

    header("Location: /gxbank_html_by_page/pages/biz-loan-detail.php?loan_id=" . $loan_id);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT bl.*, a.business_name, a.business_reg_no, a.account_number, a.balance
    FROM business_loans bl
    JOIN accounts a ON bl.business_account_id = a.account_id
    WHERE bl.loan_id = ? AND bl.user_id = ?
    LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $loan_id, $user_id);
mysqli_stmt_execute($stmt);
$loan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$stmt = mysqli_prepare($conn, "SELECT * FROM business_loan_transactions WHERE loan_id = ? ORDER BY transaction_date DESC LIMIT 8");
mysqli_stmt_bind_param($stmt, "i", $loan_id);
mysqli_stmt_execute($stmt);
$loan_transactions = mysqli_stmt_get_result($stmt);

$progress = $loan['approved_amount'] > 0 ? (($loan['approved_amount'] - $loan['outstanding_amount']) / $loan['approved_amount']) * 100 : 0;
$progress = max(0, min(100, $progress));
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Biz Loan Detail</title>

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

        <a href="/gxbank_html_by_page/pages/biz-loan.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Loan List</span>
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

        <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-2xl p-6 relative overflow-hidden mb-4">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>

            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <p class="text-purple-100 text-sm font-medium">Biz Flexi Loan</p>
                    <span class="<?php echo $loan['loan_status'] == 'active' ? 'bg-green-500/20 text-green-100' : ($loan['loan_status'] == 'rejected' ? 'bg-red-500/20 text-red-100' : 'bg-slate-900/30 text-white'); ?> text-xs px-3 py-1 rounded-full">
                        <?php echo strtoupper($loan['loan_status']); ?>
                    </span>
                </div>

                <h1 class="text-white text-2xl font-bold mt-2">
                    <?php echo htmlspecialchars($loan['business_name']); ?>
                </h1>

                <p class="text-white text-3xl font-bold tracking-tight mt-4">
                    RM <?php echo number_format($loan['outstanding_amount'], 2); ?>
                </p>

                <p class="text-purple-100 text-xs mt-2">
                    Outstanding Balance
                </p>

                <div class="w-full bg-white/20 rounded-full h-2 mt-4">
                    <div class="bg-white h-2 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                </div>

                <p class="text-purple-100 text-xs mt-2">
                    <?php echo number_format($progress, 0); ?>% repaid
                </p>
            </div>
        </div>

        <div class="bg-slate-900 rounded-xl p-4 border border-slate-800 mb-4">
            <p class="text-slate-300 font-medium text-sm mb-3">Loan Information</p>

            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-400">Business Account</span>
                    <span class="text-white font-medium text-right"><?php echo htmlspecialchars($loan['account_number']); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Business Balance</span>
                    <span class="text-white font-medium">RM <?php echo number_format($loan['balance'], 2); ?></span>
                </div>

                <div class="border-t border-slate-800 my-2"></div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Requested Amount</span>
                    <span class="text-white font-medium">RM <?php echo number_format($loan['requested_amount'], 2); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Approved Amount</span>
                    <span class="text-white font-medium">RM <?php echo number_format($loan['approved_amount'], 2); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Monthly Payment</span>
                    <span class="text-white font-medium">RM <?php echo number_format($loan['monthly_payment'], 2); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Interest Rate</span>
                    <span class="text-white font-medium"><?php echo number_format($loan['interest_rate'], 2); ?>%</span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Term</span>
                    <span class="text-white font-medium"><?php echo intval($loan['approved_term_months']); ?> months</span>
                </div>

                <div class="border-t border-slate-800 my-2"></div>

                <div class="flex justify-between">
                    <span class="text-slate-400">AI Score</span>
                    <span class="text-white font-medium"><?php echo intval($loan['ai_score']); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Risk Level</span>
                    <span class="text-white font-medium"><?php echo htmlspecialchars($loan['risk_level']); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-slate-400">Approval</span>
                    <span class="text-white font-medium"><?php echo strtoupper($loan['approval_status']); ?></span>
                </div>

                <div class="border-t border-slate-800 my-2"></div>

                <div class="flex justify-between gap-3">
                    <span class="text-slate-400">Purpose</span>
                    <span class="text-white font-medium text-right"><?php echo htmlspecialchars($loan['purpose']); ?></span>
                </div>
            </div>
        </div>

        <?php if ($loan['loan_status'] == 'active') { ?>
            <div class="bg-slate-900 rounded-xl p-4 border border-slate-800 mb-4">
                <p class="text-slate-300 font-medium text-sm mb-4">Repay Loan</p>

                <form method="POST" class="space-y-4">
                    <input type="number" step="0.01" name="repayment_amount" required
                        placeholder="Repayment amount"
                        value="<?php echo htmlspecialchars($loan['monthly_payment']); ?>"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-purple-400">

                    <input type="text" name="notes" placeholder="Notes optional"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-purple-400">

                    <button type="submit" name="repay_loan"
                        class="w-full bg-purple-500 hover:bg-purple-600 text-white font-semibold py-3 rounded-xl transition">
                        Pay From Business Account
                    </button>
                </form>
            </div>
        <?php } ?>

        <div class="bg-slate-900 rounded-xl p-4 border border-slate-800">
            <p class="text-slate-300 font-medium text-sm mb-3">Loan Transactions</p>

            <div class="space-y-2">
                <?php if (mysqli_num_rows($loan_transactions) == 0) { ?>
                    <p class="text-slate-500 text-sm">No loan transaction yet.</p>
                <?php } ?>

                <?php while($t = mysqli_fetch_assoc($loan_transactions)) { 
                    $isDisbursement = $t['transaction_type'] == 'disbursement';
                ?>
                    <div class="flex justify-between items-start text-sm border-b border-slate-800 pb-2 last:border-b-0">
                        <div>
                            <span class="text-slate-300 block">
                                <?php echo $isDisbursement ? 'Loan Disbursement' : 'Loan Repayment'; ?>
                            </span>

                            <?php if (!empty($t['notes'])) { ?>
                                <span class="text-slate-500 text-xs">
                                    <?php echo htmlspecialchars($t['notes']); ?>
                                </span>
                            <?php } ?>

                            <span class="text-slate-600 text-xs block mt-1">
                                <?php echo date("d M Y, h:i A", strtotime($t['transaction_date'])); ?>
                            </span>
                        </div>

                        <span class="<?php echo $isDisbursement ? 'text-green-400' : 'text-red-400'; ?> font-medium">
                            <?php echo $isDisbursement ? '+' : '-'; ?>RM <?php echo number_format($t['amount'], 2); ?>
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