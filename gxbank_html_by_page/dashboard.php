<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "php/dbconn.php";

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT u.full_name, u.username, u.email, a.account_type, a.account_number, a.balance
                               FROM users u
                               JOIN accounts a ON u.user_id = a.user_id
                               WHERE u.user_id = ? AND a.account_type = 'Savings Account'
                               LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$userData = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$products = mysqli_query($conn, "SELECT * FROM gxbank_products ORDER BY product_id ASC");

$fullAccountNumber = $userData['account_number'];
$last4 = substr($fullAccountNumber, -4);
$realBalance = "RM " . number_format($userData['balance'], 2);
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Banking Dashboard</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* { font-family: 'DM Sans', sans-serif; }
body { box-sizing: border-box; }
.feature-btn { transition: transform 0.15s, box-shadow 0.15s; }
.feature-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
.feature-btn:active { transform: translateY(0); }
.user-menu { display: none; }
.user-menu.show { display: block; }
.modal-bg { display: none; }
.modal-bg.show { display: flex; }
</style>

</head>

<body class="h-full">

<div class="h-full w-full bg-slate-950 overflow-auto">
    <div class="max-w-md mx-auto px-5 py-8">

        <div class="flex items-center justify-between mb-8 relative">
            <div>
                <p class="text-white text-xl font-bold">
                    Hi, <?php echo htmlspecialchars($userData['full_name']); ?> 👋
                </p>
                <p class="text-slate-400 text-sm mt-1">Welcome back</p>
            </div>

            <div class="relative">
                <button type="button" onclick="toggleUserMenu()"
                    class="w-11 h-11 rounded-full bg-emerald-500 hover:bg-emerald-600 flex items-center justify-center transition">
                    <i data-lucide="user" class="w-5 h-5 text-white"></i>
                </button>

                <div id="userMenu" class="user-menu absolute right-0 mt-3 w-64 bg-slate-900 border border-slate-800 rounded-2xl shadow-xl z-50 overflow-hidden">
                    <div class="p-4 border-b border-slate-800">
                        <p class="text-white font-semibold text-sm">
                            <?php echo htmlspecialchars($userData['full_name']); ?>
                        </p>
                        <p class="text-slate-400 text-xs mt-1">
                            <?php echo htmlspecialchars($userData['email']); ?>
                        </p>
                    </div>

                    <a href="/gxbank_html_by_page/pages/update-profile.php"
                       class="flex items-center gap-3 px-4 py-3 text-slate-200 hover:bg-slate-800 transition text-sm">
                        <i data-lucide="settings" class="w-4 h-4 text-emerald-400"></i>
                        <span>Update Information</span>
                    </a>

                    <button type="button" onclick="openLogoutModal()"
                        class="w-full flex items-center gap-3 px-4 py-3 text-red-300 hover:bg-red-500/10 transition text-sm">
                        <i data-lucide="log-out" class="w-4 h-4 text-red-400"></i>
                        <span>Logout</span>
                    </button>
                </div>
            </div>
        </div>

        <div id="copyMessage" class="hidden bg-blue-500/15 text-blue-300 rounded-xl p-3 text-sm mb-4">
            Account number copied.
        </div>

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

        <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-2xl p-6 mb-8 relative overflow-hidden">

            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>

            <div class="relative z-10">

                <div class="flex items-center justify-between mb-1">
                    <p class="text-emerald-100 text-sm font-medium">
                        <?php echo htmlspecialchars($userData['account_type']); ?>
                    </p>

                    <button type="button" onclick="toggleAccountPrivacy()"
                        class="w-9 h-9 rounded-full bg-white/15 hover:bg-white/25 flex items-center justify-center transition">
                        <i id="eyeIcon" data-lucide="eye-off" class="w-5 h-5 text-white"></i>
                    </button>
                </div>

                <p id="balanceAmount"
                   class="text-white text-3xl font-bold tracking-tight"
                   data-full="<?php echo htmlspecialchars($realBalance); ?>">
                    RM ••••••
                </p>

                <p id="accountNumber"
                   onclick="copyAccountNumber()"
                   class="text-emerald-200 text-xs mt-2 cursor-pointer hover:text-white transition inline-flex items-center gap-1"
                   data-full="<?php echo htmlspecialchars($fullAccountNumber); ?>"
                   data-last4="<?php echo htmlspecialchars($last4); ?>">
                    Account: •••• <?php echo htmlspecialchars($last4); ?>
                    <i data-lucide="copy" class="w-3 h-3"></i>
                </p>

            </div>
        </div>

        <h2 class="text-slate-300 text-sm font-semibold uppercase tracking-wider mb-4">
            Features
        </h2>

        <div class="grid grid-cols-3 gap-3">
            <?php while($p = mysqli_fetch_assoc($products)) { ?>
                <a href="<?php echo htmlspecialchars($p['page_url']); ?>"
                   class="feature-btn flex flex-col items-center gap-2 p-4 rounded-xl bg-slate-900 border border-slate-800">

                    <div class="w-11 h-11 rounded-xl <?php echo htmlspecialchars($p['bg_class']); ?> flex items-center justify-center">
                        <i data-lucide="<?php echo htmlspecialchars($p['icon']); ?>" class="w-5 h-5 <?php echo htmlspecialchars($p['text_class']); ?>"></i>
                    </div>

                    <span class="text-slate-200 text-xs font-medium text-center leading-tight">
                        <?php echo htmlspecialchars($p['product_name']); ?>
                    </span>

                </a>
            <?php } ?>
        </div>

    </div>
