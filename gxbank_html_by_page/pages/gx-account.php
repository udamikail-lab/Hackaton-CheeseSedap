<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT * FROM accounts WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$account) {
    die("No account found for this user.");
}

$tstmt = mysqli_prepare($conn, "SELECT * FROM transactions WHERE account_id = ? ORDER BY transaction_date DESC LIMIT 5");
mysqli_stmt_bind_param($tstmt, "i", $account['account_id']);
mysqli_stmt_execute($tstmt);
$transactions = mysqli_stmt_get_result($tstmt);

$fullAccountNumber = $account['account_number'];
$last4 = substr($fullAccountNumber, -4);
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>GX Account</title>

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

        <h1 class="text-2xl font-bold text-white mb-6">GX Account</h1>

        <?php if(isset($_SESSION['success'])) { ?>
            <div class="bg-emerald-500/15 text-emerald-300 rounded-xl p-3 text-sm mb-4">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php } ?>

        <div id="copyMessage" class="hidden bg-blue-500/15 text-blue-300 rounded-xl p-3 text-sm mb-4">
            Account number copied.
        </div>

        <div class="space-y-4">

            <div onclick="copyAccountNumber()"
                 class="bg-slate-900 rounded-xl p-4 border border-slate-800 cursor-pointer hover:border-emerald-500/50 transition">

                <div class="flex items-center justify-between mb-2">
                    <p class="text-slate-400 text-sm">Account Balance</p>
                    <span class="text-slate-500 text-xs flex items-center gap-1">
                        <i data-lucide="copy" class="w-3 h-3"></i>
                        Tap to copy
                    </span>
                </div>

                <p class="text-white text-2xl font-bold mt-1">
                    RM <?php echo number_format($account['balance'], 2); ?>
                </p>

                <div class="flex items-center justify-between mt-3">
                    <p id="accountNumber"
                       class="text-slate-400 text-xs"
                       data-full="<?php echo htmlspecialchars($fullAccountNumber); ?>"
                       data-last4="<?php echo htmlspecialchars($last4); ?>">
                        Account: •••• <?php echo htmlspecialchars($last4); ?>
                    </p>
                </div>
            </div>

            <button type="button" onclick="toggleAccountNumber()"
                class="w-full bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium py-3 rounded-lg transition flex items-center justify-center gap-2">
                <i data-lucide="eye" class="w-4 h-4"></i>
                <span id="showAccountText">Show Full Account No</span>
            </button>

            <div class="grid grid-cols-2 gap-3">
                <a href="/gxbank_html_by_page/pages/transfer.php"
                   class="bg-blue-500/15 hover:bg-blue-500/25 text-blue-400 rounded-lg p-4 transition text-left">
                    <i data-lucide="send" class="w-5 h-5 mb-2"></i>
                    <p class="text-xs font-medium">Transfer</p>
                </a>
                
                <a href="/gxbank_html_by_page/pages/scan-pay.php"
                class="bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-400 rounded-lg p-4 transition text-left">
                    <i data-lucide="qr-code" class="w-5 h-5 mb-2"></i>
                    <p class="text-xs font-medium">Scan & Pay</p>
                </a>

                <a href="/gxbank_html_by_page/pages/receive.php"
                class="bg-blue-500/15 hover:bg-blue-500/25 text-blue-400 rounded-lg p-4 transition text-left">
                    <i data-lucide="arrow-down-left" class="w-5 h-5 mb-2"></i>
                    <p class="text-xs font-medium">Receive</p>
                </a>
            </div>

            <div class="bg-slate-900 rounded-xl p-4 border border-slate-800 mt-4">
                <p class="text-slate-300 font-medium text-sm mb-3">Recent Transactions</p>

                <div class="space-y-2">
                    <?php if (mysqli_num_rows($transactions) == 0) { ?>
                        <p class="text-slate-500 text-sm">No transactions yet.</p>
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
</div>

<script>
let accountShown = false;

function toggleAccountNumber() {
    const accountNumber = document.getElementById("accountNumber");
    const showAccountText = document.getElementById("showAccountText");

    if (!accountShown) {
        accountNumber.innerText = "Account: " + accountNumber.dataset.full;
        showAccountText.innerText = "Hide Account No";
        accountShown = true;
    } else {
        accountNumber.innerText = "Account: •••• " + accountNumber.dataset.last4;
        showAccountText.innerText = "Show Full Account No";
        accountShown = false;
    }
}

function copyAccountNumber() {
    const accountNumber = document.getElementById("accountNumber").dataset.full;
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