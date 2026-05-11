<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];

function calculateLuhnCheckDigit($number) {
    $sum = 0;
    $shouldDouble = true;

    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $digit = intval($number[$i]);

        if ($shouldDouble) {
            $digit *= 2;

            if ($digit > 9) {
                $digit -= 9;
            }
        }

        $sum += $digit;
        $shouldDouble = !$shouldDouble;
    }

    return (10 - ($sum % 10)) % 10;
}

function generateDebitCardNumber($conn) {
    $prefix = "455588";

    do {
        $randomPart = "";

        for ($i = 0; $i < 9; $i++) {
            $randomPart .= rand(0, 9);
        }

        $numberWithoutCheckDigit = $prefix . $randomPart;
        $checkDigit = calculateLuhnCheckDigit($numberWithoutCheckDigit);
        $cardNumber = $numberWithoutCheckDigit . $checkDigit;

        $stmt = mysqli_prepare($conn, "SELECT card_id FROM debit_cards WHERE card_number = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $cardNumber);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        $exists = mysqli_stmt_num_rows($stmt) > 0;

    } while ($exists);

    return $cardNumber;
}

function generateCVC() {
    return str_pad(rand(0, 999), 3, "0", STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['create_card'])) {

        $full_card_number = generateDebitCardNumber($conn);
        $cvc = generateCVC();
        $expiry_month = 12;
        $expiry_year = date("Y") + 4;
        $card_status = "active";
        $monthly_limit = 10000.00;
        $monthly_spending = 0.00;

        $stmt = mysqli_prepare($conn, "INSERT INTO debit_cards 
            (user_id, card_number, cvc, expiry_month, expiry_year, card_status, monthly_limit, monthly_spending) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        mysqli_stmt_bind_param(
            $stmt,
            "issiisdd",
            $user_id,
            $full_card_number,
            $cvc,
            $expiry_month,
            $expiry_year,
            $card_status,
            $monthly_limit,
            $monthly_spending
        );

        mysqli_stmt_execute($stmt);

        header("Location: debit-card.php");
        exit();
    }

    if (isset($_POST['change_card'])) {

        $full_card_number = generateDebitCardNumber($conn);
        $cvc = generateCVC();
        $expiry_month = 12;
        $expiry_year = date("Y") + 4;
        $card_status = "active";
        $monthly_spending = 0.00;

        $stmt = mysqli_prepare($conn, "UPDATE debit_cards 
            SET card_number = ?, 
                cvc = ?, 
                expiry_month = ?, 
                expiry_year = ?, 
                card_status = ?, 
                monthly_spending = ?
            WHERE user_id = ?");

        mysqli_stmt_bind_param(
            $stmt,
            "ssiisdi",
            $full_card_number,
            $cvc,
            $expiry_month,
            $expiry_year,
            $card_status,
            $monthly_spending,
            $user_id
        );

        mysqli_stmt_execute($stmt);

        header("Location: debit-card.php");
        exit();
    }

    if (isset($_POST['delete_card'])) {

        $stmt = mysqli_prepare($conn, "DELETE FROM debit_cards WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);

        header("Location: debit-card.php");
        exit();
    }

    if (isset($_POST['toggle_status'])) {

        $current = $_POST['current_status'] ?? 'active';
        $newStatus = $current == 'active' ? 'locked' : 'active';

        $stmt = mysqli_prepare($conn, "UPDATE debit_cards SET card_status = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $newStatus, $user_id);
        mysqli_stmt_execute($stmt);

        header("Location: debit-card.php");
        exit();
    }
}

$stmt = mysqli_prepare($conn, "SELECT * FROM debit_cards WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$card = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$percent = 0;

if ($card) {
    $percent = $card['monthly_limit'] > 0 ? min(100, ($card['monthly_spending'] / $card['monthly_limit']) * 100) : 0;
    $fullCardNumber = $card['card_number'];
    $last4 = substr($fullCardNumber, -4);
    $cvc = $card['cvc'] ?? '000';
}
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>GX Debit Card</title>

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

        <h1 class="text-2xl font-bold text-white mb-6">GX Debit Card</h1>

        <?php if (!$card) { ?>

            <div class="bg-slate-900 rounded-2xl p-6 border border-slate-800 text-center">

                <div class="w-16 h-16 rounded-2xl bg-purple-500/15 flex items-center justify-center mx-auto mb-5">
                    <i data-lucide="credit-card" class="w-8 h-8 text-purple-400"></i>
                </div>

                <h2 class="text-white text-xl font-bold mb-2">No Debit Card Yet</h2>

                <p class="text-slate-400 text-sm mb-6">
                    You don’t have a GX Debit Card yet. Get one now to start spending from your account.
                </p>

                <form method="POST">
                    <button type="submit" name="create_card"
                        class="w-full bg-purple-500 hover:bg-purple-600 text-white font-medium py-3 rounded-lg transition">
                        Get GX Debit Card
                    </button>
                </form>

            </div>

        <?php } else { ?>

            <div class="space-y-4">

                <div class="bg-gradient-to-br from-purple-600 to-purple-800 rounded-2xl p-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>

                    <p class="text-purple-200 text-sm font-medium">GX Debit Card</p>

                    <p id="cardNumber"
                       class="text-white text-xl font-bold mt-4 tracking-widest"
                       data-full="<?php echo htmlspecialchars(chunk_split($fullCardNumber, 4, ' ')); ?>"
                       data-last4="<?php echo htmlspecialchars($last4); ?>">
                        •••• •••• •••• <?php echo htmlspecialchars($last4); ?>
                    </p>

                    <div class="flex justify-between mt-6">
                        <div>
                            <p class="text-purple-200 text-xs">Cardholder</p>
                            <p class="text-white font-medium">
                                <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </p>
                        </div>

                        <div>
                            <p class="text-purple-200 text-xs">Expiry</p>
                            <p class="text-white font-medium">
                                <?php echo sprintf('%02d', $card['expiry_month']); ?>/<?php echo substr($card['expiry_year'], -2); ?>
                            </p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <p class="text-purple-200 text-xs">CVC</p>
                        <p id="cardCVC"
                           class="text-white font-bold tracking-widest"
                           data-cvc="<?php echo htmlspecialchars($cvc); ?>">
                            •••
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <button type="button" onclick="toggleCardNumber()"
                        class="bg-purple-500/15 hover:bg-purple-500/25 text-purple-400 rounded-lg p-4 transition text-left">
                        <i data-lucide="eye" class="w-5 h-5 mb-2"></i>
                        <p id="showCardText" class="text-xs font-medium">Show Card</p>
                    </button>

                    <form method="POST">
                        <input type="hidden" name="current_status" value="<?php echo htmlspecialchars($card['card_status']); ?>">

                        <button type="submit" name="toggle_status"
                            class="w-full bg-purple-500/15 hover:bg-purple-500/25 text-purple-400 rounded-lg p-4 transition text-left">
                            <i data-lucide="lock" class="w-5 h-5 mb-2"></i>
                            <p class="text-xs font-medium">
                                <?php echo $card['card_status'] == 'active' ? 'Lock Card' : 'Unlock Card'; ?>
                            </p>
                        </button>
                    </form>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <form method="POST" onsubmit="return confirm('Are you sure you want to change your card? Your old card number and CVC will be replaced.');">
                        <button type="submit" name="change_card"
                            class="w-full bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium py-3 rounded-lg transition flex items-center justify-center gap-2">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                            Change
                        </button>
                    </form>

                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this debit card?');">
                        <button type="submit" name="delete_card"
                            class="w-full bg-red-500/15 hover:bg-red-500/25 text-red-400 font-medium py-3 rounded-lg transition flex items-center justify-center gap-2">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                            Delete
                        </button>
                    </form>
                </div>

                <div class="bg-slate-900 rounded-xl p-4 border border-slate-800">

                    <div class="flex justify-between mb-3">
                        <p class="text-slate-300 font-medium text-sm">Card Status</p>

                        <span class="<?php echo $card['card_status'] == 'active' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?> text-xs px-2 py-1 rounded">
                            <?php echo strtoupper($card['card_status']); ?>
                        </span>
                    </div>

                    <p class="text-slate-300 font-medium text-sm mb-3">Monthly Spending</p>

                    <p class="text-white text-xl font-bold">
                        RM <?php echo number_format($card['monthly_spending'], 2); ?>
                    </p>

                    <p class="text-slate-400 text-xs mt-1">
                        of RM <?php echo number_format($card['monthly_limit'], 2); ?> limit
                    </p>

                    <div class="w-full bg-slate-700 rounded-full h-2 mt-2">
                        <div class="bg-purple-500 h-2 rounded-full" style="width: <?php echo $percent; ?>%"></div>
                    </div>
                </div>

            </div>

        <?php } ?>

    </div>
</div>

<script>
let cardShown = false;

function toggleCardNumber() {
    const cardNumber = document.getElementById("cardNumber");
    const showCardText = document.getElementById("showCardText");
    const cardCVC = document.getElementById("cardCVC");

    if (!cardNumber || !showCardText || !cardCVC) {
        return;
    }

    if (!cardShown) {
        cardNumber.innerText = cardNumber.dataset.full.trim();
        cardCVC.innerText = cardCVC.dataset.cvc;
        showCardText.innerText = "Hide Card";
        cardShown = true;
    } else {
        cardNumber.innerText = "•••• •••• •••• " + cardNumber.dataset.last4;
        cardCVC.innerText = "•••";
        showCardText.innerText = "Show Card";
        cardShown = false;
    }
}

lucide.createIcons();
</script>

</body>
</html>