</div>

<div id="logoutModal" class="modal-bg fixed inset-0 bg-black/70 z-[100] items-center justify-center px-5">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-sm w-full">
        <div class="w-12 h-12 rounded-xl bg-red-500/15 flex items-center justify-center mb-4">
            <i data-lucide="log-out" class="w-6 h-6 text-red-400"></i>
        </div>

        <h2 class="text-white text-xl font-bold mb-2">Confirm Logout</h2>

        <p class="text-slate-400 text-sm mb-6">
            Are you sure you want to logout from your GXBank account?
        </p>

        <div class="grid grid-cols-2 gap-3">
            <button type="button" onclick="closeLogoutModal()"
                class="bg-slate-800 hover:bg-slate-700 text-white font-semibold py-3 rounded-xl transition">
                Cancel
            </button>

            <a href="/gxbank_html_by_page/php/logout.php"
               class="bg-red-500 hover:bg-red-600 text-white font-semibold py-3 rounded-xl transition text-center">
                Yes, Logout
            </a>
        </div>
    </div>
</div>

<script>
let accountShown = false;

function toggleUserMenu() {
    const menu = document.getElementById("userMenu");
    menu.classList.toggle("show");
}

function openLogoutModal() {
    document.getElementById("userMenu").classList.remove("show");
    document.getElementById("logoutModal").classList.add("show");
    lucide.createIcons();
}

function closeLogoutModal() {
    document.getElementById("logoutModal").classList.remove("show");
}

function toggleAccountPrivacy() {
    const balanceAmount = document.getElementById("balanceAmount");
    const accountNumber = document.getElementById("accountNumber");
    const eyeIcon = document.getElementById("eyeIcon");

    if (!balanceAmount || !accountNumber || !eyeIcon) {
        return;
    }

    if (!accountShown) {
        balanceAmount.innerText = balanceAmount.dataset.full;
        accountNumber.innerHTML = 'Account: ' + accountNumber.dataset.full + ' <i data-lucide="copy" class="w-3 h-3"></i>';
        eyeIcon.setAttribute("data-lucide", "eye");
        accountShown = true;
    } else {
        balanceAmount.innerText = "RM ••••••";
        accountNumber.innerHTML = 'Account: •••• ' + accountNumber.dataset.last4 + ' <i data-lucide="copy" class="w-3 h-3"></i>';
        eyeIcon.setAttribute("data-lucide", "eye-off");
        accountShown = false;
    }

    lucide.createIcons();
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

document.addEventListener("click", function(event) {
    const menu = document.getElementById("userMenu");
    const clickedInsideMenu = event.target.closest("#userMenu");
    const clickedUserButton = event.target.closest("button[onclick='toggleUserMenu()']");

    if (!clickedInsideMenu && !clickedUserButton) {
        menu.classList.remove("show");
    }
});

lucide.createIcons();
</script>

</body>
</html>