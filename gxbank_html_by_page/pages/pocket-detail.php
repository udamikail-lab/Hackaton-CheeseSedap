<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];
$pocket_id = intval($_GET['pocket_id'] ?? 0);

if ($pocket_id <= 0) {
    header("Location: /gxbank_html_by_page/pages/bonus-pockets.php");
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM bonus_pockets WHERE pocket_id = ? AND user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $pocket_id, $user_id);
mysqli_stmt_execute($stmt);
$pocket = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$pocket) {
    $_SESSION['error'] = "Tabung not found.";
    header("Location: /gxbank_html_by_page/pages/bonus-pockets.php");
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM accounts WHERE user_id = ? AND account_type = 'Savings Account' LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$main_account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$main_account) {
    die("Main savings account not found.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['transfer_in'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($amount <= 0) {
        $_SESSION['error'] = "Please enter valid amount.";
    } elseif ($amount > $main_account['balance']) {
        $_SESSION['error'] = "Insufficient main account balance.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            $stmt = mysqli_prepare($conn, "UPDATE accounts SET balance = balance - ? WHERE account_id = ?");
            mysqli_stmt_bind_param($stmt, "di", $amount, $main_account['account_id']);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($conn, "UPDATE bonus_pockets SET current_amount = current_amount + ? WHERE pocket_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "dii", $amount, $pocket_id, $user_id);
            mysqli_stmt_execute($stmt);

            $main_name = "Transfer to Tabung: " . $pocket['pocket_name'];
            $main_type = "expense";
            $reason = $notes != "" ? $notes : "Money transferred from main account into tabung.";

            $stmt = mysqli_prepare($conn, "INSERT INTO transactions
                (account_id, transaction_name, transaction_type, amount, transfer_reason)
                VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issds", $main_account['account_id'], $main_name, $main_type, $amount, $reason);
            mysqli_stmt_execute($stmt);

            $pocket_type = "transfer_in";

            $stmt = mysqli_prepare($conn, "INSERT INTO pocket_transactions
                (pocket_id, account_id, transaction_type, amount, notes)
                VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iisds", $pocket_id, $main_account['account_id'], $pocket_type, $amount, $reason);
            mysqli_stmt_execute($stmt);

            mysqli_commit($conn);

            $_SESSION['success'] = "Money transferred into tabung.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Transfer failed.";
        }
    }

    header("Location: /gxbank_html_by_page/pages/pocket-detail.php?pocket_id=" . $pocket_id);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['transfer_out'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($amount <= 0) {
        $_SESSION['error'] = "Please enter valid amount.";
    } elseif ($amount > $pocket['current_amount']) {
        $_SESSION['error'] = "Insufficient tabung balance.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            $stmt = mysqli_prepare($conn, "UPDATE bonus_pockets SET current_amount = current_amount - ? WHERE pocket_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "dii", $amount, $pocket_id, $user_id);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($conn, "UPDATE accounts SET balance = balance + ? WHERE account_id = ?");
            mysqli_stmt_bind_param($stmt, "di", $amount, $main_account['account_id']);
            mysqli_stmt_execute($stmt);

            $main_name = "Transfer from Tabung: " . $pocket['pocket_name'];
            $main_type = "income";
            $reason = $notes != "" ? $notes : "Money transferred from tabung back to main account.";

            $stmt = mysqli_prepare($conn, "INSERT INTO transactions
                (account_id, transaction_name, transaction_type, amount, transfer_reason)
                VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issds", $main_account['account_id'], $main_name, $main_type, $amount, $reason);
            mysqli_stmt_execute($stmt);

            $pocket_type = "transfer_out";

            $stmt = mysqli_prepare($conn, "INSERT INTO pocket_transactions
                (pocket_id, account_id, transaction_type, amount, notes)
                VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iisds", $pocket_id, $main_account['account_id'], $pocket_type, $amount, $reason);
            mysqli_stmt_execute($stmt);

            mysqli_commit($conn);

            $_SESSION['success'] = "Money transferred out from tabung.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Transfer failed.";
        }
    }

    header("Location: /gxbank_html_by_page/pages/pocket-detail.php?pocket_id=" . $pocket_id);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_pocket'])) {
    mysqli_begin_transaction($conn);

    try {
        $return_amount = floatval($pocket['current_amount']);

        if ($return_amount > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE accounts SET balance = balance + ? WHERE account_id = ?");
            mysqli_stmt_bind_param($stmt, "di", $return_amount, $main_account['account_id']);
            mysqli_stmt_execute($stmt);

            $main_name = "Returned balance from Tabung: " . $pocket['pocket_name'];
            $main_type = "income";
            $reason = "Tabung deleted. Remaining balance returned to main account.";

            $stmt = mysqli_prepare($conn, "INSERT INTO transactions
                (account_id, transaction_name, transaction_type, amount, transfer_reason)
                VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issds", $main_account['account_id'], $main_name, $main_type, $return_amount, $reason);
            mysqli_stmt_execute($stmt);
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM bonus_pockets WHERE pocket_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $pocket_id, $user_id);
        mysqli_stmt_execute($stmt);

        mysqli_commit($conn);

        $_SESSION['success'] = "Tabung deleted. Remaining balance returned to main account.";
        header("Location: /gxbank_html_by_page/pages/bonus-pockets.php");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Failed to delete tabung.";
        header("Location: /gxbank_html_by_page/pages/pocket-detail.php?pocket_id=" . $pocket_id);
        exit();
    }
}

$stmt = mysqli_prepare($conn, "SELECT * FROM bonus_pockets WHERE pocket_id = ? AND user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $pocket_id, $user_id);
mysqli_stmt_execute($stmt);
$pocket = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$stmt = mysqli_prepare($conn, "SELECT * FROM accounts WHERE user_id = ? AND account_type = 'Savings Account' LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$main_account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$stmt = mysqli_prepare($conn, "SELECT * FROM pocket_transactions WHERE pocket_id = ? ORDER BY transaction_date DESC LIMIT 6");
mysqli_stmt_bind_param($stmt, "i", $pocket_id);
mysqli_stmt_execute($stmt);
$pocket_transactions = mysqli_stmt_get_result($stmt);

$target = floatval($pocket['target_amount']);
$current = floatval($pocket['current_amount']);
$percent = $target > 0 ? min(100, ($current / $target) * 100) : 0;
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Tabung Detail</title>

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

        <a href="/gxbank_html_by_page/pages/bonus-pockets.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Tabung List</span>
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

        <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-2xl p-6 relative overflow-hidden mb-4">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>

            <div class="relative z-10">
                <p class="text-emerald-100 text-sm font-medium">Tabung</p>

                <h1 class="text-white text-2xl font-bold mt-1">
                    <?php echo htmlspecialchars($pocket['pocket_name']); ?>
                </h1>

                <p class="text-white text-3xl font-bold tracking-tight mt-4">
                    RM <?php echo number_format($current, 2); ?>
                </p>

                <p class="text-emerald-100 text-xs mt-2">
                    Target: RM <?php echo number_format($target, 2); ?>
                </p>

                <div class="w-full bg-white/20 rounded-full h-2 mt-4">
                    <div class="bg-white h-2 rounded-full" style="width: <?php echo $percent; ?>%"></div>
                </div>

                <p class="text-emerald-100 text-xs mt-2">
                    <?php echo number_format($percent, 0); ?>% completed
                </p>
            </div>
        </div>

        <div class="bg-slate-900 rounded-xl p-4 border border-slate-800 mb-4">
            <p class="text-slate-300 font-medium text-sm mb-3">Main Account</p>

            <div class="flex justify-between text-sm">
                <span class="text-slate-400">Available Balance</span>
                <span class="text-white font-medium">RM <?php echo number_format($main_account['balance'], 2); ?></span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <button type="button" onclick="showForm('in')"
                class="bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-400 rounded-xl p-4 transition text-left">
                <i data-lucide="arrow-down-left" class="w-5 h-5 mb-2"></i>
                <p class="text-xs font-medium">Transfer Into Tabung</p>
            </button>

            <button type="button" onclick="showForm('out')"
                class="bg-blue-500/15 hover:bg-blue-500/25 text-blue-400 rounded-xl p-4 transition text-left">
                <i data-lucide="arrow-up-right" class="w-5 h-5 mb-2"></i>
                <p class="text-xs font-medium">Transfer Out</p>
            </button>
        </div>

        <div id="transferInForm" class="hidden bg-slate-900 rounded-xl p-4 border border-slate-800 mb-4">
            <p class="text-slate-300 font-medium text-sm mb-4">Transfer Into Tabung</p>

            <form method="POST" class="space-y-4">
                <input type="number" step="0.01" name="amount" required placeholder="Amount"
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">

                <input type="text" name="notes" placeholder="Notes optional"
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">

                <button type="submit" name="transfer_in"
                    class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 rounded-xl transition">
                    Confirm Transfer In
                </button>
            </form>
        </div>

        <div id="transferOutForm" class="hidden bg-slate-900 rounded-xl p-4 border border-slate-800 mb-4">
            <p class="text-slate-300 font-medium text-sm mb-4">Transfer Out to Main Account</p>

            <form method="POST" class="space-y-4">
                <input type="number" step="0.01" name="amount" required placeholder="Amount"
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-blue-400">

                <input type="text" name="notes" placeholder="Notes optional"
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-blue-400">

                <button type="submit" name="transfer_out"
                    class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 rounded-xl transition">
                    Confirm Transfer Out
                </button>
            </form>
        </div>

        <div class="bg-slate-900 rounded-xl p-4 border border-slate-800 mb-4">
            <p class="text-slate-300 font-medium text-sm mb-3">Tabung Transactions</p>

            <div class="space-y-2">
                <?php if (mysqli_num_rows($pocket_transactions) == 0) { ?>
                    <p class="text-slate-500 text-sm">No tabung transactions yet.</p>
                <?php } ?>

                <?php while($t = mysqli_fetch_assoc($pocket_transactions)) { 
                    $isIn = $t['transaction_type'] == 'transfer_in';
                ?>
                    <div class="flex justify-between items-start text-sm border-b border-slate-800 pb-2 last:border-b-0">
                        <div>
                            <span class="text-slate-300 block">
                                <?php echo $isIn ? 'Transfer Into Tabung' : 'Transfer Out to Main'; ?>
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

                        <span class="<?php echo $isIn ? 'text-green-400' : 'text-red-400'; ?> font-medium">
                            <?php echo $isIn ? '+' : '-'; ?>RM <?php echo number_format($t['amount'], 2); ?>
                        </span>
                    </div>
                <?php } ?>
            </div>
        </div>

        <form method="POST" onsubmit="return confirm('Delete this tabung? Any remaining balance will be returned to your main account and recorded in transaction history.');">
            <button type="submit" name="delete_pocket"
                class="w-full bg-red-500/15 hover:bg-red-500/25 text-red-400 font-medium py-3 rounded-xl transition flex items-center justify-center gap-2">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                Delete Tabung
            </button>
        </form>

    </div>
</div>

<script>
function showForm(type) {
    const inForm = document.getElementById("transferInForm");
    const outForm = document.getElementById("transferOutForm");

    if (type === "in") {
        inForm.classList.remove("hidden");
        outForm.classList.add("hidden");
    } else {
        outForm.classList.remove("hidden");
        inForm.classList.add("hidden");
    }
}

lucide.createIcons();
</script>

</body>
</html>