<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];
$error = "";
$confirm = false;

$recipient_account_number = trim($_GET['recipient_account_number'] ?? '');
$amount = trim($_GET['requested_amount'] ?? '');
$reason = trim($_GET['request_reason'] ?? '');
$is_payment_request = isset($_GET['payment_request']) && $_GET['payment_request'] == "1";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $recipient_account_number = trim($_POST['recipient_account_number'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $is_payment_request = isset($_POST['payment_request']) && $_POST['payment_request'] == "1";
}

function getRecipientDisplayName($account) {
    if ($account['account_type'] == 'Business Account' && !empty($account['business_name'])) {
        return $account['business_name'];
    }

    return $account['full_name'];
}

function getSelectedNormalAccount($conn, $user_id, $account_id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM accounts WHERE user_id = ? AND account_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $account_id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

function getSelectedFlexiCard($conn, $user_id, $flexi_card_id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM flexi_cards 
        WHERE user_id = ? 
        AND flexi_card_id = ? 
        AND application_status IN ('approved','reduced_approved') 
        AND card_status != 'closed'
        LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $flexi_card_id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

function getRecipientAccount($conn, $recipient_account_number) {
    $stmt = mysqli_prepare($conn, "SELECT 
            a.account_id,
            a.user_id,
            a.account_type,
            a.business_name,
            a.business_reg_no,
            a.account_number,
            a.balance,
            u.full_name
        FROM accounts a
        JOIN users u ON a.user_id = u.user_id
        WHERE a.account_number = ?
        LIMIT 1");

    mysqli_stmt_bind_param($stmt, "s", $recipient_account_number);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

function parseSource($source_value) {
    $parts = explode(":", $source_value);

    if (count($parts) != 2) {
        return ["type" => "", "id" => 0];
    }

    return [
        "type" => $parts[0],
        "id" => intval($parts[1])
    ];
}

$accounts_stmt = mysqli_prepare($conn, "SELECT * FROM accounts WHERE user_id = ? ORDER BY account_type ASC");
mysqli_stmt_bind_param($accounts_stmt, "i", $user_id);
mysqli_stmt_execute($accounts_stmt);
$user_accounts_result = mysqli_stmt_get_result($accounts_stmt);

$user_accounts = [];
while ($row = mysqli_fetch_assoc($user_accounts_result)) {
    $user_accounts[] = $row;
}

$stmt = mysqli_prepare($conn, "SELECT * FROM flexi_cards 
    WHERE user_id = ? 
    AND application_status IN ('approved','reduced_approved') 
    AND card_status != 'closed'
    ORDER BY flexi_card_id DESC
    LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$active_flexi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (count($user_accounts) == 0) {
    die("No account found for this user.");
}

$source_value = trim($_POST['source_value'] ?? ("account:" . $user_accounts[0]['account_id']));

$recipient = null;

if ($recipient_account_number != "") {
    $recipient = getRecipientAccount($conn, $recipient_account_number);
}

if (!$recipient) {
    $error = "QR recipient account not found.";
}

$sender_account = null;
$sender_flexi = null;

function validateQRPayment($conn, $user_id, $source_value, $recipient, $amount, $reason, &$sender_account, &$sender_flexi) {
    $source = parseSource($source_value);
    $amount_value = floatval($amount);

    if (!$recipient) {
        return "Recipient account not found.";
    }

    if ($amount_value <= 0) {
        return "Please enter a valid amount.";
    }

    if (trim($reason) == "") {
        return "Please enter payment reason.";
    }

    if ($source['type'] == "account") {
        $sender_account = getSelectedNormalAccount($conn, $user_id, $source['id']);

        if (!$sender_account) {
            return "Invalid source account.";
        }

        if ($recipient['account_id'] == $sender_account['account_id']) {
            return "You cannot pay to the same account.";
        }

        if ($amount_value > $sender_account['balance']) {
            return "Insufficient balance in selected account.";
        }
    } elseif ($source['type'] == "flexi") {
        $sender_flexi = getSelectedFlexiCard($conn, $user_id, $source['id']);

        if (!$sender_flexi) {
            return "Invalid FlexiCard source.";
        }

        if ($sender_flexi['card_status'] == "locked") {
            return "Your FlexiCard is locked. You cannot pay using FlexiCard.";
        }

        if ($amount_value > $sender_flexi['available_limit']) {
            return "Insufficient FlexiCard available limit.";
        }
    } else {
        return "Invalid payment source.";
    }

    return "";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['continue_payment'])) {
    $error = validateQRPayment($conn, $user_id, $source_value, $recipient, $amount, $reason, $sender_account, $sender_flexi);

    if ($error == "") {
        $confirm = true;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_payment'])) {
    $error = validateQRPayment($conn, $user_id, $source_value, $recipient, $amount, $reason, $sender_account, $sender_flexi);

    if ($error == "") {
        $source = parseSource($source_value);
        $amount_value = floatval($amount);
        $recipient_display_name = getRecipientDisplayName($recipient);

        mysqli_begin_transaction($conn);

        try {
            if ($source['type'] == "account") {
                $stmt = mysqli_prepare($conn, "UPDATE accounts SET balance = balance - ? WHERE account_id = ?");
                mysqli_stmt_bind_param($stmt, "di", $amount_value, $sender_account['account_id']);
                mysqli_stmt_execute($stmt);

                if ($sender_account['account_type'] == 'Business Account' && !empty($sender_account['business_name'])) {
                    $sender_display_name = $sender_account['business_name'];
                } else {
                    $sender_display_name = $_SESSION['full_name'];
                }

                $sender_transaction_name = "QR Payment to " . $recipient_display_name;
                $sender_type = "transfer";

                $stmt = mysqli_prepare($conn, "INSERT INTO transactions 
                    (account_id, transaction_name, transaction_type, amount, transfer_reason) 
                    VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "issds", $sender_account['account_id'], $sender_transaction_name, $sender_type, $amount_value, $reason);
                mysqli_stmt_execute($stmt);
            } else {
                $new_available = $sender_flexi['available_limit'] - $amount_value;
                $new_outstanding = $sender_flexi['outstanding_balance'] + $amount_value;

                $stmt = mysqli_prepare($conn, "UPDATE flexi_cards 
                    SET available_limit = ?, outstanding_balance = ?
                    WHERE flexi_card_id = ?");
                mysqli_stmt_bind_param($stmt, "ddi", $new_available, $new_outstanding, $sender_flexi['flexi_card_id']);
                mysqli_stmt_execute($stmt);

                $sender_display_name = "FlexiCard " . $sender_flexi['flexi_account_number'];

                $flexi_transaction_name = "QR Payment to " . $recipient_display_name;
                $flexi_type = "spend";
                $category = "QR Payment";
                $payment_status = "not_applicable";

                $stmt = mysqli_prepare($conn, "INSERT INTO flexi_card_transactions
                    (flexi_card_id, transaction_name, transaction_type, amount, category, notes, payment_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "issdsss", $sender_flexi['flexi_card_id'], $flexi_transaction_name, $flexi_type, $amount_value, $category, $reason, $payment_status);
                mysqli_stmt_execute($stmt);
            }

            $stmt = mysqli_prepare($conn, "UPDATE accounts SET balance = balance + ? WHERE account_id = ?");
            mysqli_stmt_bind_param($stmt, "di", $amount_value, $recipient['account_id']);
            mysqli_stmt_execute($stmt);

            $receiver_transaction_name = "QR Payment from " . $sender_display_name;
            $receiver_type = "income";

            $stmt = mysqli_prepare($conn, "INSERT INTO transactions 
                (account_id, transaction_name, transaction_type, amount, transfer_reason) 
                VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issds", $recipient['account_id'], $receiver_transaction_name, $receiver_type, $amount_value, $reason);
            mysqli_stmt_execute($stmt);

            mysqli_commit($conn);

            $_SESSION['success'] = "QR payment successful.";

            if ($source['type'] == "flexi") {
                header("Location: /gxbank_html_by_page/pages/flexi-credit.php");
            } else {
                header("Location: /gxbank_html_by_page/pages/gx-account.php");
            }

            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "QR payment failed. Please try again.";
        }
    }
}

$recipient_display_name = $recipient ? getRecipientDisplayName($recipient) : "";
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>QR Payment</title>

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

        <a href="/gxbank_html_by_page/pages/scan-pay.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Back to Scan</span>
        </a>

        <h1 class="text-2xl font-bold text-white mb-2">QR Payment</h1>
        <p class="text-slate-400 text-sm mb-6">
            This page is only for QR payments.
        </p>

        <?php if ($error != "") { ?>
            <div class="bg-red-500/15 text-red-300 rounded-xl p-3 text-sm mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php } ?>

        <?php if ($recipient) { ?>

            <?php if (!$confirm) { ?>

                <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800 mb-4">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-500/15 flex items-center justify-center mb-4">
                        <i data-lucide="qr-code" class="w-7 h-7 text-emerald-400"></i>
                    </div>

                    <h2 class="text-white text-xl font-bold mb-4">
                        <?php echo $is_payment_request ? 'Payment Request Detected' : 'QR Account Detected'; ?>
                    </h2>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-slate-400">Recipient</span>
                            <span class="text-white font-medium text-right"><?php echo htmlspecialchars($recipient_display_name); ?></span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-slate-400">Account</span>
                            <span class="text-white font-medium"><?php echo htmlspecialchars($recipient['account_number']); ?></span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-slate-400">Account Type</span>
                            <span class="text-white font-medium text-right"><?php echo htmlspecialchars($recipient['account_type']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800">
                    <form method="POST" class="space-y-4">

                        <input type="hidden" name="recipient_account_number" value="<?php echo htmlspecialchars($recipient_account_number); ?>">
                        <input type="hidden" name="payment_request" value="<?php echo $is_payment_request ? '1' : '0'; ?>">

                        <div>
                            <label class="text-slate-300 text-sm font-medium">Pay From</label>

                            <select name="source_value"
                                class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">

                                <?php foreach ($user_accounts as $acc) { 
                                    if ($acc['account_type'] == 'Business Account' && !empty($acc['business_name'])) {
                                        $label = $acc['business_name'] . " - Business Account";
                                    } elseif ($acc['account_type'] == 'Savings Account') {
                                        $label = "Personal Savings Account";
                                    } else {
                                        $label = $acc['account_type'];
                                    }

                                    $value = "account:" . $acc['account_id'];
                                    $selected = $source_value == $value ? "selected" : "";
                                ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                        - RM <?php echo number_format($acc['balance'], 2); ?>
                                        - •••• <?php echo htmlspecialchars(substr($acc['account_number'], -4)); ?>
                                    </option>
                                <?php } ?>

                                <?php if ($active_flexi) { 
                                    $value = "flexi:" . $active_flexi['flexi_card_id'];
                                    $selected = $source_value == $value ? "selected" : "";
                                    $disabled = $active_flexi['card_status'] == "locked" ? "disabled" : "";
                                ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $selected; ?> <?php echo $disabled; ?>>
                                        FlexiCard Credit
                                        - Available RM <?php echo number_format($active_flexi['available_limit'], 2); ?>
                                        - •••• <?php echo htmlspecialchars(substr($active_flexi['flexi_account_number'], -4)); ?>
                                        <?php echo $active_flexi['card_status'] == "locked" ? " - LOCKED" : ""; ?>
                                    </option>
                                <?php } ?>

                            </select>

                            <?php if ($active_flexi && $active_flexi['card_status'] == "locked") { ?>
                                <p class="text-red-300 text-xs mt-2">
                                    FlexiCard is locked. Unlock it before using FlexiCard for QR payment.
                                </p>
                            <?php } ?>
                        </div>

                        <div>
                            <label class="text-slate-300 text-sm font-medium">Amount</label>
                            <input type="number" step="0.01" name="amount"
                                value="<?php echo htmlspecialchars($amount); ?>"
                                required
                                <?php echo $is_payment_request ? 'readonly' : ''; ?>
                                placeholder="0.00"
                                class="w-full mt-2 <?php echo $is_payment_request ? 'bg-slate-800 text-slate-300' : 'bg-slate-950 text-white'; ?> border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-emerald-400">
                        </div>

                        <div>
                            <label class="text-slate-300 text-sm font-medium">Reason</label>
                            <input type="text" name="reason"
                                value="<?php echo htmlspecialchars($reason); ?>"
                                required
                                <?php echo $is_payment_request ? 'readonly' : ''; ?>
                                placeholder="Example: Lunch payment"
                                class="w-full mt-2 <?php echo $is_payment_request ? 'bg-slate-800 text-slate-300' : 'bg-slate-950 text-white'; ?> border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-emerald-400">
                        </div>



                        <button type="submit" name="continue_payment"
                            class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 rounded-xl transition">
                            Continue
                        </button>

                    </form>
                </div>

            <?php } else { 
                $source = parseSource($source_value);

                if ($source['type'] == "account" && !$sender_account) {
                    $sender_account = getSelectedNormalAccount($conn, $user_id, $source['id']);
                }

                if ($source['type'] == "flexi" && !$sender_flexi) {
                    $sender_flexi = getSelectedFlexiCard($conn, $user_id, $source['id']);
                }

                if ($source['type'] == "flexi") {
                    $source_display_name = "FlexiCard " . $sender_flexi['flexi_account_number'];
                    $source_number = $sender_flexi['flexi_account_number'];
                } elseif ($sender_account['account_type'] == 'Business Account' && !empty($sender_account['business_name'])) {
                    $source_display_name = $sender_account['business_name'] . " - Business Account";
                    $source_number = $sender_account['account_number'];
                } else {
                    $source_display_name = "Personal Savings Account";
                    $source_number = $sender_account['account_number'];
                }
            ?>

                <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800 mb-4">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-500/15 flex items-center justify-center mb-4">
                        <i data-lucide="check-circle" class="w-7 h-7 text-emerald-400"></i>
                    </div>

                    <h2 class="text-white text-xl font-bold mb-4">Confirm QR Payment</h2>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-slate-400">Pay From</span>
                            <span class="text-white font-medium text-right"><?php echo htmlspecialchars($source_display_name); ?></span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-slate-400">Source No.</span>
                            <span class="text-white font-medium"><?php echo htmlspecialchars($source_number); ?></span>
                        </div>

                        <div class="border-t border-slate-800 my-3"></div>

                        <div class="flex justify-between gap-4">
                            <span class="text-slate-400">Requested By</span>
                            <span class="text-white font-medium text-right"><?php echo htmlspecialchars($recipient_display_name); ?></span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-slate-400">Recipient Account</span>
                            <span class="text-white font-medium"><?php echo htmlspecialchars($recipient['account_number']); ?></span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-slate-400">Recipient Type</span>
                            <span class="text-white font-medium text-right"><?php echo htmlspecialchars($recipient['account_type']); ?></span>
                        </div>

                        <div class="border-t border-slate-800 my-3"></div>

                        <div class="flex justify-between gap-4">
                            <span class="text-slate-400">Amount</span>
                            <span class="text-white font-bold">RM <?php echo number_format(floatval($amount), 2); ?></span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-slate-400">Reason</span>
                            <span class="text-white font-medium text-right"><?php echo htmlspecialchars($reason); ?></span>
                        </div>

                        

                        <?php if ($source['type'] == "flexi") { ?>
                            <div class="bg-rose-500/15 text-rose-200 rounded-xl p-3 text-xs mt-4">
                                This QR payment will use your FlexiCard credit limit. Outstanding balance will increase after confirmation.
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <form method="POST" class="space-y-3">
                    <input type="hidden" name="source_value" value="<?php echo htmlspecialchars($source_value); ?>">
                    <input type="hidden" name="recipient_account_number" value="<?php echo htmlspecialchars($recipient_account_number); ?>">
                    <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                    <input type="hidden" name="reason" value="<?php echo htmlspecialchars($reason); ?>">
                    <input type="hidden" name="payment_request" value="<?php echo $is_payment_request ? '1' : '0'; ?>">

                    <button type="submit" name="confirm_payment"
                        class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 rounded-xl transition">
                        Confirm Payment
                    </button>
                </form>

                <form method="GET" action="/gxbank_html_by_page/pages/scan-pay.php" class="mt-3">
                    <button type="submit"
                        class="w-full bg-slate-800 hover:bg-slate-700 text-white font-semibold py-3 rounded-xl transition">
                        Cancel
                    </button>
                </form>

            <?php } ?>

        <?php } ?>

    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>