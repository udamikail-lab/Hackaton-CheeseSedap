<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];
$account_id = intval($_GET['account_id'] ?? 0);

if ($account_id <= 0) {
    header("Location: /gxbank_html_by_page/pages/biz-account.php");
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM accounts WHERE account_id = ? AND user_id = ? AND account_type = 'Business Account' LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $account_id, $user_id);
mysqli_stmt_execute($stmt);
$business_account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$business_account) {
    $_SESSION['error'] = "Business account not found.";
    header("Location: /gxbank_html_by_page/pages/biz-account.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_business'])) {
    mysqli_begin_transaction($conn);

    try {
        $stmt = mysqli_prepare($conn, "SELECT * FROM accounts WHERE user_id = ? AND account_type = 'Savings Account' LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $main_account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$main_account) {
            throw new Exception("Main account not found.");
        }

        $business_balance = floatval($business_account['balance']);

        if ($business_balance > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE accounts SET balance = balance + ? WHERE account_id = ?");
            mysqli_stmt_bind_param($stmt, "di", $business_balance, $main_account['account_id']);
            mysqli_stmt_execute($stmt);

            $transaction_name = "Returned balance from " . $business_account['business_name'];
            $transaction_type = "income";
            $reason = "Business account deleted. Remaining balance returned to main account.";

            $stmt = mysqli_prepare($conn, "INSERT INTO transactions 
                (account_id, transaction_name, transaction_type, amount, transfer_reason)
                VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param(
                $stmt,
                "issds",
                $main_account['account_id'],
                $transaction_name,
                $transaction_type,
                $business_balance,
                $reason
            );
            mysqli_stmt_execute($stmt);
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM transactions WHERE account_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $account_id);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($conn, "DELETE FROM accounts WHERE account_id = ? AND user_id = ? AND account_type = 'Business Account'");
        mysqli_stmt_bind_param($stmt, "ii", $account_id, $user_id);
        mysqli_stmt_execute($stmt);

        mysqli_commit($conn);

        $_SESSION['success'] = "Business account deleted successfully. Remaining balance has been returned to your main account.";
        header("Location: /gxbank_html_by_page/pages/biz-account.php");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);

        $_SESSION['error'] = "Failed to delete business account: " . $e->getMessage();
        header("Location: /gxbank_html_by_page/pages/biz-detail.php?account_id=" . $account_id);
        exit();
    }
}

$stmt = mysqli_prepare($conn, "SELECT * FROM transactions WHERE account_id = ? ORDER BY transaction_date DESC LIMIT 5");
mysqli_stmt_bind_param($stmt, "i", $account_id);
mysqli_stmt_execute($stmt);
$transactions = mysqli_stmt_get_result($stmt);

$fullAccount = $business_account['account_number'];
$last4 = substr($fullAccount, -4);
$qrData = "GXACC|" . $fullAccount;
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Business Detail</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
            <span class="text-sm font-medium">Business List</span>
        </a>

        <?php if(isset($_SESSION['error'])) { ?>
            <div class="bg-red-500/15 text-red-300 rounded-xl p-3 text-sm mb-4">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php } ?>

        <div id="copyMessage" class="hidden bg-blue-500/15 text-blue-300 rounded-xl p-3 text-sm mb-4">
            Business account number copied.
        </div>

        <div class="bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-2xl p-6 relative overflow-hidden mb-4">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>

            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <p class="text-indigo-100 text-sm font-medium">Business Account</p>

                    <button type="button" onclick="toggleBizAccountNumber()"
                        class="w-9 h-9 rounded-full bg-white/15 hover:bg-white/25 flex items-center justify-center transition">
                        <i id="bizEyeIcon" data-lucide="eye-off" class="w-5 h-5 text-white"></i>
                    </button>
                </div>

                <p class="text-white text-3xl font-bold tracking-tight mt-2">
                    RM <?php echo number_format($business_account['balance'], 2); ?>
                </p>

                <p id="bizAccountNumber"
                   onclick="copyBizAccountNumber()"
                   class="text-indigo-200 text-xs mt-2 cursor-pointer hover:text-white transition inline-flex items-center gap-1"
                   data-full="<?php echo htmlspecialchars($fullAccount); ?>"
                   data-last4="<?php echo htmlspecialchars($last4); ?>">
                    Account: •••• <?php echo htmlspecialchars($last4); ?>
                    <i data-lucide="copy" class="w-3 h-3"></i>
                </p>

                <div class="mt-5 border-t border-white/20 pt-4">
                    <p class="text-indigo-100 text-xs">Business Name</p>
                    <p class="text-white font-semibold">
                        <?php echo htmlspecialchars($business_account['business_name']); ?>
                    </p>

                    <p class="text-indigo-100 text-xs mt-3">Registration No.</p>
                    <p class="text-white font-semibold">
                        <?php echo htmlspecialchars($business_account['business_reg_no']); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <a href="/gxbank_html_by_page/pages/transfer.php?recipient_account_number=<?php echo urlencode($fullAccount); ?>"
               class="bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl p-4 transition">
                <i data-lucide="send" class="w-5 h-5 mb-2"></i>
                <p class="text-xs font-medium">Transfer Here</p>
            </a>

            <form method="POST" onsubmit="return confirm('Delete this business account? Any remaining balance will be returned to your main account and recorded in transaction history.');">
                <button type="submit" name="delete_business"
                    class="w-full h-full bg-red-500/15 hover:bg-red-500/25 text-red-400 rounded-xl p-4 transition text-left">
                    <i data-lucide="trash-2" class="w-5 h-5 mb-2"></i>
                    <p class="text-xs font-medium">Delete Business</p>
                </button>
            </form>
        </div>

        <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800 text-center mb-4">
            <h2 class="text-white text-lg font-bold mb-2">Business QR Payment</h2>

            <p class="text-slate-400 text-sm mb-5">
                This QR is linked to this business account only.
            </p>

            <div class="bg-white rounded-2xl p-4 inline-block">
                <div id="businessQR"></div>
            </div>

            <p class="text-slate-500 text-xs mt-4">
                QR linked to account: <?php echo htmlspecialchars($fullAccount); ?>
            </p>
        </div>

        <div class="bg-slate-900 rounded-xl p-4 border border-slate-800">
            <p class="text-slate-300 font-medium text-sm mb-3">Recent Business Transactions</p>

            <div class="space-y-2">
                <?php if (mysqli_num_rows($transactions) == 0) { ?>
                    <p class="text-slate-500 text-sm">No business transactions yet.</p>
                <?php } ?>

                <?php while($t = mysqli_fetch_assoc($transactions)) { 
                    $isIncome = $t['transaction_type'] == 'income';
                ?>
                    <div class="flex justify-between items-start text-sm border-b border-slate-800 pb-2 last:border-b-0">
                        <div>
                            <span class="text-slate-300 block">
                                <?php echo htmlspecialchars($t['transaction_name']); ?>
                            </span>

                            <?php if (!empty($t['transfer_reason'])) { ?>
                                <span class="text-slate-500 text-xs">
                                    <?php echo htmlspecialchars($t['transfer_reason']); ?>
                                </span>
                            <?php } ?>

                            <span class="text-slate-600 text-xs block mt-1">
                                <?php echo date("d M Y, h:i A", strtotime($t['transaction_date'])); ?>
                            </span>
                        </div>

                        <span class="<?php echo $isIncome ? 'text-green-400' : 'text-red-400'; ?> font-medium">
                            <?php echo $isIncome ? '+' : '-'; ?>RM <?php echo number_format($t['amount'], 2); ?>
                        </span>
                    </div>
                <?php } ?>
            </div>
        </div>

    </div>
</div>

<script>
const qrData = "<?php echo htmlspecialchars($qrData); ?>";

new QRCode(document.getElementById("businessQR"), {
    text: qrData,
    width: 190,
    height: 190,
    colorDark: "#000000",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
});

let bizAccountShown = false;

function toggleBizAccountNumber() {
    const accountNumber = document.getElementById("bizAccountNumber");
    const eyeIcon = document.getElementById("bizEyeIcon");

    if (!bizAccountShown) {
        accountNumber.innerHTML = 'Account: ' + accountNumber.dataset.full + ' <i data-lucide="copy" class="w-3 h-3"></i>';
        eyeIcon.setAttribute("data-lucide", "eye");
        bizAccountShown = true;
    } else {
        accountNumber.innerHTML = 'Account: •••• ' + accountNumber.dataset.last4 + ' <i data-lucide="copy" class="w-3 h-3"></i>';
        eyeIcon.setAttribute("data-lucide", "eye-off");
        bizAccountShown = false;
    }

    lucide.createIcons();
}

function copyBizAccountNumber() {
    const accountNumber = document.getElementById("bizAccountNumber").dataset.full;
    const copyMessage = document.getElementById("copyMessage");

    navigator.clipboard.writeText(accountNumber).then(function() {
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