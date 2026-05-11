<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];
$error = "";

function generateFlexiAccountNumber($conn) {
    do {
        $number = "78" . rand(100000, 999999);

        $stmt = mysqli_prepare($conn, "SELECT flexi_card_id FROM flexi_cards WHERE flexi_account_number = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $number);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        $exists = mysqli_stmt_num_rows($stmt) > 0;
    } while ($exists);

    return $number;
}

function calculateFlexiCardApproval($monthly_income, $monthly_commitment, $employment_type, $requested_limit, $credit_score_grade) {
    $score = 0;

    if ($monthly_income >= 8000) {
        $score += 40;
    } elseif ($monthly_income >= 5000) {
        $score += 30;
    } elseif ($monthly_income >= 3000) {
        $score += 20;
    } elseif ($monthly_income >= 2000) {
        $score += 10;
    } else {
        $score -= 20;
    }

    if ($employment_type == "Permanent Employee") {
        $score += 25;
    } elseif ($employment_type == "Self Employed") {
        $score += 15;
    } elseif ($employment_type == "Contract Employee") {
        $score += 10;
    } elseif ($employment_type == "Student") {
        $score -= 10;
    } else {
        $score -= 30;
    }

    $debt_ratio = $monthly_income > 0 ? ($monthly_commitment / $monthly_income) : 1;

    if ($debt_ratio <= 0.30) {
        $score += 25;
    } elseif ($debt_ratio <= 0.50) {
        $score += 10;
    } else {
        $score -= 25;
    }

    if ($requested_limit <= $monthly_income) {
        $score += 15;
    } elseif ($requested_limit <= ($monthly_income * 2)) {
        $score += 5;
    } else {
        $score -= 20;
    }

    if ($credit_score_grade == "Excellent") {
        $score += 25;
    } elseif ($credit_score_grade == "Good") {
        $score += 15;
    } elseif ($credit_score_grade == "Average") {
        $score += 5;
    } else {
        $score -= 30;
    }

    if ($employment_type == "Unemployed") {
        return ["score" => $score, "status" => "rejected", "approved_limit" => 0];
    }

    if ($employment_type == "Permanent Employee") {
        $max_limit = $monthly_income * 2;
    } elseif ($employment_type == "Self Employed") {
        $max_limit = $monthly_income * 1.5;
    } elseif ($employment_type == "Contract Employee") {
        $max_limit = $monthly_income * 1.2;
    } elseif ($employment_type == "Student") {
        $max_limit = 1000;
    } else {
        $max_limit = 0;
    }

    if ($score >= 80) {
        $approved_limit = min($requested_limit, $max_limit);
        $status = $approved_limit < $requested_limit ? "reduced_approved" : "approved";
    } elseif ($score >= 60) {
        $approved_limit = min($requested_limit, $max_limit) * 0.75;
        $status = "reduced_approved";
    } else {
        $approved_limit = 0;
        $status = "rejected";
    }

    return ["score" => $score, "status" => $status, "approved_limit" => round($approved_limit, 2)];
}

function calculateLimitReviewScore($card) {
    $score = 0;

    if ($card['on_time_payment_count'] >= 12) {
        $score += 50;
    } elseif ($card['on_time_payment_count'] >= 6) {
        $score += 35;
    } elseif ($card['on_time_payment_count'] >= 3) {
        $score += 20;
    }

    if ($card['late_payment_count'] == 1) {
        $score -= 25;
    } elseif ($card['late_payment_count'] == 2) {
        $score -= 50;
    } elseif ($card['late_payment_count'] >= 3) {
        $score -= 100;
    }

    $utilisation = $card['approved_limit'] > 0 ? ($card['outstanding_balance'] / $card['approved_limit']) : 1;

    if ($utilisation <= 0.30) {
        $score += 15;
    } elseif ($utilisation <= 0.70) {
        $score += 10;
    } elseif ($utilisation >= 0.90) {
        $score -= 15;
    }

    if ($card['outstanding_balance'] <= 0 && $card['on_time_payment_count'] > 0) {
        $score += 20;
    } elseif ($card['outstanding_balance'] > 0 && $utilisation <= 0.50) {
        $score += 5;
    }

    return $score;
}

$stmt = mysqli_prepare($conn, "SELECT * FROM accounts WHERE user_id = ? AND account_type = 'Savings Account' LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$savings_account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$savings_account) {
    die("Savings account not found.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_flexicard'])) {
    $monthly_income = floatval($_POST['monthly_income'] ?? 0);
    $monthly_commitment = floatval($_POST['monthly_commitment'] ?? 0);
    $employment_type = trim($_POST['employment_type'] ?? '');
    $requested_limit = floatval($_POST['requested_limit'] ?? 0);
    $credit_score_grade = trim($_POST['credit_score_grade'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');

    if ($monthly_income <= 0 || $requested_limit <= 0 || $employment_type == "" || $credit_score_grade == "" || $purpose == "") {
        $error = "Please fill in all FlexiCard application details.";
    } else {
        $approval = calculateFlexiCardApproval($monthly_income, $monthly_commitment, $employment_type, $requested_limit, $credit_score_grade);

        $flexi_account_number = generateFlexiAccountNumber($conn);
        $approved_limit = $approval['approved_limit'];
        $available_limit = $approved_limit;
        $outstanding_balance = 0.00;
        $ai_score = $approval['score'];
        $application_status = $approval['status'];

        $stmt = mysqli_prepare($conn, "INSERT INTO flexi_cards 
            (user_id, flexi_account_number, requested_limit, approved_limit, available_limit, outstanding_balance, monthly_income, monthly_commitment, employment_type, credit_score_grade, purpose, ai_score, application_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        mysqli_stmt_bind_param(
            $stmt,
            "isddddddsssis",
            $user_id,
            $flexi_account_number,
            $requested_limit,
            $approved_limit,
            $available_limit,
            $outstanding_balance,
            $monthly_income,
            $monthly_commitment,
            $employment_type,
            $credit_score_grade,
            $purpose,
            $ai_score,
            $application_status
        );

        if (mysqli_stmt_execute($stmt)) {
            if ($application_status == "approved") {
                $_SESSION['success'] = "FlexiCard approved. Your virtual account number is " . $flexi_account_number . ".";
            } elseif ($application_status == "reduced_approved") {
                $_SESSION['success'] = "FlexiCard approved with reduced limit. Your virtual account number is " . $flexi_account_number . ".";
            } else {
                $_SESSION['error'] = "FlexiCard application rejected. AI score: " . $ai_score . ".";
            }

            header("Location: /gxbank_html_by_page/pages/flexi-credit.php");
            exit();
        } else {
            $error = "Failed to submit FlexiCard application: " . mysqli_error($conn);
        }
    }
}

$stmt = mysqli_prepare($conn, "SELECT * FROM flexi_cards 
    WHERE user_id = ? 
    AND application_status IN ('approved','reduced_approved') 
    AND card_status != 'closed'
    ORDER BY flexi_card_id DESC 
    LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$flexi_card = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($flexi_card && empty($flexi_card['flexi_account_number'])) {
    $new_number = generateFlexiAccountNumber($conn);
    $stmt = mysqli_prepare($conn, "UPDATE flexi_cards SET flexi_account_number = ? WHERE flexi_card_id = ?");
    mysqli_stmt_bind_param($stmt, "si", $new_number, $flexi_card['flexi_card_id']);
    mysqli_stmt_execute($stmt);
    $flexi_card['flexi_account_number'] = $new_number;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pay_with_flexicard'])) {
    if (!$flexi_card) {
        $error = "No active FlexiCard found.";
    } elseif ($flexi_card['card_status'] == "locked") {
        $error = "Your FlexiCard is locked.";
    } else {
        $transaction_name = trim($_POST['transaction_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($transaction_name == "" || $category == "" || $amount <= 0) {
            $error = "Please fill in merchant name, category and valid amount.";
        } elseif ($amount > $flexi_card['available_limit']) {
            $error = "Insufficient FlexiCard available limit.";
        } else {
            mysqli_begin_transaction($conn);

            try {
                $new_available_limit = $flexi_card['available_limit'] - $amount;
                $new_outstanding_balance = $flexi_card['outstanding_balance'] + $amount;

                $stmt = mysqli_prepare($conn, "UPDATE flexi_cards 
                    SET available_limit = ?, outstanding_balance = ?
                    WHERE flexi_card_id = ?");
                mysqli_stmt_bind_param($stmt, "ddi", $new_available_limit, $new_outstanding_balance, $flexi_card['flexi_card_id']);
                mysqli_stmt_execute($stmt);

                $transaction_type = "spend";
                $payment_status = "not_applicable";

                $stmt = mysqli_prepare($conn, "INSERT INTO flexi_card_transactions 
                    (flexi_card_id, transaction_name, transaction_type, amount, category, notes, payment_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "issdsss", $flexi_card['flexi_card_id'], $transaction_name, $transaction_type, $amount, $category, $notes, $payment_status);
                mysqli_stmt_execute($stmt);

                mysqli_commit($conn);

                $_SESSION['success'] = "FlexiCard payment successful.";
                header("Location: /gxbank_html_by_page/pages/flexi-credit.php");
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "FlexiCard payment failed.";
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['repay_flexicard'])) {
    if (!$flexi_card) {
        $error = "No active FlexiCard found.";
    } else {
        $repayment_amount = floatval($_POST['repayment_amount'] ?? 0);
        $payment_status = trim($_POST['payment_status'] ?? 'on_time');
        $notes = trim($_POST['repayment_notes'] ?? '');

        if ($repayment_amount <= 0) {
            $error = "Please enter valid repayment amount.";
        } elseif ($repayment_amount > $flexi_card['outstanding_balance']) {
            $error = "Repayment amount cannot exceed outstanding balance.";
        } elseif ($repayment_amount > $savings_account['balance']) {
            $error = "Insufficient savings account balance.";
        } else {
            mysqli_begin_transaction($conn);

            try {
                $new_savings_balance = $savings_account['balance'] - $repayment_amount;
                $new_outstanding_balance = $flexi_card['outstanding_balance'] - $repayment_amount;
                $new_available_limit = $flexi_card['available_limit'] + $repayment_amount;

                if ($new_available_limit > $flexi_card['approved_limit']) {
                    $new_available_limit = $flexi_card['approved_limit'];
                }

                $on_time_increment = $payment_status == "on_time" ? 1 : 0;
                $late_increment = $payment_status == "late" ? 1 : 0;

                $stmt = mysqli_prepare($conn, "UPDATE accounts SET balance = ? WHERE account_id = ?");
                mysqli_stmt_bind_param($stmt, "di", $new_savings_balance, $savings_account['account_id']);
                mysqli_stmt_execute($stmt);

                $stmt = mysqli_prepare($conn, "UPDATE flexi_cards
                    SET available_limit = ?, 
                        outstanding_balance = ?,
                        on_time_payment_count = on_time_payment_count + ?,
                        late_payment_count = late_payment_count + ?
                    WHERE flexi_card_id = ?");
                mysqli_stmt_bind_param($stmt, "ddiii", $new_available_limit, $new_outstanding_balance, $on_time_increment, $late_increment, $flexi_card['flexi_card_id']);
                mysqli_stmt_execute($stmt);

                $transaction_name = "FlexiCard Repayment";
                $transaction_type = "repayment";
                $category = "Repayment";

                $stmt = mysqli_prepare($conn, "INSERT INTO flexi_card_transactions
                    (flexi_card_id, transaction_name, transaction_type, amount, category, notes, payment_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "issdsss", $flexi_card['flexi_card_id'], $transaction_name, $transaction_type, $repayment_amount, $category, $notes, $payment_status);
                mysqli_stmt_execute($stmt);

                $main_transaction_name = "FlexiCard Repayment";
                $main_transaction_type = "expense";
                $reason = $payment_status == "on_time" ? "On-time FlexiCard repayment." : "Late FlexiCard repayment.";

                $stmt = mysqli_prepare($conn, "INSERT INTO transactions
                    (account_id, transaction_name, transaction_type, amount, transfer_reason)
                    VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "issds", $savings_account['account_id'], $main_transaction_name, $main_transaction_type, $repayment_amount, $reason);
                mysqli_stmt_execute($stmt);

                mysqli_commit($conn);

                $_SESSION['success'] = "FlexiCard repayment successful.";
                header("Location: /gxbank_html_by_page/pages/flexi-credit.php");
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Repayment failed.";
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['review_limit'])) {
    if (!$flexi_card) {
        $error = "No active FlexiCard found.";
    } else {
        $review_score = calculateLimitReviewScore($flexi_card);

        if ($review_score >= 80) {
            $increase_amount = $flexi_card['approved_limit'] * 0.25;
            $eligible = "yes";
        } elseif ($review_score >= 60) {
            $increase_amount = $flexi_card['approved_limit'] * 0.10;
            $eligible = "yes";
        } else {
            $increase_amount = 0;
            $eligible = "no";
        }

        mysqli_begin_transaction($conn);

        try {
            if ($increase_amount > 0) {
                $new_approved_limit = $flexi_card['approved_limit'] + $increase_amount;
                $new_available_limit = $flexi_card['available_limit'] + $increase_amount;

                $stmt = mysqli_prepare($conn, "UPDATE flexi_cards
                    SET approved_limit = ?,
                        available_limit = ?,
                        limit_increase_eligible = ?,
                        limit_review_score = ?,
                        last_limit_review = CURDATE()
                    WHERE flexi_card_id = ?");
                mysqli_stmt_bind_param($stmt, "ddsii", $new_approved_limit, $new_available_limit, $eligible, $review_score, $flexi_card['flexi_card_id']);
                mysqli_stmt_execute($stmt);

                $transaction_name = "AI Limit Increase";
                $transaction_type = "limit_increase";
                $category = "AI Review";
                $notes = "FlexiCard limit increased based on repayment behaviour and utilisation.";
                $payment_status = "not_applicable";

                $stmt = mysqli_prepare($conn, "INSERT INTO flexi_card_transactions
                    (flexi_card_id, transaction_name, transaction_type, amount, category, notes, payment_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "issdsss", $flexi_card['flexi_card_id'], $transaction_name, $transaction_type, $increase_amount, $category, $notes, $payment_status);
                mysqli_stmt_execute($stmt);

                $_SESSION['success'] = "AI review approved. Your FlexiCard limit increased by RM " . number_format($increase_amount, 2) . ".";
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE flexi_cards
                    SET limit_increase_eligible = ?,
                        limit_review_score = ?,
                        last_limit_review = CURDATE()
                    WHERE flexi_card_id = ?");
                mysqli_stmt_bind_param($stmt, "sii", $eligible, $review_score, $flexi_card['flexi_card_id']);
                mysqli_stmt_execute($stmt);

                $_SESSION['error'] = "Not eligible for limit increase yet. AI review score: " . $review_score . ".";
            }

            mysqli_commit($conn);

            header("Location: /gxbank_html_by_page/pages/flexi-credit.php");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Limit review failed.";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_card_status'])) {
    if ($flexi_card) {
        $new_status = $flexi_card['card_status'] == "active" ? "locked" : "active";

        $stmt = mysqli_prepare($conn, "UPDATE flexi_cards SET card_status = ? WHERE flexi_card_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $new_status, $flexi_card['flexi_card_id']);
        mysqli_stmt_execute($stmt);

        $_SESSION['success'] = "FlexiCard status updated.";
        header("Location: /gxbank_html_by_page/pages/flexi-credit.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['close_card'])) {
    if ($flexi_card) {
        if ($flexi_card['outstanding_balance'] > 0) {
            $error = "Cannot close FlexiCard while outstanding balance still exists.";
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE flexi_cards SET card_status = 'closed' WHERE flexi_card_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $flexi_card['flexi_card_id']);
            mysqli_stmt_execute($stmt);

            $_SESSION['success'] = "FlexiCard closed successfully.";
            header("Location: /gxbank_html_by_page/pages/flexi-credit.php");
            exit();
        }
    }
}

$stmt = mysqli_prepare($conn, "SELECT * FROM flexi_cards 
    WHERE user_id = ? 
    AND application_status IN ('approved','reduced_approved') 
    AND card_status != 'closed'
    ORDER BY flexi_card_id DESC 
    LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$flexi_card = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$stmt = mysqli_prepare($conn, "SELECT * FROM flexi_cards WHERE user_id = ? ORDER BY flexi_card_id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$latest_application = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$flexi_transactions = null;

if ($flexi_card) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM flexi_card_transactions WHERE flexi_card_id = ? ORDER BY transaction_date DESC LIMIT 6");
    mysqli_stmt_bind_param($stmt, "i", $flexi_card['flexi_card_id']);
    mysqli_stmt_execute($stmt);
    $flexi_transactions = mysqli_stmt_get_result($stmt);
}

$flexi_number = $flexi_card ? $flexi_card['flexi_account_number'] : "";
$flexi_last4 = $flexi_number ? substr($flexi_number, -4) : "";
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>FlexiCard</title>

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

        <a href="/gxbank_html_by_page/dashboard.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Back</span>
        </a>

        <h1 class="text-2xl font-bold text-white mb-2">FlexiCard</h1>
        <p class="text-slate-400 text-sm mb-6">
            Virtual credit account for QR payment and transfer.
        </p>

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

        <?php if($error != "") { ?>
            <div class="bg-red-500/15 text-red-300 rounded-xl p-3 text-sm mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php } ?>

        <div id="copyMessage" class="hidden bg-blue-500/15 text-blue-300 rounded-xl p-3 text-sm mb-4">
            FlexiCard number copied.
        </div>

        <?php if (!$flexi_card) { ?>

            <?php if ($latest_application && $latest_application['application_status'] == 'rejected') { ?>
                <div class="bg-red-500/15 border border-red-500/30 rounded-2xl p-5 mb-4">
                    <p class="text-red-300 font-bold text-lg">Latest Application Rejected</p>
                    <p class="text-slate-300 text-sm mt-2">AI Score: <?php echo intval($latest_application['ai_score']); ?></p>
                </div>
            <?php } ?>

            <div class="bg-slate-900 rounded-2xl p-6 border border-slate-800 text-center mb-4">
                <div class="w-16 h-16 rounded-2xl bg-rose-500/15 flex items-center justify-center mx-auto mb-5">
                    <i data-lucide="badge-dollar-sign" class="w-8 h-8 text-rose-400"></i>
                </div>

                <h2 class="text-white text-xl font-bold mb-2">No Active FlexiCard</h2>
                <p class="text-slate-400 text-sm">
                    Apply for a credit limit. If approved, you get a virtual FlexiCard account number starting with 78.
                </p>
            </div>

            <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800">
                <form method="POST" class="space-y-4">

                    <input type="number" step="0.01" name="monthly_income" required placeholder="Monthly Income"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-rose-400">

                    <input type="number" step="0.01" name="monthly_commitment" required placeholder="Existing Monthly Commitment"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-rose-400">

                    <select name="employment_type"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-rose-400">
                        <option value="Permanent Employee">Permanent Employee</option>
                        <option value="Self Employed">Self Employed</option>
                        <option value="Contract Employee">Contract Employee</option>
                        <option value="Student">Student</option>
                        <option value="Unemployed">Unemployed</option>
                    </select>

                    <select name="credit_score_grade"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-rose-400">
                        <option value="Excellent">Excellent</option>
                        <option value="Good">Good</option>
                        <option value="Average">Average</option>
                        <option value="Poor">Poor</option>
                    </select>

                    <input type="number" step="0.01" name="requested_limit" required placeholder="Requested Limit"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-rose-400">

                    <input type="text" name="purpose" required placeholder="Purpose"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-rose-400">

                    <button type="submit" name="apply_flexicard"
                        class="w-full bg-rose-500 hover:bg-rose-600 text-white font-semibold py-3 rounded-xl transition">
                        Apply FlexiCard
                    </button>

                </form>
            </div>

        <?php } else { 
            $used_percent = $flexi_card['approved_limit'] > 0 ? min(100, ($flexi_card['outstanding_balance'] / $flexi_card['approved_limit']) * 100) : 0;
        ?>

            <div class="space-y-4">

                <div class="bg-gradient-to-br from-rose-500 to-rose-700 rounded-2xl p-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>

                    <div class="relative z-10">
                        <div class="flex items-center justify-between">
                            <p class="text-rose-100 text-sm font-medium">FlexiCard Virtual Account</p>

                            <button type="button" onclick="toggleFlexiNumber()"
                                class="w-9 h-9 rounded-full bg-white/15 hover:bg-white/25 flex items-center justify-center transition">
                                <i id="flexiEyeIcon" data-lucide="eye-off" class="w-5 h-5 text-white"></i>
                            </button>
                        </div>

                        <p class="text-white text-3xl font-bold tracking-tight mt-2">
                            RM <?php echo number_format($flexi_card['available_limit'], 2); ?>
                        </p>

                        <p class="text-rose-100 text-xs mt-2">
                            Available Credit Limit
                        </p>

                        <p id="flexiNumber"
                           onclick="copyFlexiNumber()"
                           class="text-rose-100 text-xs mt-3 cursor-pointer hover:text-white transition inline-flex items-center gap-1"
                           data-full="<?php echo htmlspecialchars($flexi_number); ?>"
                           data-last4="<?php echo htmlspecialchars($flexi_last4); ?>">
                            Flexi No: •••• <?php echo htmlspecialchars($flexi_last4); ?>
                            <i data-lucide="copy" class="w-3 h-3"></i>
                        </p>

                        <div class="mt-5 border-t border-white/20 pt-4 grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-rose-100 text-xs">Approved Limit</p>
                                <p class="text-white font-semibold">RM <?php echo number_format($flexi_card['approved_limit'], 2); ?></p>
                            </div>

                            <div>
                                <p class="text-rose-100 text-xs">Outstanding</p>
                                <p class="text-white font-semibold">RM <?php echo number_format($flexi_card['outstanding_balance'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="/gxbank_html_by_page/pages/transfer.php?source=flexi"
                   class="block w-full text-center bg-rose-500 hover:bg-rose-600 text-white font-semibold py-3 rounded-xl transition">
                    Pay / Transfer Using FlexiCard
                </a>

                <div class="bg-slate-900 rounded-xl p-4 border border-slate-800">
                    <p class="text-slate-300 font-medium text-sm mb-3">AI Credit Summary</p>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Initial AI Score</span>
                            <span class="text-white font-medium"><?php echo intval($flexi_card['ai_score']); ?></span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-slate-400">On-time Payments</span>
                            <span class="text-green-400 font-medium"><?php echo intval($flexi_card['on_time_payment_count']); ?></span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-slate-400">Late Payments</span>
                            <span class="text-red-400 font-medium"><?php echo intval($flexi_card['late_payment_count']); ?></span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-slate-400">Last Review Score</span>
                            <span class="text-white font-medium"><?php echo intval($flexi_card['limit_review_score']); ?></span>
                        </div>
                    </div>

                    <div class="w-full bg-slate-700 rounded-full h-2 mt-4">
                        <div class="bg-rose-500 h-2 rounded-full" style="width: <?php echo $used_percent; ?>%"></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <form method="POST">
                        <button type="submit" name="toggle_card_status"
                            class="w-full bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium py-3 rounded-lg transition">
                            <?php echo $flexi_card['card_status'] == 'active' ? 'Lock Card' : 'Unlock Card'; ?>
                        </button>
                    </form>

                    <form method="POST">
                        <button type="submit" name="review_limit"
                            class="w-full bg-amber-500 hover:bg-amber-600 text-white font-medium py-3 rounded-lg transition">
                            AI Limit Review
                        </button>
                    </form>
                </div>

                <div class="bg-slate-900 rounded-xl p-4 border border-slate-800">
                    <p class="text-slate-300 font-medium text-sm mb-4">Repay FlexiCard</p>

                    <p class="text-slate-500 text-xs mb-3">
                        Savings balance: RM <?php echo number_format($savings_account['balance'], 2); ?>
                    </p>

                    <form method="POST" class="space-y-4">
                        <input type="number" step="0.01" name="repayment_amount" required placeholder="Repayment amount"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-rose-400">

                        <select name="payment_status"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-rose-400">
                            <option value="on_time">On Time</option>
                            <option value="late">Late</option>
                        </select>

                        <input type="text" name="repayment_notes" placeholder="Repayment notes optional"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-rose-400">

                        <button type="submit" name="repay_flexicard"
                            class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 rounded-xl transition">
                            Repay Now
                        </button>
                    </form>
                </div>

                <div class="bg-slate-900 rounded-xl p-4 border border-slate-800">
                    <p class="text-slate-300 font-medium text-sm mb-3">Recent FlexiCard Activity</p>

                    <div class="space-y-2">
                        <?php if ($flexi_transactions && mysqli_num_rows($flexi_transactions) == 0) { ?>
                            <p class="text-slate-500 text-sm">No FlexiCard transactions yet.</p>
                        <?php } ?>

                        <?php if ($flexi_transactions) { ?>
                            <?php while($t = mysqli_fetch_assoc($flexi_transactions)) { 
                                $isPositive = $t['transaction_type'] == 'repayment' || $t['transaction_type'] == 'limit_increase';
                            ?>
                                <div class="flex justify-between items-start text-sm border-b border-slate-800 pb-2 last:border-b-0">
                                    <div>
                                        <span class="text-slate-300 block">
                                            <?php echo htmlspecialchars($t['transaction_name']); ?>
                                        </span>

                                        <span class="text-slate-500 text-xs">
                                            <?php echo htmlspecialchars($t['category'] ?? ''); ?>
                                            <?php if (!empty($t['notes'])) { ?>
                                                · <?php echo htmlspecialchars($t['notes']); ?>
                                            <?php } ?>
                                        </span>
                                    </div>

                                    <span class="<?php echo $isPositive ? 'text-green-400' : 'text-red-400'; ?> font-medium">
                                        <?php echo $isPositive ? '+' : '-'; ?>RM <?php echo number_format($t['amount'], 2); ?>
                                    </span>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>

                <form method="POST" onsubmit="return confirm('Close this FlexiCard? You can only close it if outstanding balance is zero.');">
                    <button type="submit" name="close_card"
                        class="w-full bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium py-3 rounded-lg transition">
                        Close FlexiCard
                    </button>
                </form>

            </div>

        <?php } ?>

    </div>
</div>

<script>
let flexiShown = false;

function toggleFlexiNumber() {
    const flexiNumber = document.getElementById("flexiNumber");
    const eyeIcon = document.getElementById("flexiEyeIcon");

    if (!flexiShown) {
        flexiNumber.innerHTML = 'Flexi No: ' + flexiNumber.dataset.full + ' <i data-lucide="copy" class="w-3 h-3"></i>';
        eyeIcon.setAttribute("data-lucide", "eye");
        flexiShown = true;
    } else {
        flexiNumber.innerHTML = 'Flexi No: •••• ' + flexiNumber.dataset.last4 + ' <i data-lucide="copy" class="w-3 h-3"></i>';
        eyeIcon.setAttribute("data-lucide", "eye-off");
        flexiShown = false;
    }

    lucide.createIcons();
}

function copyFlexiNumber() {
    const number = document.getElementById("flexiNumber").dataset.full;
    const copyMessage = document.getElementById("copyMessage");

    navigator.clipboard.writeText(number).then(function() {
        copyMessage.classList.remove("hidden");

        setTimeout(function() {
            copyMessage.classList.add("hidden");
        }, 1800);
    });
}

lucide.createIcons();
</script>

</body>
</html>