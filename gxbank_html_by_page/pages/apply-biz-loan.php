<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];
$error = "";

$stmt = mysqli_prepare($conn, "SELECT * FROM accounts WHERE user_id = ? AND account_type = 'Business Account' ORDER BY account_id DESC");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$business_accounts = mysqli_stmt_get_result($stmt);

function calculateBizLoanApproval($monthly_revenue, $monthly_expense, $business_age_months, $requested_amount, $requested_term_months) {
    $score = 0;

    $net_cashflow = $monthly_revenue - $monthly_expense;
    $profit_margin = $monthly_revenue > 0 ? ($net_cashflow / $monthly_revenue) : -1;
    $requested_vs_revenue = $monthly_revenue > 0 ? ($requested_amount / $monthly_revenue) : 999;

    if ($monthly_revenue >= 50000) {
        $score += 35;
    } elseif ($monthly_revenue >= 20000) {
        $score += 25;
    } elseif ($monthly_revenue >= 8000) {
        $score += 15;
    } elseif ($monthly_revenue >= 3000) {
        $score += 5;
    } else {
        $score -= 20;
    }

    if ($profit_margin >= 0.30) {
        $score += 30;
    } elseif ($profit_margin >= 0.15) {
        $score += 20;
    } elseif ($profit_margin >= 0.05) {
        $score += 10;
    } else {
        $score -= 25;
    }

    if ($business_age_months >= 36) {
        $score += 25;
    } elseif ($business_age_months >= 18) {
        $score += 15;
    } elseif ($business_age_months >= 6) {
        $score += 5;
    } else {
        $score -= 15;
    }

    if ($requested_vs_revenue <= 1.0) {
        $score += 20;
    } elseif ($requested_vs_revenue <= 2.0) {
        $score += 10;
    } elseif ($requested_vs_revenue <= 3.0) {
        $score -= 5;
    } else {
        $score -= 25;
    }

    if ($requested_term_months <= 12) {
        $score += 10;
    } elseif ($requested_term_months <= 24) {
        $score += 5;
    } elseif ($requested_term_months <= 36) {
        $score += 0;
    } else {
        $score -= 10;
    }

    if ($score >= 85) {
        $risk_level = "Low Risk";
        $status = "approved";
        $interest_rate = 4.50;
        $max_amount = $monthly_revenue * 2.5;
    } elseif ($score >= 65) {
        $risk_level = "Medium Risk";
        $status = "reduced_approved";
        $interest_rate = 6.50;
        $max_amount = $monthly_revenue * 1.5;
    } elseif ($score >= 50) {
        $risk_level = "High Risk";
        $status = "reduced_approved";
        $interest_rate = 8.50;
        $max_amount = $monthly_revenue * 0.75;
    } else {
        $risk_level = "Rejected Risk";
        $status = "rejected";
        $interest_rate = 0.00;
        $max_amount = 0.00;
    }

    $approved_amount = $status == "rejected" ? 0.00 : min($requested_amount, $max_amount);

    if ($approved_amount < 1000 && $status != "rejected") {
        $status = "rejected";
        $risk_level = "Rejected Risk";
        $approved_amount = 0.00;
        $interest_rate = 0.00;
    }

    $approved_term_months = $status == "rejected" ? 0 : min($requested_term_months, 36);

    $monthly_interest = $interest_rate / 100 / 12;

    if ($status == "rejected" || $approved_term_months <= 0) {
        $monthly_payment = 0.00;
    } else {
        if ($monthly_interest > 0) {
            $monthly_payment = ($approved_amount * $monthly_interest) / (1 - pow(1 + $monthly_interest, -$approved_term_months));
        } else {
            $monthly_payment = $approved_amount / $approved_term_months;
        }
    }

    return [
        "score" => $score,
        "risk_level" => $risk_level,
        "status" => $status,
        "approved_amount" => round($approved_amount, 2),
        "approved_term_months" => $approved_term_months,
        "interest_rate" => $interest_rate,
        "monthly_payment" => round($monthly_payment, 2),
        "net_cashflow" => $net_cashflow,
        "profit_margin" => $profit_margin
    ];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $business_account_id = intval($_POST['business_account_id'] ?? 0);
    $requested_amount = floatval($_POST['requested_amount'] ?? 0);
    $monthly_revenue = floatval($_POST['monthly_revenue'] ?? 0);
    $monthly_expense = floatval($_POST['monthly_expense'] ?? 0);
    $business_age_months = intval($_POST['business_age_months'] ?? 0);
    $requested_term_months = intval($_POST['requested_term_months'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');

    $stmt = mysqli_prepare($conn, "SELECT * FROM accounts WHERE account_id = ? AND user_id = ? AND account_type = 'Business Account' LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $business_account_id, $user_id);
    mysqli_stmt_execute($stmt);
    $business_account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$business_account) {
        $error = "Invalid business account selected.";
    } elseif ($requested_amount <= 0 || $monthly_revenue <= 0 || $monthly_expense < 0 || $business_age_months <= 0 || $requested_term_months <= 0 || $purpose == "") {
        $error = "Please fill in all loan application details.";
    } else {
        $approval = calculateBizLoanApproval(
            $monthly_revenue,
            $monthly_expense,
            $business_age_months,
            $requested_amount,
            $requested_term_months
        );

        $approved_amount = $approval['approved_amount'];
        $outstanding_amount = $approved_amount;
        $approved_term_months = $approval['approved_term_months'];
        $interest_rate = $approval['interest_rate'];
        $monthly_payment = $approval['monthly_payment'];
        $ai_score = $approval['score'];
        $risk_level = $approval['risk_level'];
        $approval_status = $approval['status'];
        $loan_status = $approval_status == "rejected" ? "rejected" : "active";

        mysqli_begin_transaction($conn);

        try {
            $stmt = mysqli_prepare($conn, "INSERT INTO business_loans
                (user_id, business_account_id, requested_amount, approved_amount, outstanding_amount, monthly_revenue, monthly_expense, business_age_months, requested_term_months, approved_term_months, interest_rate, monthly_payment, purpose, ai_score, risk_level, approval_status, loan_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            mysqli_stmt_bind_param(
                $stmt,
                "iidddddiiiddsisss",
                $user_id,
                $business_account_id,
                $requested_amount,
                $approved_amount,
                $outstanding_amount,
                $monthly_revenue,
                $monthly_expense,
                $business_age_months,
                $requested_term_months,
                $approved_term_months,
                $interest_rate,
                $monthly_payment,
                $purpose,
                $ai_score,
                $risk_level,
                $approval_status,
                $loan_status
            );

            mysqli_stmt_execute($stmt);
            $loan_id = mysqli_insert_id($conn);

            if ($approval_status != "rejected" && $approved_amount > 0) {
                $stmt = mysqli_prepare($conn, "UPDATE accounts SET balance = balance + ? WHERE account_id = ?");
                mysqli_stmt_bind_param($stmt, "di", $approved_amount, $business_account_id);
                mysqli_stmt_execute($stmt);

                $txn_type = "disbursement";
                $notes = "Biz Flexi Loan disbursed to business account.";

                $stmt = mysqli_prepare($conn, "INSERT INTO business_loan_transactions
                    (loan_id, business_account_id, transaction_type, amount, notes)
                    VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iisds", $loan_id, $business_account_id, $txn_type, $approved_amount, $notes);
                mysqli_stmt_execute($stmt);

                $transaction_name = "Biz Flexi Loan Disbursement";
                $transaction_type = "income";
                $reason = "Approved business loan disbursed. AI score: " . $ai_score . ".";

                $stmt = mysqli_prepare($conn, "INSERT INTO transactions
                    (account_id, transaction_name, transaction_type, amount, transfer_reason)
                    VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "issds", $business_account_id, $transaction_name, $transaction_type, $approved_amount, $reason);
                mysqli_stmt_execute($stmt);
            }

            mysqli_commit($conn);

            if ($approval_status == "approved") {
                $_SESSION['success'] = "Biz Flexi Loan approved. RM " . number_format($approved_amount, 2) . " has been disbursed.";
            } elseif ($approval_status == "reduced_approved") {
                $_SESSION['success'] = "Biz Flexi Loan approved with reduced amount. RM " . number_format($approved_amount, 2) . " has been disbursed.";
            } else {
                $_SESSION['error'] = "Biz Flexi Loan rejected. AI score: " . $ai_score . ".";
            }

            header("Location: /gxbank_html_by_page/pages/biz-loan.php");
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Loan application failed: " . $e->getMessage();
        }
    }
}
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Apply Biz Flexi Loan</title>

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
            <span class="text-sm font-medium">Back</span>
        </a>

        <h1 class="text-2xl font-bold text-white mb-2">Apply Biz Flexi Loan</h1>
        <p class="text-slate-400 text-sm mb-6">
            Apply working capital financing for your selected business account.
        </p>

        <?php if($error != "") { ?>
            <div class="bg-red-500/15 text-red-300 rounded-xl p-3 text-sm mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php } ?>

        <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800 mb-4">
            <p class="text-slate-300 font-medium text-sm mb-3">AI Approval Criteria</p>

            <div class="space-y-3 text-sm">
                <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <p class="text-white font-semibold">Revenue Strength</p>
                    <p class="text-slate-400 text-xs mt-1">Higher monthly revenue improves approval score.</p>
                </div>

                <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <p class="text-white font-semibold">Net Cashflow</p>
                    <p class="text-slate-400 text-xs mt-1">Profit margin is used to estimate repayment ability.</p>
                </div>

                <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <p class="text-white font-semibold">Business Age</p>
                    <p class="text-slate-400 text-xs mt-1">Older businesses are treated as lower risk.</p>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800">
            <form method="POST" class="space-y-4">

                <div>
                    <label class="text-slate-300 text-sm font-medium">Business Account</label>
                    <select name="business_account_id"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-purple-400">

                        <?php 
                        mysqli_data_seek($business_accounts, 0);
                        while($biz = mysqli_fetch_assoc($business_accounts)) { 
                        ?>
                            <option value="<?php echo intval($biz['account_id']); ?>">
                                <?php echo htmlspecialchars($biz['business_name']); ?>
                                - RM <?php echo number_format($biz['balance'], 2); ?>
                                - •••• <?php echo htmlspecialchars(substr($biz['account_number'], -4)); ?>
                            </option>
                        <?php } ?>

                    </select>
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Requested Amount</label>
                    <input type="number" step="0.01" name="requested_amount" required placeholder="Example: 20000"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-purple-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Monthly Revenue</label>
                    <input type="number" step="0.01" name="monthly_revenue" required placeholder="Example: 30000"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-purple-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Monthly Expense</label>
                    <input type="number" step="0.01" name="monthly_expense" required placeholder="Example: 18000"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-purple-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Business Age</label>
                    <input type="number" name="business_age_months" required placeholder="In months, example: 24"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-purple-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Requested Term</label>
                    <select name="requested_term_months"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-purple-400">
                        <option value="6">6 months</option>
                        <option value="12">12 months</option>
                        <option value="18">18 months</option>
                        <option value="24">24 months</option>
                        <option value="36">36 months</option>
                    </select>
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Purpose</label>
                    <input type="text" name="purpose" required placeholder="Example: Stock purchase, working capital"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-purple-400">
                </div>

                <div class="bg-purple-500/15 text-purple-300 rounded-xl p-3 text-sm">
                    If approved, the loan amount will be disbursed directly into the selected business account.
                </div>

                <button type="submit"
                    class="w-full bg-purple-500 hover:bg-purple-600 text-white font-semibold py-3 rounded-xl transition">
                    Submit Application
                </button>

            </form>
        </div>

    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>