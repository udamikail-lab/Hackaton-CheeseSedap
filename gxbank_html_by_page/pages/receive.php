<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT u.full_name, a.account_id, a.account_number, a.balance
    FROM users u
    JOIN accounts a ON u.user_id = a.user_id
    WHERE u.user_id = ? AND a.account_type = 'Savings Account'
    LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$data) {
    die("Main savings account not found.");
}

$amount = "";
$reason = "";
$qrData = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = floatval($_POST['amount'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($amount > 0 && $reason != "") {
        $qrData = "GXREQ|" . $data['account_number'] . "|" . number_format($amount, 2, '.', '') . "|" . $reason;
    }
}
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Receive Money</title>

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

        <a href="/gxbank_html_by_page/pages/gx-account.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Back</span>
        </a>

        <h1 class="text-2xl font-bold text-white mb-2">Receive Money</h1>
        <p class="text-slate-400 text-sm mb-6">
            Set amount and reason. Other users can scan your QR and pay the exact requested amount.
        </p>

        <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800 mb-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-11 h-11 rounded-xl bg-emerald-500/15 flex items-center justify-center">
                    <i data-lucide="wallet" class="w-5 h-5 text-emerald-400"></i>
                </div>

                <div>
                    <p class="text-white font-semibold"><?php echo htmlspecialchars($data['full_name']); ?></p>
                    <p class="text-slate-400 text-xs">Account: <?php echo htmlspecialchars($data['account_number']); ?></p>
                </div>
            </div>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="text-slate-300 text-sm font-medium">Requested Amount</label>
                    <input type="number" step="0.01" name="amount" value="<?php echo htmlspecialchars($amount); ?>" required
                        placeholder="Example: 50.00"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Reason</label>
                    <input type="text" name="reason" value="<?php echo htmlspecialchars($reason); ?>" required
                        placeholder="Example: Lunch payment, rent, group dinner"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">
                </div>

                <button type="submit"
                    class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 rounded-xl transition">
                    Generate Receive QR
                </button>
            </form>
        </div>

        <?php if ($qrData != "") { ?>

            <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800 text-center">

                <div class="w-12 h-12 rounded-2xl bg-emerald-500/15 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="qr-code" class="w-6 h-6 text-emerald-400"></i>
                </div>

                <h2 class="text-white text-lg font-bold mb-2">Payment Request QR</h2>

                <p class="text-slate-400 text-sm mb-5">
                    Ask other user to scan this QR. Amount and reason will be auto-filled.
                </p>

                <div class="bg-white rounded-2xl p-4 inline-block">
                    <div id="receiveQR"></div>
                </div>

                <div class="mt-5 bg-slate-950 rounded-xl p-4 border border-slate-800 text-left">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-slate-400">Requester</span>
                        <span class="text-white font-medium"><?php echo htmlspecialchars($data['full_name']); ?></span>
                    </div>

                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-slate-400">Account</span>
                        <span class="text-white font-medium"><?php echo htmlspecialchars($data['account_number']); ?></span>
                    </div>

                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-slate-400">Amount</span>
                        <span class="text-white font-medium">RM <?php echo number_format($amount, 2); ?></span>
                    </div>

                    <div class="flex justify-between text-sm gap-3">
                        <span class="text-slate-400">Reason</span>
                        <span class="text-white font-medium text-right"><?php echo htmlspecialchars($reason); ?></span>
                    </div>
                </div>

            </div>

            <script>
                new QRCode(document.getElementById("receiveQR"), {
                    text: <?php echo json_encode($qrData); ?>,
                    width: 190,
                    height: 190,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            </script>

        <?php } ?>

    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>